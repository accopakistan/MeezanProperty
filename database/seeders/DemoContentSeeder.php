<?php

namespace Database\Seeders;

use App\Enums\AreaUnit;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Enums\Role;
use App\Models\Area;
use App\Models\DealerProfile;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * LOCAL DEVELOPMENT ONLY — sample accounts and clearly flagged demo listings.
 *
 *   php artisan db:seed --class=DemoContentSeeder
 *
 * Accounts (password for all: "password"):
 *   admin@meezanproperty.test   – administrator
 *   dealer@meezanproperty.test  – dealer, verification PENDING
 *   buyer@meezanproperty.test   – buyer
 *
 * Every listing is is_demo = true, shows a "Demo listing" badge, is excluded
 * from the sitemap and carries no coordinates. Photos are illustrative crops
 * from the design references, not photos of the listed property. No dealer is
 * marked verified and no inquiries, leads or payments are created.
 */
class DemoContentSeeder extends Seeder
{
    /** Replace existing demo photos (only ever touches is_demo listings). */
    public bool $refreshPhotos = true;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoContentSeeder only runs in local or testing environments.');

            return;
        }

        $this->call(AreaSeeder::class);

        $admin = $this->user('admin@meezanproperty.test', 'Meezan Admin (Demo)', Role::Admin);
        $buyer = $this->user('buyer@meezanproperty.test', 'Ayesha Buyer (Demo)', Role::Buyer);
        $dealer = $this->user('dealer@meezanproperty.test', 'Imran Dealer (Demo)', Role::Dealer);

        $profile = DealerProfile::firstOrNew(['user_id' => $dealer->id]);
        $profile->fill([
            'agency_name' => 'Demo Realty (Sample Data)',
            'slug' => 'demo-realty-sample-data',
            'city' => 'Lahore',
            'office_address' => 'Sample address — not a real office',
            'bio' => 'Sample dealer profile used for local development.',
        ]);
        $profile->user_id = $dealer->id;
        $profile->save();
        $profile->areas()->sync(Area::whereIn('name', ['DHA Phase 5', 'DHA Phase 6', 'Gulberg', 'Johar Town', 'Bahria Town'])->pluck('id'));

        foreach ($this->listings() as $i => $row) {
            [$title, $purpose, $type, $area, $price, $size, $unit, $beds, $baths, $amenities] = $row;

            $property = Property::withTrashed()->firstOrNew(['title' => $title, 'user_id' => $dealer->id]);
            $property->fill([
                'purpose' => $purpose,
                'type' => $type,
                'city' => 'Lahore',
                'area_id' => Area::where('name', $area)->value('id'),
                'price' => $price,
                'area_size' => $size,
                'area_unit' => $unit,
                'bedrooms' => $beds,
                'bathrooms' => $baths,
                'amenities' => $amenities,
                'description' => "Demo listing for local development — this is not real inventory.\n\nA {$size} ".$unit->label()." {$type->label()} in {$area}, Lahore, used to preview how listings appear on Meezan Property.",
            ]);
            $property->user_id = $dealer->id;
            $property->status = PropertyStatus::Published;
            $property->is_demo = true;
            $property->is_featured = $i < 4;
            $property->submitted_at = now()->subDays(20 - $i);
            $property->published_at = now()->subDays(18 - $i);
            $property->expires_at = now()->addDays(90);
            $property->save();

            $this->attachDemoPhotos($property, $i);
        }

        $this->command?->info('Demo content ready. Sign in with the accounts listed in DemoContentSeeder.');
    }

    /**
     * Illustrative photos cropped from the design references (public/images/ref).
     * They are not photos of the listed property; every demo listing is badged as such.
     */
    protected function attachDemoPhotos(Property $property, int $i): void
    {
        if ($property->images()->exists() && ! $this->refreshPhotos) {
            return;
        }

        foreach ($property->images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }

        // Ordered by source resolution so the sharpest crops become covers.
        $houses = ['demo-house-1', 'demo-house-6', 'demo-house-5', 'demo-house-2', 'demo-house-4', 'demo-house-3'];
        $interiors = ['demo-interior-1', 'demo-interior-2', 'demo-interior-3'];

        $set = match ($property->type->group()) {
            'plots' => [['demo-plot-1', 'demo-plot-2'][$i % 2]],
            'commercial' => [['demo-commercial-1', 'demo-commercial-2'][$i % 2], 'demo-interior-2'],
            default => in_array($property->type, [PropertyType::Apartment, PropertyType::UpperPortion, PropertyType::LowerPortion, PropertyType::Penthouse], true)
                ? [$interiors[$i % 3], $interiors[($i + 1) % 3], $houses[$i % 6]]
                : [$houses[$i % 6], $interiors[$i % 3], $interiors[($i + 1) % 3], $houses[($i + 1) % 6]],
        };

        foreach ($set as $order => $name) {
            $source = public_path("images/ref/{$name}.jpg");
            if (! is_file($source)) {
                continue;
            }
            $path = "properties/{$property->id}/{$name}.jpg";
            Storage::disk('public')->put($path, file_get_contents($source));
            $property->images()->create(['path' => $path, 'sort_order' => $order]);
        }
    }

    protected function user(string $email, string $name, Role $role): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'phone' => null, 'city' => 'Lahore']);
        if (! $user->exists) {
            $user->password = 'password';
        }
        $user->role = $role;
        $user->status = 'active';
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }

    /**
     * @return list<array{0:string,1:Purpose,2:PropertyType,3:string,4:int,5:float,6:AreaUnit,7:?int,8:?int,9:list<string>}>
     */
    protected function listings(): array
    {
        return [
            ['1 Kanal Modern House in DHA Phase 6', Purpose::Sale, PropertyType::House, 'DHA Phase 6', 95_000_000, 1, AreaUnit::Kanal, 5, 6, ['parking', 'gas', 'lawn', 'servant_quarter', 'security', 'basement']],
            ['10 Marla House with Basement in DHA Phase 5', Purpose::Sale, PropertyType::House, 'DHA Phase 5', 58_000_000, 10, AreaUnit::Marla, 4, 5, ['parking', 'gas', 'basement', 'security']],
            ['2 Bed Apartment in Gulberg', Purpose::Sale, PropertyType::Apartment, 'Gulberg', 32_000_000, 1250, AreaUnit::SquareFeet, 2, 2, ['lift', 'parking', 'security', 'electricity_backup']],
            ['Corner Commercial Plot on Main Boulevard, Johar Town', Purpose::Sale, PropertyType::CommercialPlot, 'Johar Town', 120_000_000, 8, AreaUnit::Marla, null, null, ['corner', 'main_boulevard']],
            ['5 Marla House in Bahria Town', Purpose::Sale, PropertyType::House, 'Bahria Town', 21_500_000, 5, AreaUnit::Marla, 3, 4, ['parking', 'gas', 'mosque_nearby']],
            ['10 Marla Residential Plot in Lake City', Purpose::Sale, PropertyType::ResidentialPlot, 'Lake City', 27_000_000, 10, AreaUnit::Marla, null, null, ['park_facing']],
            ['1 Kanal Plot in DHA Phase 7', Purpose::Sale, PropertyType::ResidentialPlot, 'DHA Phase 7', 48_000_000, 1, AreaUnit::Kanal, null, null, []],
            ['Upper Portion for Rent in Model Town', Purpose::Rent, PropertyType::UpperPortion, 'Model Town', 120_000, 1, AreaUnit::Kanal, 3, 3, ['gas', 'parking', 'drawing_room']],
            ['7 Marla House for Rent in Johar Town', Purpose::Rent, PropertyType::House, 'Johar Town', 95_000, 7, AreaUnit::Marla, 4, 4, ['gas', 'parking', 'school_nearby']],
            ['Furnished Studio Apartment for Rent in Gulberg', Purpose::Rent, PropertyType::Apartment, 'Gulberg', 85_000, 650, AreaUnit::SquareFeet, 1, 1, ['lift', 'security', 'electricity_backup']],
            ['Ground Floor Shop in Wapda Town Market', Purpose::Sale, PropertyType::Shop, 'Wapda Town', 18_000_000, 300, AreaUnit::SquareFeet, null, null, ['main_boulevard']],
            ['Office Floor for Rent in Gulberg III', Purpose::Rent, PropertyType::Office, 'Gulberg', 450_000, 3200, AreaUnit::SquareFeet, null, 2, ['lift', 'parking', 'electricity_backup', 'security', 'cctv']],
            ['5 Marla Plot in Bahria Orchard', Purpose::Sale, PropertyType::ResidentialPlot, 'Bahria Orchard', 6_500_000, 5, AreaUnit::Marla, null, null, []],
            ['Lower Portion for Rent in DHA Phase 5', Purpose::Rent, PropertyType::LowerPortion, 'DHA Phase 5', 160_000, 1, AreaUnit::Kanal, 3, 3, ['gas', 'lawn', 'parking']],
            ['Commercial Building in Garden Town', Purpose::Sale, PropertyType::Building, 'Garden Town', 350_000_000, 1, AreaUnit::Kanal, null, 8, ['lift', 'parking', 'electricity_backup']],
            ['8 Marla House in Valencia Town', Purpose::Sale, PropertyType::House, 'Valencia Town', 32_500_000, 8, AreaUnit::Marla, 4, 4, ['gas', 'parking']],
        ];
    }
}
