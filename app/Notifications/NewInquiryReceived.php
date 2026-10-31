<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * To the listing's dealer when a buyer sends an inquiry. The buyer's phone
 * and email stay in the dashboard rather than travelling by email.
 */
class NewInquiryReceived extends QueuedMailNotification
{
    public function __construct(public Inquiry $inquiry)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        $property = $this->inquiry->loadMissing('property:id,title,reference')->property;

        return [
            'title' => 'New inquiry from '.$this->inquiry->name,
            'body' => $property ? $property->reference.' · '.Str::limit($property->title, 70) : 'About one of your listings',
            'url' => route('dealer.leads'),
            'icon' => 'message',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inquiry = $this->inquiry->loadMissing('property:id,title,reference');
        $property = $inquiry->property;
        $contact = ['call' => 'a phone call', 'whatsapp' => 'WhatsApp', 'email' => 'email'][$inquiry->preferred_contact] ?? $inquiry->preferred_contact;

        return (new MailMessage)
            ->subject('New inquiry: '.($property ? $property->reference.' – '.Str::limit($property->title, 60) : 'your listing'))
            ->greeting('Assalam o Alaikum '.$notifiable->name.',')
            ->line($inquiry->name.' has sent an inquiry about '.($property ? '**'.$property->title.'** ('.$property->reference.')' : 'one of your listings').'.')
            ->line('They prefer to be contacted by '.$contact.'.')
            ->line('> '.Str::limit(preg_replace('/\s+/', ' ', $inquiry->message), 300))
            ->action('View inquiry and contact details', route('dealer.leads'))
            ->line('Marking the inquiry as contacted records your response time in your performance report.')
            ->salutation('Meezan Property')
            ->line($this->preferencesLine());
    }
}
