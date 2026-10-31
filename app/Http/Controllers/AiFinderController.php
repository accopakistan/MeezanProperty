<?php

namespace App\Http\Controllers;

use App\Enums\AreaUnit;
use App\Enums\DealerStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Conversation;
use App\Models\DealerProfile;
use App\Models\Property;
use App\Services\Ai\RequirementParser;
use App\Services\Assistant\AssistantReply;
use App\Services\Assistant\ConversationAssistant;
use App\Services\Assistant\RequirementState;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\PropertyMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Website channel of the property assistant. Each browser session has one
 * active conversation (found through a random token kept in the session,
 * which survives signing in), so the assistant remembers the requirement
 * across messages and page reloads. Nobody can open another session's chat.
 */
class AiFinderController extends Controller
{
    protected const SESSION_KEY = 'ai_conversation_token';

    public function show(Request $request, PropertyMatcher $matcher): View
    {
        // A structured (no-JS) search submitted via GET still renders results directly.
        $result = null;
        if ($request->filled('purpose') || $request->filled('type') || $request->filled('message')) {
            $result = $matcher->match($this->structured($request, app(RequirementParser::class)));
        }

        $conversation = $this->conversation($request, create: false);

        return view('ai-finder.show', [
            'result' => $result,
            'conversation' => $conversation,
            'history' => $conversation?->recentMessages(50) ?? collect(),
            'summary' => (new RequirementState($conversation?->state))->summary(),
            'substantial' => (new RequirementState($conversation?->state))->isSubstantial(),
            'publishedCount' => Property::published()->count(),
            'verifiedDealers' => DealerProfile::where('verification_status', DealerStatus::Verified)->count(),
        ]);
    }

    public function match(Request $request, RequirementParser $parser, ConversationAssistant $assistant): JsonResponse
    {
        $request->validate(['message' => ['nullable', 'string', 'max:500']]);

        $conversation = $this->conversation($request);
        $structured = $this->structured($request, null);

        // Choices from the "Refine preferences" panel are added to what the buyer typed.
        if (! $this->structuredIsEmpty($structured)) {
            $state = new RequirementState($conversation->state);
            $state->absorb($structured);
            $conversation->state = $state->data;
        }

        $message = trim((string) $request->input('message'));
        $reply = $assistant->respond($conversation, $message !== '' ? $message : 'Show matches for my preferences', $request->user());

        return $this->json($reply);
    }

    /** Close the current conversation and begin a fresh one. */
    public function reset(Request $request): JsonResponse
    {
        $conversation = $this->conversation($request, create: false);
        if ($conversation && $conversation->status === 'active') {
            $conversation->update(['status' => 'closed']);
        }
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['reply' => 'Starting a new search. Which city, area and type of property are you looking for?']);
    }

    public function save(Request $request, ConversationAssistant $assistant): JsonResponse
    {
        $conversation = $this->conversation($request, create: false);
        abort_unless($conversation, 404);

        return $this->json($assistant->respond($conversation, 'save my requirement', $request->user()));
    }

    /** Share the requirement with matching dealers: signed-in buyers only, with explicit consent. */
    public function contact(Request $request, ConversationAssistant $assistant): JsonResponse
    {
        $request->validate(['consent' => ['accepted']], ['consent.accepted' => 'Please confirm that we may share your requirement and contact details.']);
        $conversation = $this->conversation($request, create: false);
        abort_unless($conversation, 404);

        $conversation->messages()->create(['direction' => 'inbound', 'role' => 'user', 'type' => 'text', 'intent' => 'dealer_consent', 'body' => 'Yes, share my requirement with matching dealers.']);
        $reply = $assistant->connectDealers($conversation, new RequirementState($conversation->state), $request->user());
        $conversation->messages()->create(['direction' => 'outbound', 'role' => 'assistant', 'type' => 'text', 'intent' => $reply->intent, 'body' => trim($reply->text."\n".$reply->followUp)]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        return $this->json($reply);
    }

    protected function json(AssistantReply $reply): JsonResponse
    {
        return response()->json([
            'reply' => $reply->text,
            'follow_up' => $reply->followUp,
            'choices' => $reply->choices,
            'actions' => $reply->actions,
            'intent' => $reply->intent,
            'result_count' => $reply->result?->matches->count() ?? 0,
            'html' => view('ai-finder.partials.results', ['result' => $reply->result])->render(),
            'understood' => view('ai-finder.partials.requirement-summary', ['summary' => $reply->summary])->render(),
        ]);
    }

    /**
     * The session's conversation, or a new one. Only the token in this
     * browser's session can open it; a signed-in user's id is recorded.
     */
    protected function conversation(Request $request, bool $create = true): ?Conversation
    {
        $token = $request->session()->get(self::SESSION_KEY);
        $conversation = $token ? Conversation::where('channel', 'website')->where('session_key', $token)->whereNotIn('status', ['closed'])->first() : null;

        if (! $conversation && $create) {
            $token = Str::random(48);
            $request->session()->put(self::SESSION_KEY, $token);
            $conversation = Conversation::create([
                'channel' => 'website',
                'external_contact' => 'web-'.Str::random(32),
                'session_key' => $token,
                'mode' => 'bot',
                'status' => 'active',
                'last_message_at' => now(),
            ]);
        }
        if ($conversation && $request->user() && ! $conversation->user_id) {
            $conversation->forceFill(['user_id' => $request->user()->id])->save();
        }

        return $conversation;
    }

    /**
     * Preferences from the side panel (and the no-JS GET form). When a parser
     * is given, the free-text message is parsed too and the panel wins.
     */
    protected function structured(Request $request, ?RequirementParser $parser): MatchCriteria
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
            'purpose' => ['nullable', Rule::enum(Purpose::class)],
            'type' => ['nullable', Rule::enum(PropertyType::class)],
            'areas' => ['nullable', 'array', 'max:5'],
            'areas.*' => ['integer', 'exists:areas,id'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'min_size' => ['nullable', 'numeric', 'min:0'],
            'size_unit' => ['nullable', Rule::enum(AreaUnit::class)],
            'bedrooms' => ['nullable', 'integer', 'between:1,10'],
        ]);

        $structured = new MatchCriteria(
            purpose: isset($data['purpose']) ? Purpose::from($data['purpose']) : null,
            type: isset($data['type']) ? PropertyType::from($data['type']) : null,
            city: '',
            areaIds: array_map('intval', $data['areas'] ?? []),
            maxPrice: isset($data['max_price']) ? (int) $data['max_price'] : null,
            minAreaSqft: isset($data['min_size'])
                ? (AreaUnit::tryFrom($data['size_unit'] ?? '') ?? AreaUnit::Marla)->toSquareFeet((float) $data['min_size'])
                : null,
            bedrooms: isset($data['bedrooms']) ? (int) $data['bedrooms'] : null,
        );

        return $parser ? $parser->parse($data['message'] ?? '')->merge($structured) : $structured;
    }

    protected function structuredIsEmpty(MatchCriteria $c): bool
    {
        return ! $c->purpose && ! $c->type && ! $c->areaIds && ! $c->maxPrice && ! $c->minAreaSqft && ! $c->bedrooms;
    }
}
