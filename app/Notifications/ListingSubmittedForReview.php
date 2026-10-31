<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** To active admins when a dealer submits a listing for moderation. */
class ListingSubmittedForReview extends QueuedMailNotification
{
    /**
     * @param  list<string>  $possibleDuplicates  references of similar listings
     */
    public function __construct(public Property $property, public array $possibleDuplicates = [])
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        return [
            'title' => 'Listing to review: '.$this->property->reference,
            'body' => Str::limit($this->property->title, 80).($this->possibleDuplicates ? ' · possible duplicate' : ''),
            'url' => route('admin.properties.show', $this->property),
            'icon' => 'layers',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $property = $this->property->loadMissing(['area:id,name', 'dealer.dealerProfile:id,user_id,agency_name']);
        $by = $property->dealer?->dealerProfile?->agency_name ?? $property->dealer?->name ?? 'A dealer';

        $message = (new MailMessage)
            ->subject('Listing to review: '.$property->reference.' – '.Str::limit($property->title, 60))
            ->greeting('Listing submitted for review')
            ->line($by.' submitted **'.$property->title.'** ('.$property->reference.') in '.($property->area?->name ?? $property->city).' at '.$property->priceLabel().'.')
            ->line($property->hasCoordinates() ? 'The dealer placed an exact map pin. Check it sits in the stated area.' : 'No exact map pin was provided.');

        if ($this->possibleDuplicates) {
            $message->line('Possible duplicate of: '.implode(', ', $this->possibleDuplicates).'.');
        }

        return $message
            ->action('Review listing', route('admin.properties.show', $property))
            ->salutation('Meezan Property');
    }
}
