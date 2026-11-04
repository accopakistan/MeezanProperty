<?php

namespace App\Http\Controllers;

use App\Models\InvestmentCalculation;
use App\Models\InvestmentMethodology;
use App\Models\InvestmentRanking;
use App\Services\Investment\InvestmentInsights;
use App\Services\Investment\MarketMetrics;
use App\Support\Cities;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public investment intelligence: rankings, analysis, comparison,
 * methodology and the gross-yield calculator. Reads published snapshots
 * only: never drafts, unverified records or anything about users.
 */
class InvestmentController extends Controller
{
    protected const RISK_ORDER = ['low' => 1, 'medium' => 2, 'high' => 3, 'insufficient' => 4];

    protected const CONFIDENCE_ORDER = ['high' => 4, 'medium' => 3, 'limited' => 2, 'insufficient' => 1];

    public function index(Request $request): View
    {
        $categories = config('meezan.investment.categories');
        $f = $request->validate([
            'category' => ['nullable', 'in:'.implode(',', array_keys($categories))],
            'asset' => ['nullable', 'in:residential,commercial'],
            'level' => ['nullable', 'in:city,area,project'],
            'city' => ['nullable', 'in:'.implode(',', Cities::names())],
            'risk' => ['nullable', 'in:low,medium,high'],
            'confidence' => ['nullable', 'in:high,medium,limited'],
            'period' => ['nullable', 'in:1y,2y'],
            'budget' => ['nullable', 'integer', 'min:100000'],
            'min_yield' => ['nullable', 'numeric', 'between:0,50'],
            'min_growth' => ['nullable', 'numeric', 'between:-100,500'],
            'property_type' => ['nullable', 'string', 'max:30'],
        ]);
        $category = $f['category'] ?? 'overall';
        $asset = $categories[$category]['asset_class'] ?? ($f['asset'] ?? 'residential');
        $period = $f['period'] ?? '1y';

        $calc = InvestmentCalculation::current();
        $rows = $calc ? $calc->rankings()->where('category', $category)->where('asset_class', $asset)
            ->when($f['city'] ?? null, fn ($q, $c) => $q->where('city', $c))
            ->when($f['level'] ?? null, fn ($q, $l) => $q->where('entity_type', $l))
            ->get() : collect();

        $insufficient = $rows->where('status', '!=', 'ranked')->count();
        $ranked = $rows->where('status', 'ranked')
            ->filter(fn ($r) => ! isset($f['risk']) || (self::RISK_ORDER[$r->risk] ?? 9) <= self::RISK_ORDER[$f['risk']])
            ->filter(fn ($r) => ! isset($f['confidence']) || (self::CONFIDENCE_ORDER[$r->confidence] ?? 0) >= self::CONFIDENCE_ORDER[$f['confidence']])
            ->filter(fn ($r) => ! isset($f['budget']) || (($p = $r->metric('current.amount')) !== null && $p <= $f['budget']))
            ->filter(fn ($r) => ! isset($f['min_yield']) || (($y = $r->metric('yield.gross')) !== null && $y >= $f['min_yield']))
            ->filter(fn ($r) => ! isset($f['min_growth']) || (($g = $r->metric($period === '2y' ? 'change_2y.percent' : 'change_1y.percent')) !== null && $g >= $f['min_growth']))
            ->filter(fn ($r) => empty($f['property_type']) || $r->metric('current.type') === $f['property_type'])
            ->sortBy([['score', 'desc'], ['confidence_score', 'desc'], ['entity_name', 'asc']])->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($ranked->forPage($page, 20)->values(), $ranked->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('investment.index', [
            'calc' => $calc,
            'rankings' => $paginator,
            'insufficient' => $insufficient,
            'filters' => $f + ['category' => $category, 'asset' => $asset, 'period' => $period],
            'categories' => $categories,
        ]);
    }

    public function show(Request $request, string $entityType, int $entityId, InvestmentInsights $insights): View
    {
        $calc = InvestmentCalculation::current();
        $rows = $calc ? $calc->rankings()->where('entity_type', $entityType)->where('entity_id', $entityId)->whereNull('segment')->get() : collect();

        $assets = $rows->pluck('asset_class')->unique()->values();
        $asset = in_array($request->query('asset'), $assets->all(), true) ? $request->query('asset') : $assets->first();
        $forAsset = $rows->where('asset_class', $asset);
        $category = $forAsset->firstWhere('category', $request->query('category')) ? $request->query('category') : ($forAsset->firstWhere('category', 'overall') ? 'overall' : $forAsset->first()?->category);
        $row = $forAsset->firstWhere('category', $category);

        // Earlier published snapshots for the same entry (history is never rewritten).
        $history = $row ? InvestmentRanking::query()
            ->whereIn('calculation_id', InvestmentCalculation::whereIn('status', ['published', 'superseded'])->select('id'))
            ->where(['entity_type' => $entityType, 'entity_id' => $entityId, 'asset_class' => $asset, 'category' => $category])
            ->with('calculation:id,published_at,methodology_version')
            ->orderBy('calculation_id')->get(['id', 'calculation_id', 'score', 'status', 'confidence', 'methodology_version']) : collect();

        return view('investment.show', [
            'calc' => $calc,
            'row' => $row,
            'rows' => $forAsset,
            'assets' => $assets,
            'asset' => $asset,
            'category' => $category,
            'insight' => $row ? $insights->for($row) : null,
            'history' => $history,
            'entityType' => $entityType,
            'entityId' => $entityId,
        ]);
    }

    public function compare(Request $request): View
    {
        $f = $request->validate([
            'items' => ['nullable', 'array', 'max:4'],
            'items.*' => ['string', 'regex:/^(city|area|project):\d+$/'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(config('meezan.investment.categories')))],
            'asset' => ['nullable', 'in:residential,commercial'],
        ]);
        $category = $f['category'] ?? 'overall';
        $asset = config('meezan.investment.categories.'.$category.'.asset_class') ?? ($f['asset'] ?? 'residential');
        $calc = InvestmentCalculation::current();

        $items = collect($f['items'] ?? [])->unique()->values();
        $rows = collect();
        if ($calc && $items->isNotEmpty()) {
            $rows = $calc->rankings()->where('category', $category)->where('asset_class', $asset)
                ->where(function ($q) use ($items) {
                    foreach ($items as $item) {
                        [$type, $id] = explode(':', $item);
                        $q->orWhere(fn ($w) => $w->where('entity_type', $type)->where('entity_id', (int) $id));
                    }
                })->get();
        }

        // Candidates to add: ranked entries in the same category and asset class.
        $candidates = $calc ? $calc->rankings()->where('category', $category)->where('asset_class', $asset)->ranked()
            ->orderBy('city')->orderBy('entity_name')->limit(300)->get(['entity_type', 'entity_id', 'entity_name', 'city']) : collect();

        return view('investment.compare', [
            'calc' => $calc,
            'rows' => $rows,
            'winners' => $this->winners($rows),
            'candidates' => $candidates,
            'category' => $category,
            'asset' => $asset,
            'items' => $items,
        ]);
    }

