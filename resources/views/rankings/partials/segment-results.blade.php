{{--
    Ranking table for one segment (replaced in place by sorting, filters and pages).
    $items (paginator of RankingBrowser items), $seg, $segmentKey, $citySlug, $group, $filters, $sizes.
--}}
@php
    use App\Services\Investment\InvestmentInsights;
    $headers = ['size' => $seg['group_by'] === 'bedrooms' ? 'Bedrooms' : 'Size', 'price' => 'Current Price', 'price_sqft' => 'Price (per sq ft)', 'change_1y' => '1 Yr Change',
        'change_2y' => '2 Yr Change', 'yield' => 'Rental Yield (Annual)', 'score' => 'MPIS Score', 'risk' => 'Risk', 'occupancy' => 'Occupancy', 'development' => 'Development', 'status' => 'Status'];
    $sortable = ['price' => 'price', 'price_sqft' => 'price', 'change_1y' => 'change', 'yield' => 'yield', 'score' => 'score'];
    $sort = $filters['sort'] ?? 'score';
    $dir = $filters['dir'] ?? (in_array($sort, ['name', 'price'], true) ? 'asc' : 'desc');
    $sortUrl = fn (string $key) => request()->fullUrlWithQuery(['sort' => $key, 'dir' => $sort === $key && $dir === 'desc' ? 'asc' : 'desc', 'page' => null, 'selected' => null]);
    $pct = fn ($v) => $v === null ? null : InvestmentInsights::signed($v).'%';
    $cell = function (string $col, array $i) use ($pct) {
        return match ($col) {
            'size' => e($i['group_label'] ?? '—'),
            'price' => $i['price'] ? e($i['price']['label']).'<span class="block text-[11px] text-muted">'.e($i['price']['basis_label']).'</span>' : '<span class="text-muted">No verified price</span>',
            'price_sqft' => ($i['price']['per_sqft'] ?? null) ? 'PKR '.number_format($i['price']['per_sqft']).'<span class="block text-[11px] text-muted">'.e($i['price']['basis_label']).'</span>' : ($i['price'] ? e($i['price']['label']) : '<span class="text-muted">No verified price</span>'),
            'change_1y', 'change_2y' => $i[$col] === null ? '<span class="text-muted">Insufficient data</span>' : '<span class="font-semibold '.($i[$col] >= 0 ? 'text-emerald-700' : 'text-red-600').'">'.($i[$col] >= 0 ? '▲ ' : '▼ ').e($pct($i[$col])).'</span>',
            'yield' => $i['yield'] === null ? '<span class="text-muted">No data</span>' : '<span class="font-semibold text-emerald-700">'.e($i['yield']).'%</span>',
            'risk' => '<span class="font-semibold '.match ($i['risk']) { 'low' => 'text-emerald-700', 'medium' => 'text-amber-700', 'high' => 'text-red-600', default => 'text-muted' }.'">'.e($i['risk_label']).'</span>',
            'occupancy' => $i['occupancy'] === null ? '<span class="text-muted">Not verified</span>' : e(rtrim(rtrim(number_format($i['occupancy'], 1), '0'), '.')).'%',
            'development' => $i['development'] === null ? '<span class="text-muted">No record</span>' : e(rtrim(rtrim(number_format($i['development'], 1), '0'), '.')).'%',
            'status' => e($i['stage_label'] ?? '—'),
            default => '',
        };
    };
    $selectUrl = fn ($i) => request()->fullUrlWithQuery(['selected' => $i['key']]).'#performance';
