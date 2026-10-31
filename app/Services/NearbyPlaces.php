<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Named places around a listing's exact pin, grouped by category, from
 * OpenStreetMap (Overpass API). Distances are straight-line, not travel.
 *
 * Results are cached for 30 days per ~10 m location; a failed look-up is
 * remembered for 10 minutes so an outage is not retried on every view.
 * Nothing is invented: unnamed or uncategorised features are left out.
 */
class NearbyPlaces
{
    public const CATEGORIES = [
        'education' => ['label' => 'Schools & colleges', 'icon' => 'briefcase'],
        'health' => ['label' => 'Hospitals & clinics', 'icon' => 'plus'],
        'mosques' => ['label' => 'Mosques', 'icon' => 'building'],
        'shopping' => ['label' => 'Shopping', 'icon' => 'store'],
        'parks' => ['label' => 'Parks', 'icon' => 'map'],
    ];

    /**
     * Null when the listing has no exact pin, the feature is off, or the
     * service is unavailable.
     *
     * @return array{radius: int, categories: list<array<string, mixed>>}|null
     */
    public function around(Property $property): ?array
    {
        // Measured from the public point only: distances from a hidden exact
        // pin to several places would give that pin away.
        $point = $property->publicCoordinates();
        if (! $point || ! config('meezan.nearby.enabled')) {
            return null;
        }
        [$lat, $lng] = $point;

        $radius = (int) config('meezan.nearby.radius_m');
        $key = sprintf('nearby:%.4f,%.4f:%d', $lat, $lng, $radius);

        if (Cache::has($key.':failed')) {
            return null;
        }
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        $elements = $this->query($lat, $lng, $radius);
        if ($elements === null) {
            Cache::put($key.':failed', true, now()->addMinutes(10));

            return null;
        }

        $result = ['radius' => $radius, 'categories' => $this->group($elements, $lat, $lng)];
        Cache::put($key, $result, now()->addDays(30));

        return $result;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    protected function query(float $lat, float $lng, int $radius): ?array
    {
        $around = "(around:{$radius},{$lat},{$lng})";
        $ql = <<<QL
            [out:json][timeout:20];
            (
              nwr{$around}[amenity~"^(school|college|university)$"][name];
              nwr{$around}[amenity~"^(hospital|clinic)$"][name];
              nwr{$around}[amenity=place_of_worship][religion=muslim][name];
              nwr{$around}[shop~"^(supermarket|mall|department_store)$"][name];
              nwr{$around}[amenity=marketplace][name];
              nwr{$around}[leisure=park][name];
            );
            out center tags 200;
            QL;

        try {
            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => config('app.name').' nearby places ('.config('app.url').')'])
                ->asForm()
                ->post(config('meezan.nearby.endpoint'), ['data' => $ql]);
        } catch (\Throwable $e) {
            Log::warning('Nearby places request failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful() || ! is_array($response->json('elements'))) {
            Log::warning('Nearby places service returned an error', ['status' => $response->status()]);

            return null;
        }

        return $response->json('elements');
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return list<array{key: string, label: string, icon: string, places: list<array{name: string, distance: int}>}>
     */
    protected function group(array $elements, float $lat, float $lng): array
    {
        $groups = array_fill_keys(array_keys(self::CATEGORIES), []);

        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];
            $name = trim((string) ($tags['name:en'] ?? $tags['name'] ?? ''));
            $pLat = $element['lat'] ?? $element['center']['lat'] ?? null;
            $pLng = $element['lon'] ?? $element['center']['lon'] ?? null;
            $category = $this->categorise($tags);

            if ($name === '' || $pLat === null || $pLng === null || ! $category) {
                continue;
            }

            $groups[$category][$name] = min($groups[$category][$name] ?? PHP_INT_MAX, $this->metres($lat, $lng, (float) $pLat, (float) $pLng));
        }

        return collect($groups)->map(function (array $places, string $key) {
            asort($places);

            return self::CATEGORIES[$key] + [
                'key' => $key,
                'places' => collect($places)->take((int) config('meezan.nearby.per_category'))
                    ->map(fn ($distance, $name) => ['name' => (string) $name, 'distance' => $distance])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, string>  $tags
     */
    protected function categorise(array $tags): ?string
    {
        $amenity = $tags['amenity'] ?? null;

        return match (true) {
            in_array($amenity, ['school', 'college', 'university'], true) => 'education',
            in_array($amenity, ['hospital', 'clinic'], true) => 'health',
            $amenity === 'place_of_worship' && ($tags['religion'] ?? null) === 'muslim' => 'mosques',
            $amenity === 'marketplace' || in_array($tags['shop'] ?? null, ['supermarket', 'mall', 'department_store'], true) => 'shopping',
            ($tags['leisure'] ?? null) === 'park' => 'parks',
            default => null,
        };
    }

    protected function metres(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        [$a, $b, $c, $d] = array_map('deg2rad', [$lat1, $lng1, $lat2, $lng2]);
        $h = sin(($c - $a) / 2) ** 2 + cos($a) * cos($c) * sin(($d - $b) / 2) ** 2;

        return (int) round(6371000 * 2 * asin(sqrt($h)));
    }
}
