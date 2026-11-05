{{--
    City-page results for one tab (replaced in place when a tab, sort or page changes).
    $items (paginator of RankingBrowser items, each with 'segment' unless the tab is a segment), $tab, $segmentKey, $city, $citySlug.
--}}
@php
    use App\Services\Investment\InvestmentInsights;
    use App\Services\Investment\RankingBrowser;
    $label = RankingBrowser::SPECIAL_TABS[$tab] ?? RankingBrowser::segment($tab)['label'];
    $segmentOf = fn ($i) => $segmentKey ?? $i['segment'];
    $url = fn ($i) => route('rankings.segment', [$citySlug, RankingBrowser::segment($segmentOf($i))['group'], $segmentOf($i), 'selected' => $i['key']]).'#performance';
@endphp
<section class="card overflow-hidden" aria-labelledby="city-results-title" data-rankings-results>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
        <h2 id="city-results-title" class="text-base font-bold">{{ $label }} in {{ $city }}</h2>
        @if ($segmentKey)<a href="{{ route('rankings.segment', [$citySlug, RankingBrowser::segment($segmentKey)['group'], $segmentKey]) }}" class="link text-[13px]">Open the {{ strtolower($label) }} dashboard →</a>@endif
    </div>
    @if ($items->isEmpty())
        <div class="p-6 text-center text-sm text-muted">
            <p class="font-semibold text-ink">Insufficient Verified Data</p>
            <p class="mt-1">{{ $tab === 'all' ? 'Nothing' : 'No '.strtolower($label) }} to show in {{ $city }} yet.</p>
            <p class="mt-1">{{ in_array($tab, ['highest-yield', 'fastest-growing'], true) ? 'This list needs verified rent and price history; nothing is estimated.' : 'Entries appear when projects are published or verified market records exist.' }}</p>
        </div>
    @else
        <ul class="divide-y divide-line">
            @foreach ($items as $i)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap" data-item-key="{{ $i['key'] }}">
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-[12px] font-bold text-brand-700">{{ $i['score'] !== null ? $items->firstItem() + $loop->index : '–' }}</span>
                    @if ($i['image'])<img src="{{ $i['image'] }}" alt="" class="size-11 shrink-0 rounded-md object-cover" loading="lazy">@endif
                    <div class="min-w-0 flex-1">
                        <a href="{{ $url($i) }}" class="font-semibold text-brand-700 hover:underline">{{ $i['name'] }}</a>
                        <p class="text-[12px] text-muted">{{ $i['location'] }} · {{ RankingBrowser::segment($segmentOf($i))['label'] }}@if ($i['group_label']) · {{ $i['group_label'] }}@endif</p>
                    </div>
                    <dl class="flex shrink-0 flex-wrap gap-x-5 gap-y-1 text-[12px]">
                        <div><dt class="text-muted">Price</dt><dd class="font-semibold">{{ $i['price']['label'] ?? '—' }}</dd></div>
                        <div><dt class="text-muted">1 Yr</dt><dd @class(['font-semibold', 'text-emerald-700' => ($i['change_1y'] ?? -1) >= 0 && $i['change_1y'] !== null, 'text-red-600' => $i['change_1y'] !== null && $i['change_1y'] < 0])>{{ $i['change_1y'] !== null ? InvestmentInsights::signed($i['change_1y']).'%' : '—' }}</dd></div>
                        <div><dt class="text-muted">Yield</dt><dd class="font-semibold">{{ $i['yield'] !== null ? $i['yield'].'%' : '—' }}</dd></div>
                        <div><dt class="sr-only">Status</dt><dd>@include('rankings.partials.badges', ['badge' => 'status', 'item' => $i])</dd></div>
                    </dl>
                    <div class="shrink-0">@include('rankings.partials.badges', ['badge' => 'score', 'item' => $i])</div>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($items->hasPages())<div class="border-t border-line p-3" data-rankings-pages>{{ $items->onEachSide(1)->links() }}</div>@endif
</section>
