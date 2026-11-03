<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * To active admins when the AI workforce needs a person: an approval is
 * waiting, a task failed for good, or a budget stopped the language model.
 * Carries a short description only, never report contents.
 */
class AgentNeedsAttention extends QueuedMailNotification
{
    public function __construct(public string $title, public string $body, public string $url)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'icon' => 'alert'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Meezan AI: '.$this->title)
            ->line($this->title.'.')
            ->line($this->body)
            ->action('Open the AI Command Center', $this->url)
            ->salutation('Meezan Property');
    }
}
