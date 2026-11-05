<?php

namespace App\Services\Assistant;

use App\Models\ContactConsent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Notifications\AssistantNeedsAttention;
use App\Services\Agents\AgentChatRouter;
use App\Services\Agents\AiProvider;
use App\Services\Ai\RequirementParser;
use App\Services\Ai\RulesRequirementParser;
use App\Services\AuditLogger;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\MatchResult;
use App\Services\Matching\PropertyMatcher;
use App\Services\WhatsApp\HumanHandoff;
use App\Support\LocationResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The property assistant, shared by every channel (website, WhatsApp, any
 * future app). For each message it:
 *
 *   stores the message → detects the intent → parses the requirement →
 *   merges it into the conversation's remembered state → asks a clarifying
 *   or follow-up question, or matches real published listings → replies.
 *
 * Listings, prices and statuses always come from the database. Leads are
 * created only on an explicit request with consent (RequirementSaver).
 */
class ConversationAssistant
{
    /** The assistant message stored for the last reply (channels add delivery details). */
    public ?ConversationMessage $lastMessage = null;

    /** The channel of the current conversation, so the language model follows that channel's settings. */
    protected ?string $channel = null;

    public function __construct(
        protected RequirementParser $parser,
        protected PropertyMatcher $matcher,
        protected IntentDetector $intents,
        protected RequirementSaver $saver,
    ) {}

    /**
     * @param  ConversationMessage|null  $userMessage  already stored by the channel (WhatsApp); stored here otherwise
     */
    public function respond(Conversation $conversation, string $text, ?User $user = null, ?ConversationMessage $userMessage = null): AssistantReply
    {
        $text = mb_substr(trim($text), 0, 500);
        $this->channel = $conversation->channel;
        $userMessage ??= $conversation->messages()->create(['direction' => 'inbound', 'role' => 'user', 'type' => 'text', 'body' => $text]);
        $state = new RequirementState($conversation->state);
        $intent = $this->intents->detect($text);

        $this->watchForBursts($conversation, $state);

        // Help, investment and dealer-account questions go to the AI agents (through the Central AI Brain).
        $control = [IntentDetector::STOP, IntentDetector::HUMAN, IntentDetector::RESET, IntentDetector::SAVE, IntentDetector::YES, IntentDetector::NO, IntentDetector::GREETING, IntentDetector::THANKS];
        $reply = in_array($intent, $control, true) ? null : $this->agentReply($conversation, $text, $user, $userMessage);
        $intent = $reply?->intent ?? $intent;

        $reply ??= match ($intent) {
            IntentDetector::STOP => $this->stop($conversation, $state),
            IntentDetector::HUMAN => $this->handOver($conversation, $state),
            IntentDetector::RESET => $this->reset($state),
            IntentDetector::SELL => new AssistantReply('To sell or rent out your property, you can list it on Meezan Property: '.route('sell').' Every listing is reviewed before it goes live.', $intent, actions: ['sell']),
            IntentDetector::THANKS => new AssistantReply('You are welcome. Tell me if you would like to change anything in your search.', $intent, summary: $state->summary()),
            IntentDetector::GREETING => $this->greet($state),
            IntentDetector::SAVE => $this->saveRequirement($conversation, $state, $user),
            IntentDetector::DEALER => $this->offerDealers($conversation, $state, $user),
            IntentDetector::YES => $state->get('awaiting') === 'dealer_consent' ? $this->connectDealers($conversation, $state, $user) : $this->requirement($conversation, $state, $text, $user),
            IntentDetector::NO => $state->get('awaiting') ? $this->cancelPending($state) : $this->requirement($conversation, $state, $text, $user),
            default => $this->requirement($conversation, $state, $text, $user),
        };

        $userMessage->forceFill(['role' => 'user', 'intent' => $intent, 'extracted' => $state->only() ?: null])->save();
        $conversation->forceFill(['state' => $state->data ?: null, 'last_message_at' => now(), 'user_id' => $conversation->user_id ?? $user?->id])->save();

        if ($conversation->channel === 'whatsapp') {
            $conversation->forceFill(['assigned_agent' => AgentChatRouter::agentFor($reply->intent)])->save();
        }

        $this->lastMessage = $conversation->messages()->create([
            'direction' => 'outbound',
            'role' => 'assistant',
            'type' => 'text',
            'intent' => $reply->intent,
            'agent_slug' => $conversation->channel === 'whatsapp' ? AgentChatRouter::agentFor($reply->intent) : null,
            // WhatsApp keeps exactly what was sent; the website shows listings as cards instead.
            'body' => $conversation->channel === 'whatsapp' ? $reply->toPlainText() : trim($reply->text."\n".$reply->followUp),
            'extracted' => array_filter(['summary' => $reply->summary, 'matches' => $reply->result?->matches->map(fn ($m) => $m->property->id)->all()]) ?: null,
        ]);

        return $reply;
    }

