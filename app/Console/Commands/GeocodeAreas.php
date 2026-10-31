<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\City;
use App\Support\LocationName;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Looks up an approximate centre point for each city and area with
 * OpenStreetMap Nominatim (one request per second, per its usage policy).
 * Only fills rows without coordinates unless --force is given.
 *
 * A result is stored only when it is inside the city radius, its OSM name
 * shares the location's distinctive words, and (for a child such as a DHA
 * sector) it is not simply the parent's point. Anything else is left empty
 * for manual entry in Admin → Locations; nothing is guessed.
 */
class GeocodeAreas extends Command
{
    protected $signature = 'meezan:geocode-areas
        {--force : Re-geocode rows that already have coordinates}
        {--city= : Only this city}
        {--cities : Also fill missing city centre points first}
        {--retry : Look up again rows that had no reliable match before}
        {--limit=0 : Stop after this many look-ups (0 = no limit)}
        {--fix-shared : Clear OpenStreetMap points that are shared by several locations or are the wrong kind of place}';

    protected $description = 'Fill approximate city and area centre points from OpenStreetMap';

    /** Accept results within this distance of the city centre (km). */
    protected const MAX_KM_FROM_CITY = ['Karachi' => 65, 'Islamabad' => 40, 'default' => 40];

    /** Words too common to prove a match on their own. */
    protected const GENERIC = ['phase', 'sector', 'block', 'town', 'city', 'housing', 'scheme', 'society', 'cooperative', 'colony', 'garden', 'gardens',
        'road', 'park', 'avenue', 'villas', 'homes', 'enclave', 'residencia', 'valley', 'extension', 'employees', 'new', 'model', 'and', 'of', 'the',
        'lahore', 'karachi', 'islamabad', 'multan', 'faisalabad', 'quetta', 'peshawar'];

    /**
     * OSM object kinds (addresstype or type) accepted per location type. A shop,
     * shrine or office that merely carries the name is not the area itself.
     */
    protected const AREA_KINDS = ['suburb', 'neighbourhood', 'quarter', 'residential', 'city_district', 'borough', 'hamlet', 'village', 'town',
        'locality', 'isolated_dwelling', 'allotments', 'district', 'municipality', 'city_block', 'farm', 'military'];

    protected const ROAD_KINDS = ['road', 'motorway', 'trunk', 'primary', 'secondary', 'tertiary', 'residential', 'unclassified', 'street'];

    protected const MARKET_KINDS = ['commercial', 'retail', 'marketplace', 'mall', 'shop'];

    protected int $lookups = 0;

    /** Marker for "looked up, nothing reliable found"; latitude/longitude stay empty. */
    public const NO_MATCH = 'osm_no_match';

    protected bool $lastLookupAnswered = false;

    public function handle(): int
    {
        if ($this->option('cities')) {
            $this->geocodeCities();
        }
        if ($this->option('fix-shared')) {
            return $this->fixShared();
        }

        $areas = Area::query()->with('parent:id,latitude,longitude')
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('latitude'))
            ->when(! $this->option('force') && ! $this->option('retry'), fn ($q) => $q->where(fn ($n) => $n->whereNull('coordinates_source')->orWhere('coordinates_source', '!=', self::NO_MATCH)))
            ->when($this->option('city'), fn ($q, $city) => $q->where('city', $city))
            ->whereIn('city', config('meezan.cities'))
            ->where('is_active', true)
            ->orderBy('depth')->orderBy('city')->orderBy('name')->get();

        if ($areas->isEmpty()) {
            $this->info('Every area already has coordinates.');

            return self::SUCCESS;
        }

        $saved = 0;
        foreach ($areas as $area) {
            if ($this->limitReached()) {
                break;
            }

            // Several candidates: the first that passes every rule wins (OSM's top
            // hit is often a shop or office named after the area).
            $point = null;
            foreach ($this->lookup("{$area->name}, {$area->city}", [], 5) as $hit) {
                if ($point = $this->accept($area, $hit)) {
                    break;
                }
            }
            if (! $point) {
                $this->warn("No reliable match for {$area->name}, {$area->city}.");
                // Remember the miss (coordinates stay empty) so later runs skip it unless --retry.
                if ($this->lastLookupAnswered) {
                    $area->update(['coordinates_source' => self::NO_MATCH]);
                }

                continue;
            }

            $area->update(['latitude' => $point[0], 'longitude' => $point[1], 'coordinates_source' => 'openstreetmap', 'coordinate_precision' => $point[2]]);
            $this->line("{$area->name}, {$area->city}: {$point[0]}, {$point[1]} ({$point[2]})");
            $saved++;
        }

        $this->info("Saved coordinates for {$saved} of {$areas->count()} areas ({$this->lookups} look-ups).");

