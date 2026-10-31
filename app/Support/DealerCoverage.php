<?php

namespace App\Support;

use App\Models\Area;
use App\Models\City;
use App\Models\DealerProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cities and areas a dealer serves: shared validation and saving for dealer
 * registration and the dealer profile. Leads are routed only inside these.
 */
class DealerCoverage
{
    /**
     * Served cities default to the head-office city, which is always included.
     *
     * @return array<string, mixed>
     */
    public static function rules(Request $request, string $homeCity): array
    {
        return [
            'cities' => ['nullable', 'array', 'max:'.count(config('meezan.cities'))],
            'cities.*' => [Rule::in(config('meezan.cities'))],
            'areas' => ['required', 'array', 'min:1', 'max:10'],
            'areas.*' => ['integer', Rule::exists('areas', 'id')->whereIn('city', self::cities($request, $homeCity))->where('is_active', true)],
        ];
    }

    /** @return list<string> */
    public static function cities(Request $request, string $homeCity): array
    {
        $chosen = array_intersect((array) $request->input('cities', []), config('meezan.cities'));

        return array_values(array_unique(array_merge([$homeCity], $chosen)));
    }

    /** @param  list<int|string>  $areaIds */
    public static function save(DealerProfile $profile, array $cities, array $areaIds): void
    {
        $profile->cities()->sync(City::whereIn('name', $cities)->pluck('id'));
        $profile->areas()->sync($areaIds);
    }

    /** Popular areas per city offered as quick picks (the rest via search). */
    public static function suggestions()
    {
        return Area::active()->popular()->whereIn('city', config('meezan.cities'))->ordered()->get(['id', 'name', 'city'])->groupBy('city');
    }
}
