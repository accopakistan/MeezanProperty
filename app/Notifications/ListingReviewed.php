<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** To the dealer when an admin approves, rejects, requests changes to, suspends or unpublishes a listing. */
class ListingReviewed extends QueuedMailNotification
{
    public function __construct(public Property $property, public string $decision, public ?string $reason = null)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        $ref = $this->property->reference;

        return match ($this->decision) {
            'approve' => ['title' => 'Listing approved: '.$ref, 'body' => Str::limit($this->property->title, 80).' is now live.', 'url' => route('properties.show', $this->property), 'icon' => 'badge-check'],
            'reject' => ['title' => 'Changes needed: '.$ref, 'body' => $this->reason ?: 'See the reviewer note on your listing.', 'url' => route('dealer.properties.edit', $this->property), 'icon' => 'alert'],
            'request_changes' => ['title' => 'Changes requested: '.$ref, 'body' => $this->reason ?: 'See the reviewer note on your listing.', 'url' => route('dealer.properties.edit', $this->property), 'icon' => 'pencil'],
            'suspend' => ['title' => 'Listing suspended: '.$ref, 'body' => $this->reason ?: 'Taken off the website while our team reviews it.', 'url' => route('dealer.properties.edit', $this->property), 'icon' => 'ban'],
            default => ['title' => 'Listing unpublished: '.$ref, 'body' => $this->reason ?: 'Returned to your drafts by our team.', 'url' => route('dealer.properties.edit', $this->property), 'icon' => 'archive'],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $property = $this->property;
        $title = '**'.$property->title.'** ('.$property->reference.')';

        $message = (new MailMessage)->greeting('Assalam o Alaikum '.$notifiable->name.',');

        return match ($this->decision) {
            'approve' => $message
                ->subject('Your listing is live: '.$property->reference)
                ->line('Good news: '.$title.' has been approved and is now visible to buyers.')
                ->line($property->expires_at ? 'It will stay live until '.$property->expires_at->format('j M Y').'. We will remind you before it expires.' : '')
                ->action('View listing', route('properties.show', $property))
                ->salutation('Meezan Property')
                ->line($this->preferencesLine()),
            'reject' => $message
                ->subject('Changes needed: '.$property->reference.' – '.Str::limit($property->title, 50))
                ->line($title.' was not approved.')
                ->line('Reviewer note: '.($this->reason ?: 'No note was given.'))
                ->action('Edit and resubmit', route('dealer.properties.edit', $property))
                ->salutation('Meezan Property')
                ->line($this->preferencesLine()),
            'request_changes' => $message
                ->subject('Changes requested: '.$property->reference.' – '.Str::limit($property->title, 50))
                ->line('Our reviewer asked for changes to '.$title.' before it can be published.')
                ->line('Reviewer note: '.($this->reason ?: 'No note was given.'))
                ->action('Make the changes', route('dealer.properties.edit', $property))
                ->salutation('Meezan Property')
                ->line($this->preferencesLine()),
            'suspend' => $message
                ->subject('Listing suspended: '.$property->reference)
                ->line($title.' has been taken off the website while our team reviews it.')
                ->line('Note: '.($this->reason ?: 'Contact Meezan Property support for details.'))
                ->action('Open listing', route('dealer.properties.edit', $property))
                ->salutation('Meezan Property')
                ->line($this->preferencesLine()),
            default => $message
                ->subject('Listing unpublished: '.$property->reference)
                ->line($title.' has been taken off the website by our team and returned to your drafts.')
                ->line($this->reason ? 'Note: '.$this->reason : 'Contact Meezan Property support if you have questions.')
                ->action('Open listing', route('dealer.properties.edit', $property))
                ->salutation('Meezan Property')
                ->line($this->preferencesLine()),
        };
    }
}
