<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To active admins when the assistant hands over: a buyer asked for a person,
 * repeated messages were not understood, a burst of messages looked
 * suspicious, or a WhatsApp webhook failed. No message text is included.
 */
class AssistantNeedsAttention extends QueuedMailNotification
{
    public const REASONS = [
        'human' => 'A buyer asked to speak to a person',
        'not_understood' => 'Several messages in a row were not understood',
        'suspicious' => 'Unusually many messages in a short time',
        'webhook_failed' => 'A WhatsApp webhook delivery failed',
    ];

    public function __construct(public string $reason, public ?Conversation $conversation = null, public ?string $detail = null)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        return [
            'title' => self::REASONS[$this->reason] ?? 'Assistant needs attention',
            'body' => $this->conversation
                ? ucfirst($this->conversation->channel).' conversation #'.$this->conversation->id
                : ($this->detail ?? ''),
            'url' => $this->conversation ? route('admin.conversations.show', $this->conversation) : route('admin.conversations.index'),
            'icon' => 'alert',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $entry = $this->inApp($notifiable);

        return (new MailMessage)
            ->subject('Meezan assistant: '.$entry['title'])
            ->line($entry['title'].'.')
            ->line($entry['body'])
            ->action('Open in admin', $entry['url'])
            ->salutation('Meezan Property');
    }
}