    protected function agentReply(Conversation $conversation, string $text, ?User $user, ConversationMessage $message): ?AssistantReply
    {
        try {
            return app(AgentChatRouter::class)->reply($conversation, $text, $user, $message);
        } catch (Throwable $e) {
            report($e);

            return null; // never let an agent failure break the chat
        }
    }

    /** WhatsApp only: a message that is not a property search may still be for an agent (the model picks, within budget). */
    protected function classifiedAgentReply(Conversation $conversation, string $text, ?User $user): ?AssistantReply
    {
        if ($conversation->channel !== 'whatsapp' || ! ($message = $conversation->messages()->getQuery()->reorder()->where('direction', 'inbound')->latest('id')->first())) {
            return null;
        }
        try {
            return app(AgentChatRouter::class)->classifiedReply($conversation, $text, $user, $message);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /* Requirement messages ------------------------------------------------ */

    protected function requirement(Conversation $conversation, RequirementState $state, string $text, ?User $user): AssistantReply
    {
        $resolved = $this->resolvePendingChoice($state, $text);
        $criteria = $this->parse($text);

        if ($criteria->unsupportedCity && ! $resolved) {
            return new AssistantReply(
                "Meezan Property does not cover {$criteria->unsupportedCity} yet. We currently list property in ".$this->cityList().'. Which of these should I search?',
                'unsupported_city',
                choices: $this->cityChoices(),
                summary: $state->summary(),
            );
        }

        if ($criteria->clarify && ! $resolved) {
            $state->set('pending', $criteria->clarify['choices'] ?? []);
            $state->absorb($this->withoutLocation($criteria));

            return new AssistantReply(
                $criteria->clarify['question'],
                'clarify_location',
                choices: array_map(fn ($c) => ['label' => $c['label'], 'value' => $c['city']], $criteria->clarify['choices'] ?? []),
                summary: $state->summary(),
            );
        }

        $changed = $state->absorb($criteria) || $resolved;

        if (! $changed && $state->isEmpty()) {
            return $this->classifiedAgentReply($conversation, $text, $user) ?? $this->notUnderstood($conversation, $state);
        }
        $state->set('failures', null);

        if (! $state->get('city')) {
            $state->markAsked('city');

            return new AssistantReply(
                'Which city are you looking in? Meezan Property covers '.$this->cityList().'.',
                'ask_city',
                choices: $this->cityChoices(),
                summary: $state->summary(),
            );
        }

        $state->inferPurpose();
        $result = $this->matcher->match($state->toCriteria());

        if ($conversation->requirement) {
            // A saved requirement follows the conversation, so it stays current.
            $conversation->state = $state->data;
            $requirement = $this->saver->save($conversation, $user);
            $requirement && $this->saver->recordMatches($requirement, $result->matches->count());
        }

        $question = $state->nextQuestion();
        if ($question) {
            $state->markAsked($question['key']);
        }

        return new AssistantReply(
            $this->resultText($result, $state),
            $result->isEmpty() ? 'no_results' : 'results',
            result: $result,
            followUp: $question['text'] ?? null,
            choices: ($question['key'] ?? null) === 'purpose' ? [['label' => 'Buy', 'value' => 'I want to buy'], ['label' => 'Rent', 'value' => 'I want to rent']] : [],
            actions: $state->isSubstantial() ? ['save', 'contact_dealers', 'new_search'] : ['new_search'],
            summary: $state->summary(),
        );
    }

    protected function resultText(MatchResult $result, RequirementState $state): string
    {
        if (! $result->isEmpty()) {
            $text = $result->summaryText();
            if ($result->matches->contains(fn ($m) => $m->budgetStatus && $m->budgetStatus !== 'within')) {
                $text .= ' Some are above your budget and are labelled as such.';
            }

            return $text;
        }

        return 'No exact match is available right now for: '.implode(' · ', $result->criteria->describe()).'. '
            .implode(' ', $result->hints)
            .($state->isSubstantial() ? ' You can save this requirement to hear about new listings, or ask me to connect you with up to '.(int) config('meezan.leads.max_dealers_per_requirement', 3).' verified dealers who cover the area.' : '');
    }

    /** A reply such as "Lahore", "2" or "Quetta wala" settles an open location question. */
    protected function resolvePendingChoice(RequirementState $state, string $text): bool
    {
        $choices = $state->get('pending', []);
        if (! $choices) {
            return false;
        }

        $pick = null;
        if (preg_match('/^\s*(\d)\s*$/', $text, $m) && isset($choices[(int) $m[1] - 1])) {
            $pick = $choices[(int) $m[1] - 1];
        } else {
            [, $city] = LocationResolver::splitCity($text);
            $city ??= collect(config('meezan.cities'))->first(fn ($c) => stripos($text, $c) !== false);
            $pick = collect($choices)->firstWhere('city', $city);
        }
        if (! $pick) {
            return false;
        }

        $state->set('pending', null);
        $state->set('city', $pick['city']);
        $state->set('area_ids', $pick['ids'] ?: null);

        return true;
    }

    protected function parse(string $text): MatchCriteria
    {
        try {
            return app(AiProvider::class)->inChannel($this->channel, fn () => $this->parser->parse($text));
        } catch (Throwable $e) {
            // The rules engine is always available; buyers never see a technical error.
            Log::warning('Requirement parser failed; using rules', ['error' => $e->getMessage()]);

            return app(RulesRequirementParser::class)->parse($text);
        }
    }

    protected function withoutLocation(MatchCriteria $c): MatchCriteria
    {
        $copy = clone $c;
        $copy->city = '';
        $copy->areaIds = [];

        return $copy;
    }

    protected function notUnderstood(Conversation $conversation, RequirementState $state): AssistantReply
    {
        $failures = (int) $state->get('failures', 0) + 1;
        $state->set('failures', $failures);

        if ($failures >= (int) config('meezan.ai.escalate_after_failures', 3)) {
            $this->escalate($conversation, 'not_understood');
            $conversation->status = 'failed';
        }

        return new AssistantReply(
            'Tell me a little more — for example the city, area, property type and your budget, like "10 marla house in DHA Lahore around 4 crore" or "Mujhe Karachi mein flat kiraye par chahiye".'
                .($conversation->channel === 'whatsapp' ? ' You can also reply "human" to reach our team.' : ''),
            'not_understood',
            choices: [],
        );
    }

    /* Other intents ------------------------------------------------------- */

    protected function greet(RequirementState $state): AssistantReply
    {
        if (! $state->isEmpty()) {
            return new AssistantReply('Welcome back. I still have your search: '.implode(' · ', $state->summary()).'. Tell me what to change, or ask me to search again.', IntentDetector::GREETING, summary: $state->summary());
        }

        return new AssistantReply(
            'Assalam o Alaikum! I am the Meezan Property assistant. Tell me a little more about what you need — city, area, property type and budget — in English, Urdu or Roman Urdu.',
            IntentDetector::GREETING,
        );
    }

    protected function reset(RequirementState $state): AssistantReply
    {
        $state->data = [];

        return new AssistantReply('Starting a new search. Which city, area and type of property are you looking for?', IntentDetector::RESET, choices: $this->cityChoices());
    }

    protected function saveRequirement(Conversation $conversation, RequirementState $state, ?User $user): AssistantReply
    {
        $state->inferPurpose();
        if (! $state->isSubstantial()) {
            return $this->needMore($state, 'save your requirement');
        }
        if ($conversation->channel === 'website' && ! $user) {
            return new AssistantReply('Please sign in or create a free account to save this requirement — your search will be kept.', IntentDetector::SAVE, actions: ['login'], summary: $state->summary());
        }

        $contact = $conversation->channel === 'whatsapp' ? ['name' => $conversation->contact_name, 'phone' => $conversation->external_contact] : [];
        if ($conversation->channel === 'whatsapp') {
            ContactConsent::record('whatsapp_replies', 'whatsapp', 'whatsapp_keyword', null, $conversation->external_contact, $conversation->id);
        }
        $conversation->state = $state->data;
        $requirement = $this->saver->save($conversation, $user, $contact);

        return new AssistantReply(
            'Saved: '.$requirement->summary().'. I will keep it up to date as we talk'.($user ? ', and you can manage it under My requirements.' : '.'),
            IntentDetector::SAVE,
            actions: ['contact_dealers', 'new_search'],
            summary: $state->summary(),
        );
    }

    protected function offerDealers(Conversation $conversation, RequirementState $state, ?User $user): AssistantReply
    {
        $state->inferPurpose();
        if (! $state->isSubstantial()) {
            return $this->needMore($state, 'connect you with dealers');
        }
        $max = (int) config('meezan.leads.max_dealers_per_requirement', 3);

        if ($conversation->channel === 'website') {
            return $user
                ? new AssistantReply("I can share this requirement and your contact details with up to {$max} verified dealers who cover {$state->get('city')}. Use “Contact dealers” below to confirm.", IntentDetector::DEALER, actions: ['contact_dealers'], summary: $state->summary())
                : new AssistantReply('Please sign in or create a free account first, so the dealers can reach you. Your search will be kept.', IntentDetector::DEALER, actions: ['login'], summary: $state->summary());
        }

        $state->set('awaiting', 'dealer_consent');

        return new AssistantReply(
            "Reply YES to share your requirement and this WhatsApp number with up to {$max} verified dealers who cover {$state->get('city')}. Reply NO to cancel.",
            IntentDetector::DEALER,
            summary: $state->summary(),
        );
    }

    /** Explicit consent given (WhatsApp YES, or the website's confirm button). */
    public function connectDealers(Conversation $conversation, RequirementState $state, ?User $user): AssistantReply
    {
        $state->set('awaiting', null);
        $conversation->state = $state->data;
        $contact = $conversation->channel === 'whatsapp' ? ['name' => $conversation->contact_name, 'phone' => $conversation->external_contact] : [];
        $outcome = $this->saver->connectDealers($conversation, $user, $conversation->channel === 'whatsapp' ? 'whatsapp_keyword' : 'ai_finder', $contact);

        if (! $outcome['requirement']) {
            return $this->needMore($state, 'connect you with dealers');
        }

        return new AssistantReply(
            $outcome['dealers'] > 0
                ? "Done. Your requirement was shared with {$outcome['dealers']} verified ".str('dealer')->plural($outcome['dealers']).($outcome['dealers'] === 1 ? ' who covers ' : ' who cover ')."{$state->get('city')}. They will contact you directly."
                : 'Your requirement is saved. No verified dealer covers this area yet, so our team will review it and get back to you.',
            'dealers_connected',
            actions: ['new_search'],
            summary: $state->summary(),
        );
    }

    protected function cancelPending(RequirementState $state): AssistantReply
    {
        $state->set('awaiting', null);
        $state->set('pending', null);

        return new AssistantReply('No problem — nothing was shared. Tell me if you want to change your search.', IntentDetector::NO, summary: $state->summary());
    }

    protected function stop(Conversation $conversation, RequirementState $state): AssistantReply
    {
        if ($conversation->channel === 'whatsapp') {
            ContactConsent::revokeAll('whatsapp', $conversation->external_contact);
        }
        $conversation->forceFill(['mode' => 'closed', 'status' => 'closed']);
        if ($conversation->hasOpenHandoff()) {
            $conversation->forceFill(['handoff_status' => 'resolved', 'handoff_resolved_at' => now()]);
        }
        AuditLogger::record('conversation.opted_out', $conversation, ['channel' => $conversation->channel]);

        return new AssistantReply('You have been unsubscribed and will not receive further messages from Meezan Property here. Send a new message any time to start again.', IntentDetector::STOP);
    }

    protected function handOver(Conversation $conversation, RequirementState $state): AssistantReply
    {
        $this->escalate($conversation, 'human');
        if ($conversation->channel === 'whatsapp') {
            app(HumanHandoff::class)->open($conversation, 'human');

            return new AssistantReply('Thank you. A member of the Meezan Property team will reply here shortly.', IntentDetector::HUMAN);
        }

        return new AssistantReply('I have passed this to the Meezan Property team. You can also reach us on the Contact page'.($state->isSubstantial() ? ', or I can connect you with verified dealers for this search.' : '.'), IntentDetector::HUMAN,
            actions: $state->isSubstantial() ? ['contact_dealers'] : [], summary: $state->summary());
    }

    protected function needMore(RequirementState $state, string $purpose): AssistantReply
    {
        $missing = array_keys(array_filter([
            'the city' => ! $state->get('city'),
            'whether you want to buy or rent' => ! $state->get('purpose'),
            'the property type, area or budget' => ! ($state->get('type') || $state->get('area_ids') || $state->get('max_price')),
        ]));

        return new AssistantReply('To '.$purpose.', I need '.implode(', ', $missing).'.', 'need_more', summary: $state->summary());
    }

    /* Escalation and abuse ------------------------------------------------ */

    protected function escalate(Conversation $conversation, string $reason): void
    {
        $alreadyFlagged = $conversation->escalated_at && $conversation->escalation_reason === $reason;
        $conversation->forceFill([
            'escalated_at' => now(),
            'escalation_reason' => $reason,
            'status' => $conversation->status === 'lead' ? 'lead' : 'escalated',
        ])->save();

        if (! $alreadyFlagged) {
            AuditLogger::record('conversation.escalated', $conversation, ['reason' => $reason]);
            Notification::send(User::activeAdmins(), new AssistantNeedsAttention($reason, $conversation));
        }
    }

    protected function watchForBursts(Conversation $conversation, RequirementState $state): void
    {
        $limit = (int) config('meezan.ai.burst_limit', 20);
        $recent = $conversation->messages()->getQuery()->reorder()->where('role', 'user')->where('created_at', '>=', now()->subMinute())->count();

        if ($recent > $limit && ! $state->get('burst_flagged')) {
            $state->set('burst_flagged', now()->toIso8601String());
            $this->escalate($conversation, 'suspicious');
        }
    }

    /* Helpers ------------------------------------------------------------- */

    protected function cityList(): string
    {
        $cities = config('meezan.cities');

        return implode(', ', array_slice($cities, 0, -1)).' and '.end($cities);
    }

    /** @return list<array{label: string, value: string}> */
    protected function cityChoices(): array
    {
        return array_map(fn ($c) => ['label' => $c, 'value' => $c], config('meezan.cities'));
    }
}