@endphp
<div data-rankings-results>
    <form method="GET" class="flex flex-wrap items-end gap-2 border-b border-line p-3" data-rankings-filter>
        @foreach (['sort', 'dir', 'period'] as $keep)@if (request($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif @endforeach
        <label class="min-w-40 flex-1 text-[11px] font-medium text-muted">Search
            <input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="80" class="input mt-0.5 h-9 py-1 text-[13px]" placeholder="Name or location">
        </label>
        @if ($sizes)
            <label class="text-[11px] font-medium text-muted">{{ $seg['group_by'] === 'bedrooms' ? 'Bedrooms' : 'Size' }}
                <select name="size" class="input mt-0.5 h-9 py-1 text-[13px]"><option value="">All</option>@foreach ($sizes as $k => $l)<option value="{{ $k }}" @selected(($filters['size'] ?? null) === $k)>{{ $l }}</option>@endforeach</select>
            </label>
        @endif
        @if (in_array('status', $seg['columns'], true) || $seg['entities'] === ['project'])
            <label class="text-[11px] font-medium text-muted">Status
                <select name="stage" class="input mt-0.5 h-9 py-1 text-[13px]"><option value="">All</option>@foreach (\App\Services\Investment\RankingBrowser::PROJECT_STAGES as $k => $l)<option value="{{ $k }}" @selected(($filters['stage'] ?? null) === $k)>{{ $l }}</option>@endforeach</select>
            </label>
        @endif
        <label class="text-[11px] font-medium text-muted">Risk
            <select name="risk" class="input mt-0.5 h-9 py-1 text-[13px]"><option value="">Any</option>@foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $k => $l)<option value="{{ $k }}" @selected(($filters['risk'] ?? null) === $k)>{{ $l }}</option>@endforeach</select>
        </label>
        <label class="text-[11px] font-medium text-muted">Data confidence
            <select name="confidence" class="input mt-0.5 h-9 py-1 text-[13px]"><option value="">Any</option><option value="medium" @selected(($filters['confidence'] ?? null) === 'medium')>Medium or high</option><option value="high" @selected(($filters['confidence'] ?? null) === 'high')>High</option></select>
        </label>
        <button class="btn btn-secondary btn-sm h-9">Apply</button>
    </form>

    <form method="GET" action="{{ route('rankings.compare', [$citySlug, $group, $segmentKey]) }}" data-compare-form>
        @if ($items->isEmpty())
            <div class="p-6 text-center text-sm text-muted">
                <p class="font-semibold text-ink">{{ request()->hasAny(['q', 'size', 'stage', 'risk', 'confidence', 'min_yield', 'budget']) ? 'Nothing matches these filters.' : 'Insufficient Verified Data: no '.strtolower($seg['label']).' are tracked here yet.' }}</p>
                <p class="mt-1">Rankings appear only when verified market records exist. Nothing is estimated to fill the table.</p>
            </div>
        @else
            {{-- Phones: cards --}}
            <ul class="divide-y divide-line md:hidden">
                @foreach ($items as $i)
                    <li class="flex gap-3 p-3" data-item-key="{{ $i['key'] }}">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-[12px] font-bold text-brand-700">{{ $i['score'] !== null ? $items->firstItem() + $loop->index : '–' }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ $selectUrl($i) }}" class="font-semibold text-brand-700" data-select-item="{{ $i['key'] }}">{{ $i['name'] }}</a>
                            <p class="text-[12px] text-muted">{{ $i['location'] }}@if ($i['group_label']) · {{ $i['group_label'] }}@endif</p>
                            <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[12px]">
                                @foreach (array_diff($seg['columns'], ['score', 'size']) as $col)
                                    <div class="min-w-0"><dt class="text-muted">{{ $headers[$col] }}</dt><dd>{!! $cell($col, $i) !!}</dd></div>
                                @endforeach
                            </dl>
                            <div class="mt-2 flex flex-wrap items-center gap-2">@include('rankings.partials.badges', ['badge' => 'status', 'item' => $i])
                                <label class="flex items-center gap-1.5 text-[12px]"><input type="checkbox" name="items[]" value="{{ $i['key'] }}" class="checkbox" data-compare-check> Compare</label></div>
                        </div>
                        <div class="shrink-0">@include('rankings.partials.badges', ['badge' => 'score', 'item' => $i])</div>
                    </li>
                @endforeach
            </ul>

            {{-- Tablets and up: table --}}
            <div class="relative hidden overflow-x-auto md:block">
                <table class="table table-compact text-[13px] [&_td]:px-2 [&_th]:px-2 [&_th]:normal-case [&_th]:tracking-normal">
                    <caption class="sr-only">{{ $seg['label'] }} ranked by {{ $sort }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="w-8">#</th>
                            <th scope="col"><a href="{{ $sortUrl('name') }}" data-rankings-link class="hover:text-brand-700">{{ $seg['singular'] }}</a></th>
                            @foreach ($seg['columns'] as $col)
                                <th scope="col" @if ($sort === ($sortable[$col] ?? null)) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                    @if (isset($sortable[$col]))<a href="{{ $sortUrl($sortable[$col]) }}" data-rankings-link class="hover:text-brand-700">{{ $headers[$col] }}@if ($sort === $sortable[$col]) {{ $dir === 'asc' ? '↑' : '↓' }}@endif</a>@else{{ $headers[$col] }}@endif
                                </th>
                            @endforeach
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $i)
                            <tr data-item-key="{{ $i['key'] }}" @class(['bg-brand-50/60' => ($selected['key'] ?? null) === $i['key']])>
                                <td>
                                    @php $pos = $i['score'] !== null ? $items->firstItem() + $loop->index : null; @endphp
                                    <span @class(['grid size-7 place-items-center rounded-full text-[12px] font-bold', 'bg-gold-500 text-white' => $pos === 1 && $sort === 'score', 'bg-brand-50 text-brand-700' => ! ($pos === 1 && $sort === 'score')])>{{ $pos ?? '–' }}</span>
                                </td>
                                <td class="min-w-40">
                                    <div class="flex items-center gap-2.5">
                                        @if ($i['image'])<img src="{{ $i['image'] }}" alt="" class="size-10 shrink-0 rounded-md object-cover" loading="lazy">@endif
                                        <div class="min-w-0"><a href="{{ $selectUrl($i) }}" class="font-semibold text-brand-700 hover:underline" data-select-item="{{ $i['key'] }}">{{ $i['name'] }}</a>
                                            <span class="block text-[12px] text-muted">{{ $i['location'] }}</span>
                                            @if ($i['status'] !== 'ranked')<span class="mt-0.5 inline-block">@include('rankings.partials.badges', ['badge' => 'status', 'item' => $i])</span>@endif</div>
                                    </div>
                                </td>
                                @foreach ($seg['columns'] as $col)
                                    <td class="text-[13px] whitespace-nowrap">@if ($col === 'score')@include('rankings.partials.badges', ['badge' => 'score', 'item' => $i])@else{!! $cell($col, $i) !!}@endif</td>
                                @endforeach
                                <td class="whitespace-nowrap">
                                    <a href="{{ $selectUrl($i) }}" class="btn btn-outline btn-sm" data-select-item="{{ $i['key'] }}">View</a>
                                    <label class="ml-2 inline-flex items-center gap-1 text-[12px]"><input type="checkbox" name="items[]" value="{{ $i['key'] }}" class="checkbox" data-compare-check><span class="sr-only">Compare {{ $i['name'] }}</span></label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-3">
            <p class="text-[12px] text-muted">{{ $items->total() }} {{ \Illuminate\Support\Str::plural('entry', $items->total()) }} · ranked by MPIS within the same property type @if ($seg['group_by'])and {{ $seg['group_by'] === 'bedrooms' ? 'bedroom count' : 'size' }}@endif</p>
            <button class="btn btn-gold btn-sm" data-compare-submit>Compare selected</button>
        </div>
    </form>
    @if ($items->hasPages())<div class="border-t border-line p-3" data-rankings-pages>{{ $items->onEachSide(1)->links() }}</div>@endif
</div>
