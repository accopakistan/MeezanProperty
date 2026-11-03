<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Property;
use Illuminate\Support\Collection;

/**
 * Map payload for a set of listings.
 *
 * Listings with their own coordinates get a price pin at that point. Listings
 * without one are grouped into a single marker per area, placed at the area's
 * approximate centre and labelled as approximate, never given a made-up pin.
 */
class MapMarkers
{
    /** Listings published within this many days carry a "New" badge. */
    public const NEW_DAYS = Property::NEW_DAYS;

    /**
     * @param  iterable<Property>  $properties  with area and coverImage loaded
     * @param  array<string, mixed>|null  $query  current search query, for "see all in this area" links
     * @return list<array<string, mixed>>
     */
    public static function for(iterable $properties, ?array $query = null, string $route = 'properties.map'): array
    {
        $properties = collect($properties);

        // Only public coordinates leave the server: the exact pin when the
        // advertiser allows it, a blurred point for "approximate", and the
        // area centre for "area only" or no pin.
        $pins = $properties->filter(fn (Property $p) => $p->publicCoordinates() !== null)->map(fn (Property $p) => [
            'kind' => $p->showsExactLocation() ? 'pin' : 'approx',
            'id' => $p->id,
            'lat' => $p->public_latitude,
            'lng' => $p->public_longitude,
            'label' => Format::compactPrice($p->price),
            'isNew' => static::isNew($p),
        ] + static::item($p));

        $areas = $properties
            ->filter(fn (Property $p) => $p->publicCoordinates() === null)
            ->filter(fn (Property $p) => $p->area?->hasCoordinates())
            ->groupBy('area_id')
            ->map(fn (Collection $group) => static::areaGroup($group, $query, $route));

        return $pins->concat($areas)->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function areaGroup(Collection $group, ?array $query, string $route): array
    {
        $area = $group->first()->area;
        $count = $group->count();

        return [
            'kind' => 'area',
            'id' => 'area-'.$area->id,
            'ids' => $group->pluck('id')->all(),
            'lat' => $area->latitude,
            'lng' => $area->longitude,
            'count' => $count,
            'label' => $count === 1 ? Format::compactPrice($group->first()->price) : $count.' listings',
            'from' => $count > 1 ? Format::compactPrice((int) $group->min('price')).'+' : null,
            'isNew' => $group->contains(fn ($p) => static::isNew($p)),
            'title' => $area->name,
            'url' => $query === null ? null : route($route, array_merge(array_diff_key($query, array_flip(['page', 'selected', 'areas', 'poly', 'north', 'south', 'east', 'west', 'follow', 'centres'])), ['area' => $area->slug])),
            'items' => $group->take(4)->map(fn (Property $p) => static::item($p))->values()->all(),
        ];
    }

    /**
     * Area marker from counts only (large result sets): no listing rows are
     * loaded, so it carries no preview items, only a link to the area.
     *
     * @param  array<string, mixed>|null  $query
     * @return array<string, mixed>
     */
    public static function areaSummary(Area $area, int $count, int $minPrice, ?array $query, string $route = 'properties.map'): array
    {
        return [
            'kind' => 'area',
            'id' => 'area-'.$area->id,
            'ids' => [],
            'lat' => $area->latitude,
            'lng' => $area->longitude,
            'count' => $count,
            'label' => $count === 1 ? Format::compactPrice($minPrice) : $count.' listings',
            'from' => $count > 1 ? Format::compactPrice($minPrice).'+' : null,
            'isNew' => false,
            'title' => $area->name,
            'url' => $query === null ? null : route($route, array_merge(array_diff_key($query, array_flip(['page', 'selected', 'areas', 'poly', 'north', 'south', 'east', 'west', 'follow', 'centres'])), ['area' => $area->slug])),
            'items' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function item(Property $p): array
    {
        return [
            'title' => $p->title,
            'price' => $p->priceLabel(),
            'meta' => collect([$p->bedrooms ? $p->bedrooms.' bd' : null, $p->areaLabel(), $p->area?->name])->filter()->implode(' · '),
            'photo' => $p->coverThumbUrl(),
            'url' => route('properties.show', $p),
        ];
    }

    protected static function isNew(Property $p): bool
    {
        return (bool) $p->published_at?->gt(now()->subDays(self::NEW_DAYS));
    }
}
