<?php

namespace App\Services\Matching;

use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Property;
use App\Support\Format;
use App\Support\LocationResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deterministic, database-backed matching. Hard constraints (purpose,
 * category, city and budget ceiling) filter the candidates; soft criteria
 * (preferred areas, budget fit, size, bedrooms, dealer verification) rank them.
 */
class PropertyMatcher
{
    protected const CANDIDATE_LIMIT = 200;

    public function match(MatchCriteria $criteria, ?int $limit = null): MatchResult
    {
        $limit ??= (int) config('meezan.ai.max_results', 3);

        $candidates = $this->candidates($criteria)->limit(self::CANDIDATE_LIMIT)->get();

        $matches = $candidates
            ->map(fn (Property $property) => $this->score($property, $criteria))
            // Listings well above budget are only offered when they are otherwise on target.
            ->reject(fn (PropertyMatch $m) => $m->budgetStatus === PropertyMatch::BUDGET_ABOVE && ! ($m->inArea && $this->typeFits($m->property, $criteria)))
            // Requested area first, then within budget before close/above, then score.
            ->sortByDesc(fn (PropertyMatch $m) => [(int) $m->inArea, $m->budgetRank(), $m->score, $m->property->published_at?->timestamp ?? 0])
            ->take($limit)
            ->values();

        return new MatchResult($criteria, $matches, $matches->isEmpty() ? $this->fallbackHints($criteria) : []);
    }

    /** Budget margins: "close" (default +10%) and "alternative" (default +25%). */
    public static function margin(string $kind): float
    {
        return (float) config('meezan.ai.budget.'.$kind.'_margin', $kind === 'close' ? 0.10 : 0.25);
    }

    protected function candidates(MatchCriteria $criteria, bool $withBudget = true): Builder
    {
        return Property::query()
            ->published()
            ->forCards()
            ->when($criteria->city, fn ($q, $v) => $q->where('city', $v))
            ->when($criteria->purpose, fn ($q, $v) => $q->where('purpose', $v))
            ->when($criteria->type, fn ($q, $v) => $q->where('type', $v))
            ->when(! $criteria->type && $criteria->group, fn ($q) => $q->inGroup($criteria->group))
            ->when($withBudget && $criteria->maxPrice, fn ($q) => $q->where('price', '<=', (int) floor($criteria->maxPrice * (1 + self::margin('alternative')))));
    }

    protected function typeFits(Property $property, MatchCriteria $c): bool
    {
        return ! $c->type || $property->type === $c->type;
    }

    /** @var array<string, list<int>> */
    protected array $areaSets = [];

    /** Preferred areas plus their phases/sectors ("DHA Lahore" includes "DHA Phase 6"). */
    protected function areaSet(MatchCriteria $c): array
    {
        return $this->areaSets[implode(',', $c->areaIds)] ??= LocationResolver::withDescendants($c->areaIds);
    }

