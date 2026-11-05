<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Assistant\IntentDetector;
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
                    // Older messages have no outbound row; everything else moves only forward, there.
                    if (! $this->messenger->applyStatus($status) && in_array($status['status'] ?? null, ['sent', 'delivered', 'read', 'failed'], true)) {
                        ConversationMessage::where('external_id', $status['id'] ?? '')->whereNotNull('external_id')->update(['delivery_status' => $status['status']]);
                    }
                }
            }
        }

        return $stored;
    }

    /**
     * What a delivery contained, for webhook_events (no content).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function eventType(array $payload): string
    {
        $values = collect($payload['entry'] ?? [])->flatMap(fn ($e) => $e['changes'] ?? [])->map(fn ($c) => $c['value'] ?? []);
        $messages = $values->contains(fn ($v) => ! empty($v['messages']));
        $statuses = $values->contains(fn ($v) => ! empty($v['statuses']));

        return match (true) {
            $messages && $statuses => 'mixed',
            $messages => 'messages',
            $statuses => 'statuses',
            default => 'other',
        };
    }

    /**
     * Text the assistant should read: typed text, the meaning of a tapped
     * button or list row, or a media caption. Null for media without a caption,
     * locations and other types (stored, not parsed).
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
            'image', 'video', 'document' => $message[$message['type']]['caption'] ?? null,
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
                // Only the envelope and a reference to any media are kept; no profile or device data. Files are not downloaded.
                'payload' => array_intersect_key($message, array_flip(['id', 'from', 'timestamp', 'type'])) + self::mediaReference($message),
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

    /**
     * @param  array<string, mixed>  $message
     * @return array{media?: array<string, string>}
     */
    protected static function mediaReference(array $message): array
    {
        $type = $message['type'] ?? null;
        if (! in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true) || ! is_array($message[$type] ?? null)) {
            return [];
        }

        return ['media' => array_filter(array_intersect_key($message[$type], array_flip(['id', 'mime_type', 'filename'])), 'is_string')];
    }

    /** Should the assistant answer this message? */
    protected function decide(Conversation $conversation, ConversationMessage $inbound): string
    {
        // STOP always works, even while a person is handling the chat.
        if ($inbound->body !== null && app(IntentDetector::class)->detect($inbound->body) === IntentDetector::STOP) {
            return 'queued';
        }
        if ($conversation->mode !== 'bot') {
            return 'skipped';
        }
        // A reaction or a WhatsApp system notice is kept, but needs no answer.
        if (in_array($inbound->type, ['reaction', 'system'], true)) {
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
