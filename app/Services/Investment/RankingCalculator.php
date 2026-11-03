<?php

namespace App\Services\Investment;

use App\Models\Area;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentMethodology;
use App\Models\InvestmentRanking;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\Property;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AreaListingCounts;
use App\Support\Cities;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Runs a recalculation from verified data with the active methodology and
 * stores a ranking snapshot. Runs are never published automatically:
 * an admin reviews a completed run and publishes it, which supersedes the
 * previous publication (kept as history, never rewritten).
 */
class RankingCalculator
{
    /** Authority approval statuses (areas table) -> legal verification level. */
    protected const APPROVAL_TO_LEGAL = [
        'approved' => 'verified', 'final_approved' => 'verified', 'noc_issued' => 'verified',
        'technical_approval' => 'partially_verified', 'layout_plan_approved' => 'partially_verified', 'under_process' => 'partially_verified',
        'noc_not_issued' => 'unverified', 'cancelled' => 'unverified', 'declared_illegal' => 'disputed',
    ];

    public function run(?User $user = null, string $trigger = 'manual'): InvestmentCalculation
    {
        $methodology = InvestmentMethodology::active() ?? throw new RuntimeException('No active methodology.');

        $calc = new InvestmentCalculation;
        $calc->forceFill([
            'methodology_id' => $methodology->id, 'methodology_version' => $methodology->version, 'scope' => 'all',
            'status' => 'running', 'trigger' => $trigger, 'triggered_by' => $user?->id, 'started_at' => now(),
        ])->save();

        try {
            DB::transaction(fn () => $this->calculate($calc, $methodology));
            $calc->forceFill(['status' => 'completed', 'finished_at' => now()])->save();
            AuditLogger::record('investment.calculated', $calc, ['methodology' => $methodology->version, 'trigger' => $trigger] + ($calc->summary ?? []), $user?->id);
        } catch (Throwable $e) {
            report($e);
            $calc->forceFill(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        return $calc->fresh();
    }

    public function publish(InvestmentCalculation $calc, User $user): void
    {
        if ($calc->status !== 'completed') {
            throw new RuntimeException('Only a completed, unpublished calculation can be published.');
        }

        DB::transaction(function () use ($calc, $user) {
            InvestmentCalculation::where('status', 'published')->update(['status' => 'superseded']);
            $calc->forceFill(['status' => 'published', 'published_by' => $user->id, 'published_at' => now()])->save();
        });
        AuditLogger::record('investment.published', $calc, ['methodology' => $calc->methodology_version], $user->id);
    }

    protected function calculate(InvestmentCalculation $calc, InvestmentMethodology $m): void
    {
        $cities = Cities::names();
        $relations = ['source', 'area:id,name,city,approval_status,verification_source_url,verified_on,authority', 'project:id,name,city,status,approval_note'];
        $observations = MarketObservation::verified()->whereIn('city', $cities)->with($relations)->get();
        $indicators = MarketIndicator::verified()->whereIn('city', $cities)->with($relations)->get();

        $entities = $this->entities($observations, $indicators);
        $cityListings = Property::published()->toBase()->groupBy('city')->pluck(DB::raw('count(*)'), 'city')->map(fn ($n) => (int) $n);
        $areaListings = AreaListingCounts::for(collect($entities)->where('type', 'area')->pluck('id')->unique()->values()->all());

        $rows = [];
        foreach ($entities as $entity) {
            $analysis = $this->analyse($entity, $m);
            $analysis['metrics']['platform_listings'] = match ($entity['type']) {
                'city' => $cityListings[$entity['city']] ?? 0,
                'area' => $areaListings[$entity['id']] ?? 0,
                default => null,
            };

            foreach (config('meezan.investment.categories') as $category => $definition) {
                if (isset($definition['asset_class']) && $definition['asset_class'] !== $entity['asset_class']) {
                    continue;
                }
                if (! empty($definition['emerging_only']) && ! $analysis['emerging']) {
                    continue;
                }
                $result = InvestmentScorer::score($analysis['factor_scores'], $m->weightsFor($category), $definition['requires'] ?? [],
                    (float) $m->parameter('min_coverage'), (float) $m->parameter('coverage_penalty'));

                $factors = [];
                foreach ($result['factors'] as $factor => $f) {
                    $factors[$factor] = $f + ['basis' => $analysis['factor_basis'][$factor] ?? 'No verified data.'];
                }

                $rows[] = [
                    'calculation_id' => $calc->id,
                    'methodology_version' => $m->version,
                    'category' => $category,
                    'entity_type' => $entity['type'],
                    'entity_id' => $entity['id'],
                    'entity_name' => $entity['name'],
                    'country' => 'Pakistan',
                    'city' => $entity['city'],
                    'asset_class' => $entity['asset_class'],
                    'status' => $result['status'],
                    'score' => $result['score'],
                    'confidence' => $result['status'] === 'ranked' ? $analysis['confidence']['level'] : 'insufficient',
                    'confidence_score' => $analysis['confidence']['score'],
                    'risk' => $analysis['risk']['level'],
                    'factors' => json_encode(['items' => $factors, 'coverage' => $result['coverage'], 'reason' => $result['reason']]),
                    'metrics' => json_encode($analysis['metrics']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Ranks within each category and asset class, by score, then confidence, then name.
        $ranked = collect($rows)->filter(fn ($r) => $r['status'] === 'ranked')
            ->groupBy(fn ($r) => $r['category'].'|'.$r['asset_class'])
            ->flatMap(fn (Collection $g) => $g->sort(fn ($a, $b) => [$b['score'], $b['confidence_score'], $a['entity_name']] <=> [$a['score'], $a['confidence_score'], $b['entity_name']])
                ->values()->map(fn ($r, $i) => $r + ['rank' => $i + 1]));
        $rows = $ranked->concat(collect($rows)->filter(fn ($r) => $r['status'] !== 'ranked')->map(fn ($r) => $r + ['rank' => null]))->values();

        foreach ($rows->chunk(200) as $chunk) {
            InvestmentRanking::insert($chunk->all());
        }

        $ids = ['observations' => $observations->pluck('id')->sort()->values()->all(), 'indicators' => $indicators->pluck('id')->sort()->values()->all()];
        $calc->forceFill([
            'inputs' => $ids + ['checksum' => hash('sha256', json_encode([
                $observations->map(fn ($o) => [$o->id, $o->updated_at?->toIso8601String()]),
                $indicators->map(fn ($i) => [$i->id, $i->updated_at?->toIso8601String()]),
                $m->version,
            ]))],
            'summary' => [
                'entities' => count($entities),
                'rows' => $rows->count(),
                'ranked' => $rows->where('status', 'ranked')->count(),
                'insufficient' => $rows->where('status', '!=', 'ranked')->count(),
                'observations' => $observations->count(),
                'indicators' => $indicators->count(),
            ],
        ])->save();
    }

    /**
     * Entities (city / area / project) × asset class that have verified data.
     * Indicators without an asset class apply to every asset class of their
     * entity (residential when the entity has no observations).
     *
     * @return list<array{type: string, id: int, name: string, city: string, asset_class: string, observations: Collection, indicators: Collection, model: mixed}>
     */
    protected function entities(Collection $observations, Collection $indicators): array
    {
        $keyOf = fn ($r) => $r->entityType().':'.($r->project_id ?? $r->area_id ?? $this->cityId($r->city));
        $entities = [];
        $make = function ($r, string $asset) use (&$entities, $keyOf) {
            $key = $keyOf($r).':'.$asset;
            $entities[$key] ??= [
                'type' => $r->entityType(),
                'id' => (int) ($r->project_id ?? $r->area_id ?? $this->cityId($r->city)),
                'name' => $r->project?->name ?? $r->area?->name ?? $r->city,
                'city' => $r->city,
                'asset_class' => $asset,
                'observations' => collect(),
                'indicators' => collect(),
                'model' => $r->project ?? $r->area,
            ];

            return $key;
        };

        foreach ($observations as $o) {
            $entities[$make($o, $o->asset_class)]['observations']->push($o);
        }
        foreach ($indicators->whereNotNull('asset_class') as $i) {
            $entities[$make($i, $i->asset_class)]['indicators']->push($i);
        }
        foreach ($indicators->whereNull('asset_class') as $i) {
            $prefix = $keyOf($i).':';
            $keys = array_filter(array_keys($entities), fn ($k) => str_starts_with($k, $prefix));
            foreach ($keys ?: [$make($i, 'residential')] as $key) {
                $entities[$key]['indicators']->push($i);
            }
        }

        return array_values($entities);
    }

    protected function cityId(string $city): int
    {
        static $ids;
        $ids ??= DB::table('cities')->pluck('id', 'name')->all();

        return (int) ($ids[$city] ?? 0);
    }

    /**
     * @return array{factor_scores: array<string, ?float>, factor_basis: array<string, string>, metrics: array, risk: array, confidence: array, emerging: bool}
     */
    protected function analyse(array $entity, InvestmentMethodology $m): array
    {
        $p = fn (string $k) => $m->parameter($k);
        $sales = $entity['observations']->where('kind', 'sale');
        $rents = $entity['observations']->where('kind', 'rent');
        $series = MarketMetrics::comparableSeries($sales);

        $one = MarketMetrics::change($series, $p('one_year_window'), 12);
        $two = MarketMetrics::change($series, $p('two_year_window'), 24);
        $annualised = $one ? MarketMetrics::annualise($one['change'], $one['months']) : ($two ? MarketMetrics::annualise($two['change'], $two['months']) : null);
        $yield = MarketMetrics::grossYield($sales, $rents, (int) $p('yield_max_gap_months'));

        // Latest verified indicator of each kind.
        $latest = $entity['indicators']->sortByDesc(fn ($i) => $i->observed_on->timestamp)->unique('indicator')->keyBy('indicator');
        $ind = $latest->map(fn ($i) => ['level' => $i->level, 'value' => $i->numeric_value])->all();
        $levelScore = fn (string $kind) => isset($ind[$kind]['level']) ? (config("meezan.investment.indicators.$kind.levels")[$ind[$kind]['level']] ?? null) : null;

        $legal = $this->legalStatus($entity, $latest);
        $risk = RiskAssessor::assess($ind, GrowthScenarios::periodRates($series), $legal['status'], (int) $p('risk_min_components'), $p('risk_levels'));

        $demand = $levelScore('demand');
        $supply = $levelScore('supply');
        $ds = array_values(array_filter([$demand, $supply], fn ($v) => $v !== null));
        $range = $p('price_change_range');
        $yieldRange = $p('yield_range');

        $scores = [
            'price_performance' => $annualised === null ? null : MarketMetrics::normalise($annualised, $range[0], $range[1]),
            'demand_supply' => $ds ? round(array_sum($ds) / count($ds), 1) : null,
            'rental_yield' => $yield ? MarketMetrics::normalise($yield['yield'], $yieldRange[0], $yieldRange[1]) : null,
            'location_infrastructure' => $levelScore('infrastructure'),
            'development_progress' => isset($ind['development_progress']['value']) ? round(max(0, min(100, $ind['development_progress']['value'])), 1) : null,
            'liquidity' => $levelScore('liquidity'),
            'risk' => $risk['score'] === null ? null : round(100 - $risk['score'], 1),
            'growth' => $levelScore('growth_drivers'),
        ];
        $basis = [
            'price_performance' => $annualised === null ? 'Insufficient Historical Data.' : 'Annualised change '.$annualised.'% a year (scored on '.$range[0].'% to '.$range[1].'%).',
            'demand_supply' => $ds ? 'Demand: '.($ind['demand']['level'] ?? 'no data').'; supply: '.($ind['supply']['level'] ?? 'no data').'.' : 'No verified demand or supply indicator.',
            'rental_yield' => $yield ? 'Estimated gross yield '.$yield['yield'].'% (scored on '.$yieldRange[0].'% to '.$yieldRange[1].'%).' : 'No matching verified rent and sale observations.',
            'location_infrastructure' => isset($ind['infrastructure']) ? 'Infrastructure: '.$ind['infrastructure']['level'].'.' : 'No verified infrastructure indicator.',
            'development_progress' => isset($ind['development_progress']) ? 'Development progress '.$ind['development_progress']['value'].'%.' : 'No verified development progress record.',
            'liquidity' => isset($ind['liquidity']) ? 'Liquidity: '.$ind['liquidity']['level'].'.' : 'No verified liquidity indicator.',
            'risk' => $risk['score'] === null ? 'Not enough evidence to assess risk.' : 'Risk score '.$risk['score'].' ('.$risk['level'].'); factor = 100 − risk.',
            'growth' => isset($ind['growth_drivers']) ? 'Growth drivers: '.$ind['growth_drivers']['level'].'.' : 'No verified growth indicator.',
        ];

        $overall = $m->weightsFor('overall');
        $coverage = array_sum(array_map(fn ($f) => $scores[$f] === null ? 0 : $overall[$f], array_keys($overall))) / max(1, array_sum($overall));

        $usedObs = $series->concat($yield ? [$yield['rent'], $yield['sale']] : [])->unique('id')->values();
        $confidence = ConfidenceAssessor::assess($usedObs, $latest->values(), $coverage, [
            'confidence_weights' => $p('confidence_weights'), 'confidence_full_observations' => $p('confidence_full_observations'),
            'confidence_full_sources' => $p('confidence_full_sources'), 'confidence_levels' => $p('confidence_levels'),
        ]);

        $project = $entity['type'] === 'project' ? $entity['model'] : null;
        $emerging = ($project && in_array($project->status, ['upcoming', 'under_construction'], true))
            || (isset($ind['development_progress']['value']) && $ind['development_progress']['value'] < 100)
            || in_array($ind['developer_status']['level'] ?? null, ['on_track', 'delayed'], true);

        $obsInfo = fn ($o) => $o ? [
            'amount' => $o->average_amount, 'label' => $o->amountLabel(), 'date' => $o->observed_on->toDateString(), 'sample' => $o->sample_size,
            'basis' => $o->price_basis, 'basis_label' => $o->basisLabel(), 'type' => $o->property_type, 'size' => $o->sizeLabel(), 'currency' => $o->currency,
            'source' => $o->source?->name, 'min' => $o->min_amount, 'max' => $o->max_amount,
        ] : null;
        $change = fn ($c) => $c ? ['percent' => $c['change'], 'months' => $c['months'], 'from' => $obsInfo($c['from']), 'to' => $obsInfo($c['to'])] : null;

        $sources = $usedObs->map->source->concat($latest->map->source)->filter()->unique('id')->map(fn ($s) => [
            'name' => $s->name, 'type' => $s->typeLabel(), 'url' => $s->url, 'license' => $s->license, 'reliability' => $s->reliability,
            'last_checked' => $s->last_checked_on?->toDateString(),
        ])->values()->all();

        return [
            'factor_scores' => $scores,
            'factor_basis' => $basis,
            'emerging' => $emerging,
            'risk' => $risk,
            'confidence' => $confidence,
            'metrics' => [
                'current' => $obsInfo($series->last()),
                'series' => $series->map(fn ($o) => ['date' => $o->observed_on->toDateString(), 'amount' => $o->average_amount, 'sample' => $o->sample_size, 'basis' => $o->price_basis])->all(),
                'change_1y' => $change($one),
                'change_2y' => $change($two),
                'annualised' => $annualised,
                'yield' => $yield ? ['gross' => $yield['yield'], 'rent' => $obsInfo($yield['rent']), 'sale' => $obsInfo($yield['sale'])] : null,
                'indicators' => $latest->map(fn ($i) => [
                    'label' => $i->indicatorLabel(), 'level' => $i->level, 'value' => $i->numeric_value, 'display' => $i->valueLabel(),
                    'date' => $i->observed_on->toDateString(), 'source' => $i->source?->name, 'methodology' => $i->methodology,
                ])->all(),
                'legal' => $legal,
                'risk' => $risk,
                'confidence' => $confidence,
                'scenarios' => GrowthScenarios::from($series, [
                    'scenario_min_months' => $p('scenario_min_months'), 'scenario_min_observations' => $p('scenario_min_observations'), 'scenario_years' => $p('scenario_years'),
                ]),
                'sources' => $sources,
                'emerging' => $emerging,
                'project_status' => $project?->status,
            ],
        ];
    }

    /** @return array{status: string, basis: string, detail: ?string, url: ?string, date: ?string} */
    protected function legalStatus(array $entity, Collection $latest): array
    {
        if ($i = $latest->get('legal_verification')) {
            return ['status' => $i->level, 'basis' => 'Verified record in the Data Centre', 'detail' => $i->methodology, 'url' => $i->source?->url, 'date' => $i->observed_on->toDateString()];
        }
        $area = $entity['type'] === 'area' ? $entity['model'] : null;
        if ($area instanceof Area && isset(self::APPROVAL_TO_LEGAL[$area->approval_status])) {
            return [
                'status' => self::APPROVAL_TO_LEGAL[$area->approval_status],
                'basis' => 'Location record: '.(Area::APPROVAL_STATUSES[$area->approval_status] ?? $area->approval_status).($area->authority ? ' ('.$area->authority.')' : ''),
                'detail' => null, 'url' => $area->verification_source_url, 'date' => $area->verified_on?->toDateString(),
            ];
        }

        return ['status' => 'unknown', 'basis' => 'No verified legal or approval record on file', 'detail' => null, 'url' => null, 'date' => null];
    }
}
