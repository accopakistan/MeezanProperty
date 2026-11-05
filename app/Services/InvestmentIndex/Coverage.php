<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentCollectionRun;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentException;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentLegalRecord;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketSource;
use App\Models\Project;
use App\Services\InvestmentIndex\Collection\SchemeNames;
use App\Support\Cities;
use Illuminate\Support\Collection;

/**
 * Real-world data coverage, from actual records only. It says how much is
 * known and how much is missing; it never claims market-wide coverage.
 */
class Coverage
{
    /** @return array{totals: array, cities: array, categories: array} */
    public function summary(): array
    {
        $map = IndexEngine::categoryMap();
        $run = InvestmentIndexRun::latestPublished();
        $scores = $run ? $run->scores()->with('project:id,city,status')->get() : collect();
        $projects = Project::where('is_demo', false)->where('is_published', true)->whereIn('category', array_keys($map))->get(['id', 'city', 'category']);
        $count = fn (Collection $s) => [
            'legal_verified' => $s->where('legal_status', '!=', 'not_verified')->count(),
            'operational_verified' => $s->whereNotNull('operational_status')->count(),
            'history_verified' => $s->filter(fn ($x) => ($x->factors['market_performance']['score'] ?? null) !== null)->count(),
            'rental_verified' => $s->whereNotNull('verified_yield')->count(),
            'ranked' => $s->where('status', 'ranked')->count(),
            'under_review' => $s->where('status', 'not_eligible')->count(),
            'insufficient' => $s->where('status', 'insufficient')->count(),
            'excluded' => $s->where('status', 'excluded')->count(),
        ];
        $cities = collect(Cities::names())->mapWithKeys(fn ($city) => [$city => ['projects' => $projects->where('city', $city)->count()] + $count($scores->filter(fn ($s) => $s->project?->city === $city))])->all();
        $categories = collect(IndexBrowser::categories())->mapWithKeys(fn ($c, $key) => [$key => ['label' => $c['label'], 'projects' => $projects->filter(fn ($p) => $map[$p->category] === $key)->count()]
            + $count($scores->where('category', $key))])->all();

        return ['totals' => ['projects' => $projects->count()] + $count($scores), 'cities' => $cities, 'categories' => $categories, 'published_at' => $run?->published_at];
    }

    /** Every source in the registry except demo data, highest authority first. */
    public function sources(): Collection
    {
        return MarketSource::where('is_demo', false)->orderByRaw('authority_level is null')->orderBy('authority_level')->orderBy('name')->get()->map(function (MarketSource $s) {
            $last = InvestmentCollectionRun::where('source_id', $s->id)->latest('id')->first();

            return ['source' => $s, 'connected' => $s->isCollectable() && $last?->status === 'completed', 'last_run' => $last,
                'registry' => InvestmentRegistryEntry::where('source_id', $s->id)->whereNull('removed_at')->count(),
                'matched' => InvestmentRegistryEntry::where('source_id', $s->id)->whereNull('removed_at')->whereIn('match_status', ['matched', 'manual'])->count(),
                'projects' => Project::where('discovery_source_id', $s->id)->count()];
        });
    }

    /** Verified records past the age the engine still uses. */
    public function staleEvidence(): int
    {
        $limit = now()->subMonths((int) config('investment_index.parameters.max_age_months'));

        return InvestmentEvidence::verified()->where(fn ($q) => $q->where('observed_on', '<', $limit)->orWhere(fn ($n) => $n->whereNull('observed_on')->where('collected_at', '<', $limit)))->count();
    }

    /** Records waiting for a person. */
    public function pending(): array
    {
        return ['legal' => InvestmentLegalRecord::where('verification_status', 'unverified')->count(), 'evidence' => InvestmentEvidence::where('verification_status', 'unverified')->count(),
            'exceptions' => InvestmentException::where('status', 'open')->count(), 'stale' => $this->staleEvidence(),
            'rejected' => InvestmentLegalRecord::where('verification_status', 'rejected')->count() + InvestmentEvidence::where('verification_status', 'rejected')->count()];
    }

    /**
     * Possible duplicates: real projects in one city and category with very
     * similar names. Only compared within the same first word, and only shown:
     * merging or keeping them is a person's decision.
     *
     * @return list<array{a: Project, b: Project, score: float}>
     */
    public function possibleDuplicates(int $limit = 50): array
    {
        $found = [];
        $review = (float) config('investment_index.collection.match_review');
        Project::where('is_demo', false)->get(['id', 'name', 'slug', 'city', 'category'])
            ->groupBy(fn (Project $p) => $p->city.'|'.$p->category.'|'.(explode(' ', SchemeNames::normalize($p->name))[0] ?? ''))
            ->each(function (Collection $group) use (&$found, $review, $limit) {
                $list = $group->values();
                for ($i = 0; $i < $list->count() && count($found) < $limit; $i++) {
                    for ($j = $i + 1; $j < $list->count() && count($found) < $limit; $j++) {
                        $score = SchemeNames::similarity($list[$i]->name, $list[$j]->name);
                        if ($score >= $review) {
                            $found[] = ['a' => $list[$i], 'b' => $list[$j], 'score' => $score];
                        }
                    }
                }
            });

        return $found;
    }

    /** The read-only summary agents get. */
    public function forAgents(): array
    {
        $s = $this->summary();

        return ['totals' => $s['totals'], 'cities' => $s['cities'], 'categories' => collect($s['categories'])->map(fn ($c) => array_diff_key($c, ['label' => 1]) + ['category' => $c['label']])->values()->all(),
            'published_at' => $s['published_at']?->toDateTimeString(), 'pending' => $this->pending(),
            'sources' => $this->sources()->map(fn ($x) => ['name' => $x['source']->name, 'type' => $x['source']->type, 'url' => $x['source']->url, 'automatically_collected' => $x['connected'],
                'read_once_by_hand' => ! $x['source']->collector, 'last_success' => $x['source']->last_success_at?->toDateString(), 'last_run_status' => $x['last_run']?->status, 'registry_entries' => $x['registry'], 'projects_found' => $x['projects']])->all(),
            'possible_duplicates' => count($this->possibleDuplicates(25)),
            'validation' => collect(app(DataValidator::class)->report())->map(fn ($c) => ['check' => $c['label'], 'severity' => $c['severity'], 'count' => $c['count']])->values()->all(),
            'limits' => ['These counts show collected records only, not all of Pakistan\'s developments.', 'Being listed by a source does not make a project verified beyond what that source says.']];
    }
}
