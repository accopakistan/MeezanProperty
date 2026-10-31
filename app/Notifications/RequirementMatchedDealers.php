<?php

namespace App\Notifications;

use App\Models\BuyerRequirement;
use Illuminate\Notifications\Messages\MailMessage;

/** To a buyer when the AI assistant shared their requirement with matching dealers. */
class RequirementMatchedDealers extends QueuedMailNotification
{
    public function __construct(public BuyerRequirement $requirement, public int $dealers)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        return [
            'title' => 'Shared with '.$this->dealers.' verified '.str('dealer')->plural($this->dealers),
            'body' => $this->requirement->summary(),
            'url' => route('account.requirements.index'),
            'icon' => 'users',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your requirement was shared with '.$this->dealers.' verified '.str('dealer')->plural($this->dealers))
            ->greeting('Dealers will be in touch')
            ->line('As you asked, we shared this requirement with up to '.$this->dealers.' verified dealers who cover the area:')
            ->line($this->requirement->summary())
            ->action('View your requirements', route('account.requirements.index'))
            ->line('Only dealers who serve this city received it. You can close the requirement at any time.')
            ->salutation('Meezan Property');
    }
}
