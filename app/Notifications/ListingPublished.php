<?php

namespace App\Notifications;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * To active admins, in the notification centre only (no email), when a
 * listing passed the automatic checks and went live without review. Admins
 * can still suspend, reject or request changes from the listing page.
 */
class ListingPublished extends QueuedMailNotification
{
    public function __construct(public Property $property)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && $notifiable->isActive() && $notifiable->notify_in_app !== false ? ['database'] : [];
    }

    public function inApp(object $notifiable): ?array
    {
        return [
            'title' => 'New live listing: '.$this->property->reference,
            'body' => Str::limit($this->property->title, 80).' · published without review',
            'url' => route('admin.properties.show', $this->property),
            'icon' => 'layers',
        ];
    }
}
