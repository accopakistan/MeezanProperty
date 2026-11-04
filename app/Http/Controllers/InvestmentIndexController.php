<?php

namespace App\Http\Controllers;

use App\Models\InvestmentIndexMethodology;
use App\Models\Project;
use App\Services\InvestmentIndex\EvidenceSubmissions;
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
 * enough verified evidence says "Insufficient Verified Data". The earlier
 * city / group / segment pages redirect here permanently.
 */
class InvestmentIndexController extends Controller
{
    /** Leaderboards show the top 100; every project's rank is also on its profile. */
    public const PAGE_LIMIT = 100;

    public function __construct(protected IndexBrowser $index) {}

    public function home(): View
    {
        $categories = collect(IndexBrowser::categories())->map(fn ($c, $key) => $c + [
            'key' => $key, 'top' => $this->index->leaderboard($key, null, 3), 'counts' => $this->index->counts($key),
        ]);

        return view('investment-index.home', [
            'run' => $this->index->run(),
            'categories' => $categories,
            'overall' => $this->index->overall(10),
            'counts' => $this->index->counts(),
        ]);
    }

    public function category(Request $request, string $category): View
    {
        $config = IndexBrowser::categories()[$category] ?? abort(404);
        $city = in_array($request->query('city'), Cities::names(), true) ? $request->query('city') : null;
        $run = $this->index->run();

        $m = InvestmentIndexMethodology::current();

        return view('investment-index.category', [
            'key' => $category, 'category' => $config, 'city' => $city, 'run' => $run,
            'weights' => $m->weightsFor($category), 'methodologyVersion' => $m->version,
            'ranked' => $this->index->leaderboard($category, $city, self::PAGE_LIMIT),
            'counts' => $this->index->counts($category),
            // Assessed but not ranked: shown by name and status, never with a score.
            'others' => $run ? $run->scores()->where('category', $category)->where('status', '!=', 'ranked')
                ->when($city, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('city', $city)))
                ->with('project:id,name,slug,city,developer')->orderByRaw("case status when 'excluded' then 0 when 'not_eligible' then 1 else 2 end")->limit(100)->get() : collect(),
        ]);
    }

    public function overall(): View
    {
        return view('investment-index.overall', ['run' => $this->index->run(), 'overall' => $this->index->overall(self::PAGE_LIMIT),
            'minPeers' => (int) InvestmentIndexMethodology::current()->parameter('overall_min_peers')]);
    }

    public function project(Project $project): View
    {
        abort_if($project->is_demo || ! $project->is_published || ! isset(IndexEngine::categoryMap()[$project->category]), 404);

        return view('investment-index.project', ['project' => $project->load('area:id,name'), 'run' => $this->index->run(),
            'canSubmit' => EvidenceSubmissions::accepts($project)] + $this->index->profile($project));
    }

    public function methodology(): View
    {
        $m = InvestmentIndexMethodology::current();

        return view('investment-index.methodology', ['methodology' => $m, 'categories' => IndexBrowser::categories(), 'run' => $this->index->run()]);
    }

    /* The earlier segment rankings (individual plots, houses, shops… and city pages) now redirect. */

    public function legacyCity(string $city): RedirectResponse
    {
        return redirect()->route('rankings.index', [], 301);
    }

    public function legacyGroup(string $city, string $group): RedirectResponse
    {
        return redirect()->route('rankings.index', [], 301);
    }

    public function legacySegment(string $city, string $group, string $segment): RedirectResponse
    {
        $category = IndexBrowser::categoryForSegment($segment);
        $name = Cities::fromSlug($city);

        return $category
            ? redirect()->route('rankings.category', array_filter(['category' => $category, 'city' => $name]), 301)
            : redirect()->route('rankings.index', [], 301);
    }
}
