<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketObservation;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs the Meezan Investment Index without human steps: assesses every
 * real, published project in the five categories from verified evidence,
 * ranks each category, builds the Overall Index, and publishes the result
 * at once (the previous run is kept as history). A run whose results equal
 * the published ones is recorded as "unchanged" and publishes nothing.
 *
 * Never read: demo records, asking prices, featured flags, packages or
 * subscriptions. There is no paid or sponsored placement.
 */
class IndexEngine
{
    public function run(string $trigger = 'manual', ?User $user = null): InvestmentIndexRun
    {
        $m = InvestmentIndexMethodology::current();
        $run = new InvestmentIndexRun;
        $run->forceFill(['methodology_id' => $m->id, 'methodology_version' => $m->version, 'trigger' => $trigger, 'status' => 'running',
            'triggered_by' => $user?->id, 'started_at' => now()])->save();

        try {
            $rows = $this->withResearchState($this->rank($this->assessAll($m), $m), $m);
            $hash = hash('sha256', json_encode([$m->version, $rows]));
            $previous = InvestmentIndexRun::latestPublished();
            $summary = $this->summary($rows);

            if ($previous && $previous->inputs_hash === $hash) {
                $run->forceFill(['status' => 'unchanged', 'inputs_hash' => $hash, 'summary' => $summary, 'finished_at' => now()])->save();

                return $run;
            }

            DB::transaction(function () use ($run, $rows, $previous, $hash, $summary) {
                $now = now();
                foreach (array_chunk($rows, 200) as $chunk) {
                    InvestmentIndexScore::insert(array_map(fn ($r) => array_merge($r, [
                        'run_id' => $run->id, 'created_at' => $now, 'updated_at' => $now,
                        'factors' => json_encode($r['factors']), 'reasons' => json_encode($r['reasons']),
                        'risks' => json_encode($r['risks']), 'source_ids' => json_encode($r['source_ids']),
                    ]), $chunk));
                }
                $changes = ChangeHistory::record($run, $previous, $rows);
                InvestmentIndexRun::where('status', 'published')->whereKeyNot($run->id)->update(['status' => 'superseded']);
                $run->forceFill(['status' => 'published', 'inputs_hash' => $hash, 'summary' => $summary + ['changes' => $changes],
                    'finished_at' => $now, 'published_at' => $now])->save();
            });
            AuditLogger::record('investment_index.published', $run, ['trigger' => $trigger, 'methodology' => $m->version] + $summary, $user?->id);
        } catch (Throwable $e) {
            report($e);
            $run->forceFill(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        return $run->fresh();
    }

    /** Project::category => index category. */
    public static function categoryMap(): array
    {
        $map = [];
        foreach (config('investment_index.categories') as $key => $category) {
            foreach ($category['project_categories'] as $projectCategory) {
                $map[$projectCategory] = $key;
            }
        }

        return $map;
    }

    /** @return list<array> one unranked row per project */
    protected function assessAll(InvestmentIndexMethodology $m): array
    {
        $today = now()->startOfDay();
        $map = static::categoryMap();
        $projects = Project::where('is_demo', false)->where('is_published', true)->whereIn('category', array_keys($map))->orderBy('id')->get();
        if ($projects->isEmpty()) {
            return [];
        }
        $ids = $projects->pluck('id');
        $evidence = InvestmentEvidence::verified()->whereIn('project_id', $ids)->with('source')->get()->groupBy('project_id');
        $legal = InvestmentLegalRecord::verified()->whereIn('project_id', $ids)->with('source')->get()->groupBy('project_id');
        $observations = MarketObservation::verified()->where('is_demo', false)->whereIn('project_id', $ids)->with('source')->get()
            ->filter(fn ($o) => $o->source && $o->source->is_active && ! $o->source->is_demo && ! in_array($o->source->type, InvestmentEvidence::UNTRUSTED_SOURCE_TYPES, true))
            ->groupBy('project_id');
        $deliveries = $this->deliveriesByDeveloper();

        $assessor = new ProjectAssessor($m, $today);

        return $projects->map(fn (Project $p) => array_diff_key($assessor->assess($p, $map[$p->category],
            $evidence->get($p->id, collect()), $legal->get($p->id, collect()), $observations->get($p->id, collect()),
            ($deliveries[static::developerKey($p->developer)] ?? collect())->where('project_id', '!=', $p->id)->values()), ['_computed_score' => 1, '_scored_factors' => 1]))->all();
    }

    /**
     * How much is on file for each project, and a separate provisional score
     * that also counts records awaiting a staff check. A project with a verified
     * rank, or excluded on legal grounds, gets no provisional figure. Verified
     * scores and ranks are never touched here.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function withResearchState(array $rows, InvestmentIndexMethodology $m): array
    {
        $blank = ['provisional_score' => null, 'provisional_rank' => null, 'provisional_coverage' => null, 'provisional_confidence' => null, 'provisional_unchecked' => null];
        if ($rows === []) {
            return [];
        }
        $ids = array_column($rows, 'project_id');
        $projects = Project::whereIn('id', $ids)->get()->keyBy('id');
        $notRejected = ['verified', 'unverified'];
        $evidence = InvestmentEvidence::whereIn('verification_status', $notRejected)->whereIn('project_id', $ids)->with('source')->get()->groupBy('project_id');
        $legal = InvestmentLegalRecord::whereIn('verification_status', $notRejected)->whereIn('project_id', $ids)->with('source')->get()->groupBy('project_id');
        $observations = MarketObservation::where('is_demo', false)->whereIn('project_id', $ids)->pluck('project_id')->countBy();
        $deliveries = $this->deliveriesByDeveloper();
        $today = now()->startOfDay();
        $assessor = new ProjectAssessor($m, $today);
        $rules = config('investment_index.provisional');

        $rows = array_map(function (array $row) use ($blank, $projects, $evidence, $legal, $observations, $deliveries, $assessor, $rules) {
            $id = $row['project_id'];
            $project = $projects[$id];
            $e = $evidence->get($id, collect());
            $l = $legal->get($id, collect());
            $row += $blank;
            $row['research_depth'] = 3 * $l->count() + 2 * $e->count() + (int) ($observations[$id] ?? 0) + min(5, count($project->research_facts ?? []));
            if (in_array($row['status'], ['ranked', 'excluded'], true) || ($e->isEmpty() && $l->isEmpty())) {
                return $row;
            }
            $trial = $assessor->assess($project, $row['category'], $e, $l, collect(), ($deliveries[static::developerKey($project->developer)] ?? collect())->where('project_id', '!=', $id)->values());
            if (in_array($trial['legal_status'], ['approved', 'partially_approved'], true) && $trial['status'] !== 'excluded'
                && $trial['_computed_score'] !== null && $trial['_scored_factors'] >= (int) $rules['min_factors'] && (float) $trial['coverage'] >= (float) $rules['min_coverage']) {
                $row['provisional_score'] = $trial['_computed_score'];
                $row['provisional_coverage'] = $trial['coverage'];
                $row['provisional_confidence'] = $trial['confidence_score'];
                $row['provisional_unchecked'] = $e->where('verification_status', 'unverified')->count() + $l->where('verification_status', 'unverified')->count();
            }

            return $row;
        }, $rows);

        $eligible = collect($rows)->filter(fn ($r) => $r['provisional_score'] !== null)->groupBy('category');
        $ranks = [];
        foreach ($eligible as $group) {
            foreach ($group->sortBy([['provisional_score', 'desc'], ['provisional_coverage', 'desc'], ['project_id', 'asc']])->values() as $i => $r) {
                $ranks[$r['project_id']] = $i + 1;
            }
        }

        return array_map(fn ($r) => ['provisional_rank' => $ranks[$r['project_id']] ?? null] + $r, $rows);
    }

    /** The newest verified delivery status of every real project, grouped by developer. */
    protected function deliveriesByDeveloper(): array
    {
        $types = config('investment_index.context_evidence.delivery_status.sources');
        $levels = config('investment_index.context_evidence.delivery_status.levels');

        return InvestmentEvidence::verified()->where('factor', 'delivery_status')
            ->whereHas('project', fn ($q) => $q->where('is_demo', false))
            ->with(['source', 'project:id,name,developer'])->get()
            ->filter(fn ($e) => $e->source && in_array($e->source->type, $types, true) && isset($levels[$e->value_level]) && static::developerKey($e->project->developer) !== '')
            ->sortByDesc(fn ($e) => [$e->observed_on->timestamp, $e->id])
            ->unique('project_id')
            ->groupBy(fn ($e) => static::developerKey($e->project->developer))
            ->map(fn ($g) => $g->map(fn ($e) => ['project_id' => $e->project_id, 'project' => $e->project->name, 'level' => $e->value_level, 'record' => $e])->values())
            ->all();
    }

    public static function developerKey(?string $developer): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower((string) $developer)));
    }

    /**
     * Category ranks (score, then confidence, then project id), and the
     * Overall Index: within each category with enough ranked peers, a
     * project's mid-rank percentile (0-100) is its normalized score; the
     * index is that, scaled by confidence (x0.5 at 0 to x1.0 at 100).
     * Unlike assets are therefore compared by standing within their own
     * category, never by raw prices or yields.
     */
    protected function rank(array $rows, InvestmentIndexMethodology $m): array
    {
        $minPeers = (int) $m->parameter('overall_min_peers');
        $rows = array_map(fn ($r) => $r + ['category_rank' => null, 'normalized_score' => null, 'overall_index' => null, 'overall_rank' => null], $rows);

        $ranked = collect($rows)->filter(fn ($r) => $r['status'] === 'ranked')->groupBy('category');
        $byProject = [];
        foreach ($ranked as $group) {
            $sorted = $group->sortBy([['score', 'desc'], ['confidence_score', 'desc'], ['project_id', 'asc']])->values();
            $n = $sorted->count();
            foreach ($sorted as $i => $r) {
                $normalized = $n >= $minPeers ? round(($n - $i - 0.5) / $n * 100, 1) : null;
                $byProject[$r['project_id']] = ['category_rank' => $i + 1, 'normalized_score' => $normalized,
                    'overall_index' => $normalized === null ? null : round($normalized * (0.5 + 0.5 * $r['confidence_score'] / 100), 1)];
            }
        }
        $overall = collect($byProject)->filter(fn ($r) => $r['overall_index'] !== null)
            ->map(fn ($r, $id) => $r + ['id' => $id, 'confidence' => collect($rows)->firstWhere('project_id', $id)['confidence_score']])
            ->sortBy([['overall_index', 'desc'], ['confidence', 'desc'], ['id', 'asc']])->values();
        foreach ($overall as $i => $r) {
            $byProject[$r['id']]['overall_rank'] = $i + 1;
        }

        return array_map(fn ($r) => array_merge($r, $byProject[$r['project_id']] ?? []), $rows);
    }

    protected function summary(array $rows): array
    {
        $c = collect($rows);

        return ['projects' => $c->count(), 'ranked' => $c->where('status', 'ranked')->count(), 'not_eligible' => $c->where('status', 'not_eligible')->count(),
            'insufficient' => $c->where('status', 'insufficient')->count(), 'excluded' => $c->where('status', 'excluded')->count(),
            'in_overall_index' => $c->whereNotNull('overall_rank')->count(),
            'by_category' => collect(config('investment_index.categories'))->map(fn ($_, $k) => $c->where('category', $k)->where('status', 'ranked')->count())->all()];
    }

    /** @return Collection<int, InvestmentIndexScore> the published leaderboard of a category */
    public static function leaderboard(string $category): Collection
    {
        $run = InvestmentIndexRun::latestPublished();

        return $run ? $run->scores()->where('category', $category)->where('status', 'ranked')->orderBy('category_rank')->with('project')->get() : collect();
    }
}
