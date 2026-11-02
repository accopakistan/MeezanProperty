<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp channel adapter for incoming webhooks. It only does the quick,
 * safe part synchronously:
 *
 *   normalise each message (text, button replies, other types) → store it
 *   once (keyed by the WhatsApp message id) → decide whether the assistant
 *   should answer → queue ProcessInboundWhatsAppMessage.
 *
 * Nothing is discarded: messages that are not answered automatically
 * (a person is handling the chat, auto-reply is off, a flood or an exact
 * repeat) are stored with a processing status staff can see. Delivery
 * status updates are applied to the outbound log.
 */
class WebhookProcessor
{
    public function __construct(
        protected WhatsAppSettings $settings,
        protected OutboundMessenger $messenger,
    ) {}

    /**
     * Meta's change notification shape: object + entry[].changes[].value.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function isValid(array $payload): bool
    {
        return ($payload['object'] ?? 'whatsapp_business_account') === 'whatsapp_business_account'
            && is_array($payload['entry'] ?? null)
            && collect($payload['entry'])->every(fn ($e) => is_array($e) && is_array($e['changes'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return int Number of new inbound messages stored.
     */
    public function process(array $payload): int
    {
        $stored = 0;
        $ownNumber = config('services.whatsapp.phone_number_id');

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // Only events for our own business number are accepted.
                $for = $value['metadata']['phone_number_id'] ?? null;
                if ($for !== null && filled($ownNumber) && (string) $for !== (string) $ownNumber) {
                    Log::warning('WhatsApp webhook for another phone number ignored');

                    continue;
                }

                $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [$c['wa_id'] ?? '' => $c['profile']['name'] ?? null]);

                foreach ($value['messages'] ?? [] as $message) {
                    if (is_array($message) && $this->storeInbound($message, $names->get($message['from'] ?? ''))) {
                        $stored++;
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (! is_array($status)) {
                        continue;
                    }
                    ConversationMessage::where('external_id', $status['id'] ?? '')->whereNotNull('external_id')->update(['delivery_status' => $status['status'] ?? null]);
                    $this->messenger->applyStatus($status);
                }
            }
        }

        return $stored;
    }

    /**
     * Text the assistant should read: typed text, or the meaning of a tapped
     * button. Null for media, locations and other types (stored, not parsed).
     *
     * @param  array<string, mixed>  $message
     */
    public static function textOf(array $message): ?string
    {
        $text = match ($message['type'] ?? 'text') {
            'text' => $message['text']['body'] ?? null,
            'interactive' => WhatsAppFormatter::buttonText(
                (string) ($message['interactive']['button_reply']['id'] ?? $message['interactive']['list_reply']['id'] ?? ''),
                $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? null,
            ),
            'button' => $message['button']['text'] ?? null,
            default => null,
        };

        return $text === null ? null : mb_substr((string) $text, 0, 4096);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function storeInbound(array $message, ?string $name): bool
    {
        $externalId = $message['id'] ?? null;
        $from = preg_replace('/\D+/', '', (string) ($message['from'] ?? ''));

        if (! $externalId || ! $from) {
            return false;
        }

        $inbound = DB::transaction(function () use ($message, $externalId, $from, $name) {
            if (ConversationMessage::where('external_id', $externalId)->lockForUpdate()->exists()) {
                return null;
            }

            $conversation = Conversation::firstOrCreate(
                ['channel' => 'whatsapp', 'external_contact' => $from],
                ['contact_name' => $name, 'mode' => 'bot', 'status' => 'active'],
            );
            // A closed (opted-out) chat reopens when the buyer writes again.
            if ($conversation->mode === 'closed') {
                $conversation->mode = 'bot';
                $conversation->status = 'active';
            }
            $conversation->last_message_at = now();
            $conversation->save();

            $text = self::textOf($message);
            $inbound = $conversation->messages()->create([
                'direction' => 'inbound',
                'role' => 'user',
                'external_id' => $externalId,
                'type' => $message['type'] ?? 'text',
                'body' => $text,
                // Only the message envelope is kept; no profile, media or device data.
                'payload' => array_intersect_key($message, array_flip(['id', 'from', 'timestamp', 'type'])),
            ]);
            $inbound->forceFill(['processing_status' => $this->decide($conversation, $inbound)])->save();

            return $inbound;
        });

        if (! $inbound) {
            return false;
        }

        if ($inbound->processing_status === 'queued') {
            ProcessInboundWhatsAppMessage::dispatch($inbound->id);
        }

        return true;
    }

    /** Should the assistant answer this message? */
    protected function decide(Conversation $conversation, ConversationMessage $inbound): string
    {
        if ($conversation->mode !== 'bot') {
            return 'skipped';
        }
        if (! $this->settings->autoReplyEnabled()) {
            return 'held';
        }

        $recent = ConversationMessage::where('conversation_id', $conversation->id)->where('direction', 'inbound')
            ->whereKeyNot($inbound->id)->where('created_at', '>=', now()->subMinute())->count();
        if ($recent >= (int) config('meezan.whatsapp.max_inbound_per_minute')) {
            return 'rate_limited';
        }

        $repeat = $inbound->body !== null && ConversationMessage::where('conversation_id', $conversation->id)->where('direction', 'inbound')
            ->whereKeyNot($inbound->id)->where('body', $inbound->body)->whereIn('processing_status', ['queued', 'processing', 'processed'])
            ->where('created_at', '>=', now()->subSeconds((int) config('meezan.whatsapp.duplicate_window_seconds')))->exists();

        return $repeat ? 'duplicate' : 'queued';
    }
}
