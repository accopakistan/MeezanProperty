<?php

namespace App\Jobs;

use App\Models\ConversationMessage;
use App\Models\User;
use App\Notifications\AssistantNeedsAttention;
use App\Services\Agents\AgentChatRouter;
use App\Services\Assistant\AssistantReply;
use App\Services\Assistant\ConversationAssistant;
use App\Services\Assistant\IntentDetector;
use App\Services\WhatsApp\AccountLinker;
use App\Services\WhatsApp\OutboundMessenger;
use App\Services\WhatsApp\WhatsAppFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Runs one stored inbound WhatsApp message through the same assistant as
 * the website (requirement memory, seven-city locations, real listings)
 * and queues the reply. Each message is processed at most once: the job
 * claims it ("queued" → "processing") and is never retried automatically,
 * because the assistant updates the conversation's state. If processing
 * fails the buyer gets a short apology (no technical details) and admins
 * are alerted.
 */
class ProcessInboundWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $conversationMessageId)
    {
        $this->onQueue(config('meezan.whatsapp.queue'));
    }

    public function handle(ConversationAssistant $assistant, OutboundMessenger $messenger, WhatsAppFormatter $formatter, AccountLinker $linker): void
    {
        $claimed = ConversationMessage::whereKey($this->conversationMessageId)->where('processing_status', 'queued')->update(['processing_status' => 'processing']);
        if (! $claimed) {
            return;
        }
        $inbound = ConversationMessage::with('conversation.user')->findOrFail($this->conversationMessageId);
        $conversation = $inbound->conversation;

        // A person is handling the chat, unless this is the contact's STOP.
        if ($conversation->mode !== 'bot' && ($inbound->body === null || app(IntentDetector::class)->detect($inbound->body) !== IntentDetector::STOP)) {
            $inbound->forceFill(['processing_status' => 'skipped', 'processed_at' => now()])->save();

            return;
        }

        try {
            $text = $inbound->body;
            if ($text !== null && $linker->matches($text)) {
                $reply = $linker->link($conversation, $text);
                $stored = null;
            } elseif ($text === null) {
                $reply = new AssistantReply('I can read text messages only for now. Please type what you are looking for, e.g. "10 marla house in DHA Lahore around 4 crore", or reply "human" to reach our team.', 'unsupported_type');
                $stored = null;
            } else {
                $reply = $assistant->respond($conversation, $text, $conversation->user, $inbound);
                $stored = $assistant->lastMessage;
                $assistant->lastMessage = null;
            }

            $formatted = $formatter->format($reply);
            $stored ??= $conversation->messages()->create(['direction' => 'outbound', 'role' => 'assistant', 'type' => 'text', 'intent' => $reply->intent, 'agent_slug' => AgentChatRouter::agentFor($reply->intent)]);
            $stored->forceFill(['body' => $formatted['body']])->save();

            $messenger->queue([
                'to' => $conversation->external_contact,
                'body' => $formatted['body'],
                'buttons' => $formatted['buttons'],
                'list' => $formatted['list'],
                'category' => 'transactional',
                'purpose' => $reply->intent === IntentDetector::STOP ? 'opt_out_confirmation' : 'reply',
                'dedupe_key' => 'reply:'.$inbound->id,
                'conversation_id' => $conversation->id,
                'conversation_message_id' => $stored->id,
                'lead_id' => $conversation->fresh()->lead_id,
                'user_id' => $conversation->user_id,
            ]);
            $inbound->forceFill(['processing_status' => 'processed', 'processed_at' => now()])->save();
        } catch (Throwable $e) {
            $inbound->forceFill(['processing_status' => 'failed', 'processed_at' => now()])->save();
            Log::error('WhatsApp message processing failed', ['conversation_message' => $inbound->id, 'error' => get_class($e).': '.$e->getMessage()]);
            Notification::send(User::activeAdmins(), new AssistantNeedsAttention('processing_failed', $conversation));

            $apology = $conversation->messages()->create(['direction' => 'outbound', 'role' => 'assistant', 'type' => 'text', 'intent' => 'error',
                'body' => 'Sorry, something went wrong while reading your message. Our team has been told and will follow up; you can also try again in a moment, or reply "human" to reach our team.']);
            $messenger->queue([
                'to' => $conversation->external_contact, 'body' => $apology->body, 'category' => 'transactional', 'purpose' => 'reply',
                'dedupe_key' => 'reply:'.$inbound->id, 'conversation_id' => $conversation->id, 'conversation_message_id' => $apology->id,
            ]);
        }
    }
}
