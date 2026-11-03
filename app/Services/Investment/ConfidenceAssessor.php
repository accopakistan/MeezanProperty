<?php

namespace App\Services\Investment;

use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use Illuminate\Support\Collection;

/**
 * Data confidence (0-100 and a level), separate from the investment score:
 * how many observations, how recent, how reliable and independent the
 * sources are, how consistent the figures are and how much of the scoring
 * weight has data. Each component is explained in plain words.
 */
class ConfidenceAssessor
{
    /**
     * @param  Collection<int, MarketObservation>  $observations  observations used
     * @param  Collection<int, MarketIndicator>  $indicators  indicators used
     * @param  float  $coverage  share (0-1) of the overall weight that has data
     * @return array{level: string, score: float, components: array<string, float>, reasons: list<string>}
     */
    public static function assess(Collection $observations, Collection $indicators, float $coverage, array $params): array
    {
        $records = $observations->concat($indicators);
        if ($records->isEmpty()) {
            return ['level' => 'insufficient', 'score' => 0.0, 'components' => [], 'reasons' => ['No verified data is on file.']];
        }

        $weights = $params['confidence_weights'];
        $full = max(1, (int) $params['confidence_full_observations']);
        $fullSources = max(1, (int) $params['confidence_full_sources']);
        $reasons = [];

        $count = $observations->count();
        $obs = min(1, $count / $full);
        $reasons[] = "{$count} verified price/rent ".($count === 1 ? 'observation' : 'observations')." ({$full} or more for full credit).";

        $newest = $records->max(fn ($r) => $r->observed_on->timestamp);
        $ageMonths = max(0, (int) round((now()->timestamp - $newest) / (86400 * 30.4375)));
        $recency = $ageMonths <= 6 ? 1.0 : max(0, 1 - ($ageMonths - 6) / 18);
        $reasons[] = "Newest data is {$ageMonths} ".($ageMonths === 1 ? 'month' : 'months').' old.';

        $sources = $records->map->source->filter()->unique('id');
        $quality = $sources->isEmpty() ? 0 : $records->avg(fn ($r) => $r->source?->reliabilityWeight() ?? 0);
        $independent = $sources->map->independenceKey()->unique()->count();
        $independence = min(1, $independent / $fullSources);
        $reasons[] = "{$independent} independent ".($independent === 1 ? 'source' : 'sources').'; average source reliability '.round($quality * 100).'%.';
        if ($observations->contains('price_basis', 'asking') && ! $observations->contains('price_basis', 'transaction')) {
            $reasons[] = 'Prices are asking prices, not completed transactions.';
        }

        $consistency = static::consistency($observations);
        $reasons[] = $consistency === null ? 'Consistency could not be checked (no price range or repeated observations).' : 'Price consistency '.round($consistency * 100).'%.';

        $reasons[] = 'Data covers '.round($coverage * 100).'% of the scoring weight.';

        $components = [
            'observations' => $obs,
            'recency' => $recency,
            'source_quality' => $quality,
            'independence' => $independence,
            'consistency' => $consistency ?? 0.0,
            'coverage' => max(0, min(1, $coverage)),
        ];
        $score = round(array_sum(array_map(fn ($k) => ($weights[$k] ?? 0) * $components[$k], array_keys($components))), 1);
        $levels = $params['confidence_levels'];
        $level = $score >= $levels['high'] ? 'high' : ($score >= $levels['medium'] ? 'medium' : ($score >= $levels['limited'] ? 'limited' : 'insufficient'));

        return ['level' => $level, 'score' => $score, 'components' => array_map(fn ($v) => round($v, 3), $components), 'reasons' => $reasons];
    }

    /** 1 = tight agreement, 0 = wide spread; null when it cannot be measured. */
    protected static function consistency(Collection $observations): ?float
    {
        $spreads = $observations->filter(fn ($o) => $o->min_amount && $o->max_amount && $o->average_amount > 0)
            ->map(fn ($o) => ($o->max_amount - $o->min_amount) / $o->average_amount);
        if ($spreads->isNotEmpty()) {
            // A range of ±30% around the average or more counts as fully inconsistent.
            return round(max(0, 1 - $spreads->avg() / 0.6), 3);
        }

        $groups = $observations->groupBy(fn ($o) => MarketMetrics::seriesKey($o).'|'.$o->observed_on->format('Y-m'))->filter(fn ($g) => $g->count() > 1);
        if ($groups->isEmpty()) {
            return null;
        }
        $cv = $groups->map(function ($g) {
            $values = $g->pluck('average_amount');
            $mean = $values->avg();
            $sd = sqrt($values->map(fn ($v) => ($v - $mean) ** 2)->sum() / max(1, $values->count() - 1));

            return $mean > 0 ? $sd / $mean : 1;
        })->avg();

        return round(max(0, 1 - $cv / 0.3), 3);
    }
}
