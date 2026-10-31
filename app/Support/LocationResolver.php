<?php

namespace App\Support;

use App\Models\Area;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Turns typed location text into launch cities and areas. Shared by search,
 * the suggestion endpoint and the AI requirement parser so all three agree.
 * A name that exists in several cities is never silently assigned to one.
 */
class LocationResolver
{
    /** Common short forms people type for the launch cities. */
    public const CITY_ALIASES = [
        'lhr' => 'Lahore', 'khi' => 'Karachi', 'isb' => 'Islamabad', 'isl' => 'Islamabad', 'fsd' => 'Faisalabad', 'lyallpur' => 'Faisalabad',
    ];

    /** Recognised but not a launch city: mentioned so callers can say so instead of guessing. */
    public const OTHER_CITIES = ['rawalpindi', 'pindi', 'gujranwala', 'sialkot', 'hyderabad', 'bahawalpur', 'sargodha', 'abbottabad', 'murree'];

    /** Canonical launch city for a word or phrase ("isb", "LAHORE"), or null. */
    public static function city(?string $text): ?string
    {
        $t = mb_strtolower(trim((string) $text));
        foreach (config('meezan.cities') as $city) {
            if ($t === mb_strtolower($city)) {
                return $city;
            }
        }

        return self::CITY_ALIASES[$t] ?? null;
    }

    /**
     * "Gulberg, Lahore" → ["Gulberg", "Lahore"]; "DHA Karachi" → ["DHA", "Karachi"]
     * (unless "DHA Karachi" is itself a stored name); "Lahore" → ["", "Lahore"].
     *
     * @return array{0: string, 1: ?string}
     */
    public static function splitCity(string $text): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($city = self::city($text)) {
            return ['', $city];
        }

        $names = array_merge(config('meezan.cities'), array_keys(self::CITY_ALIASES));
        $pattern = '/(^|[\s,\-]+)(?:in\s+)?('.implode('|', array_map(fn ($n) => preg_quote($n, '/'), $names)).')\s*$/i';
        if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            $place = trim(substr($text, 0, $m[0][1]), ' ,-');
            $city = self::city($m[2][0]);
            // Keep names that contain the city ("DHA City Karachi", "Lahore Motorway City").
            if ($place !== '' && Area::active()->where('city', $city)->where('normalized_name', LocationName::normalize($text))->exists()) {
                return [$text, $city];
            }

            return [$place, $city];
        }

        return [$text, null];
    }

    /**
     * Areas whose name or alias matches the text exactly, else by prefix.
     * Exact name matches win over alias matches, so "Gulberg" picks Gulberg,
     * Lahore and offers "Gulberg, Faisalabad" (alias) as an alternative.
     *
     * @return Collection<int, Area>
     */
    public static function find(string $text, ?string $city = null, int $limit = 6): Collection
    {
        $norm = LocationName::normalize($text);
        if (mb_strlen($norm) < 2) {
            return new Collection;
        }

        $base = fn () => Area::active()->inCity($city)->whereIn('city', config('meezan.cities'));

        $byName = $base()->where('normalized_name', $norm)->orderByDesc('is_popular')->orderBy('depth')->get();
        $byAlias = $base()->whereHas('aliases', fn (Builder $a) => $a->where('normalized', $norm))->whereNotIn('id', $byName->pluck('id'))->get();

        if ($byName->count() === 1) {
            return $byName->concat($byAlias)->take($limit)->values();
        }
        if ($byName->isNotEmpty() || $byAlias->isNotEmpty()) {
            return $byName->concat($byAlias)->take($limit)->values();
        }

        return self::suggest($text, $city, $limit);
    }

    /**
     * Prefix/word-prefix suggestions for the location box, best first.
     *
     * @return Collection<int, Area>
     */
    public static function suggest(string $text, ?string $city = null, int $limit = 10): Collection
    {
        $norm = LocationName::normalize($text);
        if (mb_strlen($norm) < 2) {
            return new Collection;
        }
        $like = str_replace(['%', '_'], ['\%', '\_'], $norm);

        return Area::active()->inCity($city)->whereIn('city', config('meezan.cities'))
            ->where(fn (Builder $q) => $q->where('normalized_name', 'like', $like.'%')
                ->orWhere('normalized_name', 'like', '% '.$like.'%')
                ->orWhereHas('aliases', fn (Builder $a) => $a->where('normalized', 'like', $like.'%')))
            ->with('parent:id,name')
            ->orderByRaw('case when normalized_name = ? then 0 when normalized_name like ? then 1 else 2 end', [$norm, $like.'%'])
            ->orderByDesc('is_popular')->orderBy('depth')->orderBy('sort_order')->orderBy('name')
            ->limit($limit)
            ->get(['id', 'city', 'name', 'slug', 'type', 'parent_id', 'depth', 'is_popular', 'sort_order', 'normalized_name']);
    }

    /**
     * The given areas with everything above and below them, for coverage
     * matching (a dealer serving a society covers its phases, and the reverse).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function related(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $all = self::withDescendants($ids);
        $frontier = $ids;
        for ($level = 0; $frontier && $level < 4; $level++) {
            $frontier = Area::whereIn('id', $frontier)->whereNotNull('parent_id')->pluck('parent_id')->all();
            $all = array_merge($all, $frontier);
        }

        return array_values(array_unique(array_map('intval', $all)));
    }

    /**
     * The given area ids plus every phase/sector/block below them.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function withDescendants(array $ids): array
    {
        $all = $ids;
        $frontier = $ids;
        for ($level = 0; $frontier && $level < 4; $level++) {
            $frontier = Area::whereIn('parent_id', $frontier)->whereNotIn('id', $all)->pluck('id')->all();
            $all = array_merge($all, $frontier);
        }

        return array_values(array_unique($all));
    }
}
