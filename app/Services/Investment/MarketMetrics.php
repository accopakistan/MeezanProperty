<?php

namespace App\Services\Investment;

use App\Models\MarketObservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Calculated metrics from verified observations only. Every comparison
 * uses one comparable series: same kind, price basis, currency, property
 * type and size. When a comparison cannot be made the result is null and
 * the page shows "Insufficient Historical Data"; nothing is filled in.
 */
class MarketMetrics
{
    /**
     * Pick the comparable sale series with the most observations (verified
     * transactions preferred over valuations, valuations over asking prices).
     *
     * @param  Collection<int, MarketObservation>  $sales
     * @return Collection<int, MarketObservation> oldest first
     */
    public static function comparableSeries(Collection $sales): Collection
    {
        $priority = ['transaction' => 3, 'valuation' => 2, 'asking' => 1];

        // FBR values are tax values revised by notification, so they are never a price trend.
        return $sales->reject(fn (MarketObservation $o) => $o->price_basis === 'fbr_tax')
            ->groupBy(fn (MarketObservation $o) => static::seriesKey($o))
            ->sortByDesc(fn (Collection $g) => [$g->count(), $priority[$g->first()->price_basis] ?? 0])
            ->first(default: collect())
            ->sortBy(fn ($o) => $o->observed_on->timestamp)
            ->values();
    }

    public static function seriesKey(MarketObservation $o): string
    {
        return implode('|', [$o->kind, $o->price_basis, $o->currency, $o->property_type, $o->size_sqft === null ? 'any' : (int) round($o->size_sqft)]);
    }

    /**
     * Change between the latest observation and the one closest to N months
     * earlier inside the window, as ((current - historical) / historical) * 100.
     *
     * @param  array{0: int, 1: int}  $window  months back [from, to]
     * @return array{change: float, from: MarketObservation, to: MarketObservation, months: int}|null
     */
    public static function change(Collection $series, array $window, int $target): ?array
    {
        $current = $series->last();
        if (! $current || $series->count() < 2) {
            return null;
        }

        $candidate = $series->filter(function (MarketObservation $o) use ($current, $window) {
            $months = static::monthsBetween($o->observed_on, $current->observed_on);

            return $months >= $window[0] && $months <= $window[1];
        })->sortBy(fn ($o) => abs(static::monthsBetween($o->observed_on, $current->observed_on) - $target))->first();

        if (! $candidate || $candidate->average_amount <= 0) {
            return null;
        }

        return [
            'change' => round(($current->average_amount - $candidate->average_amount) / $candidate->average_amount * 100, 2),
            'from' => $candidate,
            'to' => $current,
            'months' => static::monthsBetween($candidate->observed_on, $current->observed_on),
        ];
    }

    /** Annualised change (% a year) from a change over $months. */
    public static function annualise(float $changePercent, int $months): float
    {
        if ($months <= 0) {
            return 0.0;
        }

        return round((pow(1 + $changePercent / 100, 12 / $months) - 1) * 100, 2);
    }

    /**
     * Estimated gross yield: monthly rent × 12 / price × 100, from a rent and a
     * sale observation of the same type, size and currency within $maxGap months.
     *
     * @return array{yield: float, rent: MarketObservation, sale: MarketObservation}|null
     */
    public static function grossYield(Collection $sales, Collection $rents, int $maxGap): ?array
    {
        $best = null;
        foreach ($rents->sortByDesc(fn ($o) => $o->observed_on->timestamp) as $rent) {
            $sale = $sales->filter(fn (MarketObservation $s) => $s->property_type === $rent->property_type && $s->currency === $rent->currency
                && (int) round((float) $s->size_sqft) === (int) round((float) $rent->size_sqft)
                && static::monthsBetween($s->observed_on, $rent->observed_on, absolute: true) <= $maxGap)
                ->sortByDesc(fn ($o) => $o->observed_on->timestamp)->first();
            if ($sale && $sale->average_amount > 0) {
                $best = ['yield' => static::yieldPercent($rent->average_amount, $sale->average_amount), 'rent' => $rent, 'sale' => $sale];
                break;
            }
        }

        return $best;
    }

    /** Gross yield % = monthly rent × 12 / property value × 100. */
    public static function yieldPercent(float $monthlyRent, float $value): float
    {
        return $value > 0 ? round($monthlyRent * 12 / $value * 100, 2) : 0.0;
    }

    /**
     * Net yield, only when the annual costs are known: (annual rent - costs) / value × 100.
     */
    public static function netYieldPercent(float $monthlyRent, float $annualCosts, float $value): float
    {
        return $value > 0 ? round(($monthlyRent * 12 - $annualCosts) / $value * 100, 2) : 0.0;
    }

    public static function monthsBetween(CarbonInterface $from, CarbonInterface $to, bool $absolute = false): int
    {
        $months = (int) round($from->diffInDays($to, absolute: false) / 30.4375);

        return $absolute ? abs($months) : $months;
    }

    /** Linear 0-100 score over [min, max], clamped. */
    public static function normalise(float $value, float $min, float $max): float
    {
        if ($max <= $min) {
            return 0.0;
        }

        return round(max(0, min(100, ($value - $min) / ($max - $min) * 100)), 1);
    }
}
