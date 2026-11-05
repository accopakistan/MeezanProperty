<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketObservation;
use App\Models\Project;
use App\Services\InvestmentIndex\Collection\SchemeNames;

/**
 * Checks the real-world ranking data for problems a reader should never meet:
 * projects without a source, scores or legal statuses without evidence, prices
 * without dates or units, duplicates, stale records and wrong categories. It
 * reads only, and looks at real records only: demo rows are never in scope.
 */
class DataValidator
{
    /**
     * @return array<string, array{label: string, severity: string, count: int, examples: list<string>}>
     */
    public function report(): array
    {
        $real = fn () => Project::where('is_demo', false);
        $run = InvestmentIndexRun::latestPublished();
        $maxAge = (int) config('investment_index.parameters.max_age_months');
        $marketReal = fn () => MarketObservation::where('is_demo', false);
        $map = IndexEngine::categoryMap();

        $checks = [
            'projects_without_sources' => ['Projects with no source of existence', 'error',
                $real()->where(fn ($q) => $q->whereNull('discovery_source_id')->orWhereNull('discovery_url'))->pluck('name')],
            'invalid_categories' => ['Projects outside the five categories', 'error',
                $real()->whereNotIn('category', array_keys($map))->pluck('name')],
            'duplicate_projects' => ['Projects with the same normalised name, city and category', 'warning', $this->duplicates()],
            'scores_without_evidence' => ['Ranked scores with no verified evidence behind them', 'error',
                $run ? $run->scores()->where('status', 'ranked')->with('project:id,name')->get()->filter(fn ($s) => ! InvestmentEvidence::verified()->where('project_id', $s->project_id)->exists()
                    && ! InvestmentLegalRecord::verified()->where('project_id', $s->project_id)->exists())->map(fn ($s) => $s->project?->name)->values() : collect()],
            'demo_in_published_run' => ['Demo projects in the published run', 'error',
                $run ? $run->scores()->whereHas('project', fn ($q) => $q->where('is_demo', true))->with('project:id,name')->get()->map(fn ($s) => $s->project?->name) : collect()],
            'legal_without_authority' => ['Verified legal records with no authority or source address', 'error',
                InvestmentLegalRecord::verified()->where(fn ($q) => $q->whereNull('authority')->orWhere('authority', '')->orWhereNull('source_url'))->with('project:id,name')->get()->map(fn ($l) => $l->project?->name)],
            'legal_from_unofficial_source' => ['Verified legal records whose source is not official', 'error',
                InvestmentLegalRecord::verified()->whereHas('source', fn ($q) => $q->where('type', '!=', 'official_record'))->with('project:id,name')->get()->map(fn ($l) => $l->project?->name)],
            'observations_without_dates' => ['Price records with no date', 'error',
                $marketReal()->whereNull('observed_on')->pluck('id')->map(fn ($id) => 'Observation #'.$id)],
            'prices_without_units' => ['Price records with no unit or size', 'error',
                $marketReal()->where(fn ($q) => $q->whereNull('size_unit')->orWhereNull('size_value'))->pluck('id')->map(fn ($id) => 'Observation #'.$id)],
            'asking_as_transaction' => ['Transaction prices from an asking-price source', 'error',
                $marketReal()->where('price_basis', 'transaction')->whereHas('source', fn ($q) => $q->where('type', 'public_asking_prices'))->pluck('id')->map(fn ($id) => 'Observation #'.$id)],
            'stale_evidence' => ['Verified evidence older than the '.$maxAge.'-month limit', 'warning',
                InvestmentEvidence::verified()->where('observed_on', '<', now()->subMonths($maxAge))->with('project:id,name')->get()->map(fn ($e) => $e->project?->name)],
            'awaiting_review' => ['Records waiting for a person to check them', 'info',
                InvestmentEvidence::where('verification_status', 'unverified')->with('project:id,name')->get()->map(fn ($e) => $e->project?->name)
                    ->concat(InvestmentLegalRecord::where('verification_status', 'unverified')->with('project:id,name')->get()->map(fn ($l) => $l->project?->name))],
            'projects_without_legal_evidence' => ['Real projects with no verified legal record (shown as Unknown / Unverified)', 'info',
                $real()->whereNotIn('id', InvestmentLegalRecord::verified()->select('project_id'))->pluck('name')],
        ];

        return collect($checks)->map(fn ($c) => ['label' => $c[0], 'severity' => $c[1], 'count' => count($c[2]), 'examples' => collect($c[2])->filter()->unique()->take(8)->values()->all()])->all();
    }

    /** Errors and warnings; "info" lines describe work to do, not faults. */
    public function problemCount(): int
    {
        return collect($this->report())->whereIn('severity', ['error', 'warning'])->sum('count');
    }

    /** @return list<string> */
    protected function duplicates(): array
    {
        return Project::where('is_demo', false)->get(['id', 'name', 'city', 'category'])
            ->groupBy(fn ($p) => $p->city.'|'.$p->category.'|'.SchemeNames::normalize($p->name))
            ->filter(fn ($g) => $g->count() > 1)->map(fn ($g) => $g->first()->name.' ('.$g->first()->city.')')->values()->all();
    }
}
