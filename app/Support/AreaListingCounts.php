<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Live listing counts per area, including its phases / sectors / blocks
 * (the same four levels LocationResolver::withDescendants() follows).
 * One grouped query and one tree query, cached for ten minutes, instead of
 * a count per area.
 */
class AreaListingCounts
{
    /**
     * @param  list<int>  $areaIds
     * @return array<int, int>
     */
    public static function for(array $areaIds): array
    {
        $direct = Cache::remember('map:area-listing-counts', 600, fn () => Property::query()->published()->whereNotNull('area_id')
            ->toBase()->groupBy('area_id')->pluck(DB::raw('count(*)'), 'area_id')->map(fn ($n) => (int) $n)->all());

        $children = Cache::remember('map:area-children', 600, fn () => Area::whereNotNull('parent_id')->toBase()
            ->get(['id', 'parent_id'])->groupBy('parent_id')->map(fn ($rows) => $rows->pluck('id')->all())->all());

        $counts = [];
        foreach ($areaIds as $id) {
            $total = $direct[$id] ?? 0;
            $seen = [$id => true];
            $frontier = [$id];
            for ($level = 0; $frontier && $level < 4; $level++) {
                $next = [];
                foreach ($frontier as $parent) {
                    foreach ($children[$parent] ?? [] as $child) {
                        if (! isset($seen[$child])) {
                            $seen[$child] = true;
                            $total += $direct[$child] ?? 0;
                            $next[] = $child;
                        }
                    }
                }
                $frontier = $next;
            }
            $counts[$id] = $total;
        }

        return $counts;
    }
}
