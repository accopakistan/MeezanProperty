<?php

namespace App\Notifications\Channels;

use App\Services\WhatsApp\OutboundMessenger;
use Illuminate\Notifications\Notification;

/**
 * Notification channel for WhatsApp. A notification provides toWhatsApp()
 * and the message goes through OutboundMessenger (policy, consent, 24-hour
 * window / template, logging), the same path as every WhatsApp message.
 * The in-app record stays the single notification record; this only adds
 * an outbound_messages row.
 */
class WhatsAppChannel
{
    public function __construct(protected OutboundMessenger $messenger) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $message = method_exists($notification, 'toWhatsApp') ? $notification->toWhatsApp($notifiable) : null;

        if ($message) {
            $this->messenger->queue($message + ['category' => 'notification', 'user_id' => $notifiable->id ?? null]);
        }
    }
}