        return self::SUCCESS;
    }

    protected function geocodeCities(): void
    {
        $cities = City::whereIn('name', config('meezan.cities'))
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('latitude'))
            ->when($this->option('city'), fn ($q, $city) => $q->where('name', $city))->get();

        foreach ($cities as $city) {
            $hit = $this->lookup("{$city->name}, {$city->province}, Pakistan", ['featureType' => 'city'])[0] ?? null;
            $bounds = config('meezan.map.pakistan_bounds');
            if (! $hit || $hit['lat'] < $bounds['south'] || $hit['lat'] > $bounds['north'] || $hit['lon'] < $bounds['west'] || $hit['lon'] > $bounds['east']) {
                $this->warn("No reliable match for the city {$city->name}.");

                continue;
            }
            $city->update(['latitude' => round((float) $hit['lat'], 7), 'longitude' => round((float) $hit['lon'], 7), 'coordinates_source' => 'openstreetmap']);
            $this->line("City {$city->name}: {$city->latitude}, {$city->longitude}");
        }
    }

    /**
     * @return list<array<string, mixed>> Nominatim results, best first ([] on failure)
     */
    protected function lookup(string $query, array $extra = [], int $limit = 1): array
    {
        if ($this->lookups++ > 0) {
            usleep(1_100_000);
        }

        $this->lastLookupAnswered = false;
        // A dropped connection skips this row (it stays empty and is retried on the next run).
        try {
            $response = Http::timeout(20)->retry(2, 3000, throw: false)
                ->withHeaders(['User-Agent' => config('app.name').' area lookup ('.config('app.url').')'])
                ->get('https://nominatim.openstreetmap.org/search', $extra + [
                    'q' => $query,
                    'countrycodes' => 'pk',
                    'format' => 'jsonv2',
                    'limit' => $limit,
                ]);
        } catch (ConnectionException $e) {
            $this->warn("Lookup failed for {$query}: {$e->getMessage()}");

            return [];
        }

        $this->lastLookupAnswered = $response->successful();

        return $response->successful() ? array_values(array_filter((array) $response->json(), 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array{0: float, 1: float, 2: string}|null
     */
    protected function accept(Area $area, array $hit): ?array
    {
        $point = [round((float) $hit['lat'], 7), round((float) $hit['lon'], 7)];

        $centre = City::centre($area->city);
        $max = self::MAX_KM_FROM_CITY[$area->city] ?? self::MAX_KM_FROM_CITY['default'];
        if (! $centre || $this->km($centre, $point) > $max) {
            return null;
        }

        // The OSM object must carry the location's distinctive words.
        $wanted = array_diff(explode(' ', LocationName::normalize($area->name)), self::GENERIC);
        $found = explode(' ', LocationName::normalize(($hit['name'] ?? '').' '.($hit['display_name'] ?? '')));
        if ($wanted && isset($hit['display_name']) && ! array_intersect($wanted, $found)) {
            return null;
        }
        if ((isset($hit['addresstype']) || isset($hit['type'])) && ! $this->rightKind($area->type, [$hit['addresstype'] ?? null, $hit['type'] ?? null])) {
            return null;
        }
        // A phase/sector that resolves to its parent's point was not found itself.
        if ($area->parent?->hasCoordinates() && $this->km([$area->parent->latitude, $area->parent->longitude], $point) < 0.05) {
            return null;
        }
        // Nor may it reuse a point another location in the city already has.
        if (Area::where('city', $area->city)->whereKeyNot($area->id)->where('latitude', $point[0])->where('longitude', $point[1])->exists()) {
            return null;
        }

        return [$point[0], $point[1], (string) ($hit['addresstype'] ?? $hit['type'] ?? 'approximate')];
    }

    /** @param  list<?string>  $kinds */
    protected function rightKind(string $type, array $kinds): bool
    {
        $allowed = match ($type) {
            'corridor' => self::ROAD_KINDS,
            'commercial_area' => array_merge(self::AREA_KINDS, self::MARKET_KINDS),
            default => self::AREA_KINDS,
        };

        return array_intersect(array_filter($kinds), $allowed) !== [];
    }

    /**
     * One OSM object matched for several locations (a society and its phases,
     * or two similar names) proves nothing about the others: keep the point on
     * the earliest record and clear the rest. Manual points are never touched.
     */
    protected function fixShared(): int
    {
        $groups = Area::where('coordinates_source', 'openstreetmap')
            ->get(['id', 'city', 'name', 'latitude', 'longitude'])
            ->groupBy(fn (Area $a) => $a->city.'|'.$a->latitude.'|'.$a->longitude)
            ->filter(fn ($group) => $group->count() > 1);

        $cleared = 0;
        foreach ($groups as $group) {
            $keep = $group->sortBy('id')->first();
            foreach ($group->where('id', '!=', $keep->id) as $area) {
                $area->update(['latitude' => null, 'longitude' => null, 'coordinates_source' => self::NO_MATCH, 'coordinate_precision' => null]);
                $this->line("Cleared {$area->name} (shared point with {$keep->name})");
                $cleared++;
            }
        }
        $this->info("Cleared {$cleared} shared point(s) in {$groups->count()} group(s).");

        // Points recorded with a kind that does not fit the location (only rows
        // with a recorded kind; older points without one are left alone).
        $wrong = 0;
        foreach (Area::where('coordinates_source', 'openstreetmap')->whereNotNull('coordinate_precision')->where('coordinate_precision', '!=', 'approximate')->get(['id', 'name', 'type', 'coordinate_precision']) as $area) {
            if (! $this->rightKind($area->type, [$area->coordinate_precision])) {
                $kind = $area->coordinate_precision;
                $area->update(['latitude' => null, 'longitude' => null, 'coordinates_source' => self::NO_MATCH, 'coordinate_precision' => null]);
                $this->line("Cleared {$area->name} (OSM object was a {$kind})");
                $wrong++;
            }
        }
        $this->info("Cleared {$wrong} point(s) of the wrong kind.");

        return self::SUCCESS;
    }

    protected function limitReached(): bool
    {
        $limit = (int) $this->option('limit');

        return $limit > 0 && $this->lookups >= $limit;
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    protected function km(array $a, array $b): float
    {
        [$lat1, $lng1, $lat2, $lng2] = array_map('deg2rad', [$a[0], $a[1], $b[0], $b[1]]);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(sqrt($h));
    }
}
