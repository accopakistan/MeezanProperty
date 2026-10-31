<?php

namespace App\Console\Commands;

use App\Models\SavedSearch;
use App\Notifications\SavedSearchMatches;
use Illuminate\Console\Command;

/**
 * Emails buyers about new listings for their saved searches. Runs only when a
 * real mail transport is configured, and only for users who opted in.
 */
class SendSearchAlerts extends Command
{
    protected $signature = 'meezan:send-search-alerts';

    protected $description = 'Email new listing matches for saved searches (opted-in users only)';

    public function handle(): int
    {
        if (in_array(config('mail.default'), ['log', 'array', null], true)) {
            $this->warn('No mail provider configured (MAIL_MAILER='.config('mail.default').'). No alerts sent.');

            return self::SUCCESS;
        }

        $sent = 0;

        SavedSearch::with('user')
            ->whereIn('alert_frequency', ['daily', 'weekly'])
            ->whereHas('user', fn ($q) => $q->where('notify_email', true)->where('status', 'active')->whereNotNull('email_verified_at'))
            ->chunkById(100, function ($searches) use (&$sent) {
                foreach ($searches as $search) {
                    $due = $search->alert_frequency === 'daily' ? now()->subDay() : now()->subWeek();
                    if ($search->last_alerted_at && $search->last_alerted_at->gt($due)) {
                        continue;
                    }

                    $since = $search->last_alerted_at ?? $search->created_at;
                    $total = $search->newMatchesQuery($since)->count();

                    if ($total > 0) {
                        $search->user->notify(new SavedSearchMatches($search, $search->newMatchesQuery($since)->with('area')->limit(5)->get(), $total));
                        $sent++;
                    }

                    $search->forceFill(['last_alerted_at' => now()])->save();
                }
            });

        $this->info("Sent {$sent} alert email(s).");

        return self::SUCCESS;
    }
}
