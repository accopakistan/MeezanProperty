<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for Meezan Property's service emails.
 *
 * - Queued and dispatched only after the surrounding database transaction
 *   commits, so a rolled-back action never sends mail.
 * - Retried 3 times (after 1 and 5 minutes); a final failure is logged
 *   rather than lost silently, and also lands in failed_jobs.
 * - Users who switched off account emails (or are suspended) get none.
 */
abstract class QueuedMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /** Skip quietly if the listing or inquiry was deleted before sending. */
    public bool $deleteWhenMissingModels = true;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        // The in-app copy is independent of email, so turning emails off never hides activity.
        $channels = $notifiable->isActive() && $notifiable->notify_in_app !== false && $this->inApp($notifiable) ? ['database'] : [];

        return $notifiable->wantsAccountEmail() ? [...$channels, 'mail'] : $channels;
    }

    /**
     * The notification-centre entry: title, body, url and icon, or null for
     * email-only notifications.
     *
     * @return array{title: string, body: string, url: string, icon: string}|null
     */
    public function inApp(object $notifiable): ?array
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inApp($notifiable) ?? [];
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Notification could not be delivered', [
            'notification' => static::class,
            'error' => $exception->getMessage(),
        ]);
    }

    /** Shared footer line explaining why the email was sent. */
    protected function preferencesLine(): string
    {
        return 'You receive this because you have listings on Meezan Property. You can turn these emails off in your profile.';
    }
}
