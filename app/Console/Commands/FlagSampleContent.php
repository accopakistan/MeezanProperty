<?php

namespace App\Console\Commands;

use App\Enums\PropertyStatus;
use App\Models\DealerProfile;
use App\Models\Property;
use App\Services\AuditLogger;
use App\Support\SampleContent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds sample and test content that is not yet flagged: dealer profiles and
 * listings of accounts on reserved test domains, and listings whose description
 * is placeholder text. With --commit it flags them as samples and archives the
 * published listings. Nothing is deleted; every change is audited.
 */
class FlagSampleContent extends Command
{
    protected $signature = 'meezan:flag-sample-content {--commit : Flag and archive; without it nothing changes}';

    protected $description = 'Find test-account and placeholder content and keep it off public pages (dry run unless --commit)';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $rows = [];
        // Saving a listing recomputes its completeness from its relations; a one-off command may load them lazily.
        Model::preventLazyLoading(false);

        foreach (DealerProfile::where('is_demo', false)->with('user:id,email')->get() as $profile) {
            if (SampleContent::isTestAccount($profile->user)) {
                $rows[] = ['Dealer', $profile->agency_name, 'Account on a reserved test domain', $commit ? 'flagged as sample' : 'would flag'];
                if ($commit) {
                    $profile->forceFill(['is_demo' => true])->save();
                    AuditLogger::record('dealer.marked_sample', $profile, ['reason' => 'Account on a reserved test domain']);
                }
            }
        }

        foreach (Property::where('is_demo', false)->with('dealer:id,email')->get() as $listing) {
            $reason = match (true) {
                SampleContent::isTestAccount($listing->dealer) => 'Listed by an account on a reserved test domain',
                (bool) preg_match('/lorem\s+ipsum|pseudo-latin\s+placeholder|placeholder\s+text/i', (string) $listing->description) => 'Placeholder description',
                default => null,
            };
            if (! $reason) {
                continue;
            }
            $published = $listing->status === PropertyStatus::Published;
            $rows[] = ['Listing', $listing->reference.' '.mb_strimwidth($listing->title, 0, 40, '…'), $reason, $commit ? ($published ? 'flagged, archived' : 'flagged') : ($published ? 'would flag and archive' : 'would flag')];
            if ($commit) {
                $listing->forceFill(['is_demo' => true] + ($published ? ['status' => PropertyStatus::Archived] : []))->save();
                AuditLogger::record('property.marked_sample', $listing, ['reason' => $reason, 'archived' => $published]);
            }
        }

        if ($rows === []) {
            $this->info('No unflagged sample or test content found.');

            return self::SUCCESS;
        }
        $this->table(['Record', 'Name', 'Why', 'Result'], $rows);
        if (! $commit) {
            $this->info('Dry run: nothing changed. Add --commit to flag these records.');
        }

        return self::SUCCESS;
    }
}
