<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessage;
use App\Models\ContactConsent;
use App\Models\ConversationMessage;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Notifications\AssistantNeedsAttention;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * The only way WhatsApp messages leave Meezan Property:
 *
 *   queue() → MessagePolicy → outbound_messages row (queued or blocked)
 *          → SendWhatsAppMessage job → deliver() → provider → status.
 *
 * A dedupe key makes queueing idempotent (a repeated webhook or job never
 * produces a second message). Temporary failures are retried a limited
 * number of times; permanent ones are marked failed for staff to review.
 * Statuses change only from real provider responses or status webhooks.
 */
class OutboundMessenger
{
    public function __construct(protected MessagePolicy $policy, protected WhatsAppClient $client) {}

    /**
     * @param  array{to: string, body: string, category: string, purpose: string, dedupe_key?: ?string,
     *               conversation_id?: ?int, conversation_message_id?: ?int, lead_id?: ?int, user_id?: ?int,
     *               buttons?: list<array{id: string, title: string}>, template?: ?array{name: string, language: string, parameters: list<string>}}  $message
     */
    public function queue(array $message): OutboundMessage
    {
        if (! in_array($message['category'], ['transactional', 'notification', 'promotional'], true)) {
            throw new InvalidArgumentException('Unknown message category.');
        }
        if (! empty($message['dedupe_key']) && ($existing = OutboundMessage::where('dedupe_key', $message['dedupe_key'])->first())) {
            return $existing;
        }

        $template = $message['template'] ?? null;
        $decision = $this->policy->check($message['to'], $message['category'], $message['purpose'], (bool) ($template['name'] ?? null));
        $provider = $this->client->provider();
        $buttons = $message['buttons'] ?? [];
        $type = match (true) {
            $decision['template'] => 'template',
            $buttons && config('meezan.whatsapp.interactive_buttons') && mb_strlen($message['body']) <= 1024 => 'interactive',
            default => 'text',
        };

        $outbound = new OutboundMessage;
        $outbound->forceFill([
            'provider' => $provider->key(),
            'recipient' => $message['to'],
            'recipient_hash' => ContactConsent::hash($message['to']),
            'recipient_last4' => substr($message['to'], -4),
            'user_id' => $message['user_id'] ?? null,
            'conversation_id' => $message['conversation_id'] ?? null,
            'conversation_message_id' => $message['conversation_message_id'] ?? null,
            'lead_id' => $message['lead_id'] ?? null,
            'category' => $message['category'],
            'purpose' => $message['purpose'],
            'type' => $type,
            'body' => mb_substr($message['body'], 0, 4096),
            'payload' => match ($type) {
                'template' => ['template' => $template['name'], 'language' => $template['language'] ?? 'en', 'parameters' => $template['parameters'] ?? []],
                'interactive' => ['buttons' => array_slice($buttons, 0, 3)],
                default => null,
            },
            'status' => $decision['allowed'] ? 'queued' : 'blocked',
            'blocked_reason' => $decision['reason'],
            'dedupe_key' => $message['dedupe_key'] ?? null,
            'is_test' => $provider->isTest(),
        ]);

        try {
            $outbound->save();
        } catch (UniqueConstraintViolationException) {
            return OutboundMessage::where('dedupe_key', $message['dedupe_key'])->firstOrFail();
        }

        $this->syncConversationMessage($outbound);
        if ($outbound->status === 'queued') {
            SendWhatsAppMessage::dispatch($outbound->id)->afterCommit();
        }

        return $outbound;
    }

