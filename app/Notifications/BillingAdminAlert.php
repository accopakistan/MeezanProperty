<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To admins: a bank transfer to review, a payment that needs attention,
 * or a buyer lead held because every suitable dealer is at their limit.
 */
class BillingAdminAlert extends QueuedMailNotification
{
    public function __construct(public string $event, public ?Model $subject = null, public ?string $note = null)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        [$title, $url, $icon] = match ($this->event) {
            'transfer_submitted' => ['Bank transfer to review'.($this->subject instanceof Payment ? ': '.$this->subject->reference : ''), $this->paymentUrl(), 'credit-card'],
            'payment_problem' => ['Payment needs attention'.($this->subject instanceof Payment ? ': '.$this->subject->reference : ''), $this->paymentUrl(), 'alert'],
            'leads_held' => ['Buyer lead waiting: dealers at their lead limit', $this->subject instanceof Lead ? route('admin.leads.show', $this->subject) : route('admin.leads.index'), 'inbox'],
            default => ['Billing update', route('admin.billing'), 'credit-card'],
        };

        return ['title' => $title, 'body' => (string) $this->note, 'url' => $url, 'icon' => $icon];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $entry = $this->inApp($notifiable);

        return (new MailMessage)
            ->subject('Meezan Property admin: '.$entry['title'])
            ->line($entry['title'])
            ->line($entry['body'])
            ->action('Open', $entry['url']);
    }

    protected function paymentUrl(): string
    {
        return $this->subject instanceof Payment ? route('admin.payments.show', $this->subject) : route('admin.payments');
    }
}
