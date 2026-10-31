<?php

namespace App\Notifications;

use App\Models\Property;
use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Queued; sent by meezan:send-search-alerts to opted-in buyers with a confirmed email. */
class SavedSearchMatches extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  Collection<int, Property>  $matches
     */
    public function __construct(public SavedSearch $search, public Collection $matches, public int $total) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->total.' new '.str('listing')->plural($this->total).' for "'.$this->search->name.'"')
            ->greeting('Assalam o Alaikum '.str($notifiable->name)->before(' ').',')
            ->line('New listings matching your saved search were published on Meezan Property:');

        foreach ($this->matches as $property) {
            $mail->line('- '.$property->title.' - '.$property->priceLabel().' ('.$property->locationLabel().')');
        }

        return $mail->action('View all matches', route('account.saved-searches.open', $this->search))
            ->line('You can change or turn off these emails in your account settings.');
    }
}
