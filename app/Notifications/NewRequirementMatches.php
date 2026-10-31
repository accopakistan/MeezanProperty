<?php

namespace App\Notifications;

use App\Models\BuyerRequirement;
use Illuminate\Notifications\Messages\MailMessage;

/** To a buyer when new listings match a requirement they saved with the AI assistant. */
class NewRequirementMatches extends QueuedMailNotification
{
    public function __construct(public BuyerRequirement $requirement, public int $count)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        return [
            'title' => $this->count.' new '.str('listing')->plural($this->count).' for your requirement',
            'body' => $this->requirement->summary(),
            'url' => route('account.requirements.matches', $this->requirement),
            'icon' => 'sparkles',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->count.' new '.str('listing')->plural($this->count).' match your requirement')
            ->line('New reviewed listings match: '.$this->requirement->summary().'.')
            ->action('See the matches', route('account.requirements.matches', $this->requirement))
            ->line('You can close the requirement at any time to stop these updates.')
            ->salutation('Meezan Property');
    }
}
