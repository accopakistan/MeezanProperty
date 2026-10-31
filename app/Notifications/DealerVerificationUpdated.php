<?php

namespace App\Notifications;

use App\Models\DealerProfile;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To a dealer when their verification moves on: a document approved or
 * rejected, more documents requested, verified, rejected, suspended or
 * expired. Only the reason written for the dealer is included; internal
 * staff notes never are. In-app always; email per the dealer's preference
 * (and only once a mail provider is configured).
 */
class DealerVerificationUpdated extends QueuedMailNotification
{
    protected const TEXT = [
        'document_approved' => ['%s approved', 'This document has been approved.'],
        'document_rejected' => ['%s needs replacing', 'Please upload a replacement.'],
        'documents_requested' => ['More documents needed', 'Please upload the documents requested and submit again.'],
        'verified' => ['Your agency is verified', 'The verified badge now appears on your profile and listings.'],
        'rejected' => ['Verification not approved', 'You can upload corrected documents and submit again.'],
        'suspended' => ['Account suspended', 'Your verified badge is removed and you cannot upload documents or receive leads while suspended. Contact support to resolve this.'],
        'expired' => ['Verification expired', 'Upload current documents and submit again to restore the verified badge.'],
    ];

    public function __construct(public DealerProfile $profile, public string $event, public ?string $reason = null, public ?string $documentLabel = null)
    {
        $this->afterCommit();
    }

    public function inApp(object $notifiable): ?array
    {
        [$title, $body] = self::TEXT[$this->event] ?? ['Verification update', ''];

        return [
            'title' => sprintf($title, $this->documentLabel ?? 'Document'),
            'body' => trim(($this->reason ? 'Reason: '.$this->reason.' ' : '').$body),
            'url' => route('dealer.verification'),
            'icon' => $this->event === 'verified' ? 'badge-check' : 'shield',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $entry = $this->inApp($notifiable);

        return (new MailMessage)
            ->subject('Meezan Property: '.$entry['title'])
            ->greeting($entry['title'])
            ->line($entry['body'])
            ->action('Open verification', $entry['url'])
            ->salutation('Meezan Property');
    }
}
