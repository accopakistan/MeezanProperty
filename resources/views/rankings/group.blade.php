@php
    use App\Services\Investment\RankingBrowser;
    $place = $city ?? 'Pakistan';
    $title = $groupLabel.' Investment Rankings in '.$place;
@endphp
<x-layouts.app :title="$title" :description="$groupLabel.' investments in '.$place.' by property type, ranked from verified market data with data confidence and sources.'"
               :canonical="route('rankings.group', [$citySlug, $group])" :robots="$indexable ? null : 'noindex, follow'">
    @include('rankings.partials.hero', [
        'title' => $groupLabel.' Investments in', 'highlight' => $place,
        'intro' => 'Each property type has its own ranking, metrics and comparison: choose one to open its dashboard.',
        'photo' => config('meezan.place_photos.'.($city ?? 'home')),
        'stats' => [[$previews->sum(fn ($p) => $p['items']->count()), 'Entries tracked', 'layers'], [$previews->sum(fn ($p) => $p['scored']->count()), 'Top scored shown', 'chart'], [7, 'Major Cities', 'map-pin'], ['v'.($calc?->methodology_version ?? \App\Models\InvestmentMethodology::active()?->version ?? '—'), 'Methodology', 'shield']],
        'breadcrumbs' => [['Investment Rankings', route('rankings.index')], [$place, route('rankings.city', $citySlug)], [$groupLabel, null]],
        'segmentKey' => null, 'filters' => [],
    ])
    @include('rankings.partials.city-cards', ['active' => $city, 'link' => fn ($slug) => route('rankings.group', [$slug, $group]),
        'countLabel' => fn ($c) => $group === 'residential' ? number_format($c['societies']).' societies' : $c['commercial_projects'].' commercial projects'])
    @include('rankings.partials.category-nav', ['mode' => 'segment', 'active' => $group])

    <div class="container-page py-6">
        @include('investment.partials.demo-notice')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <div class="grid min-w-0 gap-4 md:grid-cols-2">
                @foreach ($previews as $slug => $p)
                    <section class="card flex flex-col overflow-hidden" aria-labelledby="seg-{{ $slug }}">
                        <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                            <h2 id="seg-{{ $slug }}" class="text-base font-bold">{{ $p['segment']['label'] }}</h2>
                            <span class="text-[12px] text-muted">{{ $p['items']->count() }} tracked</span>
                        </div>
                        @if ($p['scored']->isEmpty())
                            <p class="flex-1 p-4 text-[13px] text-muted">No verified ranking yet{{ $p['items']->isNotEmpty() ? ': '.$p['items']->count().' listed with insufficient data' : '' }}.</p>
                        @else
                            <ol class="flex-1 divide-y divide-line">
                                @foreach ($p['scored'] as $n => $i)
                                    <li class="flex items-center gap-3 px-4 py-2.5 text-[13px]">
                                        <span class="grid size-6 place-items-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-700">{{ $n + 1 }}</span>
                                        <a href="{{ route('rankings.segment', [$citySlug, $group, $slug, 'selected' => $i['key']]) }}#performance" class="min-w-0 flex-1 truncate font-semibold text-brand-700">{{ $i['name'] }}@if ($i['group_label']) · {{ $i['group_label'] }}@endif</a>
                                        @include('rankings.partials.badges', ['badge' => 'score', 'item' => $i])
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                        <a href="{{ route('rankings.segment', [$citySlug, $group, $slug]) }}" class="border-t border-line px-4 py-2.5 text-[13px] font-semibold text-brand-700 hover:bg-brand-50">Open {{ strtolower($p['segment']['label']) }} dashboard →</a>
                    </section>
                @endforeach
            </div>
            <div class="min-w-0">@include('rankings.partials.advisor', ['segmentKey' => array_key_first($previews->all())])</div>
        </div>
        <div class="mt-6">@include('rankings.partials.upcoming', ['title' => 'Upcoming & New '.$groupLabel.' Projects'])</div>
        <div class="mt-6">@include('investment.partials.disclaimer')</div>
    </div>
</x-layouts.app>
