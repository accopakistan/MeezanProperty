<?php

namespace App\Console\Commands;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Notifications\ListingExpiring;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

/**
 * Daily listing housekeeping:
 *  1. Published listings past expires_at become Expired (they were already
 *     hidden from buyers by the published scope) and the dealer is told.
 *  2. Published listings expiring within `meezan.listing.reminder_days` get
 *     one reminder per listing period.
 *
 * --dry-run reports what would happen without changing or sending anything.
 */
class ListingLifecycle extends Command
{
    protected $signature = 'meezan:listing-lifecycle {--dry-run : Report only; change nothing}';

    protected $description = 'Expire listings past their end date and remind dealers before expiry';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $expired = 0;
        Property::with('dealer')
            ->where('status', PropertyStatus::Published)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($properties) use ($dry, &$expired) {
                foreach ($properties as $property) {
                    $expired++;
                    $this->line(($dry ? '[dry run] ' : '').'Expire '.$property->reference.' (ended '.$property->expires_at->format('j M Y').')');
                    if ($dry) {
                        continue;
                    }

                    $property->status = PropertyStatus::Expired;
                    $property->save();
                    AuditLogger::record('property.expired', $property, ['expired_at' => $property->expires_at->toIso8601String()], null);
                    $property->dealer?->notify(new ListingExpiring($property, expired: true));
                }
            });

        $reminded = 0;
        Property::with('dealer')
            ->where('status', PropertyStatus::Published)
            ->whereNull('expiry_reminder_sent_at')
            ->whereBetween('expires_at', [now(), now()->addDays((int) config('meezan.listing.reminder_days'))])
            ->chunkById(100, function ($properties) use ($dry, &$reminded) {
                foreach ($properties as $property) {
                    $reminded++;
                    $this->line(($dry ? '[dry run] ' : '').'Remind '.$property->reference.' (expires '.$property->expires_at->format('j M Y').')');
                    if ($dry) {
                        continue;
                    }

                    $property->forceFill(['expiry_reminder_sent_at' => now()])->save();
                    $property->dealer?->notify(new ListingExpiring($property));
                }
            });

        $this->info(($dry ? 'Dry run: would expire ' : 'Expired ').$expired.' and '.($dry ? 'remind ' : 'reminded ').$reminded.' listing(s).');

        return self::SUCCESS;
    }
}
