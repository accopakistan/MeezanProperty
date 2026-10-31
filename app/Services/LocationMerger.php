<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AreaAlias;
use App\Models\BuyerRequirement;
use App\Models\User;
use App\Support\LocationName;
use App\Support\LocationResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges a duplicate location into the record that stays. Everything that
 * points at the duplicate (listings, leads, projects, dealer coverage, buyer
 * requirements, child phases, aliases) is moved; the duplicate is kept as an
 * inactive row with merged_into_id so old links redirect. Nothing is deleted.
 */
class LocationMerger
{
    /**
     * @return array<string, int> what was moved
     */
    public function merge(Area $from, Area $into, ?User $by = null): array
    {
        if ($from->is($into)) {
            throw new InvalidArgumentException('A location cannot be merged into itself.');
        }
        if ($from->city !== $into->city) {
            throw new InvalidArgumentException('Only locations in the same city can be merged.');
        }
        if (in_array($into->id, $this->descendantIds($from), true)) {
            throw new InvalidArgumentException('A location cannot be merged into one of its own phases or blocks.');
        }

        return DB::transaction(function () use ($from, $into, $by) {
            $moved = [
                'properties' => DB::table('properties')->where('area_id', $from->id)->update(['area_id' => $into->id]),
                'leads' => DB::table('leads')->where('area_id', $from->id)->update(['area_id' => $into->id]),
                'projects' => DB::table('projects')->where('area_id', $from->id)->update(['area_id' => $into->id]),
                'children' => Area::where('parent_id', $from->id)->get()->each(fn (Area $c) => $c->update(['parent_id' => $into->id]))->count(),
                'dealers' => 0,
                'requirements' => 0,
                'aliases' => 0,
            ];

            // Dealer coverage: add the target where missing, then drop the duplicate.
            $dealerIds = DB::table('area_dealer_profile')->where('area_id', $from->id)->pluck('dealer_profile_id');
            foreach ($dealerIds as $dealerId) {
                DB::table('area_dealer_profile')->insertOrIgnore(['area_id' => $into->id, 'dealer_profile_id' => $dealerId]);
            }
            $moved['dealers'] = DB::table('area_dealer_profile')->where('area_id', $from->id)->delete();

            // Buyer requirements keep area ids in JSON.
            BuyerRequirement::whereNotNull('area_ids')->get(['id', 'area_ids'])->each(function (BuyerRequirement $r) use ($from, $into, &$moved) {
                $ids = array_map('intval', $r->area_ids ?? []);
                if (in_array($from->id, $ids, true)) {
                    $r->area_ids = array_values(array_unique(array_map(fn ($id) => $id === $from->id ? $into->id : $id, $ids)));
                    $r->save();
                    $moved['requirements']++;
                }
            });

            // The duplicate's name and aliases become aliases of the target.
            foreach (array_merge([$from->name], $from->aliases()->pluck('alias')->all()) as $alias) {
                $alias = AreaAlias::firstOrCreate(
                    ['area_id' => $into->id, 'normalized' => LocationName::normalize($alias)],
                    ['alias' => $alias, 'city_id' => $into->city_id],
                );
                $moved['aliases'] += (int) $alias->wasRecentlyCreated;
            }

            $from->update(['is_active' => false, 'merged_into_id' => $into->id, 'is_popular' => false]);

            AuditLogger::record('area.merged', $into, ['from_id' => $from->id, 'from_name' => $from->name, 'moved' => $moved], $by?->id);

            return $moved;
        });
    }

    /** @return list<int> */
    protected function descendantIds(Area $area): array
    {
        return array_values(array_diff(LocationResolver::withDescendants([$area->id]), [$area->id]));
    }
}
