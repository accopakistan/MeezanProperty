<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentIndexRun;
use App\Models\Package;
use App\Models\Property;
use App\Services\Investment\RankingBrowser;
use App\Support\Cities;
use App\Support\Legal;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * XML sitemaps: /sitemap.xml (a single list while the site is small, an index otherwise) and
 * - /sitemaps/pages.xml      static public pages and the seven city pages,
 * - /sitemaps/locations.xml  area guides that are indexable, and city/area
 *                            category pages with enough real listings,
 * - /sitemaps/properties-N.xml  live, non-demo listings, PER_FILE per file.
 *
 * Only URLs that are public and indexable are included (the same rules the
 * pages use for their robots meta). Each file is cached for an hour.
 */
class SitemapController extends Controller
{
    public const PER_FILE = 5000;

    protected const TTL = 3600;

    /**
     * While everything fits in one file (the protocol allows 50,000 URLs)
     * /sitemap.xml lists the URLs directly; beyond that it becomes an index
     * of the split files, which always exist.
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap:index', self::TTL, function () {
            if ($this->propertyFiles() <= 1) {
                return view('sitemap', ['urls' => $this->pages()->concat($this->locations())->concat($this->properties(1))])->render();
            }

            $files = collect(['pages', 'locations'])
                ->concat(range(1, max(1, $this->propertyFiles())))
                ->map(fn ($name) => is_int($name) ? 'properties-'.$name : $name);

            return view('sitemaps.index', ['sitemaps' => $files->map(fn ($name) => route('sitemap.show', $name))])->render();
        });

        return $this->xml($xml);
    }

    public function show(string $name): Response
    {
        if (str_starts_with($name, 'properties-')) {
            $page = (int) substr($name, 11);
            abort_if($page > max(1, $this->propertyFiles()), 404);
        }

        $xml = Cache::remember('sitemap:'.$name, self::TTL, fn () => view('sitemap', ['urls' => match (true) {
            $name === 'pages' => $this->pages(),
            $name === 'locations' => $this->locations(),
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
            ->concat(collect(Cities::names())->map(fn ($city) => ['loc' => route('cities.show', Cities::slug($city)), 'lastmod' => null]))
            ->push(['loc' => route('rankings.index'), 'lastmod' => null])
            ->concat($this->rankings())
            ->concat($this->rankingDashboards());
    }

    /**
     * Meezan Investment Index pages: the methodology always; a leaderboard and
     * the Overall Index once they have ranked projects; profiles of ranked
     * projects (pages without verified results are noindex and stay out).
     *
     * @return Collection<int, array{loc: string, lastmod: ?string}>
     */
    protected function rankings(): Collection
    {
        // The methodology and the five category pages always: they list researched projects and explain why they are not yet ranked.
        $urls = collect([['loc' => route('rankings.methodology'), 'lastmod' => null]])
            ->concat(collect(array_keys(config('investment_index.categories')))->map(fn ($c) => ['loc' => route('rankings.category', $c), 'lastmod' => null]));
        $run = InvestmentIndexRun::latestPublished();
        if (! $run) {
            return $urls;
        }
        $lastmod = $run->published_at?->toAtomString();
        $ranked = $run->scores()->where('status', 'ranked')->with('project:id,slug,city,is_demo,is_published')->get()
            ->filter(fn ($s) => $s->project && ! $s->project->is_demo && $s->project->is_published);

        return $urls
            ->concat($ranked->map(fn ($s) => ['loc' => route('rankings.city-category', [Cities::slug($s->project->city), $s->category]), 'lastmod' => $lastmod])->unique('loc'))
            ->concat($ranked->whereNotNull('overall_rank')->isNotEmpty() ? [['loc' => route('rankings.overall'), 'lastmod' => $lastmod]] : [])
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
            ->ordered()->get(['id', 'slug', 'city']);

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

    protected function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml', 'X-Robots-Tag' => 'noindex']);
    }
}
