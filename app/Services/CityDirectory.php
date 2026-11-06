<?php

namespace App\Services;

use App\Enums\DealerStatus;
use App\Models\Area;
use App\Models\City;
use App\Models\DealerProfile;
use App\Models\Property;
use App\Support\Cities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Real per-city figures for the homepage and city pages: published listings,
 * locations in the directory and verified dealers serving the city. Counts
 * come from the database only (cached for ten minutes); nothing is estimated.
 */
class CityDirectory
{
    /**
     * @return list<array{name: string, slug: string, province: ?string, listings: int, locations: int, dealers: int}>
     */
    public function summaries(): array
    {
        return Cache::remember('meezan.city-summaries', 600, function () {
            $listings = Property::published()->select('city', DB::raw('count(*) as total'))->groupBy('city')->pluck('total', 'city');
            $locations = Area::active()->select('city', DB::raw('count(*) as total'))->groupBy('city')->pluck('total', 'city');
            $dealers = DB::table('city_dealer_profile')
                ->join('cities', 'cities.id', '=', 'city_dealer_profile.city_id')
                ->join('dealer_profiles', 'dealer_profiles.id', '=', 'city_dealer_profile.dealer_profile_id')
                ->where('dealer_profiles.verification_status', DealerStatus::Verified->value)->where('dealer_profiles.is_demo', false)
                ->select('cities.name', DB::raw('count(*) as total'))->groupBy('cities.name')->pluck('total', 'name');

            return array_map(fn (string $city) => [
                'name' => $city,
                'slug' => Cities::slug($city),
                'province' => Cities::province($city),
                'listings' => (int) ($listings[$city] ?? 0),
                'locations' => (int) ($locations[$city] ?? 0),
                'dealers' => (int) ($dealers[$city] ?? 0),
            ], Cities::names());
        });
    }

    /** @return array{name: string, slug: string, province: ?string, listings: int, locations: int, dealers: int}|null */
    public function summary(string $city): ?array
    {
        return collect($this->summaries())->firstWhere('name', $city);
    }

    public static function flush(): void
    {
        Cache::forget('meezan.city-summaries');
    }

    public function model(string $city): ?City
    {
        return City::where('name', $city)->first();
    }

    /** Verified dealers serving the city, newest verification first. */
    public function dealers(string $city, int $limit = 6)
    {
        return DealerProfile::where('verification_status', DealerStatus::Verified)->where('is_demo', false)
            ->whereHas('cities', fn ($q) => $q->where('name', $city))
            ->latest('verified_at')->take($limit)->get();
    }
}
