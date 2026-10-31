<?php

namespace App\Services\WhatsApp;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Assistant\ConversationAssistant;
use Illuminate\Support\Facades\DB;

/**
 * WhatsApp channel adapter. Stores inbound messages idempotently (keyed by
 * the WhatsApp message id), passes text to the same ConversationAssistant
 * the website uses while the conversation is in bot mode, and sends the
 * reply through WhatsAppClient (which sends nothing unless enabled).
 * Conversations a person has taken over (mode "human") get no bot reply.
 */
class WebhookProcessor
{
    public function __construct(
        protected WhatsAppClient $client,
        protected ConversationAssistant $assistant,
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

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [$c['wa_id'] ?? '' => $c['profile']['name'] ?? null]);

                foreach ($value['messages'] ?? [] as $message) {
                    if (is_array($message) && $this->storeInbound($message, $names->get($message['from'] ?? ''))) {
                        $stored++;
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    ConversationMessage::where('external_id', $status['id'] ?? '')->update(['delivery_status' => $status['status'] ?? null]);
                }
            }
        }

        return $stored;
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

        [$conversation, $inbound] = DB::transaction(function () use ($message, $externalId, $from, $name) {
            if (ConversationMessage::where('external_id', $externalId)->lockForUpdate()->exists()) {
                return [null, null];
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

            $inbound = $conversation->messages()->create([
                'direction' => 'inbound',
                'role' => 'user',
                'external_id' => $externalId,
                'type' => $message['type'] ?? 'text',
                'body' => isset($message['text']['body']) ? mb_substr((string) $message['text']['body'], 0, 4096) : null,
                // Only the message envelope is kept; no profile or device data.
                'payload' => array_intersect_key($message, array_flip(['id', 'from', 'timestamp', 'type'])),
            ]);

            return [$conversation, $inbound];
        });

        if (! $conversation) {
            return false;
        }

        if ($conversation->mode === 'bot') {
            $text = $message['text']['body'] ?? null;
            $reply = $text !== null
                ? $this->assistant->respond($conversation, (string) $text, null, $inbound)->toPlainText()
                : 'I can read text messages only for now. Please type what you are looking for, e.g. "10 marla house in DHA Lahore around 4 crore".';

            $send = $this->client->sendText($conversation->external_contact, $reply);
            $outbound = $this->assistant->lastMessage && $text !== null
                ? $this->assistant->lastMessage
                : $conversation->messages()->create(['direction' => 'outbound', 'role' => 'assistant', 'type' => 'text', 'body' => $reply]);
            $outbound->forceFill(['external_id' => $send['id'], 'delivery_status' => $send['sent'] ? 'sent' : 'not_sent'])->save();
            $this->assistant->lastMessage = null;
        }

        return true;
    }
}
