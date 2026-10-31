<?php

namespace App\Services;

use App\Enums\AreaUnit;
use App\Enums\DealerStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Models\Property;
use App\Support\Format;
use App\Support\LocationResolver;
use App\Support\Polygon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Turns public search query parameters into an Eloquent query over published
 * listings. Invalid parameters are dropped rather than raising errors so that
 * a hand-edited URL still produces a sensible page.
 *
 * `type` and `area` accept a single value or an array (checkbox filters).
 */
class PropertySearch
{
    public const SORTS = [
        'newest' => 'Latest',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'area_desc' => 'Largest area',
    ];

    /** Size presets shown as chips on the search references (Any, 3 Marla … 2 Kanal+). */
    public const SIZES = [
        '3m' => ['label' => '3 Marla', 'min' => 3, 'max' => 3, 'unit' => 'marla'],
        '5m' => ['label' => '5 Marla', 'min' => 5, 'max' => 5, 'unit' => 'marla'],
        '7m' => ['label' => '7 Marla', 'min' => 7, 'max' => 7, 'unit' => 'marla'],
        '10m' => ['label' => '10 Marla', 'min' => 10, 'max' => 10, 'unit' => 'marla'],
        '1k' => ['label' => '1 Kanal', 'min' => 1, 'max' => 1, 'unit' => 'kanal'],
        '2k' => ['label' => '2 Kanal+', 'min' => 2, 'max' => null, 'unit' => 'kanal'],
    ];

    /** "Listed within" choices, in days. */
    public const LISTED = [
        1 => 'Last 24 hours',
        3 => 'Last 3 days',
        7 => 'Last 7 days',
        14 => 'Last 14 days',
        30 => 'Last 30 days',
    ];

    public const FURNISHING = [
        'furnished' => 'Furnished',
        'semi-furnished' => 'Semi-Furnished',
        'unfurnished' => 'Unfurnished',
    ];

    /** @var array<string, mixed> */
    protected array $filters;

    /** @var list<string> */
    protected array $types = [];

    protected Collection $areas;

    /** Locations offered when typed text matched several places (e.g. "Gulberg" in Lahore and Faisalabad). */
    protected Collection $suggestions;

    protected ?Polygon $polygon = null;

    /** Listing ids inside the drawn area, computed once per search. */
    protected ?array $polygonIds = null;

    /** Upper bound on coordinate rows examined for one drawn area. */
    protected const POLYGON_CANDIDATE_LIMIT = 10000;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(array $input)
    {
        $input = $this->normalise($input);
        $this->suggestions = new Collection;

        $validator = Validator::make($input, [
            'tab' => ['nullable', Rule::in(['sale', 'rent', 'plots', 'commercial'])],
            'purpose' => ['nullable', Rule::enum(Purpose::class)],
            'group' => ['nullable', Rule::in(['homes', 'plots', 'commercial'])],
            'types' => ['nullable', 'array', 'max:15'],
            'types.*' => [Rule::enum(PropertyType::class)],
            'city' => ['nullable', 'string', Rule::in(config('meezan.cities'))],
            'areas' => ['nullable', 'array', 'max:20'],
            'areas.*' => ['string', 'max:140'],
            'location' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'beds' => ['nullable', 'integer', 'between:1,10'],
            'baths' => ['nullable', 'integer', 'between:1,10'],
            'size' => ['nullable', Rule::in(array_keys(self::SIZES))],
            'min_area' => ['nullable', 'numeric', 'min:0'],
            'max_area' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', Rule::enum(AreaUnit::class)],
            'furnishing' => ['nullable', Rule::in(array_keys(self::FURNISHING))],
            'completion' => ['nullable', Rule::in(array_keys(Property::COMPLETION))],
            'min_covered' => ['nullable', 'integer', 'min:0'],
            'max_covered' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array', 'max:10'],
            'features.*' => [Rule::in(array_keys(config('meezan.amenities')))],
            // Map viewport ("search as I move the map"); only listings with coordinates can match.
            'north' => ['nullable', 'numeric', 'between:-90,90'],
            'south' => ['nullable', 'numeric', 'between:-90,90'],
            'east' => ['nullable', 'numeric', 'between:-180,180'],
            'west' => ['nullable', 'numeric', 'between:-180,180'],
            // Drawn search area: "lat,lng;lat,lng;..." (see App\Support\Polygon).
            'poly' => ['nullable', 'string', 'max:2600'],
            'verified' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'boolean'],
            // Days on market and recent price cuts.
            'listed' => ['nullable', Rule::in(array_keys(self::LISTED))],
            'reduced' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]);

