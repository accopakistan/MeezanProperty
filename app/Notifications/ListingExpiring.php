<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the dealer a few days before a listing expires ("soon"), and when it
 * has expired ("expired"). Both point to My Listings, where Renew lives.
 */
class ListingExpiring extends QueuedMailNotification
{
    public function __construct(public Property $property, public bool $expired = false)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        $date = $this->property->expires_at?->format('j M Y');

        return [
            'title' => ($this->expired ? 'Listing expired: ' : 'Listing expires '.$date.': ').$this->property->reference,
            'body' => $this->expired ? 'Hidden from buyers since '.$date.'. Renew it if it is still available.' : 'Renew it to keep it live.',
            'url' => route('dealer.properties.index'),
            'icon' => 'clock',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $property = $this->property;
        $title = '**'.$property->title.'** ('.$property->reference.')';
        $date = $property->expires_at?->format('j M Y');

        $message = (new MailMessage)->greeting('Assalam o Alaikum '.$notifiable->name.',');

        if ($this->expired) {
            $message->subject('Listing expired: '.$property->reference)
                ->line($title.' expired on '.$date.' and is no longer shown to buyers.')
                ->line('If the property is still available, renew it within '.config('meezan.listing.renew_grace_days').' days to put it back online straight away. After that it needs to be reviewed again.');
        } else {
            $message->subject('Listing expires on '.$date.': '.$property->reference)
                ->line($title.' will stop being shown to buyers on '.$date.'.')
                ->line('If it is still available, renew it to keep it live for another '.config('meezan.listing.lifetime_days').' days.');
        }

        return $message
            ->action('Renew in My Listings', route('dealer.properties.index'))
            ->line('If the property has been sold or rented out, no action is needed.')
            ->salutation('Meezan Property')
            ->line($this->preferencesLine());
    }
}
