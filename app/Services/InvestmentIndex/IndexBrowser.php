<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexChange;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketSource;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Read-only view of the published Meezan Investment Index for the public
 * pages: leaderboards, the Overall Index and project profiles. Everything
 * comes from the latest published run; nothing is calculated here, and a
 * project the engine did not rank is shown with its status, never a score.
 */
class IndexBrowser
{
    /** Source types whose links are public; others (owner and audited documents) are described, not linked. */
    public const PUBLIC_SOURCE_TYPES = ['official_record', 'licensed_data', 'authorized_report', 'verified_transactions', 'verified_rental'];

    protected ?InvestmentIndexRun $run = null;

    protected bool $loaded = false;

    public function run(): ?InvestmentIndexRun
    {
        if (! $this->loaded) {
            $this->run = InvestmentIndexRun::latestPublished();
            $this->loaded = true;
        }

        return $this->run;
    }

    /** @return array<string, array> category key => config */
    public static function categories(): array
    {
        return config('investment_index.categories');
    }

    /** @return Collection<int, InvestmentIndexScore> ranked projects of a category, best first (optionally one city) */
    public function leaderboard(string $category, ?string $city = null, ?int $limit = null): Collection
    {
        $run = $this->run();
        if (! $run) {
            return collect();
        }

        return $run->scores()->where('category', $category)->where('status', 'ranked')
            ->when($city, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('city', $city)))
            ->with('project:id,name,slug,city,developer,category,cover_path,area_id')->orderBy('category_rank')
            ->when($limit, fn ($q) => $q->limit($limit))->get();
    }

    /** @return array{assessed: int, ranked: int, not_eligible: int, insufficient: int, excluded: int} */
    public function counts(?string $category = null): array
    {
        $run = $this->run();
        $rows = $run ? $run->scores()->when($category, fn ($q) => $q->where('category', $category))->toBase()
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status') : collect();

        return ['assessed' => (int) $rows->sum(), 'ranked' => (int) ($rows['ranked'] ?? 0), 'not_eligible' => (int) ($rows['not_eligible'] ?? 0),
            'insufficient' => (int) ($rows['insufficient'] ?? 0), 'excluded' => (int) ($rows['excluded'] ?? 0)];
    }

    /** @return Collection<int, InvestmentIndexScore> the Overall Meezan Investment Index, best first */
    public function overall(?int $limit = null): Collection
    {
        $run = $this->run();

        return $run ? $run->scores()->whereNotNull('overall_rank')->with('project:id,name,slug,city,developer,category')
            ->orderBy('overall_rank')->when($limit, fn ($q) => $q->limit($limit))->get() : collect();
    }

    /** The project's result in the published run, or null when it was not assessed. */
    public function score(Project $project): ?InvestmentIndexScore
    {
        return $this->run()?->scores()->where('project_id', $project->id)->first();
    }

    /**
     * Everything a profile shows: the published result, the sources behind
     * it, its verified legal records, its score over time and the reasons
     * for each ranking change.
     */
    public function profile(Project $project): array
    {
        $score = $this->score($project);
        $category = IndexEngine::categoryMap()[$project->category] ?? null;

        return [
            'score' => $score,
            'category' => $category,
            'sources' => $score ? $this->sources($score) : collect(),
            'legal' => InvestmentLegalRecord::verified()->where('project_id', $project->id)->with('source:id,name,type,url')
                ->orderByDesc('issued_on')->orderByDesc('collected_at')->limit(20)->get(),
            'history' => InvestmentIndexScore::where('project_id', $project->id)
                ->whereHas('run', fn ($q) => $q->whereIn('status', ['published', 'superseded']))
                ->with('run:id,published_at,methodology_version')->orderByDesc('run_id')->limit(12)->get(),
            'changes' => InvestmentIndexChange::where('project_id', $project->id)->latest('id')->limit(15)->get(),
            'evidenceCount' => InvestmentEvidence::verified()->where('project_id', $project->id)->count(),
        ];
    }

    /**
     * The sources a result used, for "original data sources": official and
     * licensed sources link to their own pages; documents submitted by owners
     * or certified by third parties are described, because the documents are
     * private.
     *
     * @return Collection<int, array{name: string, type: string, url: ?string, note: ?string}>
     */
    public function sources(InvestmentIndexScore $score): Collection
    {
        $legalUrls = InvestmentLegalRecord::verified()->where('project_id', $score->project_id)->pluck('source_url', 'source_id');

        return MarketSource::whereIn('id', $score->source_ids ?? [])->orderBy('name')->get()->map(function (MarketSource $s) use ($legalUrls) {
            $public = in_array($s->type, self::PUBLIC_SOURCE_TYPES, true);

            return [
                'name' => $s->name,
                'type' => $s->typeLabel(),
                'url' => $public ? static::safeUrl($legalUrls[$s->id] ?? $s->url) : null,
                'note' => $public ? null : 'Documents checked by Meezan Property staff; not published.',
            ];
        });
    }

    /** A public link only for an http(s) address outside the admin area; anything else is not linked. */
    public static function safeUrl(?string $url): ?string
    {
        return $url && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) && ! str_contains($url, '/admin/') ? $url : null;
    }

    /** Old segment ranking pages => the Index category they now belong to (null: no longer ranked). */
    public static function categoryForSegment(?string $segment): ?string
    {
        return ['societies' => 'housing-societies', 'apartments' => 'apartment-buildings', 'shopping-malls' => 'shopping-malls',
            'commercial-plazas' => 'commercial-plazas', 'business-towers' => 'business-towers'][$segment] ?? null;
    }
}
