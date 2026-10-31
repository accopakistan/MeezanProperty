<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reference data: Lahore neighbourhoods and housing societies. Safe to run
 * in any environment; existing rows are updated in place, never removed.
 */
class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $popular = ['DHA Phase 5', 'DHA Phase 6', 'Bahria Town', 'Gulberg', 'Johar Town', 'Model Town', 'Lake City', 'Wapda Town'];

        $others = [
            'DHA Phase 1', 'DHA Phase 2', 'DHA Phase 3', 'DHA Phase 4', 'DHA Phase 7', 'DHA Phase 8', 'DHA Rahbar',
            'Askari', 'Allama Iqbal Town', 'Bahria Orchard', 'Bedian Road', 'Cantt', 'Cavalry Ground', 'Central Park',
            'Eden City', 'EME Society', 'Etihad Town', 'Faisal Town', 'Garden Town', 'Al Rehman Garden', 'Paragon City',
            'Park View City', 'Punjab Coop Housing Society', 'Raiwind Road', 'Samanabad', 'Shadman', 'State Life Housing Society',
            'Sukh Chayn Gardens', 'Thokar Niaz Baig', 'Township', 'Valencia Town',
        ];

        foreach ($popular as $i => $name) {
            $this->upsert($name, true, $i + 1);
        }

        foreach ($others as $name) {
            $this->upsert($name, false, 100);
        }
    }

    protected function upsert(string $name, bool $popular, int $order): void
    {
        Area::updateOrCreate(
            ['slug' => Str::slug($name.' lahore')],
            ['city' => 'Lahore', 'name' => $name, 'is_popular' => $popular, 'sort_order' => $order],
        );
    }
}
