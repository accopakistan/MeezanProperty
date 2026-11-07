<?php

namespace App\Http\Controllers;

use App\Services\Investment\AdvisorGateway;
use App\Services\Investment\InvestmentAdvisor;
use App\Services\Investment\InvestmentInsights;
use App\Services\Investment\RankingBrowser;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Support\Cities;
use App\Support\PriceChart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Investment Rankings dashboards: first a city, then a group (residential,
 * commercial), then one of the five development categories with its own table,
 * map, charts, comparison and AI advisor. Reads the published verified snapshot
 * only (RankingBrowser), and shows each city's Meezan Investment Index results.
 * Individual plots, houses, shops and offices stay in the marketplace: their
 * former pages redirect to the city dashboard. A request with the
 * X-Rankings-Partial header gets just the results block, so tabs, sorting and
 * pages update in place (links work without JavaScript).
 */
class InvestmentRankingsController extends Controller
{
    protected const PER_PAGE = 15;

    public function __construct(protected RankingBrowser $browser) {}

    public function index(Request $request, InvestmentAdvisor $advisor): View|RedirectResponse
    {
        $f = $this->filters($request);
        // The hero search form: go to the chosen city / category with the filters.
        if ($request->boolean('go')) {
            $cityInput = trim((string) $request->query('city'));
            $city = in_array(mb_strtolower($cityInput), array_merge(['pakistan'], array_map([Cities::class, 'slug'], Cities::names())), true)
                ? mb_strtolower($cityInput)
                : (Cities::slug($cityInput) ?? 'pakistan');
            $segment = RankingBrowser::publicSegment((string) $request->query('segment')) ? $request->query('segment') : null;
            $query = $f + array_filter(['period' => $request->query('period') !== '2y' ? $request->query('period') : null]);

            if ($segment !== null) {
                $cat = IndexBrowser::categoryForSegment($segment);
                return $cat && $city !== 'pakistan'
                    ? redirect()->route('rankings.city-category', [$city, $cat] + $query)
                    : redirect()->route('rankings.category', [$cat ?? 'housing-societies'] + $query);
            }

            return $city === 'pakistan'
                ? redirect()->route('rankings.index')
                : redirect()->route('rankings.city', [$city] + $query);
        }
        $featured = collect(array_keys(RankingBrowser::publicSegments()))->mapWithKeys(fn ($s) => [$s => $this->browser->publicItems($s, null, ['sort' => 'score'])
            ->filter(fn ($i) => in_array($i['status'], ['ranked', 'provisional'], true))->take(5)->values()]);

        return view('rankings.index', [
            'calc' => $this->browser->calculation(),
            'cities' => $this->browser->cities(),
            'indexBoards' => $this->indexBoards(null),
            'overview' => $this->browser->overview(null),
            'featured' => $featured,
            'upcoming' => $this->browser->upcoming(null),
            'advice' => $this->advice($request, $advisor, null, null),
            'filters' => $f,
            'indexable' => ! $this->browser->showsDemo() && $featured->flatten(1)->isNotEmpty(),
        ]);
    }

    public function city(Request $request, string $city, InvestmentAdvisor $advisor): View|RedirectResponse
    {
        if ($city === 'pakistan') {
            return $this->index($request, $advisor);
        }
        $name = $this->cityOr404($city);
        $f = $this->filters($request);
        $tab = is_string($request->query('tab')) ? $request->query('tab') : 'all';
        if (RankingBrowser::segment($tab) && ! RankingBrowser::publicSegment($tab)) {
            return redirect()->route('rankings.city', $city, 301);
        }
        abort_unless(isset(RankingBrowser::SPECIAL_TABS[$tab]) || RankingBrowser::publicSegment($tab), 404);

        [$items, $segmentKey] = $this->tabItems($tab, $name, $f);
        $indexBrowser = app(IndexBrowser::class);
        $run = $indexBrowser->run();
        $cityOverall = $indexBrowser->overall(10, $name);
        $researchTotals = $indexBrowser->researchTotals($name);
        $cityCounts = $indexBrowser->counts(null, $name);
        $categoryCounts = collect(IndexBrowser::categories())->mapWithKeys(fn ($c, $k) => [$k => $indexBrowser->counts($k, $name)])->all();

        $data = [
            'calc' => $this->browser->calculation(),
            'run' => $run,
            'city' => $name,
            'citySlug' => $city,
            'cities' => $this->browser->cities(),
            'indexBoards' => $this->indexBoards($name),
            'cityOverall' => $cityOverall,
            'researchTotals' => $researchTotals,
            'cityCounts' => $cityCounts,
            'categoryCounts' => $categoryCounts,
            'cityAnalytics' => app(\App\Services\InvestmentIndex\IndexAnalytics::class)->cityAnalytics($name),
            'overview' => $this->browser->overview($name),
            'tab' => $tab,
            'segmentKey' => $segmentKey,
            'items' => $this->paginate($items, $request),
            'filters' => $f,
            'markers' => $this->browser->markers($items, fn ($i) => $this->itemUrl($i, $name, $segmentKey)),
            'upcoming' => $this->browser->upcoming($name),
            'advice' => $this->advice($request, $advisor, $name, $segmentKey),
            'indexable' => ! $this->browser->showsDemo() && ($items->contains(fn ($i) => in_array($i['status'], ['ranked', 'provisional'], true)) || $cityOverall->isNotEmpty()),
        ];

        return $request->hasHeader('X-Rankings-Partial') ? view('rankings.partials.city-results', $data) : view('rankings.city', $data);
    }