        // valid() drops a whole array when one element fails, which is the safe outcome.
        $this->filters = array_filter($validator->valid(), fn ($v) => $v !== null && $v !== '' && $v !== []);

        // Homepage tabs: Buy / Rent set the purpose, Plots / Commercial the category.
        if ($tab = $this->pull('tab')) {
            match ($tab) {
                'sale', 'rent' => $this->filters['purpose'] ??= $tab,
                default => $this->filters['group'] ??= $tab,
            };
        }

        $this->types = array_values(array_unique($this->pull('types') ?? []));

        $slugs = $this->pull('areas') ?? [];
        // Merged duplicates resolve to the location that was kept (saved searches keep working).
        $this->areas = $slugs
            ? Area::whereIn('slug', $slugs)->ordered()->get()->map(fn (Area $a) => $a->merged_into_id ? (Area::find($a->merged_into_id) ?? $a) : $a)->unique('id')->values()
            : new Collection;

        // Free-text location typed without picking a suggestion: a city name,
        // "Area, City" / "Area City", or an area name or alias. A name found in
        // several cities is not guessed: the matches are offered instead.
        if ($location = $this->pull('location')) {
            [$place, $city] = LocationResolver::splitCity($location);
            if ($city && $place === '') {
                $this->filters['city'] ??= $city;
            } elseif ($this->areas->isEmpty()) {
                $city ??= $this->filters['city'] ?? null;
                $matches = LocationResolver::find($place, $city);
                if ($matches->count() === 1) {
                    $this->areas->push($matches->first());
                } else {
                    $this->suggestions = $matches->take(6);
                    $this->filters['q'] ??= $place;
                    if ($city) {
                        $this->filters['city'] ??= $city;
                    }
                }
            }
        }

        if (count($this->types) === 1) {
            $this->filters['group'] = PropertyType::from($this->types[0])->group();
        }

        // A drawn area takes priority over the map viewport; an invalid one is ignored.
        $this->polygon = Polygon::parse($this->filters['poly'] ?? null);
        if ($this->polygon) {
            $this->filters['poly'] = $this->polygon->toString();
            unset($this->filters['north'], $this->filters['south'], $this->filters['east'], $this->filters['west']);
        } else {
            unset($this->filters['poly']);
        }

        // Bounds only apply when all four edges are present.
        if (count(array_intersect_key($this->filters, array_flip(['north', 'south', 'east', 'west']))) < 4) {
            unset($this->filters['north'], $this->filters['south'], $this->filters['east'], $this->filters['west']);
        }

