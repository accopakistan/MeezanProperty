<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Support\Geo;
use Illuminate\Support\Collection;

/**
 * Finds listings that may be the same property and says why. Signals:
 * same purpose and type (required), then same area, near-identical size,
 * price within 10%, pins within 150 m, the same address, a similar title
 * and the same dealer. Two or more strong signals make a candidate.
 *
 * It only raises candidates for human review: nothing is rejected, hidden
 * or deleted automatically, and the wording never alleges fraud.
 */
class DuplicateDetector
{
    protected const MAX_CANDIDATES = 5;

    protected const PIN_METRES = 150;

    /**
     * @return Collection<int, Property> candidates, each with a `duplicate_reasons` attribute
     */
    public function candidates(Property $property): Collection
    {
        if (! $property->purpose || ! $property->type) {
            return collect();
        }

        $pool = Property::query()
            ->whereKeyNot($property->id)
            ->whereIn('status', [PropertyStatus::Published, PropertyStatus::Pending, PropertyStatus::ChangesRequested])
            ->where('purpose', $property->purpose)
            ->where('type', $property->type)
            ->where('city', $property->city)
            ->where(function ($q) use ($property) {
                // Cheap pre-filter: same area, or a nearby pin, or the same dealer.
                $q->when($property->area_id, fn ($w) => $w->orWhere('area_id', $property->area_id))
                    ->orWhere('user_id', $property->user_id)
                    ->when($property->hasCoordinates(), fn ($w) => $w->orWhere(fn ($b) => $b
                        ->whereBetween('latitude', [$property->latitude - 0.002, $property->latitude + 0.002])
                        ->whereBetween('longitude', [$property->longitude - 0.002, $property->longitude + 0.002])));
            })
            ->with('dealer.dealerProfile:id,user_id,agency_name')
            ->limit(60)
            ->get();

        return $pool
            ->map(function (Property $other) use ($property) {
                $reasons = $this->reasons($property, $other);
                $other->setAttribute('duplicate_reasons', $reasons);

                return $other;
            })
            ->filter(fn (Property $other) => $this->isCandidate($other->duplicate_reasons))
            ->sortByDesc(fn (Property $other) => count($other->duplicate_reasons))
            ->take(self::MAX_CANDIDATES)
            ->values();
    }

    /**
     * Store the current warnings on the listing (shown to the dealer and to
     * reviewers). Clears them when nothing matches any more.
     *
     * @return Collection<int, Property>
     */
    public function record(Property $property): Collection
    {
        $candidates = $this->candidates($property);
        $flags = collect($property->review_flags ?? [])->except('duplicates')->all();
        if ($candidates->isNotEmpty()) {
            $flags['duplicates'] = $candidates->map(fn (Property $c) => ['id' => $c->id, 'reference' => $c->reference, 'reasons' => $c->duplicate_reasons])->all();
        }
        $property->review_flags = $flags ?: null;

        return $candidates;
    }

    /**
     * @return list<string> why the two listings look alike
     */
    public function reasons(Property $a, Property $b): array
    {
        $reasons = [];

        if ($a->area_id && $a->area_id === $b->area_id) {
            $reasons[] = 'same area';
        }
        if ($a->area_sqft && $b->area_sqft && abs($a->area_sqft - $b->area_sqft) <= $a->area_sqft * 0.02) {
            $reasons[] = 'same size';
        }
        if ($a->price && $b->price && abs($a->price - $b->price) <= $a->price * 0.10) {
            $reasons[] = 'price within 10%';
        }
        if ($a->hasCoordinates() && $b->hasCoordinates() && Geo::km([$a->latitude, $a->longitude], [$b->latitude, $b->longitude]) * 1000 <= self::PIN_METRES) {
            $reasons[] = 'map pins within '.self::PIN_METRES.' m';
        }
        if ($a->address && $b->address && $this->normalise($a->address) === $this->normalise($b->address)) {
            $reasons[] = 'same address';
        }
        if ($this->titleSimilarity((string) $a->title, (string) $b->title) >= 0.85) {
            $reasons[] = 'very similar title';
        }
        if ($a->user_id && $a->user_id === $b->user_id) {
            $reasons[] = 'same dealer';
        }

        return $reasons;
    }

    /**
     * The previous rule (same area + size + price) still qualifies; so do a
     * nearby pin or matching address with one more signal, or any three.
     *
     * @param  list<string>  $reasons
     */
    protected function isCandidate(array $reasons): bool
    {
        $has = fn (string $r) => in_array($r, $reasons, true) || collect($reasons)->contains(fn ($x) => str_starts_with($x, $r));
        $strong = count(array_filter(['same area', 'same size', 'price within 10%'], $has));

        return $strong === 3
            || (($has('map pins') || $has('same address')) && count($reasons) >= 2)
            || count($reasons) >= 4
            || ($has('very similar title') && $has('same size') && $has('price within 10%'));
    }

    protected function titleSimilarity(string $a, string $b): float
    {
        $a = $this->normalise($a);
        $b = $this->normalise($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    protected function normalise(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', strtolower($text))));
    }
}
