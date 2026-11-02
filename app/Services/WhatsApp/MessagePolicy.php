<?php

namespace App\Services\WhatsApp;

use App\Models\ContactConsent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\OutboundMessage;

/**
 * Decides whether a WhatsApp message may be sent, before it is queued and
 * again just before sending. Categories are kept apart:
 *
 * - transactional: answers to something the contact asked (assistant
 *   replies, staff replies, the opt-out confirmation);
 * - notification: messages we start (dealer lead alerts), which need the
 *   contact's WhatsApp consent;
 * - promotional: marketing — not sent by this version.
 *
 * Free-form messages are only allowed within Meta's customer-service window
 * (24 h since the contact last wrote); outside it an approved template is
 * required. Opt-outs (STOP) are always respected.
 */
class MessagePolicy
{
    /** Notification purposes and the consent each needs. */
    public const CONSENT = ['dealer_lead' => 'whatsapp_lead_alerts'];

    public function __construct(protected WhatsAppSettings $settings, protected WhatsAppClient $client) {}

    /**
     * @return array{allowed: bool, reason: ?string, template: bool}
     */
    public function check(string $contact, string $category, string $purpose, bool $hasTemplate = false, ?int $outboundId = null): array
    {
        $deny = fn (string $reason) => ['allowed' => false, 'reason' => $reason, 'template' => false];
        $provider = $this->client->provider();

        if (! $provider->canSend()) {
            return $deny($provider->isTest() ? 'provider_unavailable' : 'sending_disabled');
        }
        if ($category === 'promotional' && ! config('meezan.whatsapp.promotional_enabled')) {
            return $deny('promotional_disabled');
        }
        $staff = $purpose === 'staff_reply';
        if (! $staff && ! $this->settings->automationEnabled()) {
            return $deny('automation_disabled');
        }

        if ($category === 'notification') {
            if (! ContactConsent::granted(self::CONSENT[$purpose] ?? $purpose, 'whatsapp', $contact)) {
                return $deny('no_consent');
            }
        } elseif ($purpose !== 'opt_out_confirmation' && Conversation::where('channel', 'whatsapp')->where('external_contact', $contact)->where('mode', 'closed')->exists()) {
            return $deny('opted_out');
        }

        if (! $staff && $this->sentInLastHour($contact, $outboundId) >= (int) config('meezan.whatsapp.max_outbound_per_recipient_per_hour')) {
            return $deny('rate_limited');
        }

        if ($this->withinServiceWindow($contact)) {
            return ['allowed' => true, 'reason' => null, 'template' => false];
        }

        return $hasTemplate ? ['allowed' => true, 'reason' => null, 'template' => true] : $deny('outside_service_window');
    }

    public function withinServiceWindow(string $contact): bool
    {
        return ConversationMessage::query()
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subHours((int) config('meezan.whatsapp.service_window_hours')))
            ->whereHas('conversation', fn ($q) => $q->where('channel', 'whatsapp')->where('external_contact', $contact))
            ->exists();
    }

    /** Messages to this contact in the last hour, not counting the one being checked. */
    protected function sentInLastHour(string $contact, ?int $outboundId): int
    {
        return OutboundMessage::where('recipient_hash', ContactConsent::hash($contact))
            ->when($outboundId, fn ($q) => $q->whereKeyNot($outboundId))
            ->where('created_at', '>=', now()->subHour())
            ->whereIn('status', ['queued', 'sending', 'sent', 'delivered', 'read'])
            ->count();
    }
}
