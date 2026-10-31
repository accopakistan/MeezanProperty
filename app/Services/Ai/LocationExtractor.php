<?php

namespace App\Services\Ai;

use App\Models\Area;
use App\Models\AreaAlias;
use App\Support\LocationName;
use App\Support\LocationResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Finds the city and areas in a free-text requirement ("10 marla house in
 * DHA Phase 6 Lahore", "flat in Gulshan e Iqbal", "F-7 Islamabad").
 *
 * Rules: a stated city wins; areas are only taken from that city. Without a
 * city, the areas must all point to one city, otherwise the buyer is asked
 * which one ("Gulberg" exists in Lahore and Faisalabad). Non-launch cities
 * are reported, never mapped onto a launch city.
 */
class LocationExtractor
{
    /** Bump when the dictionary rules change, so cached dictionaries are rebuilt. */
    protected const DICTIONARY_VERSION = 2;

    /** Words that never identify a place on their own. */
    protected const STOP = ['phase', 'sector', 'block', 'town', 'city', 'housing', 'scheme', 'society', 'cooperative', 'colony', 'garden', 'gardens',
        'road', 'park', 'avenue', 'villas', 'homes', 'enclave', 'valley', 'extension', 'employees', 'new', 'model', 'main', 'the', 'and', 'of',
        'house', 'home', 'plot', 'plots', 'flat', 'shop', 'office', 'market', 'commercial', 'residential', 'corner', 'lawn', 'parking', 'basement',
        'green', 'royal', 'city', 'estate', 'heights', 'residency', 'residencia', 'view', 'farm', 'land', 'old', 'north', 'south', 'east', 'west'];

    /**
     * @return array{city: ?string, areaIds: list<int>, clarify: ?array{question: string, options: list<string>, choices: list<array{label: string, city: string, ids: list<int>}>}, unsupported: ?string, matched: list<string>}
     */
    public function extract(string $message): array
    {
        $text = ' '.LocationName::normalize($message).' ';
        $result = ['city' => null, 'areaIds' => [], 'clarify' => null, 'unsupported' => null, 'matched' => []];
        if (trim($text) === '') {
            return $result;
        }

        // 1. Location names and aliases present in the text, longest first.
        $dictionary = $this->dictionary();
        $found = [];
        foreach ($dictionary as $key => $candidates) {
            if (str_contains($text, ' '.$key.' ')) {
                $found[$key] = $candidates;
            }
        }
        uksort($found, fn ($a, $b) => strlen($b) <=> strlen($a));
        $kept = [];
        foreach ($found as $key => $candidates) {
            foreach (array_keys($kept) as $longer) {
                if (str_contains(' '.$longer.' ', ' '.$key.' ')) {
                    continue 2;
                }
            }
            $kept[$key] = $candidates;
        }

        // 2. Cities named outside those location names ("Multan Road" is in Lahore).
        $rest = $text;
        foreach (array_keys($kept) as $key) {
            $rest = str_replace(' '.$key.' ', '  ', $rest);
        }
        $cities = [];
        foreach (array_merge(array_combine(array_map('strtolower', config('meezan.cities')), config('meezan.cities')), LocationResolver::CITY_ALIASES) as $word => $city) {
            if (str_contains($rest, ' '.$word.' ')) {
                $cities[$city] = true;
            }
        }
        $cities = array_keys($cities);
        if (! $cities) {
            foreach (LocationResolver::OTHER_CITIES as $other) {
                if (str_contains($rest, ' '.$other.' ')) {
                    $result['unsupported'] = $other === 'pindi' ? 'Rawalpindi' : ucfirst($other);

                    return $result;
                }
            }
        }
        if (count($cities) > 1) {
            $result['clarify'] = ['question' => 'Which city should I search?', 'options' => $cities,
                'choices' => array_map(fn ($c) => ['label' => $c, 'city' => $c, 'ids' => []], $cities)];

            return $result;
        }
        $city = $cities[0] ?? null;

        // 3. Resolve the areas against the city.
        if ($kept && ! $city) {
            $perKey = array_map(fn ($c) => array_unique(array_column($c, 1)), $kept);
            $common = array_values(array_intersect(...array_values($perKey)));
            if (count($common) === 1) {
                $city = $common[0];
            } else {
                $options = [];
                $byCity = [];
                foreach ($kept as $candidates) {
                    foreach ($candidates as [$id, $c, $name]) {
                        $options[] = $name.', '.$c;
                        $byCity[$c]['ids'][] = $id;
                        $byCity[$c]['names'][] = $name;
                    }
                }
                // One choice per city, so a reply of "Lahore" or "1" settles it.
                $choices = [];
                foreach ($byCity as $c => $group) {
                    $choices[] = ['label' => implode(' / ', array_slice(array_unique($group['names']), 0, 2)).', '.$c, 'city' => $c, 'ids' => array_values(array_unique($group['ids']))];
                }
                $result['matched'] = array_keys($kept);
                $result['clarify'] = ['question' => 'That place name is used in more than one city. Which one do you mean?',
                    'options' => array_slice(array_values(array_unique($options)), 0, 6), 'choices' => array_slice($choices, 0, 7)];

                return $result;
            }
        }

        $ids = [];
        foreach ($kept as $candidates) {
            foreach ($candidates as [$id, $c]) {
                if ($c === $city) {
                    $ids[] = $id;
                }
            }
        }

        return ['city' => $city, 'areaIds' => array_values(array_unique($ids)), 'clarify' => null, 'unsupported' => null, 'matched' => array_keys($kept)];
    }

    /**
     * Normalised name/alias → candidates [id, city, name]. Each name is also
     * indexed without its city word ("dha phase 5 karachi" → "dha phase 5"),
     * so a generic name maps to every city that has it. Cached until areas change.
     *
     * @return array<string, list<array{0: int, 1: string, 2: string}>>
     */
    public function dictionary(): array
    {
        $version = md5(Area::max('updated_at').'|'.Area::count().'|'.AreaAlias::count());

        return Cache::remember('meezan.location-dictionary.'.self::DICTIONARY_VERSION.'.'.$version, 3600, function () {
            $cityWords = array_map('strtolower', config('meezan.cities'));
            $dictionary = [];
            $add = function (string $key, Area $area) use (&$dictionary) {
                $key = trim(preg_replace('/\s+/', ' ', $key));
                if (! $this->usable($key)) {
                    return;
                }
                $dictionary[$key][$area->id] = [$area->id, $area->city, $area->name];
            };

            Area::active()->whereIn('city', config('meezan.cities'))->where('type', '!=', 'city')
                ->with('aliases:id,area_id,normalized')
                ->get(['id', 'city', 'name', 'normalized_name'])
                ->each(function (Area $area) use ($add, $cityWords) {
                    $keys = array_merge([$area->normalized_name], $area->aliases->pluck('normalized')->all());
                    foreach ($keys as $key) {
                        $add($key, $area);
                        // "DHA" on its own means any DHA phase/sector in the city.
                        if (str_starts_with((string) $key, 'dha ')) {
                            $add('dha', $area);
                        }
                        $stripped = preg_replace('/\b('.implode('|', $cityWords).')\b/', ' ', (string) $key);
                        if ($stripped !== $key) {
                            $add($stripped, $area);
                        }
                    }
                });

            return array_map('array_values', $dictionary);
        });
    }

    protected function usable(string $key): bool
    {
        if ($key === '' || in_array($key, array_map('strtolower', config('meezan.cities')), true)) {
            return false;
        }
        if (preg_match('/^[a-i]\d{1,2}$/', $key)) {
            return true; // Islamabad sector codes: f7, e11
        }
        $words = explode(' ', $key);

        // A single generic word ("garden", "town") is not a place; a phrase of them
        // can be ("Garden Town", "Model Town").
        return strlen($key) >= 3 && (count($words) > 1 || ! in_array($key, self::STOP, true)) && ! ctype_digit(str_replace(' ', '', $key));
    }
}