    public function group(Request $request, string $city, string $group, InvestmentAdvisor $advisor): View|RedirectResponse
    {
        $name = $this->cityOr404($city);
        $segments = RankingBrowser::publicSegmentsIn($group);
        // Mixed use is outside the five development categories.
        if ($segments === []) {
            return redirect()->route('rankings.city', $city, 301);
        }

        $previews = collect($segments)->map(fn ($seg, $slug) => [
            'segment' => $seg,
            'items' => $all = $this->browser->publicItems($slug, $name, ['sort' => 'score']),
            'scored' => $all->filter(fn ($i) => in_array($i['status'], ['ranked', 'provisional'], true))->take(3)->values(),
        ]);

        return view('rankings.group', [
            'calc' => $this->browser->calculation(),
            'city' => $name,
            'citySlug' => $city,
            'group' => $group,
            'groupLabel' => config('meezan.investment.groups.'.$group.'.label'),
            'cities' => $this->browser->cities(),
            'previews' => $previews,
            'upcoming' => $this->browser->upcoming($name, RankingBrowser::projectCategoriesFor($group)),
            'advice' => $this->advice($request, $advisor, $name, array_key_first($segments)),
            'indexable' => ! $this->browser->showsDemo() && $previews->contains(fn ($p) => $p['scored']->isNotEmpty()),
        ]);
    }

    public function segment(Request $request, string $city, string $group, string $segment, InvestmentAdvisor $advisor): View|RedirectResponse
    {
        $name = $this->cityOr404($city);
        $seg = RankingBrowser::segment($segment);
        abort_unless($seg && $seg['group'] === $group, 404);
        if (! RankingBrowser::publicSegment($segment)) {
            return redirect()->route('rankings.city', $city, 301);
        }
        $f = $this->filters($request);
        $items = $this->browser->publicItems($segment, $name, $f);
        // Unfiltered list for the map, featured cards and the city counts.
        $all = array_diff_key($f, ['sort' => 1, 'dir' => 1]) === [] ? $items : $this->browser->publicItems($segment, $name, ['sort' => 'score']);

        $selected = $items->firstWhere('key', $request->query('selected'))
            ?? $all->firstWhere('key', $request->query('selected'))
            ?? $items->first(fn ($i) => in_array($i['status'], ['ranked', 'provisional'], true))
            ?? $items->first();
        $period = array_key_exists($request->query('period'), PriceChart::PERIODS) ? $request->query('period') : '2y';

        $data = [
            'calc' => $this->browser->calculation(),
            'city' => $name,
            'citySlug' => $city,
            'group' => $group,
            'segmentKey' => $segment,
            'seg' => $seg,
            'indexCategory' => IndexBrowser::categoryForSegment($segment),
            'cities' => $this->browser->cities($segment),
            'items' => $this->paginate($items, $request),
            'total' => $items->count(),
            'scoredCount' => $all->whereIn('status', ['ranked', 'provisional'])->count(),
            // Projects marked featured first, then the best scored.
            'featured' => $all->filter(fn ($i) => in_array($i['status'], ['ranked', 'provisional'], true))->sortByDesc(fn ($i) => (int) $i['featured'])->take(8)->values()
                ->whenEmpty(fn () => $all->filter(fn ($i) => $i['project'])->take(8)->values()),
            'selected' => $selected,
            'insight' => $selected && $selected['row'] ? app(InvestmentInsights::class)->for($selected['row']) : null,
            'chart' => $selected && $selected['row'] ? PriceChart::build($selected['row']->metric('series', []), $period) : null,
            'period' => $period,
            'filters' => $f,
            'sizes' => $this->sizeOptions($seg, $all),
            'markers' => $this->browser->markers($all, fn ($i) => $this->itemUrl($i, $name, $segment)),
            'upcoming' => $this->browser->upcoming($name, $seg['project_types']),
            'advice' => $this->advice($request, $advisor, $name, $segment),
            'indexable' => ! $this->browser->showsDemo() && $all->contains(fn ($i) => in_array($i['status'], ['ranked', 'provisional'], true)),
        ];

        if ($request->hasHeader('X-Rankings-Partial')) {
            return view($request->query('part') === 'performance' ? 'rankings.partials.performance' : 'rankings.partials.segment-results', $data);
        }

        return view('rankings.segment', $data);
    }

