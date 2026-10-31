<?php

namespace App\Support;

use App\Models\Area;
use Illuminate\Database\Eloquent\Collection;

/**
 * The dependent selects of a location form (area/society → phase → sector/
 * block) for one city, pre-filled along the path to the chosen area. Only
 * one city's top level and the selected branch are loaded, never the whole
 * national list.
 */
class LocationLevels
{
    /**
     * @return list<array{label: string, options: Collection<int, Area>, selected: ?int}>
     */
    public static function for(?string $city, ?int $areaId): array
    {
        $city = in_array($city, config('meezan.cities'), true) ? $city : config('meezan.default_city');

        $chain = [];
        $node = $areaId ? Area::where('city', $city)->find($areaId) : null;
        while ($node && count($chain) < 5) {
            array_unshift($chain, $node);
            $node = $node->parent_id ? Area::find($node->parent_id) : null;
        }

        $levels = [['label' => 'Area / society', 'options' => self::options($city, null), 'selected' => $chain[0]->id ?? null]];
        foreach ($chain as $i => $area) {
            $children = self::options($city, $area->id);
            if ($children->isEmpty()) {
                break;
            }
            $levels[] = ['label' => self::label($children), 'options' => $children, 'selected' => $chain[$i + 1]->id ?? null];
        }

        return $levels;
    }

    /** @return Collection<int, Area> */
    public static function options(string $city, ?int $parentId): Collection
    {
        return Area::active()->where('city', $city)
            ->when($parentId, fn ($q) => $q->where('parent_id', $parentId), fn ($q) => $q->whereNull('parent_id'))
            ->withCount('children')
            ->orderByDesc('is_popular')->ordered()
            ->get(['id', 'name', 'type', 'latitude', 'longitude', 'is_popular', 'sort_order']);
    }

    /** "Phase", "Sector / block" … from what the children are. */
    public static function label(Collection $children): string
    {
        $types = $children->pluck('type')->unique();

        return match (true) {
            $types->every(fn ($t) => $t === 'phase') => 'Phase',
            $types->every(fn ($t) => in_array($t, ['sector', 'block'], true)) => 'Sector / block',
            default => 'Phase / sector / block',
        };
    }
}
