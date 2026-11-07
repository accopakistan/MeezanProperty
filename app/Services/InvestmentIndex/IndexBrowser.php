<?php

namespace App\Services\InvestmentIndex;

use App\Models\AppSetting;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentEvidenceSubmission;
use App\Models\InvestmentException;
use App\Models\InvestmentIndexChange;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Support\Cities;
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

    /** Published results of a category, narrowed by city and the page's filters. A query builder; the run must exist. */
    public function scored(string $category, ?string $city, array $filters = [])
    {
        return $this->run()->scores()->where('category', $category)
            ->when($city || isset($filters['q']) || isset($filters['development']), fn ($q) => $q->whereHas('project', fn ($p) => $p->when($city, fn ($c) => $c->where('city', $city))
                ->when($filters['development'] ?? null, fn ($c, $d) => $c->where('status', $d))
                ->when($filters['q'] ?? null, fn ($c, $text) => $c->where(fn ($n) => $n->where('name', 'like', '%'.addcslashes($text, '%_').'%')->orWhere('developer', 'like', '%'.addcslashes($text, '%_').'%')
                    ->orWhereHas('aliases', fn ($a) => $a->where('alias', 'like', '%'.addcslashes($text, '%_').'%'))))))
            ->when($filters['legal'] ?? null, fn ($q, $legal) => $q->where('legal_status', $legal))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['confidence'] ?? null, fn ($q, $level) => $q->where('confidence_level', $level))
            ->when($filters['risk'] ?? null, fn ($q, $risk) => $q->where('risk_class', $risk));
    }

    /** @return Collection<int, InvestmentIndexScore> ranked projects of a category, best first (optionally one city) */
    public function leaderboard(string $category, ?string $city = null, ?int $limit = null, array $filters = []): Collection
    {
        $run = $this->run();
        if (! $run) {
            return collect();
        }

        $top = static::publicTop();

        return $this->scored($category, $city, $filters)
            ->where('status', 'ranked')
            ->when(! $city, fn ($q) => $q->where('category_rank', '<=', $top), function ($q) use ($top) {
                $hasTop10 = (clone $q)->where('category_rank', '<=', $top)->exists();

                return $hasTop10 ? $q->where('category_rank', '<=', $top) : $q;
            })
            ->with('project:id,name,slug,city,developer,category,cover_path,area_id', 'project.area:id,name')
            ->orderBy('category_rank')
            ->limit(min($limit ?? $top, $top))
            ->get();
    }

    /** How many ranked projects a public category leaderboard may show. */
    public static function publicTop(): int
    {
        return (int) config('investment_index.public_leaderboard_size', 10);
    }

    /** A ranked position the public may see: within the published top of its category. */
    public static function publicRank(?InvestmentIndexScore $score): ?int
    {
        return $score && $score->status === 'ranked' && $score->category_rank && $score->category_rank <= static::publicTop() ? $score->category_rank : null;
    }

    /**
     * An honest leaderboard heading: "Top 10 Verified …" only when ten projects qualify,
     * "Verified … Rankings" for fewer, and the plain category name when none do.
     */
    public static function leaderboardTitle(string $category, int $published): string
    {
        $c = config("investment_index.categories.$category");
        $top = static::publicTop();

        return match (true) {
            $published >= $top => "Top {$top} Verified {$c['label']}",
            $published > 0 => 'Verified '.ucwords($c['singular']).' Rankings',
            default => $c['label'],
        };
    }

    /** @return array{assessed: int, ranked: int, not_eligible: int, insufficient: int, excluded: int} */
    public function counts(?string $category = null, ?string $city = null): array
    {
        $run = $this->run();
        $rows = $run ? $run->scores()->when($category, fn ($q) => $q->where('category', $category))
            ->when($city, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('city', $city)))->toBase()
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status') : collect();

        return ['assessed' => (int) $rows->sum(), 'ranked' => (int) ($rows['ranked'] ?? 0), 'not_eligible' => (int) ($rows['not_eligible'] ?? 0),
            'insufficient' => (int) ($rows['insufficient'] ?? 0), 'excluded' => (int) ($rows['excluded'] ?? 0)];
    }

    /** The seven city cards: actual record counts. @return list<array> */
    public function cityCards(): array
    {
        $run = $this->run();
        $rows = $run ? $run->scores()->join('projects', 'projects.id', '=', 'investment_index_scores.project_id')->toBase()
            ->selectRaw('projects.city as city, investment_index_scores.status as status, count(*) as n')->groupBy('projects.city', 'investment_index_scores.status')->get()->groupBy('city') : collect();

        return collect(Cities::names())->map(function (string $city) use ($rows) {
            $c = ($rows[$city] ?? collect())->pluck('n', 'status');

            return ['name' => $city, 'slug' => Cities::slug($city), 'photo' => config('meezan.place_photos.'.$city), 'projects' => (int) $c->sum(), 'ranked' => (int) ($c['ranked'] ?? 0),
                'review' => (int) ($c['not_eligible'] ?? 0), 'insufficient' => (int) ($c['insufficient'] ?? 0), 'excluded' => (int) ($c['excluded'] ?? 0)];
        })->all();
    }

    /** Map markers: an exact project position, else the location directory's centre, marked approximate. Nothing without a known position. */
    public function markers(string $category, ?string $city): array
    {
        $run = $this->run();
        if (! $run) {
            return [];
        }

        return $this->scored($category, $city)->with('project:id,name,slug,city,latitude,longitude,area_id', 'project.area:id,name,latitude,longitude')->limit(500)->get()
            ->map(function (InvestmentIndexScore $s) {
                $p = $s->project;
                $exact = $p?->hasCoordinates();
                if (! $p || (! $exact && ! $p->area?->hasCoordinates())) {
                    return null;
                }

                return ['key' => 'project:'.$p->id, 'lat' => (float) ($exact ? $p->latitude : $p->area->latitude), 'lng' => (float) ($exact ? $p->longitude : $p->area->longitude), 'name' => $p->name,
                    'location' => $p->area?->name ?? $p->city, 'status' => match ($s->status) {
                        'ranked' => 'ranked', 'not_eligible' => 'provisional', default => 'insufficient'
                    }, 'status_label' => $s->statusLabel().' · '.static::legalLabel($s->legal_status),
                    'score' => $s->status === 'ranked' ? (float) $s->score : null, 'price' => null, 'yield' => $s->verified_yield !== null ? (float) $s->verified_yield : null, 'approximate' => ! $exact, 'demo' => false, 'url' => route('rankings.project', $p)];
            })->filter()->values()->all();
    }

    /** The label a visitor sees for a project's legal status. */
    public static function legalLabel(?string $status): string
    {
        return match ($status) {
            null, 'not_verified' => 'Unknown / Unverified',
            'excluded' => 'Excluded: legal record',
            default => config('investment_index.legal.statuses.'.$status, $status),
        };
    }

    /** @return array<string, string> the legal statuses a category page can filter by */
    public static function legalFilters(): array
    {
        return collect(['approved', 'partially_approved', 'pending', 'not_verified', 'excluded'])->mapWithKeys(fn ($s) => [$s => static::legalLabel($s)])->all();
    }

    /**
     * How well a project's record is verified as a whole. Disputed: official
     * records disagree. Outdated: nothing verified is recent enough to use.
     */
    public function dataStatus(Project $project, ?InvestmentIndexScore $score): string
    {
        if (InvestmentException::where('project_id', $project->id)->where('type', 'legal_conflict')->where('status', 'open')->exists()) {
            return 'Disputed';
        }
        $verified = InvestmentLegalRecord::verified()->where('project_id', $project->id)->count() + InvestmentEvidence::verified()->where('project_id', $project->id)->count();
        if ($verified === 0) {
            return InvestmentEvidenceSubmission::where('project_id', $project->id)->where('status', 'pending')->exists() ? 'Under Review' : 'Unverified';
        }
        if ($score?->last_verified_at && $score->last_verified_at->lt(now()->subMonths((int) config('investment_index.parameters.max_age_months')))) {
            return 'Outdated';
        }

        return ($score?->coverage ?? 0) >= (float) config('investment_index.parameters.min_coverage') ? 'Verified' : 'Partially Verified';
    }

    /** @return Collection<int, InvestmentIndexScore> the Overall Meezan Investment Index, best first (national or city-specific) */
    public function overall(?int $limit = null, ?string $city = null): Collection
    {
        $run = $this->run();
        if (! $run) {
            return collect();
        }

        return $run->scores()->whereNotNull('overall_rank')
            ->when(! $city, fn ($q) => $q->where('overall_rank', '<=', static::publicTop()))
            ->when($city, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('city', $city)))
            ->with('project:id,name,slug,city,developer,category,area_id', 'project.area:id,name')
            ->orderByDesc('overall_index')
            ->limit(min($limit ?? static::publicTop(), static::publicTop()))
            ->get();
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
            'dataStatus' => $this->dataStatus($project, $score),
            'lastUpdate' => collect([$project->updated_at, InvestmentLegalRecord::where('project_id', $project->id)->max('updated_at'), InvestmentEvidence::where('project_id', $project->id)->max('updated_at')])->filter()->max(),
            'sources' => $score ? $this->sources($score) : collect(),
            'legal' => InvestmentLegalRecord::verified()->where('project_id', $project->id)->with('source:id,name,type,url')
                ->orderByDesc('issued_on')->orderByDesc('collected_at')->limit(20)->get(),
            'history' => InvestmentIndexScore::where('project_id', $project->id)
                ->whereHas('run', fn ($q) => $q->whereIn('status', ['published', 'superseded']))
                ->with('run:id,published_at,methodology_version')->orderByDesc('run_id')->limit(12)->get(),
            'changes' => InvestmentIndexChange::where('project_id', $project->id)->latest('id')->limit(15)->get(),
            'evidenceCount' => InvestmentEvidence::verified()->where('project_id', $project->id)->count(),
        ] + $this->research($project);
    }

    /**
     * What the sources say that nobody has checked yet, and every price series,
     * kept apart so an asking price can never read as a sale.
     *
     * @return array<string, mixed>
     */
    public function research(Project $project): array
    {
        $observations = MarketObservation::where('project_id', $project->id)->where('is_demo', false)->whereIn('status', ['submitted', 'reviewed', 'verified'])
            ->with('source:id,name,url,type')->orderByDesc('observed_on')->get();
        $series = fn (string $kind, string $basis) => $observations->where('kind', $kind)->where('price_basis', $basis)->values();

        return [
            'facts' => collect($project->research_facts ?? []),
            'reported' => InvestmentEvidence::where('project_id', $project->id)->where('verification_status', 'unverified')->with('source:id,name,url,type')->latest('observed_on')->get(),
            'pendingLegal' => InvestmentLegalRecord::where('project_id', $project->id)->where('verification_status', 'unverified')->with('source:id,name,url,type')->latest('id')->get(),
            'priceSeries' => [
                'Asking prices (advertised, not sales)' => $series('sale', 'asking'),
                'Verified transaction prices' => $series('sale', 'transaction'),
                'Rental asking prices (advertised)' => $series('rent', 'asking'),
                'Verified rental income' => $series('rent', 'transaction'),
                'Valuations' => $series('sale', 'valuation'),
                'FBR tax valuations (not market prices)' => $series('sale', 'fbr_tax'),
            ],
        ];
    }

    /** Whether visitors see the Provisional Research Ranking. The owner's choice; off until then. */
    public static function provisionalPublic(): bool
    {
        return (bool) AppSetting::get('investment_index.provisional_public', false);
    }

    /** The provisional list, whether or not it is public: callers decide who sees it. */
    public function provisional(string $category, ?string $city = null, ?int $limit = null): Collection
    {
        if (! $this->run()) {
            return collect();
        }

        return $this->scored($category, $city)->whereNotNull('provisional_rank')->with('project:id,name,slug,city,developer,status,area_id', 'project.area:id,name')
            ->orderBy('provisional_rank')->when($limit, fn ($q, $l) => $q->limit($l))->get();
    }

    /**
     * The projects with the most on file, for places with no verified ranking.
     * Order shows how much is documented. It says nothing about investment merit.
     */
    public function mostDocumented(string $category, ?string $city = null, int $limit = 3): Collection
    {
        if (! $this->run()) {
            return collect();
        }

        return $this->withWaitingLegal($this->scored($category, $city)->whereNotIn('status', ['ranked', 'excluded'])->with('project:id,name,slug,city,developer,status')
            ->orderByDesc('research_depth')->orderByDesc('coverage')->orderBy('project_id')->limit($limit)->get());
    }

    /**
     * Marks rows whose project is on an authority's list but not yet checked by
     * staff, so a list can say so instead of "Unknown". Never a verified status.
     *
     * @template T of \Illuminate\Support\Collection
     *
     * @param  T  $rows
     * @return T
     */
    public function withWaitingLegal($rows)
    {
        $waiting = InvestmentLegalRecord::where('verification_status', 'unverified')->whereIn('project_id', collect($rows->all())->pluck('project_id'))->whereIn('status', ['approved', 'partially_approved', 'pending'])
            ->orderByDesc('id')->get(['project_id', 'authority'])->keyBy('project_id');
        foreach ($rows as $row) {
            $row->setAttribute('waiting_authority', $waiting[$row->project_id]->authority ?? null);
        }

        return $rows;
    }

    /** The legal line for a row in a list: what is on file, in plain words. */
    public static function legalLine(InvestmentIndexScore $row): string
    {
        return $row->legal_status === 'not_verified' && $row->waiting_authority
            ? "On {$row->waiting_authority}'s list, not yet checked by us"
            : static::legalLabel($row->legal_status);
    }

    /** @return array{projects: int, ranked: int, provisional: int, excluded: int, research: int, legal_verified: int, awaiting_check: int, published_at: mixed} */
    public function researchTotals(?string $city = null): array
    {
        $run = $this->run();
        $inCity = fn ($q) => $q->when($city, fn ($w) => $w->whereHas('project', fn ($p) => $p->where('city', $city)));
        $counts = $run ? $inCity($run->scores())->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status') : collect();
        $projects = (int) $counts->sum();

        return [
            'projects' => $projects, 'ranked' => (int) ($counts['ranked'] ?? 0), 'excluded' => (int) ($counts['excluded'] ?? 0),
            'research' => (int) ($counts['insufficient'] ?? 0) + (int) ($counts['not_eligible'] ?? 0),
            'provisional' => $run ? $inCity($run->scores())->whereNotNull('provisional_rank')->count() : 0,
            'legal_verified' => $run ? $inCity($run->scores())->whereIn('legal_status', ['approved', 'partially_approved', 'pending', 'excluded'])->count() : 0,
            'awaiting_check' => InvestmentLegalRecord::where('verification_status', 'unverified')->whereHas('project', fn ($p) => $p->where('is_demo', false)->when($city, fn ($w) => $w->where('city', $city)))->distinct()->count('project_id'),
            'published_at' => $run?->published_at,
        ];
    }

    /** The confidence level in reader's words. */
    public static function confidenceLabel(?string $level): string
    {
        return ['high' => 'High Confidence', 'medium' => 'Moderate Confidence', 'limited' => 'Low Confidence', 'insufficient' => 'Insufficient Evidence'][$level] ?? 'Insufficient Evidence';
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
