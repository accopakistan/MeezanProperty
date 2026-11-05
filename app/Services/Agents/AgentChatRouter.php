<?php

namespace App\Services\Agents;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Assistant\AssistantReply;
use App\Services\Assistant\IntentDetector;
use App\Services\WhatsApp\HumanHandoff;
use App\Support\UrduText;

/**
 * Hands website and WhatsApp questions to agents, through the Central AI
 * Brain. Patterns are deliberately narrow; anything else stays with the
 * property-search assistant. Returns null to let the assistant answer.
 *
 * Most agents' normal workflows are staff reports, so a customer message only
 * ever reaches a customer workflow named here, and the task is marked
 * audience=customer (ToolRunner then refuses every tool outside a short list).
 */
class AgentChatRouter
{
    /** The only agents and workflows a customer's message can reach. */
    public const CUSTOMER_ROUTES = [
        'customer-support' => 'faq',
        'dealer-manager' => 'customer_onboarding',
        'property-manager' => 'customer_listing',
        'subscription-manager' => 'customer_plans',
        'finance-manager' => 'customer_payment',
        'technical-operations' => 'customer_issue',
        'investment-analyst' => 'advisor',
    ];

    protected const SUPPORT = '/\b(register(ing)? as an? (property |real estate )?dealer|become an? (verified |property )?dealer|dealer registration|how (do|can) i (register|sign up)|forgot (my )?password|reset (my )?password|can\'?t (log|sign) ?in|dealer (packages?|plans?|pricing)|how (are|do you) (listings|dealers) (get )?(reviewed|verified)|dealer kaise ban|password bhool)\b|ڈیلر.*(رجسٹر|بن)|پاس ورڈ/iu';

    protected const INVESTMENT = '/\b(rental yield|where should i invest|best (areas?|places?|locations?|societ(y|ies)) (to|for) invest(ment)?|best investment (areas?|in)|investment rankings?|kahan invest)\b|سرمایہ کاری/iu';

    protected const DEALER_SELF = '/\b(my leads|meri leads|my (subscription|package|plan)|mera package|meri subscription|leads? (left|remaining)|my listing limit)\b/i';

    /* WhatsApp only, checked in this order. */
    protected const PAYMENT = '/\b(payment (issue|problem|failed|not|nahi|masla)|paid but|i paid|charged (me )?(twice|two times|again)|double charge|refund|paise wapas|invoice (issue|problem|wrong)|payment ka masla)\b|ادائیگی|رقم واپس/iu';

    protected const DEALER_JOIN = '/\b(register(ing)? as an? (property |real estate )?dealer|become an? (verified |property )?dealer|dealer registration|join as an? dealer|dealer (kaise|kese) ban(na|un|ne|ta|te)?|dealer ban(na|ne)?( chaht[ai])?)\b|ڈیلر.*(رجسٹر|بن)/iu';

    protected const PLANS = '/\b(dealer (packages?|plans?|pricing|subscription)|subscription (plans?|price|cost|fee|details|information)|packages? (price|cost|fee|details)|your (packages|plans|pricing)|what (packages|plans) )\b|سبسکرپشن|پیکج/iu';

    protected const LISTING = '/\b(how (do|can|to) i? ?(list|post|advertise)|list (my|a) (property|house|plot|flat|shop)|post (an? )?(ad|listing)|listing (fee|process|review|approval|status|rules)|how (are|do you) listings)\b|لسٹنگ/iu';

    protected const TECHNICAL = '/\b(technical (issue|problem|error)|website (is )?(not working|down|error|nahi chal)|site (is )?(not working|down|nahi chal)|app (is )?(not working|crash)|page (is )?(not (loading|opening)|error)|(link|button) (is )?not working|found a bug|report a bug)\b/iu';

    protected const INVEST_INDEX = '/\b(meezan (investment )?index|investment index|investment rankings?|what is the index)\b/iu';

    protected const INVEST_ADVICE = '/\b(investment advice|should i invest|want to invest|invest karna|sarmaya kari)\b/iu';

    public function __construct(protected CentralAiOrchestrator $brain, protected IntentRouter $intents) {}

