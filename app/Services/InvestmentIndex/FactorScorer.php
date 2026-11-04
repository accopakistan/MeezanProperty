<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Services\Investment\MarketMetrics;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Scores one factor (0-100) for one project from verified records only.
 * Nothing is estimated: without usable evidence the factor is "missing"
 * with the reason. Asking prices never count as prices paid, and a rental
 * yield needs a verified rent and a verified transaction or valuation.
 *
 * A result: score, value (shown), basis (how it was found), missing
 * (why not scored), evidence/observation ids, and "used" (source + date of
 * each record, for the Data Confidence Score; not stored).
 */
class FactorScorer
{
    /** Price bases that are prices actually paid or professionally valued. */
    public const PRICE_BASES = ['transaction', 'valuation'];

    public function __construct(protected InvestmentIndexMethodology $methodology, protected CarbonInterface $today) {}

    /**
     * @param  Collection<int, InvestmentEvidence>  $evidence  the project's verified evidence (with source)
     * @param  Collection<int, MarketObservation>  $observations  the project's verified, non-demo market observations (with source)
     * @param  Collection<int, array{level: string, project: string}>  $developerDeliveries  latest delivery status of the developer's other projects
     */
    public function score(string $factor, Collection $evidence, Collection $observations, Collection $developerDeliveries): array
    {
        $definition = config("investment_index.factors.$factor");

        return match ($definition['type']) {
            'percent' => $this->percent($factor, $evidence),
            'level' => $this->level($factor, $definition, $evidence),
            'yield' => $this->yield($factor, $evidence, $observations),
            'history' => $this->history($observations),
            'developer' => $this->developer($developerDeliveries),
        };
    }

    /** The project's usable verified evidence for a factor: accepted source types, not too old, newest first. */
    public function usable(string $factor, Collection $evidence): Collection
    {
        $types = config("investment_index.factors.$factor.sources") ?? config("investment_index.context_evidence.$factor.sources") ?? [];
        $oldest = $this->today->copy()->subMonths((int) $this->methodology->parameter('max_age_months'));

        return $evidence->where('factor', $factor)
            ->filter(fn ($e) => $e->source && in_array($e->source->type, $types, true) && $e->observed_on->gte($oldest) && $e->observed_on->lte($this->today))
            ->sortByDesc(fn ($e) => [$e->observed_on->timestamp, $e->id])->values();
    }

    protected function percent(string $factor, Collection $evidence): array
    {
        $records = $this->usable($factor, $evidence)->filter(fn ($e) => $e->value_numeric !== null);
        if ($records->isEmpty()) {
            return $this->missing('No verified figure on file.');
        }
        $latest = $records->first();
        $value = max(0, min(100, $latest->value_numeric));

        return $this->result(round($value, 1), rtrim(rtrim(number_format($value, 1), '0'), '.').'%', $this->describe($latest), collect([$latest]),
            $this->corroborated($records, fn ($a, $b) => abs($a->value_numeric - $b->value_numeric) <= 10), $value);
    }

    protected function level(string $factor, array $definition, Collection $evidence): array
    {
        $records = $this->usable($factor, $evidence)->filter(fn ($e) => isset($definition['levels'][$e->value_level]));
        if ($records->isEmpty()) {
            return $this->missing('No verified assessment on file.');
        }
        // Per-subject factors (utilities) use the newest record for each subject.
        $latest = ($definition['per_subject'] ?? false) ? $records->groupBy(fn ($e) => mb_strtolower((string) $e->subject))->map->first()->values() : collect([$records->first()]);
        $score = round($latest->avg(fn ($e) => $definition['levels'][$e->value_level]), 1);
        $value = $latest->map(fn ($e) => ($e->subject ? ucfirst($e->subject).': ' : '').str_replace('_', ' ', $e->value_level))->implode(', ');

        $result = $this->result($score, ucfirst($value), $latest->map(fn ($e) => $this->describe($e))->implode(' '), $latest,
            $this->corroborated($records, fn ($a, $b) => $a->value_level === $b->value_level && $a->subject === $b->subject));

        return $result + ['level' => $latest->count() === 1 ? $latest->first()->value_level : null];
    }

    /** The newest usable level of a context kind (delivery_status, operational_status), or null. */
    public function contextLevel(string $kind, Collection $evidence): ?string
    {
        $levels = config("investment_index.context_evidence.$kind.levels");

        return $this->usable($kind, $evidence)->first(fn ($e) => isset($levels[$e->value_level]))?->value_level;
    }

    protected function yield(string $factor, Collection $evidence, Collection $observations): array
    {
        [$low, $high] = $this->methodology->parameter('yield_range');
        $records = $this->usable($factor, $evidence)->filter(fn ($e) => $e->value_numeric !== null && $e->value_numeric > 0);
        if ($records->isNotEmpty()) {
            $latest = $records->first();
            $yield = round($latest->value_numeric, 2);

            return $this->result($this->scale($yield, $low, $high), $yield.'% gross', $this->describe($latest), collect([$latest]),
                $this->corroborated($records, fn ($a, $b) => abs($a->value_numeric - $b->value_numeric) <= 1), $yield);
        }

        // From market records: a verified rent and a verified price paid/valued for the same unit type, close in time.
        $maxGap = (int) $this->methodology->parameter('yield_max_gap_months');
        $sales = $observations->where('kind', 'sale')->whereIn('price_basis', self::PRICE_BASES);
        // Rents too must be agreed (lease) or valued, never asking rents.
        $pairs = $observations->where('kind', 'rent')->whereIn('price_basis', self::PRICE_BASES)->map(function ($rent) use ($sales, $maxGap) {
            $sale = $sales->filter(fn ($s) => $s->property_type === $rent->property_type && $s->currency === $rent->currency
                && (int) round((float) $s->size_sqft) === (int) round((float) $rent->size_sqft) && abs($s->observed_on->diffInMonths($rent->observed_on)) <= $maxGap)
                ->sortBy(fn ($s) => abs($s->observed_on->diffInDays($rent->observed_on)))->first();

            return $sale && $sale->average_amount > 0 ? ['rent' => $rent, 'sale' => $sale, 'yield' => $rent->average_amount * 12 / $sale->average_amount * 100] : null;
        })->filter()->values();
        if ($pairs->isEmpty()) {
            return $this->missing('No verified rental income on file.');
        }
        $yield = round($pairs->avg('yield'), 2);
        $used = $pairs->flatMap(fn ($p) => [$p['rent'], $p['sale']]);

        return $this->result($this->scale($yield, $low, $high), $yield.'% gross', 'From '.$pairs->count().' verified rent and price '.($pairs->count() === 1 ? 'pair' : 'pairs').'.',
            collect(), false, $yield, $used);
    }