    protected function score(Property $property, MatchCriteria $c): PropertyMatch
    {
        $score = 0;
        $max = 0;
        $reasons = [];
        $caveats = [];

        // Location (40)
        $max += 40;
        $inArea = true;
        if ($c->areaIds) {
            if (in_array((int) $property->area_id, $this->areaSet($c), true)) {
                $score += 40;
                $reasons[] = 'In your preferred area ('.$property->area?->name.')';
            } else {
                $inArea = false;
                $score += 10;
                $caveats[] = 'Outside your preferred areas — located in '.($property->area?->name ?? $property->city);
            }
        } else {
            $score += 30;
            $reasons[] = 'Located in '.$property->locationLabel();
        }

        // Budget (25): within, close (slightly above) or above. Never called "within" when it is not.
        $budgetStatus = null;
        if ($c->maxPrice) {
            $max += 25;
            $over = $property->price / $c->maxPrice - 1;
            if ($over <= 0) {
                $budgetStatus = PropertyMatch::BUDGET_WITHIN;
                $ratio = $property->price / $c->maxPrice;
                $score += (int) round(25 * ($ratio >= 0.6 ? 1 : max(0.4, $ratio / 0.6)));
                $reasons[] = 'Within your budget at '.Format::price($property->price);
            } elseif ($over <= self::margin('close')) {
                $budgetStatus = PropertyMatch::BUDGET_CLOSE;
                $score += 12;
                $caveats[] = sprintf('%s — %d%% above your %s budget', Format::price($property->price), (int) ceil($over * 100), Format::price($c->maxPrice));
            } else {
                $budgetStatus = PropertyMatch::BUDGET_ABOVE;
                $score += 4;
                $caveats[] = sprintf('Above your budget: %s, %d%% more than %s', Format::price($property->price), (int) ceil($over * 100), Format::price($c->maxPrice));
            }

            if ($c->minPrice && $property->price < $c->minPrice) {
                $caveats[] = 'Priced below the minimum you mentioned';
            }
        }

        // Furnishing (5), only when asked for and the listing says.
        if ($c->furnishing) {
            $max += 5;
            if ($property->furnishing === $c->furnishing) {
                $score += 5;
                $reasons[] = ucfirst(str_replace('_', '-', $c->furnishing));
            } elseif ($property->furnishing) {
                $caveats[] = 'Listed as '.str_replace('_', '-', $property->furnishing);
            }
        }

        // Size (20)
        if ($c->minAreaSqft) {
            $max += 20;
            if ($property->area_sqft >= $c->minAreaSqft && (! $c->maxAreaSqft || $property->area_sqft <= $c->maxAreaSqft)) {
                $score += 20;
                $reasons[] = 'Meets your size requirement ('.$property->areaLabel().')';
            } elseif ($property->area_sqft >= $c->minAreaSqft * 0.9) {
                $score += 10;
                $caveats[] = 'Slightly smaller than requested ('.$property->areaLabel().')';
            } else {
                $caveats[] = 'Smaller than requested ('.$property->areaLabel().')';
            }
        }

        // Bedrooms (10)
        if ($c->bedrooms && $property->type->hasRooms()) {
            $max += 10;
            if (($property->bedrooms ?? 0) >= $c->bedrooms) {
                $score += 10;
                $reasons[] = $property->bedrooms.' bedrooms';
            } elseif ($property->bedrooms !== null && $property->bedrooms === $c->bedrooms - 1) {
                $score += 4;
                $caveats[] = 'One bedroom fewer than requested';
            } else {
                $caveats[] = $property->bedrooms === null ? 'Bedroom count not provided' : 'Fewer bedrooms than requested';
            }
        }

        // Bathrooms (5)
        if ($c->bathrooms && $property->type->hasRooms()) {
            $max += 5;
            if (($property->bathrooms ?? 0) >= $c->bathrooms) {
                $score += 5;
                $reasons[] = $property->bathrooms.' bathrooms';
            } else {
                $caveats[] = $property->bathrooms === null ? 'Bathroom count not provided' : 'Fewer bathrooms than requested';
            }
        }

        // Requested features (10)
        if ($c->amenities) {
            $max += 10;
            $has = array_values(array_intersect($c->amenities, $property->amenities ?? []));
            $missing = array_values(array_diff($c->amenities, $has));
            $labels = config('meezan.amenities');
            $score += (int) round(10 * count($has) / count($c->amenities));
            if ($has) {
                $reasons[] = 'Has '.implode(', ', array_map(fn ($k) => strtolower($labels[$k] ?? $k), $has));
            }
            if ($missing) {
                $caveats[] = 'Not listed: '.implode(', ', array_map(fn ($k) => strtolower($labels[$k] ?? $k), $missing));
            }
        }

        // Trust (5)
        $max += 5;
        if ($property->hasVerifiedDealer()) {
            $score += 5;
            $reasons[] = 'Listed by a verified dealer';
        }

        return new PropertyMatch($property, (int) round($score / $max * 100), $reasons, $caveats, $budgetStatus, $inArea, $c);
    }

    /**
     * When nothing matches, explain which hard constraint excluded listings.
     *
     * @return list<string>
     */
    protected function fallbackHints(MatchCriteria $c): array
    {
        $hints = [];

        if ($c->maxPrice) {
            $withoutBudget = clone $c;
            $withoutBudget->maxPrice = null;
            $cheapest = $this->candidates($withoutBudget)->min('price');

            if ($cheapest) {
                $hints[] = 'Matching listings start from '.Format::price((int) $cheapest).'. Consider increasing your budget.';
            }
        }

        if ($c->type) {
            $broader = clone $c;
            $broader->type = null;

            if ($this->candidates($broader)->exists()) {
                $hints[] = 'Other '.strtolower(PropertyType::groupLabel($c->group)).' types are available within these limits.';
            }
        }

        // Area and size only rank results, so the hard limits left are purpose and city: say what exists.
        if ($c->purpose) {
            $other = clone $c;
            $other->purpose = $c->purpose === Purpose::Sale ? Purpose::Rent : Purpose::Sale;
            $other->maxPrice = null;
            $count = $this->candidates($other)->count();
            if ($count) {
                $hints[] = sprintf('%d matching %s %s instead.', $count, $count === 1 ? 'listing is' : 'listings are', $other->purpose === Purpose::Rent ? 'for rent' : 'for sale');
            }
        }

        if (! $hints) {
            $hints[] = 'There are no published listings that fit these requirements yet. Save your requirement and we will match you as new listings are approved.';
        }

        return $hints;
    }
}
