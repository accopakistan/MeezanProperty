<?php

namespace App\Support;

use App\Enums\PropertyType;
use App\Models\Area;
use App\Models\Property;
use Illuminate\Database\Eloquent\Collection;

/**
 * Request-scoped lookups shared by layout components and forms.
 */
class Lookup
{
    /** @var array<string, Collection<int, Area>> */
    protected static array $areas = [];

    /**
     * Areas worth listing in pick lists and filters: popular ones and those
     * with published listings, optionally in one city. Bounded on purpose:
     * the full national list (1,000+) is reached through the search box.
     *
     * @return Collection<int, Area>
     */
    public static function areas(?string $city = null): Collection
    {
        return static::$areas[$city ?? '*'] ??= Area::query()
            ->active()
            ->inCity($city)
            ->whereIn('city', config('meezan.cities'))
            ->where(fn ($q) => $q->where('is_popular', true)
                ->orWhereIn('id', Property::published()->whereNotNull('area_id')->select('area_id')))
            ->orderByRaw('case when city = ? then 0 else 1 end', [config('meezan.default_city')])
            ->orderBy('city')
            ->ordered()
            ->get(['id', 'city', 'name', 'slug', 'is_popular', 'sort_order']);
    }

    /**
     * @return Collection<int, Area>
     */
    public static function popularAreas(int $limit = 8, ?string $city = null): Collection
    {
        return static::areas($city)->where('is_popular', true)->take($limit)->values();
    }

    /**
     * Property types grouped for <optgroup> rendering.
     *
     * @return array<string, list<PropertyType>>
     */
    public static function typesByGroup(): array
    {
        return [
            'Homes' => PropertyType::inGroup('homes'),
            'Plots' => PropertyType::inGroup('plots'),
            'Commercial' => PropertyType::inGroup('commercial'),
        ];
    }

    /**
     * Budget presets in PKR for search selects.
     *
     * @return array<int, string>
     */
    public static function priceOptions(string $purpose = 'sale'): array
    {
        $values = $purpose === 'rent'
            ? [25_000, 50_000, 75_000, 100_000, 150_000, 200_000, 300_000, 500_000, 1_000_000]
            : [2_500_000, 5_000_000, 10_000_000, 15_000_000, 20_000_000, 30_000_000, 50_000_000, 75_000_000, 100_000_000, 200_000_000];

        return collect($values)->mapWithKeys(fn ($v) => [$v => Format::price($v)])->all();
    }

    /** @var array<int, true>|null */
    protected static ?array $savedIds = null;

    public static function isSaved(int $propertyId): bool
    {
        if (static::$savedIds === null) {
            $user = auth()->user();
            static::$savedIds = $user
                ? array_fill_keys($user->savedProperties()->pluck('properties.id')->all(), true)
                : [];
        }

        return isset(static::$savedIds[$propertyId]);
    }

    public static function flush(): void
    {
        static::$areas = [];
        static::$savedIds = null;
    }
}
