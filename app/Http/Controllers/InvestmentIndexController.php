<?php

namespace App\Http\Controllers;

use App\Models\InvestmentIndexMethodology;
use App\Models\Project;
use App\Services\InvestmentIndex\EvidenceSubmissions;
use App\Services\InvestmentIndex\IndexAnalytics;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Services\InvestmentIndex\IndexEngine;
use App\Support\Cities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public pages of the Meezan Investment Index (/investment-rankings): five
 * Pakistan-wide leaderboards, the Overall Index, project profiles and the
 * methodology. They show only what the engine published; a project without
 * enough verified evidence says "Insufficient Verified Data". The MPIS city
 * and category dashboards sit beside it (InvestmentRankingsController).
 */
class InvestmentIndexController extends Controller
{
    /** Leaderboards show the top 100; every project's rank is also on its profile. */
    public const PAGE_LIMIT = 100;

    public function __construct(
        protected IndexBrowser $index,
        protected IndexAnalytics $analytics,
    ) {}

    public function home(Request $request): View|RedirectResponse
    {
        // The search form: straight to the chosen city and category, or to the city index.
        $category = $request->query('category');
        $cityParam = $request->query('city');
        $cityName = in_array($cityParam, Cities::names(), true) ? $cityParam : Cities::fromSlug((string) $cityParam);
        $search = array_filter(['q' => is_string($request->query('q')) ? trim($request->query('q')) : null]);

        if (is_string($category) && isset(IndexBrowser::categories()[$category])) {
            return $cityName ? redirect()->route('rankings.city-category', [Cities::slug($cityName), $category] + $search) : redirect()->route('rankings.category', [$category] + $search);
        }

        if ($cityName) {
            return redirect()->route('rankings.city', [Cities::slug($cityName)] + $search);
        }

        $categories = collect(IndexBrowser::categories())->map(fn ($c, $key) => $c + [
            'key' => $key, 'top' => $this->index->leaderboard($key, null, 3), 'counts' => $this->index->counts($key),
            'provisional' => IndexBrowser::provisionalPublic() ? $this->index->provisional($key, null, 3) : collect(),
            'documented' => $this->index->mostDocumented($key, null, 3),
        ]);

        return view('investment-index.home', [
            'run' => $this->index->run(),
            'categories' => $categories,
            'overall' => $this->index->overall(10),
            'counts' => $this->index->counts(),
            'cityCards' => $this->index->cityCards(),
            'research' => $this->index->researchTotals(),
            'analytics' => $this->analytics->nationalAnalytics(),
        ]);
    }

    public function category(Request $request, string $category): View
    {
        $cityParam = $request->query('city');
        $cityName = in_array($cityParam, Cities::names(), true) ? $cityParam : Cities::fromSlug((string) $cityParam);

        return $this->categoryPage($request, $category, $cityName);
    }

    public function cityCategory(Request $request, string $city, string $category): View
    {
        return $this->categoryPage($request, $category, Cities::fromSlug($city) ?? abort(404));
    }

    protected function categoryPage(Request $request, string $category, ?string $city): View
    {
        $config = IndexBrowser::categories()[$category] ?? abort(404);
        $run = $this->index->run();
        $filters = $this->filters($request);

        $m = InvestmentIndexMethodology::current();

        return view('investment-index.category', [
            'key' => $category, 'category' => $config, 'city' => $city, 'run' => $run,
            'weights' => $m->weightsFor($category), 'methodologyVersion' => $m->version,
            'ranked' => $this->index->leaderboard($category, $city, self::PAGE_LIMIT, $filters),
            'counts' => $this->index->counts($category, $city),
            'filters' => $filters,
            // Assessed but not ranked: shown by name and status, never with a score.
            // Everything not on the public leaderboard, including projects ranked below the published top.
            'others' => $run ? $this->index->withWaitingLegal($this->index->scored($category, $city, $filters)
                ->where(fn ($q) => $q->where('status', '!=', 'ranked')->orWhere('category_rank', '>', IndexBrowser::publicTop()))
                ->with('project:id,name,slug,city,developer,status')->orderByRaw("case status when 'not_eligible' then 0 when 'insufficient' then 1 else 2 end")
                ->orderByRaw("case legal_status when 'approved' then 0 when 'partially_approved' then 1 when 'pending' then 2 else 3 end")->orderByDesc('research_depth')->orderBy('project_id')->paginate(40)->withQueryString()) : collect(),
            'markers' => $this->index->markers($category, $city),
            'provisional' => IndexBrowser::provisionalPublic() && $filters === [] ? $this->index->provisional($category, $city) : collect(),
            'analytics' => $this->analytics->categoryAnalytics($category, $city),
        ]);
    }

    /** Search and filters on a category page. Values outside the lists are ignored. */
    protected function filters(Request $request): array
    {
        $in = fn (string $k, array $allowed) => in_array($request->query($k), $allowed, true) ? $request->query($k) : null;

        return array_filter([
            'q' => is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 80) : null,
            'legal' => $in('legal', array_keys(IndexBrowser::legalFilters())),
            'status' => $in('status', array_keys(config('investment_index.statuses'))),
            'confidence' => $in('confidence', ['high', 'medium', 'limited', 'insufficient']),
            'risk' => $in('risk', array_keys(config('investment_index.parameters.risk_classes'))),
            'development' => $in('development', array_keys(Project::STATUSES)),
        ]);
    }

    /** Up to four developments of one category, side by side. */
    public function compare(Request $request, string $category): View
    {
        $config = IndexBrowser::categories()[$category] ?? abort(404);
        $request->validate(['projects' => ['nullable', 'array', 'max:4'], 'projects.*' => ['string', 'max:255']]);
        $types = $config['project_categories'];
        $rows = Project::where('is_demo', false)->where('is_published', true)->whereIn('category', $types)->whereIn('slug', collect($request->query('projects', []))->filter(fn ($s) => is_string($s))->take(4))->get()
            ->map(fn (Project $p) => ['project' => $p, 'score' => $this->index->score($p)]);

        return view('investment-index.compare', ['key' => $category, 'category' => $config, 'rows' => $rows]);
    }

    public function overall(): View
    {
        return view('investment-index.overall', ['run' => $this->index->run(), 'overall' => $this->index->overall(self::PAGE_LIMIT),
            'minPeers' => (int) InvestmentIndexMethodology::current()->parameter('overall_min_peers')]);
    }

    public function project(Project $project): View
    {
        abort_if($project->is_demo || ! $project->is_published || ! isset(IndexEngine::categoryMap()[$project->category]), 404);

        return view('investment-index.project', ['project' => $project->load(['area:id,name', 'aliases:id,project_id,alias', 'discoverySource:id,name,type,url,publisher']), 'run' => $this->index->run(),
            'canSubmit' => EvidenceSubmissions::accepts($project)] + $this->index->profile($project));
    }

    public function methodology(): View
    {
        $m = InvestmentIndexMethodology::current();

        return view('investment-index.methodology', ['methodology' => $m, 'categories' => IndexBrowser::categories(), 'run' => $this->index->run()]);
    }
}
