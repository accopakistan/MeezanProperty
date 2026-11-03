<?php

namespace App\Services\Investment;

use App\Models\MarketObservation;
use Illuminate\Support\Collection;

/**
 * Forward-looking scenarios from a comparable historical price series.
 * Not forecasts: each scenario repeats a historical annual rate for the
 * horizon. Needs at least `scenario_min_months` of history and
 * `scenario_min_observations` observations; otherwise no numbers at all.
 */
class GrowthScenarios
{
    /**
     * @param  Collection<int, MarketObservation>  $series  comparable, oldest first
     * @return array{available: bool, reason?: string, years?: int, rates?: array, ranges?: array, assumptions?: list<string>, period?: string}
     */
    public static function from(Collection $series, array $params): array
    {
        $minMonths = (int) $params['scenario_min_months'];
        $minObs = (int) $params['scenario_min_observations'];
        $years = (int) $params['scenario_years'];

        if ($series->count() < $minObs) {
            return ['available' => false, 'reason' => "At least {$minObs} comparable observations are needed; {$series->count()} available."];
        }
        $first = $series->first();
        $last = $series->last();
        $span = MarketMetrics::monthsBetween($first->observed_on, $last->observed_on);
        if ($span < $minMonths) {
            return ['available' => false, 'reason' => "At least {$minMonths} months of comparable history are needed; {$span} available."];
        }

        $rates = static::periodRates($series);
        if (count($rates) < 2) {
            return ['available' => false, 'reason' => 'Not enough comparable periods (6 months or longer) to measure a range.'];
        }

        $expected = MarketMetrics::annualise(($last->average_amount - $first->average_amount) / $first->average_amount * 100, $span);
        $scenarios = ['conservative' => min($rates), 'expected' => $expected, 'high' => max($rates)];
        $ranges = array_map(fn ($r) => (int) round($last->average_amount * pow(1 + $r / 100, $years), -3), $scenarios);

        return [
            'available' => true,
            'years' => $years,
            'period' => $first->observed_on->format('M Y').' – '.$last->observed_on->format('M Y'),
            'rates' => array_map(fn ($r) => round($r, 2), $scenarios),
            'ranges' => $ranges,
            'base' => $last->average_amount,
            'assumptions' => [
                'Conservative repeats the weakest annualised rate seen between comparable observations.',
                'Expected repeats the average annual rate over the whole period ('.$span.' months).',
                'High growth repeats the strongest annualised rate seen.',
                'Same property type, size, currency and price basis ('.$last->price_basis.') throughout; no inflation adjustment.',
                'These are illustrations of past rates continuing, not predictions or guarantees.',
            ],
        ];
    }

    /** @return list<float> annualised % change between consecutive observations at least 6 months apart */
    public static function periodRates(Collection $series): array
    {
        $rates = [];
        $anchor = $series->first();
        foreach ($series->slice(1) as $o) {
            $months = MarketMetrics::monthsBetween($anchor->observed_on, $o->observed_on);
            if ($months < 6 || $anchor->average_amount <= 0) {
                continue;
            }
            $rates[] = MarketMetrics::annualise(($o->average_amount - $anchor->average_amount) / $anchor->average_amount * 100, $months);
            $anchor = $o;
        }

        return $rates;
    }
}