    /**
     * One delivery attempt (called by SendWhatsAppMessage).
     *
     * @return string "sent", "retry", "failed", "blocked" or "skipped"
     */
    public function deliver(int $id): string
    {
        $outbound = DB::transaction(function () use ($id) {
            $outbound = OutboundMessage::whereKey($id)->lockForUpdate()->first();
            if (! $outbound || $outbound->status !== 'queued') {
                return null; // already handled: never send twice
            }
            $outbound->forceFill(['status' => 'sending', 'attempts' => $outbound->attempts + 1])->save();

            return $outbound;
        });
        if (! $outbound) {
            return 'skipped';
        }

        // Things may have changed since queueing (opt-out, automation switched off, window closed).
        $decision = $this->policy->check($outbound->recipient, $outbound->category, $outbound->purpose, $outbound->type === 'template', $outbound->id);
        if (! $decision['allowed']) {
            $outbound->forceFill(['status' => 'blocked', 'blocked_reason' => $decision['reason']])->save();
            $this->syncConversationMessage($outbound);

            return 'blocked';
        }

        $provider = $this->client->provider();
        $result = $provider->send($outbound);

        if ($result->sent) {
            $outbound->forceFill([
                'status' => 'sent', 'provider' => $provider->key(), 'provider_message_id' => $result->providerMessageId,
                'sent_at' => now(), 'last_error' => null, 'is_test' => $outbound->is_test || $provider->isTest(),
            ])->save();
            $this->syncConversationMessage($outbound);

            return 'sent';
        }

        $retry = $result->retryable && $outbound->attempts < (int) config('meezan.whatsapp.max_send_attempts');
        $outbound->forceFill([
            'status' => $retry ? 'queued' : 'failed',
            'last_error' => mb_substr((string) $result->error, 0, 250),
            'failed_at' => $retry ? null : now(),
        ])->save();
        $this->syncConversationMessage($outbound);

        if (! $retry) {
            AuditLogger::record('whatsapp.message_failed', $outbound, ['purpose' => $outbound->purpose, 'attempts' => $outbound->attempts, 'error' => $outbound->last_error]);
            // One alert per hour at most, however many messages fail.
            if (Cache::add('whatsapp:send-failed-alert', true, now()->addHour())) {
                Notification::send(User::activeAdmins(), new AssistantNeedsAttention('send_failed', $outbound->conversation, 'Outbound message #'.$outbound->id));
            }
        }

        return $retry ? 'retry' : 'failed';
    }

    /** Staff retry a failed message; the policy is checked again (it may now be outside the 24 h window). */
    public function retry(OutboundMessage $outbound, User $by): OutboundMessage
    {
        if (! $outbound->isRetryable()) {
            throw new InvalidArgumentException('Only failed messages can be retried.');
        }
        $decision = $this->policy->check($outbound->recipient, $outbound->category, $outbound->purpose, $outbound->type === 'template', $outbound->id);
        $outbound->forceFill([
            'status' => $decision['allowed'] ? 'queued' : 'blocked',
            'blocked_reason' => $decision['reason'],
            'attempts' => 0,
            'failed_at' => null,
        ])->save();
        AuditLogger::record('whatsapp.message_retried', $outbound, ['result' => $outbound->status, 'reason' => $decision['reason']], $by->id);
        $this->syncConversationMessage($outbound);

        if ($outbound->status === 'queued') {
            SendWhatsAppMessage::dispatch($outbound->id);
        }

        return $outbound;
    }

    /**
     * Apply a status update from the provider's webhook. Progress only moves
     * forward (sent → delivered → read); "failed" is recorded with its code.
     *
     * @param  array<string, mixed>  $status  Meta's statuses[] entry
     */
    public function applyStatus(array $status): void
    {
        $outbound = OutboundMessage::where('provider_message_id', $status['id'] ?? '')->first();
        $state = $status['status'] ?? null;
        if (! $outbound || ! in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        if ($state === 'failed') {
            $error = $status['errors'][0] ?? [];
            $outbound->forceFill(['status' => 'failed', 'failed_at' => now(), 'last_error' => mb_substr(trim(($error['code'] ?? '').' '.($error['title'] ?? 'Delivery failed')), 0, 250)])->save();
        } elseif ((OutboundMessage::PROGRESS[$state] ?? 0) > (OutboundMessage::PROGRESS[$outbound->status] ?? 0)) {
            $outbound->forceFill(['status' => $state, $state.'_at' => now()])->save();
        }
    }

    /** The assistant's stored message shows the same delivery state (and id) as its outbound row. */
    protected function syncConversationMessage(OutboundMessage $outbound): void
    {
        if (! $outbound->conversation_message_id) {
            return;
        }
        ConversationMessage::whereKey($outbound->conversation_message_id)->update([
            'delivery_status' => match ($outbound->status) {
                'blocked' => 'not_sent',
                'sending' => 'queued',
                default => $outbound->status,
            },
            'external_id' => $outbound->provider_message_id,
        ]);
    }
}
