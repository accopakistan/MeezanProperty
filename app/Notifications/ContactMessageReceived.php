<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Contact-form message, to the company contact address (MEEZAN_CONTACT_EMAIL)
 * or, when that is not set, to the admins. Reply-to is the sender, so staff
 * can answer directly.
 */
class ContactMessageReceived extends QueuedMailNotification
{
    public function __construct(public Inquiry $inquiry)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        return [
            'title' => 'Contact form: '.Str::limit($this->inquiry->name, 60),
            'body' => Str::limit(preg_replace('/\s+/', ' ', $this->inquiry->message), 140).' · reply to '.$this->inquiry->email,
            'url' => route('notifications.index'),
            'icon' => 'mail',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inquiry = $this->inquiry;

        return (new MailMessage)
            ->subject('Contact form: '.Str::limit($inquiry->name, 60))
            ->replyTo($inquiry->email, $inquiry->name)
            ->greeting('New contact form message')
            ->line('From: '.$inquiry->name.' · '.$inquiry->phone)
            ->line('> '.Str::limit(preg_replace('/\s+/', ' ', $inquiry->message), 1500))
            ->line('Reply to this email to answer the sender.')
            ->salutation('Meezan Property website');
    }
}
