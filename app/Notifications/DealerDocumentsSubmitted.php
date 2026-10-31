<?php

namespace App\Notifications;

use App\Models\DealerProfile;
use Illuminate\Notifications\Messages\MailMessage;

/** To active admins when a dealer uploads verification documents. The files are not attached. */
class DealerDocumentsSubmitted extends QueuedMailNotification
{
    public function __construct(public DealerProfile $profile, public string $typeLabel, public int $count)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        return [
            'title' => 'Verification documents: '.$this->profile->agency_name,
            'body' => $this->count.' '.str('file')->plural($this->count).' · '.$this->typeLabel,
            'url' => route('admin.dealers.show', $this->profile),
            'icon' => 'file-text',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verification documents: '.$this->profile->agency_name)
            ->greeting('Documents to review')
            ->line($this->profile->agency_name.' uploaded '.$this->count.' '.str('file')->plural($this->count).' ('.$this->typeLabel.').')
            ->line('Current status: '.$this->profile->verification_status->label().'.')
            ->action('Review dealer', route('admin.dealers.show', $this->profile))
            ->line('Documents are only viewable inside the admin panel and are not attached to this email.')
            ->salutation('Meezan Property');
    }
}
