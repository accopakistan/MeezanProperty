<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEVELOPMENT ONLY: draft packages for testing billing.
 *
 *   php artisan db:seed --class=DraftPackagesSeeder
 *
 * Prices and limits are placeholders, NOT business decisions: each package
 * is marked is_demo (shown with a "Draft" label) and can be edited or
 * hidden in Admin → Packages. Runs again safely: packages are matched by
 * slug and an existing package is never changed. No subscriptions, invoices
 * or payments are created, and no package is made the default plan (that
 * would apply its limits to every dealer, so it is left to an admin).
 */
class DraftPackagesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DraftPackagesSeeder only runs in local or testing environments.');

            return;
        }

        $packages = [
            ['slug' => 'draft-free', 'name' => 'Free (draft)', 'description' => 'Draft package for testing. Terms not final.', 'price' => 0, 'listing_limit' => 5, 'lead_limit' => 5, 'featured_limit' => 0, 'features' => [], 'sort_order' => 10],
            ['slug' => 'draft-starter', 'name' => 'Starter (draft)', 'description' => 'Draft package for testing. Terms not final.', 'price' => 2500, 'listing_limit' => 25, 'lead_limit' => 20, 'featured_limit' => 2, 'features' => ['featured_listings'], 'sort_order' => 20],
            ['slug' => 'draft-gold', 'name' => 'Gold (draft)', 'description' => 'Draft package for testing. Terms not final.', 'price' => 7500, 'listing_limit' => 100, 'lead_limit' => 75, 'featured_limit' => 10, 'features' => ['featured_listings', 'priority_leads', 'advanced_analytics', 'priority_support'], 'sort_order' => 30, 'is_recommended' => true],
        ];

        $created = 0;
        foreach ($packages as $attributes) {
            $package = Package::firstOrCreate(['slug' => $attributes['slug']], $attributes + [
                'currency' => 'PKR', 'billing_interval' => 'month', 'is_active' => true, 'is_demo' => true,
            ]);
            $created += (int) $package->wasRecentlyCreated;
        }

        $this->command?->info("Draft packages: {$created} created, ".(count($packages) - $created).' already present (left unchanged).');
    }
}