        if ($size = $this->get('size')) {
            $preset = self::SIZES[$size];
            $this->filters['unit'] = $preset['unit'];
            $this->filters['min_area'] = $preset['min'];
            if ($preset['max']) {
                $this->filters['max_area'] = $preset['max'];
            } else {
                unset($this->filters['max_area']);
            }
        }
    }

    /**
     * Accept `type`/`area` as a scalar or array and expose them as `types`/`areas`.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalise(array $input): array
    {
        foreach (['type' => 'types', 'area' => 'areas'] as $single => $multi) {
            $values = array_merge((array) ($input[$single] ?? []), (array) ($input[$multi] ?? []));
            $input[$multi] = array_values(array_filter($values, fn ($v) => is_string($v) && $v !== ''));
            unset($input[$single]);
        }

        // Both "For Sale" and "For Rent" ticked means either.
        if (is_array($input['purpose'] ?? null)) {
            $input['purpose'] = count($input['purpose']) === 1 ? reset($input['purpose']) : null;
        }

        return $input;
    }

    protected function pull(string $key): mixed
    {
        $value = $this->filters[$key] ?? null;
        unset($this->filters[$key]);

        return $value;
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $value);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'type' => count($this->types) === 1 ? $this->types[0] : $default,
            'area' => $this->areas->count() === 1 ? $this->areas->first()->slug : $default,
            default => $this->filters[$key] ?? $default,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return $this->types;
    }

    public function hasType(string $type): bool
    {
        return in_array($type, $this->types, true);
    }

    public function areas(): Collection
    {
        return $this->areas;
    }

    /** Selected areas plus their phases/sectors/blocks ("DHA Lahore" includes "DHA Phase 5"). */
    public function areaIds(): array
    {
        return $this->areaIds ??= LocationResolver::withDescendants($this->areas->pluck('id')->all());
    }

    protected ?array $areaIds = null;

    /** @return Collection<int, Area> */
    public function suggestions(): Collection
    {
        return $this->suggestions;
    }

    public function hasArea(Area $area): bool
    {
        return $this->areas->contains('id', $area->id);
    }

    /** The single selected area, when exactly one is chosen. */
    public function area(): ?Area
    {
        return $this->areas->count() === 1 ? $this->areas->first() : null;
    }

    public function unit(): AreaUnit
    {
        return AreaUnit::tryFrom($this->filters['unit'] ?? '') ?? AreaUnit::Marla;
    }

    /**
     * Filters other than purpose/sort that the user can clear.
     */
    public function hasBounds(): bool
    {
        return isset($this->filters['north']);
    }

    public function polygon(): ?Polygon
    {
        return $this->polygon;
    }

    /**
     * Published listings with coordinates inside the drawn area. A bounding-box
     * query narrows the candidates; ray casting decides the exact boundary.
     *
     * @return list<int>
     */
    protected function polygonIds(): array
    {
        if ($this->polygonIds !== null) {
            return $this->polygonIds;
        }

        $b = $this->polygon->bounds();

        return $this->polygonIds = Property::query()->published()
            // Public coordinates only, so a drawn shape cannot reveal a hidden exact pin.
            ->whereBetween('public_latitude', [$b['south'], $b['north']])
            ->whereBetween('public_longitude', [$b['west'], $b['east']])
            ->limit(self::POLYGON_CANDIDATE_LIMIT)
            ->get(['id', 'public_latitude', 'public_longitude'])
            ->filter(fn ($p) => $this->polygon->contains((float) $p->public_latitude, (float) $p->public_longitude))
            ->pluck('id')
            ->all();
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->filters['features'] ?? [], true);
    }

    public function activeFilterCount(): int
    {
        return count(array_diff_key($this->filters, array_flip(['purpose', 'sort', 'unit', 'city', 'size', 'south', 'east', 'west'])))
            + ($this->types ? 1 : 0) + ($this->areas->isNotEmpty() ? 1 : 0);
    }

    public function query(): Builder
    {
        return $this->apply(Property::query()->published(), $this->filters, $this->types, true);
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  list<string>  $types
     */
    protected function apply(Builder $query, array $f, array $types, bool $withAreas, bool $sort = true): Builder
    {
        $query->when($f['purpose'] ?? null, fn ($q, $v) => $q->where('purpose', $v))
            ->when($types, fn ($q) => $q->whereIn('type', $types))
            ->when(! $types && isset($f['group']), fn ($q) => $q->inGroup($f['group']))
            ->when($f['city'] ?? null, fn ($q, $v) => $q->where('city', $v))
            ->when($withAreas && $this->areas->isNotEmpty(), fn ($q) => $q->whereIn('area_id', $this->areaIds()))
            ->when($f['min_price'] ?? null, fn ($q, $v) => $q->where('price', '>=', $v))
            ->when($f['max_price'] ?? null, fn ($q, $v) => $q->where('price', '<=', $v))
            ->when($f['beds'] ?? null, fn ($q, $v) => $q->where('bedrooms', '>=', $v))
            ->when($f['baths'] ?? null, fn ($q, $v) => $q->where('bathrooms', '>=', $v))
            ->when($f['min_area'] ?? null, fn ($q, $v) => $q->where('area_sqft', '>=', $this->unit()->toSquareFeet((float) $v)))
            ->when($f['max_area'] ?? null, fn ($q, $v) => $q->where('area_sqft', '<=', $this->unit()->toSquareFeet((float) $v)))
            ->when($f['furnishing'] ?? null, fn ($q, $v) => $q->where('furnishing', $v))
            ->when($f['completion'] ?? null, fn ($q, $v) => $q->where('completion_status', $v))
            ->when($f['min_covered'] ?? null, fn ($q, $v) => $q->where('covered_area_sqft', '>=', $v))
            ->when($f['max_covered'] ?? null, fn ($q, $v) => $q->where('covered_area_sqft', '<=', $v))
            ->when($f['features'] ?? null, function ($q, $features) {
                // Amenities are a JSON array of keys; a quoted LIKE works on MySQL and SQLite.
                foreach ($features as $feature) {
                    $q->where('amenities', 'like', '%"'.$feature.'"%');
                }
            })
            ->when($this->polygon, fn ($q) => $q->whereIn('properties.id', $this->polygonIds() ?: [0]))
            ->when(isset($f['north']), fn ($q) => $q->whereBetween('public_latitude', [min($f['south'], $f['north']), max($f['south'], $f['north'])])
                ->whereBetween('public_longitude', [min($f['west'], $f['east']), max($f['west'], $f['east'])]))
            ->when($f['photos'] ?? false, fn ($q) => $q->has('images'))
            ->when($f['listed'] ?? null, fn ($q, $days) => $q->where('published_at', '>=', now()->subDays((int) $days)))
            ->when($f['reduced'] ?? false, fn ($q) => $q->recentlyReduced())
            ->when($f['verified'] ?? false, fn ($q) => $q->whereHas('dealer.dealerProfile', fn ($d) => $d->where('verification_status', DealerStatus::Verified)))
            ->when($f['q'] ?? null, function ($q, $term) {
                $like = '%'.$this->escapeLike($term).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhereHas('area', fn ($a) => $a->where('name', 'like', $like)));
            });

        if ($sort) {
            match ($f['sort'] ?? 'newest') {
                'price_asc' => $query->orderBy('price'),
                'price_desc' => $query->orderByDesc('price'),
                'area_desc' => $query->orderByDesc('area_sqft'),
                default => $query->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('id'),
            };
        }

        return $query;
    }

    /**
     * Listing counts for the checkbox filters. Each facet ignores its own
     * selection so the numbers show what ticking another option would add.
     *
     * @return array{types: array<string, int>, areas: array<int, int>}
     */
    public function facets(): array
    {
        $types = $this->apply(Property::query()->published(), array_diff_key($this->filters, ['group' => 1]), [], true, false)
            ->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type')->map(fn ($v) => (int) $v)->all();

        $areas = $this->apply(Property::query()->published(), $this->filters, $this->types, false, false)
            ->whereNotNull('area_id')
            ->select('area_id', DB::raw('count(*) as total'))->groupBy('area_id')->pluck('total', 'area_id')->map(fn ($v) => (int) $v)->all();

        return ['types' => $types, 'areas' => $areas];
    }

    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return $this->query()
            ->forCards()
            ->paginate($perPage ?? config('meezan.listing.per_page'))
            ->withQueryString();
    }

    /**
     * Human headline for the current search, e.g. "Houses for Sale in Gulberg, Lahore".
     */
    public function headline(?int $count = null): string
    {
        $type = count($this->types) === 1
            ? PropertyType::from($this->types[0])->label().'s'
            : (isset($this->filters['group']) ? PropertyType::groupLabel($this->filters['group']) : 'Properties');

        // "1 Property for Sale…", not "1 Properties…".
        if ($count === 1) {
            $type = Str::singular($type);
        }

        $purpose = match ($this->filters['purpose'] ?? null) {
            'sale' => ' for Sale',
            'rent' => ' for Rent',
            default => '',
        };

        $area = $this->area();
        $place = $area ? $area->name.', '.$area->city : ($this->filters['city'] ?? 'Pakistan');

        return $type.$purpose.' in '.$place;
    }

    /**
     * Removable chips describing the active filters.
     *
     * @return list<array{label: string, key: string, value?: string}>
     */
    public function chips(): array
    {
        $f = $this->filters;
        $chips = [];

        foreach ($this->types as $type) {
            $chips[] = ['label' => PropertyType::from($type)->label(), 'key' => 'type', 'value' => $type];
        }
        if (! $this->types && isset($f['group'])) {
            $chips[] = ['label' => PropertyType::groupLabel($f['group']), 'key' => 'group'];
        }
        foreach ($this->areas as $area) {
            $chips[] = ['label' => $area->name, 'key' => 'area', 'value' => $area->slug];
        }
        if (isset($f['min_price'])) {
            $chips[] = ['label' => 'From '.Format::price((int) $f['min_price']), 'key' => 'min_price'];
        }
        if (isset($f['max_price'])) {
            $chips[] = ['label' => 'Up to '.Format::price((int) $f['max_price']), 'key' => 'max_price'];
        }
        if (isset($f['beds'])) {
            $chips[] = ['label' => $f['beds'].'+ beds', 'key' => 'beds'];
        }
        if (isset($f['baths'])) {
            $chips[] = ['label' => $f['baths'].'+ baths', 'key' => 'baths'];
        }
        if (isset($f['size'])) {
            $chips[] = ['label' => self::SIZES[$f['size']]['label'], 'key' => 'size'];
        } else {
            if (isset($f['min_area'])) {
                $chips[] = ['label' => 'Min '.Format::number((float) $f['min_area']).' '.$this->unit()->label(), 'key' => 'min_area'];
            }
            if (isset($f['max_area'])) {
                $chips[] = ['label' => 'Max '.Format::number((float) $f['max_area']).' '.$this->unit()->label(), 'key' => 'max_area'];
            }
        }
        if (isset($f['completion'])) {
            $chips[] = ['label' => Property::COMPLETION[$f['completion']], 'key' => 'completion'];
        }
        if (isset($f['min_covered'])) {
            $chips[] = ['label' => 'Covered from '.number_format($f['min_covered']).' sq ft', 'key' => 'min_covered'];
        }
        if (isset($f['max_covered'])) {
            $chips[] = ['label' => 'Covered up to '.number_format($f['max_covered']).' sq ft', 'key' => 'max_covered'];
        }
        foreach ($f['features'] ?? [] as $feature) {
            $chips[] = ['label' => config('meezan.amenities')[$feature], 'key' => 'feature', 'value' => $feature];
        }
        if (isset($f['north'])) {
            $chips[] = ['label' => 'Within map area', 'key' => 'bounds'];
        }
        if ($this->polygon) {
            $chips[] = ['label' => 'Within drawn area', 'key' => 'poly'];
        }
        if (isset($f['furnishing'])) {
            $chips[] = ['label' => self::FURNISHING[$f['furnishing']], 'key' => 'furnishing'];
        }
        if (! empty($f['verified'])) {
            $chips[] = ['label' => 'Verified dealers', 'key' => 'verified'];
        }
        if (! empty($f['photos'])) {
            $chips[] = ['label' => 'With photos', 'key' => 'photos'];
        }
        if (isset($f['listed'])) {
            $chips[] = ['label' => 'Listed: '.strtolower(self::LISTED[(int) $f['listed']]), 'key' => 'listed'];
        }
        if (! empty($f['reduced'])) {
            $chips[] = ['label' => 'Price reduced', 'key' => 'reduced'];
        }
        if (isset($f['q'])) {
            $chips[] = ['label' => '“'.$f['q'].'”', 'key' => 'q'];
        }

        return $chips;
    }

    /**
     * URL for the current search without one chip's value.
     */
    public function urlWithout(array $chip, string $route = 'properties.index'): string
    {
        $query = request()->except(['page']);
        $key = $chip['key'];

        if ($key === 'bounds') {
            unset($query['north'], $query['south'], $query['east'], $query['west'], $query['follow']);

            return route($route, $query);
        }

        if (isset($chip['value'])) {
            $list = array_merge((array) ($query[$key] ?? []), (array) ($query[$key.'s'] ?? []));
            $list = array_values(array_diff($list, [$chip['value']]));
            unset($query[$key.'s'], $query[$key]);
            if ($list) {
                $query[$key === 'feature' ? 'features' : $key] = $list;
            }
            if ($key === 'area') {
                unset($query['location']);
            }
        } else {
            unset($query[$key]);
            if ($key === 'size') {
                unset($query['min_area'], $query['max_area'], $query['unit']);
            }
        }

        return route($route, $query);
    }
}
