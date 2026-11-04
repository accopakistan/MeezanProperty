<?php

namespace Database\Seeders;

use App\Services\Investment\DemoDataManager;
use Illuminate\Database\Seeder;

/**
 * Investment Rankings demonstration dataset (all seven cities), then the demo
 * rankings calculated by the normal engine and published as the demo snapshot.
 * Idempotent: running it again creates no duplicates and never touches real
 * records. Run explicitly:
 *   php artisan db:seed --class=InvestmentDemoSeeder
 * or use `php artisan meezan:investment-demo seed`.
 */
class InvestmentDemoSeeder extends Seeder
{
    public function run(DemoDataManager $demo): void
    {
        $created = $demo->seed();
        $calc = $demo->recalculate();

        $this->command?->info("Demo data: {$created['projects']} new projects, {$created['observations']} observations, {$created['indicators']} indicators. Demo rankings: calculation #{$calc->id} ({$calc->status}).");
    }
}
