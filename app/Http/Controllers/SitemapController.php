<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentIndexRun;
use App\Models\Package;
use App\Models\Project;
use App\Models\Property;
use App\Services\Investment\RankingBrowser;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Support\Cities;
use App\Support\Legal;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * XML sitemaps. /sitemap.xml is a sitemap index of:
 * - /sitemaps/pages.xml         static public pages and the seven city pages,
 * - /sitemaps/research.xml      Investment Index and investment research pages that are indexable,
 * - /sitemaps/locations.xml     area guides that are indexable, and city/area category pages
 *                               with enough real listings,
 * - /sitemaps/projects-N.xml    project directory profiles with researched content,
 * - /sitemaps/agents.xml        public dealer profiles,
 * - /sitemaps/properties-N.xml  live, non-demo listings.
 *
 * Only URLs that are public and indexable are included, using the same rules the pages use
 * for their robots meta, so a noindex page never appears here. Each file is cached for an hour.
 */
class SitemapController extends Controller
{
    public const PER_FILE = 5000;

    protected const TTL = 3600;

    /** Child files other than the numbered ones. */
    public const FILES = ['pages', 'research', 'locations', 'agents'];

    public function index(): Response
    {
        $xml = Cache::remember('sitemap:index', self::TTL, function () {
            $files = collect(self::FILES)
                ->concat(collect(range(1, max(1, $this->projectFiles())))->map(fn ($n) => 'projects-'.$n))
                ->concat(collect(range(1, max(1, $this->propertyFiles())))->map(fn ($n) => 'properties-'.$n));

            return view('sitemaps.index', ['sitemaps' => $files->map(fn ($name) => route('sitemap.show', $name))])->render();
        });

        return $this->xml($xml);
    }

    public function show(string $name): Response
    {
        foreach (['properties-' => $this->propertyFiles(...), 'projects-' => $this->projectFiles(...)] as $prefix => $files) {
            if (str_starts_with($name, $prefix)) {
                abort_if((int) substr($name, strlen($prefix)) > max(1, $files()), 404);
            }
        }

        $xml = Cache::remember('sitemap:'.$name, self::TTL, fn () => view('sitemap', ['urls' => match (true) {
            $name === 'pages' => $this->pages(),
            $name === 'research' => $this->research(),
            $name === 'locations' => $this->locations(),
            $name === 'agents' => $this->agents(),
            str_starts_with($name, 'projects-') => $this->projects((int) substr($name, 9)),
            default => $this->properties((int) substr($name, 11)),
        }])->render());

        return $this->xml($xml);
    }

    /** @return Collection<int, array{loc: string, lastmod: ?string}> */
    protected function pages(): Collection
    {
        return collect(['home', 'properties.buy', 'properties.rent', 'properties.plots', 'properties.commercial', 'properties.index',
            'ai-finder', 'areas.index', 'dealers.index', 'projects.index', 'sell', 'pages.about', 'pages.how-it-works', 'pages.contact'])
            // Pricing and legal pages only once they are final: drafts are not indexed.
            ->when(Package::offered()->exists(), fn ($pages) => $pages->push('pricing'))
            ->concat(array_values(array_filter(['pages.terms', 'pages.privacy', 'pages.disclaimer'], fn ($r) => Legal::approved(substr($r, 6)))))
            ->map(fn ($name) => ['loc' => route($name), 'lastmod' => null])
            ->concat(collect(Cities::names())->map(fn ($city) => ['loc' => route('cities.show', Cities::slug($city)), 'lastmod' => null]));
    }

    /**
     * Investment research: the Index home, both methodologies and the yield calculator always;
     * leaderboards, the Overall Index and ranked project profiles only once they have ranked
     * projects (until then they are "noindex, follow" and stay out); MPIS dashboards with
     * verified scored entries.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string}>
     */
    protected function research(): Collection
    {
        return collect(['rankings.index', 'rankings.methodology', 'investment.methodology', 'investment.yield'])
            ->map(fn ($name) => ['loc' => route($name), 'lastmod' => null])
            ->concat($this->rankings())
            ->concat($this->rankingDashboards())
            ->unique('loc')->values();
    }

    /** @return Collection<int, array{loc: string, lastmod: ?string}> */
    protected function rankings(): Collection
    {
        $run = InvestmentIndexRun::latestPublished();
        if (! $run) {
            return collect();
        }
        $lastmod = $run->published_at?->toAtomString();
        $index = app(IndexBrowser::class);

        // A category leaderboard is indexable only when it lists ranked projects (the page's own rule).
        $categories = collect(array_keys(config('investment_index.categories')))
            ->filter(fn ($c) => $index->leaderboard($c, null, 1)->isNotEmpty())
            ->map(fn ($c) => ['loc' => route('rankings.category', $c), 'lastmod' => $lastmod]);

        $ranked = $run->scores()->where('status', 'ranked')->with('project:id,slug,city,is_demo,is_published')->get()
            ->filter(fn ($s) => $s->project && ! $s->project->is_demo && $s->project->is_published);

        return $categories
            ->concat($ranked->map(fn ($s) => ['loc' => route('rankings.city-category', [Cities::slug($s->project->city), $s->category]), 'lastmod' => $lastmod])->unique('loc'))
            ->concat($index->overall(1)->isNotEmpty() ? [['loc' => route('rankings.overall'), 'lastmod' => $lastmod]] : [])
            ->concat($ranked->map(fn ($s) => ['loc' => route('rankings.project', $s->project), 'lastmod' => $lastmod]))
            ->values();
    }