    public function compare(Request $request, string $city, string $group, string $segment): View|RedirectResponse
    {
        $name = $this->cityOr404($city);
        $seg = RankingBrowser::segment($segment);
        abort_unless($seg && $seg['group'] === $group, 404);
        if (! RankingBrowser::publicSegment($segment)) {
            return redirect()->route('rankings.city', $city, 301);
        }
        $request->validate(['items' => ['nullable', 'array', 'max:4'], 'items.*' => ['string', 'regex:/^(area|project):\d+(:[a-z0-9-]+)?$/']]);

        $all = $this->browser->publicItems($segment, $name, ['sort' => 'score']);
        $keys = collect($request->query('items', []))->unique()->values();
        $rows = $keys->map(fn ($k) => $all->firstWhere('key', $k))->filter()->values();

        return view('rankings.compare', [
            'calc' => $this->browser->calculation(),
            'city' => $name,
            'citySlug' => $city,
            'group' => $group,
            'segmentKey' => $segment,
            'seg' => $seg,
            'rows' => $rows,
            'candidates' => $all->reject(fn ($i) => $keys->contains($i['key']))->take(60)->values(),
            'keys' => $keys,
            'winners' => $this->winners($rows),
            'mixedSizes' => $rows->pluck('size_key')->unique()->count() > 1,
        ]);
    }

