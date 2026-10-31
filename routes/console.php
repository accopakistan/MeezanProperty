<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Saved-search emails; the command itself skips sending when no mail provider is configured.
Schedule::command('meezan:send-search-alerts')->dailyAt('08:00');

// Keep the notification centre short: read entries go after 90 days, all after 180.
Schedule::call(fn () => DB::table('notifications')
    ->where(fn ($q) => $q->where('read_at', '<', now()->subDays(90))->orWhere('created_at', '<', now()->subDays(180)))
    ->delete())->name('prune-notifications')->dailyAt('03:00');

// Shared hosting (e.g. Hostinger) often cannot keep `queue:work` running.
// With MEEZAN_QUEUE_VIA_SCHEDULER=true the cron-driven scheduler sends
// queued emails every minute instead.
if (config('meezan.queue_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping()->runInBackground();
}

// Expire lapsed listings and send expiry reminders.
Schedule::command('meezan:listing-lifecycle')->dailyAt('07:00')->withoutOverlapping();

// Assistant: new listings for AI-saved requirements, and the conversation retention policy (no-op until set).
Schedule::command('meezan:requirement-matches')->dailyAt('08:30')->withoutOverlapping();
Schedule::command('meezan:prune-conversations')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('meezan:expire-verifications')->dailyAt('02:30')->withoutOverlapping();