    public function methodology(): View
    {
        $calc = InvestmentCalculation::current();

        return view('investment.methodology', [
            'active' => InvestmentMethodology::active(),
            'published' => $calc ? InvestmentMethodology::find($calc->methodology_id) : null,
            'calc' => $calc,
        ]);
    }

    public function yieldCalculator(Request $request): View
    {
        $input = $request->validate([
            'price' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
            'rent' => ['nullable', 'integer', 'min:1', 'max:9999999999'],
            'costs' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
        ]);
        $result = null;
        if (! empty($input['price']) && ! empty($input['rent'])) {
            $result = [
                'gross' => MarketMetrics::yieldPercent($input['rent'], $input['price']),
                'net' => isset($input['costs']) ? MarketMetrics::netYieldPercent($input['rent'], $input['costs'], $input['price']) : null,
                'annual_rent' => $input['rent'] * 12,
            ];
        }

        return view('investment.yield', ['input' => $input, 'result' => $result]);
    }

    /**
     * Highlight a winner only when every compared entry has the metric and
     * none has insufficient confidence.
     *
     * @return array<string, ?string> metric => "type:id"
     */
    protected function winners(Collection $rows): array
    {
        if ($rows->count() < 2 || $rows->contains(fn ($r) => $r->confidence === 'insufficient')) {
            return [];
        }
        $pick = function (callable $value, bool $highest = true) use ($rows) {
            $values = $rows->map(fn ($r) => ['key' => $r->entity_type.':'.$r->entity_id, 'v' => $value($r)]);
            if ($values->contains(fn ($v) => $v['v'] === null)) {
                return null;
            }
            $sorted = $highest ? $values->sortByDesc('v') : $values->sortBy('v');
            [$first, $second] = [$sorted->values()[0], $sorted->values()[1]];

            return $first['v'] == $second['v'] ? null : $first['key'];
        };

        return [
            'score' => $pick(fn ($r) => $r->isRanked() ? $r->score : null),
            'growth' => $pick(fn ($r) => $r->metric('change_1y.percent')),
            'yield' => $pick(fn ($r) => $r->metric('yield.gross')),
            'risk' => $pick(fn ($r) => $r->metric('risk.score'), highest: false),
        ];
    }
}