    public function reply(Conversation $conversation, string $text, ?User $user, ConversationMessage $message): ?AssistantReply
    {
        [$slug, $intent] = $this->match($text, $user, $conversation->channel) ?? [null, null];

        return $slug ? $this->ask($slug, $intent, $conversation, $text, $user, $message) : null;
    }

    /**
     * A WhatsApp message that is not a property search and matched no pattern:
     * Gemini may pick one of the customer routes (validated; within budget).
     * Without a model, or if it does not pick one, the assistant asks for more.
     */
    public function classifiedReply(Conversation $conversation, string $text, ?User $user, ConversationMessage $message): ?AssistantReply
    {
        $slug = $this->intents->classifyCustomer($text, array_keys(self::CUSTOMER_ROUTES));

        return $slug ? $this->ask($slug, self::CUSTOMER_ROUTES[$slug], $conversation, $text, $user, $message) : null;
    }

    /** @return array{0: string, 1: ?string}|null */
    public function match(string $text, ?User $user, string $channel = 'website'): ?array
    {
        if ($user?->isDealer() && preg_match(self::DEALER_SELF, $text)) {
            return preg_match('/\b(subscription|package|plan|limit)\b/i', $text) ? ['subscription-manager', 'my_plan'] : ['sales-manager', 'my_leads'];
        }

        return ($channel === 'whatsapp' ? $this->whatsappRoute($text) : null) ?? match (true) {
            (bool) preg_match(self::SUPPORT, $text) => ['customer-support', 'faq'],
            (bool) preg_match(self::INVESTMENT, $text) => ['investment-analyst', 'advisor'],
            default => null,
        };
    }

    /**
     * Which agent answered, for the conversation record. Property search and
     * lead qualification run in the shared property assistant, on behalf of
     * the Sales Manager; greetings and help belong to Customer Support.
     */
    public static function agentFor(string $intent): string
    {
        if (str_starts_with($intent, 'agent_')) {
            return str_replace('_', '-', substr($intent, 6));
        }

        return match ($intent) {
            IntentDetector::STOP, IntentDetector::HUMAN, IntentDetector::GREETING, IntentDetector::THANKS, 'not_understood', 'unsupported_type', 'account_linked', 'error' => 'customer-support',
            IntentDetector::SELL => 'property-manager',
            default => 'sales-manager',
        };
    }

    /** @return array{0: string, 1: string}|null */
    protected function whatsappRoute(string $text): ?array
    {
        $roman = UrduText::toRoman($text).' '.$text;

        return match (true) {
            (bool) preg_match(self::PAYMENT, $roman) => ['finance-manager', 'customer_payment'],
            (bool) preg_match(self::DEALER_JOIN, $roman) => ['dealer-manager', 'customer_onboarding'],
            (bool) preg_match(self::PLANS, $roman) => ['subscription-manager', 'customer_plans'],
            (bool) preg_match(self::LISTING, $roman) => ['property-manager', 'customer_listing'],
            (bool) preg_match(self::TECHNICAL, $roman) => ['technical-operations', 'customer_issue'],
            (bool) preg_match(self::INVEST_INDEX, $roman) => ['investment-analyst', 'customer_index'],
            (bool) preg_match(self::INVEST_ADVICE, $roman) => ['investment-analyst', 'advisor'],
            default => null,
        };
    }

    protected function ask(string $slug, ?string $intent, Conversation $conversation, string $text, ?User $user, ConversationMessage $message): ?AssistantReply
    {
        $channel = $conversation->channel === 'whatsapp' ? 'whatsapp' : 'website';
        $task = $this->brain->submit($text, $slug, $user, $channel,
            ['conversation_id' => $conversation->id, 'audience' => 'customer'], key: 'chat-message-'.$message->id, intent: $intent);

        if (! in_array($task->status, ['completed', 'awaiting_approval'], true) || ! $task->result) {
            return null; // agent unavailable or failed: the normal assistant answers instead
        }
        $reply = Report::customerPlain($task->result, $channel === 'whatsapp' ? 1000 : 1200);
        if ($reply === '') {
            return null;
        }
        if ($channel === 'whatsapp' && ($reason = $task->result['handoff'] ?? null)) {
            app(HumanHandoff::class)->request($conversation, $reason);
        }

        return new AssistantReply($reply, 'agent_'.str_replace('-', '_', $slug));
    }
}
