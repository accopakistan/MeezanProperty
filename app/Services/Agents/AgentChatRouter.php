<?php

namespace App\Services\Agents;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Assistant\AssistantReply;

/**
 * Hands a small set of website / WhatsApp questions to agents, through the
 * Central AI Brain: platform help (Customer Support), investment questions
 * (Investment Analyst) and a signed-in dealer's own leads or package (Sales /
 * Subscription). Patterns are deliberately narrow; anything else stays with
 * the property-search assistant. Returns null to let the assistant answer.
 */
class AgentChatRouter
{
    protected const SUPPORT = '/\b(register(ing)? as an? (property |real estate )?dealer|become an? (verified |property )?dealer|dealer registration|how (do|can) i (register|sign up)|forgot (my )?password|reset (my )?password|can\'?t (log|sign) ?in|dealer (packages?|plans?|pricing)|how (are|do you) (listings|dealers) (get )?(reviewed|verified)|dealer kaise ban|password bhool)\b|ڈیلر.*(رجسٹر|بن)|پاس ورڈ/iu';

    protected const INVESTMENT = '/\b(rental yield|where should i invest|best (areas?|places?|locations?|societ(y|ies)) (to|for) invest(ment)?|best investment (areas?|in)|investment rankings?|kahan invest)\b|سرمایہ کاری/iu';

    protected const DEALER_SELF = '/\b(my leads|meri leads|my (subscription|package|plan)|mera package|meri subscription|leads? (left|remaining)|my listing limit)\b/i';

    public function __construct(protected CentralAiOrchestrator $brain) {}

    public function reply(Conversation $conversation, string $text, ?User $user, ConversationMessage $message): ?AssistantReply
    {
        [$slug, $intent] = $this->match($text, $user) ?? [null, null];
        if (! $slug) {
            return null;
        }

        $task = $this->brain->submit($text, $slug, $user, $conversation->channel === 'whatsapp' ? 'whatsapp' : 'website',
            ['conversation_id' => $conversation->id], key: 'chat-message-'.$message->id, intent: $intent);

        if (! in_array($task->status, ['completed', 'awaiting_approval'], true) || ! $task->result) {
            return null; // agent unavailable or failed: the normal assistant answers instead
        }
        $text = Report::plain($task->result, $conversation->channel === 'whatsapp' ? 1000 : 1200);

        return $text === '' ? null : new AssistantReply($text, 'agent_'.str_replace('-', '_', $slug));
    }

    /** @return array{0: string, 1: ?string}|null */
    public function match(string $text, ?User $user): ?array
    {
        if ($user?->isDealer() && preg_match(self::DEALER_SELF, $text)) {
            return preg_match('/\b(subscription|package|plan|limit)\b/i', $text) ? ['subscription-manager', 'my_plan'] : ['sales-manager', 'my_leads'];
        }

        return match (true) {
            (bool) preg_match(self::SUPPORT, $text) => ['customer-support', 'faq'],
            (bool) preg_match(self::INVESTMENT, $text) => ['investment-analyst', null],
            default => null,
        };
    }
}