    /** JSON for the AI Investment Advisor panel. */
    public function advisor(Request $request, AdvisorGateway $advisor): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:300'],
            'city' => ['nullable', 'string', 'in:'.implode(',', Cities::names())],
            'segment' => ['nullable', 'string', 'in:'.implode(',', array_keys(RankingBrowser::segments()))],
            'focus' => ['nullable', 'in:best,compare,rental,risk,forecast'],
        ]);
        $a = $advisor->ask($data['question'], $data['city'] ?? null, $data['segment'] ?? null, $data['focus'] ?? null, $request->user());

        return response()->json([
            'demo' => $a['demo'],
            'task' => $a['task'],
            'answer' => $a['answer'],
            'missing' => $a['missing'],
            'sources' => $a['sources'],
            'mode' => $a['mode'],
            'methodology' => $a['methodology'],
            'published_at' => $a['published_at'],
            'items' => collect($a['items'])->map(fn ($i) => [
                'name' => $i['name'], 'location' => $i['location'], 'group' => $i['group_label'], 'score' => $i['score'], 'status' => $i['status_label'],
                'confidence' => $i['confidence_label'], 'yield' => $i['yield'], 'image' => $i['image'], 'demo' => $i['is_demo'],
                'url' => $this->itemUrl($i, $a['city'], $a['segment']),
            ])->all(),
            'link' => route('rankings.segment', [RankingBrowser::citySlug($a['city']), RankingBrowser::segment($a['segment'])['group'], $a['segment']]),
        ]);
    }

    /* Helpers --------------------------------------------------------------- */

    protected function cityOr404(string $slug): ?string
    {
        $city = RankingBrowser::cityFromSlug($slug);
        abort_if($city === false, 404);

        return $city;
    }

    protected function filters(Request $request): array
    {
        return array_filter($request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'size' => ['nullable', 'string', 'max:20', 'regex:/^[a-z0-9-]+$/'],
            'stage' => ['nullable', 'in:'.implode(',', array_keys(RankingBrowser::PROJECT_STAGES))],
            'risk' => ['nullable', 'in:low,medium,high'],
            'confidence' => ['nullable', 'in:high,medium,limited'],
            'min_yield' => ['nullable', 'numeric', 'between:0,50'],
            'budget' => ['nullable', 'integer', 'min:100000'],
            'sort' => ['nullable', 'in:score,price,yield,change,name'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]), fn ($v) => $v !== null && $v !== '');
    }

    /** The Meezan Investment Index top three of each category, for one city or all of Pakistan. */
    protected function indexBoards(?string $city): array
    {
        return collect(IndexBrowser::categories())->map(fn ($c, $key) => $c + ['key' => $key, 'top' => app(IndexBrowser::class)->leaderboard($key, $city, 3), 'counts' => app(IndexBrowser::class)->counts($key, $city)])->all();
    }

    /** City-page tab → items (and the segment its rows link into). */
    protected function tabItems(string $tab, string $city, array $f): array
    {
        if (RankingBrowser::publicSegment($tab)) {
            return [$this->browser->publicItems($tab, $city, $f), $tab];
        }
        $everything = fn () => collect(array_keys(RankingBrowser::publicSegments()))->flatMap(fn ($s) => $this->browser->publicItems($s, $city, $f)->map(fn ($i) => $i + ['segment' => $s]));

        return match ($tab) {
            'highest-yield' => [$everything()->filter(fn ($i) => $i['yield'] !== null)->sortByDesc('yield')->unique('key')->values(), null],
            'fastest-growing' => [$everything()->filter(fn ($i) => ($i['row']?->metric('annualised')) !== null)->sortByDesc(fn ($i) => $i['row']->metric('annualised'))->unique('key')->values(), null],
            'new-upcoming' => [$everything()->filter(fn ($i) => in_array($i['stage'], ['upcoming', 'under_construction'], true) || ($i['row']?->metric('emerging')))->unique('key')->values(), null],
            default => [$everything()->unique('key')->sortBy(fn ($i) => [RankingBrowser::STATUS_ORDER[$i['status']], -($i['score'] ?? 0), $i['name']])->values(), null],
        };
    }

    protected function itemUrl(array $i, ?string $city, ?string $segment): string
    {
        $segment ??= $i['segment'] ?? 'societies';

        return route('rankings.segment', [RankingBrowser::citySlug($city), RankingBrowser::segment($segment)['group'], $segment, 'selected' => $i['key']]).'#performance';
    }

    protected function paginate(Collection $items, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator($items->forPage($page, self::PER_PAGE)->values(), $items->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->except(['part'])]);
    }

    /** Configured sizes first, then any other size that has verified data. */
    protected function sizeOptions(array $seg, Collection $items): array
    {
        if (empty($seg['group_by'])) {
            return [];
        }
        $options = collect($seg['group_by'] === 'bedrooms'
            ? collect($seg['bedrooms'])->mapWithKeys(fn ($label, $n) => [$n.'-bed' => $label])
            : collect($seg['sizes'] ?? [])->mapWithKeys(fn ($s) => [Str::slug($s) => $s]));

        return $options->union($items->filter(fn ($i) => $i['size_key'])->mapWithKeys(fn ($i) => [$i['size_key'] => $i['group_label']]))->all();
    }

    protected function advice(Request $request, InvestmentAdvisor $advisor, ?string $city, ?string $segment): ?array
    {
        // "example" is a suggested question button; "ask" the typed question.
        $ask = trim((string) ($request->query('ask') ?: $request->query('example', '')));
        $focus = in_array($request->query('focus'), ['compare', 'rental', 'risk', 'forecast'], true) ? $request->query('focus') : null;

        return $ask !== '' && mb_strlen($ask) <= 300 ? app(AdvisorGateway::class)->ask($ask, $city, $segment, $focus, $request->user()) : null;
    }

    /** Best value per metric, only when every compared entry has it. */
    protected function winners(Collection $rows): array
    {
        if ($rows->count() < 2) {
            return [];
        }
        $pick = function (callable $value, bool $highest = true) use ($rows) {
            $values = $rows->map(fn ($r) => ['key' => $r['key'], 'v' => $value($r)]);
            if ($values->contains(fn ($v) => $v['v'] === null)) {
                return null;
            }
            $sorted = ($highest ? $values->sortByDesc('v') : $values->sortBy('v'))->values();

            return $sorted[0]['v'] == $sorted[1]['v'] ? null : $sorted[0]['key'];
        };

        return [
            'score' => $pick(fn ($r) => $r['score']),
            'yield' => $pick(fn ($r) => $r['yield']),
            'change_1y' => $pick(fn ($r) => $r['change_1y']),
            'risk' => $pick(fn ($r) => $r['row']?->metric('risk.score'), highest: false),
        ];
    }
}
