<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/** Laravel's signed verification link, queued and in Meezan Property wording. */
class VerifyEmailAddress extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct()
    {
        $this->afterCommit();
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your email address')
            ->greeting('Welcome to Meezan Property')
            ->line('Please confirm this is your email address so we can send you inquiries, alerts and account messages.')
            ->action('Confirm email address', $url)
            ->line('The link expires in '.config('auth.verification.expire', 60).' minutes. If you did not create an account, you can ignore this email.')
            ->salutation('Meezan Property');
    }
}
