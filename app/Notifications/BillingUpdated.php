<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To a dealer about their package, invoices and payments. In-app always;
 * email per the dealer's preference, once a mail provider is configured.
 * Only facts recorded in billing are stated: a payment is "received"
 * only after PaymentProcessor confirmed it.
 */
class BillingUpdated extends QueuedMailNotification
{
    protected const TEXT = [
        'subscription_activated' => [':package is active', 'Your package limits apply from now.'],
        'trial_started' => ['Your :package trial has started', 'The trial ends on :date. You will receive an invoice before then to keep the package.'],
        'payment_pending' => ['Invoice :number is awaiting payment', 'Open Packages & Payments to see the amount and how to pay.'],
        'payment_succeeded' => ['Payment received for :number', 'Thank you. The receipt is in Packages & Payments.'],
        'payment_failed' => ['Payment for :number did not go through', ':reason You can try again from Packages & Payments.'],
        'payment_rejected' => ['Bank transfer for :number could not be confirmed', ':reason Please check the details and submit again.'],
        'renewal_due' => ['Renewal invoice :number issued', 'Pay it by :date to keep :package without a break.'],
        'payment_overdue' => ['Payment overdue for :package', 'The renewal is unpaid. :package continues for :grace more day(s); after that your account moves to the default plan.'],
        'subscription_expiring' => [':package ends on :date', 'After that your account moves to the default plan or the package you chose.'],
        'subscription_expired' => [':package has expired', 'Your account is on the default plan now. Existing listings stay live; new listings and leads follow the default plan limits.'],
        'subscription_ended' => [':package has ended', 'Your account is on the default plan now. You can choose a package at any time.'],
        'subscription_cancel_scheduled' => [':package will end on :date', 'You keep the package until then. You can undo this before that date.'],
        'downgrade_scheduled' => ['Change to :target scheduled', 'It takes effect on :date, at the end of your paid period.'],
        'subscription_suspended' => [':package is suspended', ':reason Contact Meezan Property support to resolve this.'],
        'refund_recorded' => ['Refund recorded on :number', 'A refund of :amount has been recorded. It may take a few days to reach your account.'],
        'listing_limit_reached' => ['You have reached your listing limit', 'Upgrade your package to add more listings.'],
        'lead_limit_reached' => ['You have reached your monthly lead limit', 'New buyer requirements go to other dealers until next month. Upgrade your package to receive more leads.'],
    ];

    /** @param  array<string, mixed>  $data */
    public function __construct(public string $event, public ?Subscription $subscription = null, public array $data = [])
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        [$title, $body] = self::TEXT[$this->event] ?? ['Billing update', ''];
        $date = $this->subscription?->renewsOrEndsAt();
        $replace = [
            ':package' => $this->subscription?->name() ?? 'Your package',
            ':target' => $this->data['package'] ?? 'the new package',
            ':number' => $this->data['number'] ?? 'your invoice',
            ':reason' => isset($this->data['reason']) ? 'Reason: '.rtrim($this->data['reason'], '.').'.' : '',
            ':date' => $date ? $date->format('j M Y') : 'the end of the period',
            ':grace' => (string) config('meezan.billing.grace_days'),
            ':amount' => $this->data['amount'] ?? '',
        ];

        return [
            'title' => strtr($title, $replace),
            'body' => trim(preg_replace('/\s+/', ' ', strtr($body, $replace))),
            'url' => isset($this->data['invoice_id']) ? route('dealer.billing.invoices.show', $this->data['invoice_id']) : route('dealer.subscription'),
            'icon' => in_array($this->event, ['listing_limit_reached', 'lead_limit_reached', 'payment_failed', 'payment_rejected', 'payment_overdue'], true) ? 'alert' : 'credit-card',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $entry = $this->inApp($notifiable);

        return (new MailMessage)
            ->subject('Meezan Property: '.$entry['title'])
            ->greeting($entry['title'])
            ->line($entry['body'])
            ->action('Open Packages & Payments', $entry['url'])
            ->salutation('Meezan Property');
    }
}