    /**
     * MPIS dashboards with a scored entry in a real verified snapshot. Demo
     * snapshots and pages without verified data are never offered to search engines.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string}>
     */
    protected function rankingDashboards(): Collection
    {
        $calc = InvestmentCalculation::where('status', 'published')->where('includes_demo', false)->latest('published_at')->latest('id')->first();
        if (! $calc) {
            return collect();
        }
        $lastmod = $calc->published_at?->toAtomString();
        $segments = RankingBrowser::publicSegments();
        $rows = $calc->rankings()->whereIn('segment', array_keys($segments))->ranked()->toBase()->select('segment', 'city')->distinct()->get();

        return $rows->pluck('city')->unique()->map(fn ($city) => ['loc' => route('rankings.city', Cities::slug($city)), 'lastmod' => $lastmod])
            ->concat($rows->pluck('segment')->unique()->map(fn ($s) => ['loc' => route('rankings.segment', ['pakistan', $segments[$s]['group'], $s]), 'lastmod' => $lastmod]))
            ->concat($rows->map(fn ($r) => ['loc' => route('rankings.segment', [Cities::slug($r->city), $segments[$r->segment]['group'], $r->segment]), 'lastmod' => $lastmod]))
            ->values();
    }

    /** @return Collection<int, array{loc: string, lastmod: ?string}> */
    protected function locations(): Collection
    {
        $urls = collect();

        foreach (Cities::names() as $city) {
            foreach (Seo::categoryCounts($city) as $category => $counts) {
                if (Seo::indexable($counts['real'])) {
                    $urls->push(['loc' => route('cities.category', [Cities::slug($city), $category]), 'lastmod' => null]);
                }
            }
        }

        // Area guides that are indexed: popular ones and those with live listings (as on the page itself).
        $areas = Area::active()->whereIn('city', Cities::names())
            ->where(fn ($q) => $q->where('is_popular', true)->orWhereHas('properties', fn ($p) => $p->published()))
            ->ordered()->get(['id', 'slug', 'city', 'updated_at']);

        foreach ($areas as $area) {
            $urls->push(['loc' => route('areas.show', $area->slug), 'lastmod' => null]);
        }
        // Area category pages only where an area has real listings at all.
        foreach ($areas->filter(fn ($a) => Property::published()->where('is_demo', false)->where('area_id', $a->id)->exists()) as $area) {
            foreach (Seo::categoryCounts($area->city, $area) as $category => $counts) {
                if (Seo::indexable($counts['real'])) {
                    $urls->push(['loc' => route('areas.category', [$area->slug, $category]), 'lastmod' => null]);
                }
            }
        }

        return $urls;
    }

    /** Project directory profiles with researched content (Project::indexable, as on the page). */
    protected function projects(int $page): Collection
    {
        return Project::indexable()->orderBy('id')->forPage($page, self::PER_FILE)->get(['id', 'slug', 'updated_at'])
            ->map(fn ($p) => ['loc' => route('projects.show', $p), 'lastmod' => $p->updated_at?->toAtomString()]);
    }

    /** Public dealer profiles: the same set the dealer directory lists. */
    protected function agents(): Collection
    {
        return DealerDirectoryController::listed()->orderBy('id')->get(['id', 'slug', 'updated_at'])
            ->map(fn ($d) => ['loc' => route('dealers.show', $d), 'lastmod' => $d->updated_at?->toAtomString()]);
    }

    /** @return Collection<int, array{loc: string, lastmod: ?string}> */
    protected function properties(int $page): Collection
    {
        return Property::published()->where('is_demo', false)->orderBy('id')
            ->forPage($page, self::PER_FILE)->get(['id', 'slug', 'updated_at'])
            ->map(fn ($p) => ['loc' => route('properties.show', $p), 'lastmod' => $p->updated_at?->toAtomString()]);
    }

    protected function propertyFiles(): int
    {
        return (int) ceil(Property::published()->where('is_demo', false)->count() / self::PER_FILE);
    }

    protected function projectFiles(): int
    {
        return (int) ceil(Project::indexable()->count() / self::PER_FILE);
    }

    protected function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml', 'X-Robots-Tag' => 'noindex']);
    }
}
