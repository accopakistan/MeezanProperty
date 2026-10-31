<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\DuplicateDetector;
use App\Services\ListingCompleteness;
use Illuminate\Console\Command;

/**
 * Recomputes derived trust fields for listings: public map coordinates and
 * location status (from the dealer's pin and privacy choice), completeness,
 * and stored duplicate warnings. Never changes the pin, the price or any
 * dealer-entered value, and does not touch updated_at. Safe to re-run.
 */
class RefreshListingTrust extends Command
{
    protected $signature = 'meezan:refresh-listing-trust {--duplicates : Also re-check duplicate warnings for pending and published listings}';

    protected $description = 'Recompute public coordinates, location status, completeness (and optionally duplicate warnings)';

    public function handle(DuplicateDetector $detector): int
    {
        $updated = 0;

        Property::withTrashed()->with(['area:id,latitude,longitude,name', 'images:id,property_id,width,status'])->chunkById(200, function ($properties) use (&$updated, $detector) {
            foreach ($properties as $property) {
                $property->applyLocationRules();
                $property->completeness_score = ListingCompleteness::for($property)['score'];
                if ($this->option('duplicates')) {
                    $detector->record($property);
                }
                if ($property->isDirty()) {
                    $property->timestamps = false;
                    $property->saveQuietly();
                    $updated++;
                }
            }
        });

        $this->info("Updated {$updated} listing(s).");

        return self::SUCCESS;
    }
}