    protected function history(Collection $observations): array
    {
        $minMonths = (int) $this->methodology->parameter('history_min_months');
        $maxMonths = (int) $this->methodology->parameter('max_age_months') + 24;
        $series = $observations->where('kind', 'sale')->whereIn('price_basis', self::PRICE_BASES)
            ->groupBy(fn ($o) => MarketMetrics::seriesKey($o))
            ->map(function ($g) use ($minMonths, $maxMonths) {
                $g = $g->sortBy(fn ($o) => $o->observed_on->timestamp)->values();
                $last = $g->last();
                $first = $g->first(fn ($o) => $o->observed_on->diffInMonths($last->observed_on) <= $maxMonths);
                $months = $first ? $first->observed_on->diffInMonths($last->observed_on) : 0;
                if (! $first || $months < $minMonths || $first->average_amount <= 0) {
                    return null;
                }

                return ['change' => ((($last->average_amount / $first->average_amount) ** (12 / $months)) - 1) * 100, 'used' => [$first, $last], 'months' => $months];
            })->filter();
        if ($series->isEmpty()) {
            return $this->missing('Fewer than two verified transaction or valuation prices at least '.$minMonths.' months apart.');
        }
        [$low, $high] = $this->methodology->parameter('price_change_range');
        $change = round($series->avg('change'), 1);

        return $this->result($this->scale($change, $low, $high), ($change >= 0 ? '+' : '').$change.'% a year',
            'Annualised change in verified transaction/valuation prices over '.$series->count().' '.($series->count() === 1 ? 'series' : 'series').' (asking prices excluded).',
            collect(), $series->count() > 1, $change, $series->flatMap->used);
    }

    protected function developer(Collection $deliveries): array
    {
        if ($deliveries->isEmpty()) {
            return $this->missing('No verified delivery record for the developer\'s other projects.');
        }
        $levels = config('investment_index.context_evidence.delivery_status.levels');
        $score = round($deliveries->avg(fn ($d) => $levels[$d['level']]), 1);
        $n = $deliveries->count();

        return $this->result($score, $n.' '.($n === 1 ? 'project' : 'projects').' on record',
            $deliveries->map(fn ($d) => $d['project'].': '.str_replace('_', ' ', $d['level']))->implode('; ').'.',
            collect(), $n > 1, null, $deliveries->pluck('record'));
    }

    protected function scale(float $value, float $low, float $high): float
    {
        return round(max(0, min(100, ($value - $low) / max(0.0001, $high - $low) * 100)), 1);
    }

    /** Two or more independent publishers agree within the newest fresh window. */
    protected function corroborated(Collection $records, callable $agrees): bool
    {
        $fresh = $this->today->copy()->subMonths((int) $this->methodology->parameter('fresh_months'));
        $latest = $records->first();
        $publishers = $records->filter(fn ($e) => $e->observed_on->gte($fresh) && $agrees($latest, $e))
            ->map(fn ($e) => $e->source->independenceKey())->unique();

        return $publishers->count() >= 2;
    }

    protected function describe(InvestmentEvidence $e): string
    {
        return ($e->source?->name ?? 'Source').', '.$e->observed_on->format('j M Y').'.';
    }

    protected function missing(string $reason): array
    {
        return ['score' => null, 'value' => null, 'basis' => null, 'missing' => $reason, 'evidence_ids' => [], 'observation_ids' => [], 'observed_on' => null,
            'corroborated' => false, 'raw' => null, 'used' => collect()];
    }

    /** @param  Collection<int, InvestmentEvidence>  $evidence  @param  ?Collection  $other  market observations or legal/delivery records used */
    protected function result(float $score, string $value, string $basis, Collection $evidence, bool $corroborated, ?float $raw = null, ?Collection $other = null): array
    {
        $records = $evidence->concat($other ?? []);

        return ['score' => $score, 'value' => $value, 'basis' => $basis, 'missing' => null,
            'evidence_ids' => $evidence->pluck('id')->values()->all(),
            'observation_ids' => ($other ?? collect())->filter(fn ($r) => $r instanceof MarketObservation)->pluck('id')->values()->all(),
            'observed_on' => $records->max(fn ($r) => static::dateOf($r)?->toDateString()),
            'corroborated' => $corroborated, 'raw' => $raw,
            'used' => $records->map(fn ($r) => ['source' => $r->source instanceof MarketSource ? $r->source : null, 'date' => static::dateOf($r), 'verified_at' => $r->verified_at ?? null])->values()];
    }

    public static function dateOf(object $record): ?CarbonInterface
    {
        return $record->observed_on ?? ($record instanceof InvestmentLegalRecord ? $record->effectiveDate() : null);
    }
}
