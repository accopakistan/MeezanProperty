{{--
    Category navigation. $citySlug; $active: segment slug, group slug or special tab;
    $mode: 'city' (tabs on the city page, results update in place) or 'segment' (links to dashboards).
--}}
@php
    use App\Services\Investment\RankingBrowser;
    $icons = ['societies' => 'home', 'plots' => 'plot', 'houses' => 'home', 'apartments' => 'building', 'shopping-malls' => 'store', 'commercial-plazas' => 'building',
        'business-towers' => 'building', 'office-spaces' => 'briefcase', 'commercial-shops' => 'store', 'commercial-plots' => 'plot', 'mixed-use' => 'layers',
        'all' => 'grid', 'residential' => 'home', 'commercial' => 'building', 'highest-yield' => 'chart', 'fastest-growing' => 'chart', 'new-upcoming' => 'star'];
    $link = fn (string $key) => match (true) {
        $mode === 'city' && (isset(RankingBrowser::SPECIAL_TABS[$key]) || RankingBrowser::segment($key)) => route('rankings.city', [$citySlug, 'tab' => $key]),
        in_array($key, ['residential', 'commercial'], true) => route('rankings.group', [$citySlug, $key]),
        isset(RankingBrowser::SPECIAL_TABS[$key]) => route('rankings.city', [$citySlug, 'tab' => $key]),
        default => route('rankings.segment', [$citySlug, RankingBrowser::segment($key)['group'], $key]),
    };
    $entries = $mode === 'city'
        ? ['all' => 'All Investments', 'residential' => 'Residential', 'commercial' => 'Commercial', 'societies' => 'Housing Societies', 'apartments' => 'Apartment Buildings',
            'shopping-malls' => 'Shopping Malls', 'commercial-plazas' => 'Commercial Plazas', 'business-towers' => 'Business Towers',
            'highest-yield' => 'Highest Rental Yield', 'fastest-growing' => 'Fastest Growing', 'new-upcoming' => 'New & Upcoming']
        : collect(RankingBrowser::publicSegmentsIn(RankingBrowser::segment($active)['group'] ?? $active))->map(fn ($s) => $s['label'])->all()
            + ['highest-yield' => 'Highest Rental Yield', 'fastest-growing' => 'Fastest Growing', 'new-upcoming' => 'New & Upcoming'];
@endphp
<nav class="container-page mt-5" aria-label="Investment categories">
    <ul class="flex gap-2 overflow-x-auto pb-1 scrollbar-none" @if ($mode === 'city') data-rankings-tabs @endif>
        @foreach ($entries as $key => $label)
            @php $on = $active === $key; @endphp
            <li class="shrink-0">
                <a href="{{ $link($key) }}" @if ($mode === 'city' && ! in_array($key, ['residential', 'commercial'], true)) data-rankings-link @endif
                   @class(['flex items-center gap-2 rounded-lg px-3.5 py-2.5 text-[13px] font-semibold ring-1 transition', 'bg-brand-600 text-white ring-brand-600 shadow-card' => $on, 'bg-white text-ink ring-line hover:ring-brand-300' => ! $on])
                   @if ($on) aria-current="page" @endif>
                    <x-icon :name="$icons[$key] ?? 'grid'" @class(['size-4', 'text-gold-300' => $on, 'text-brand-600' => ! $on]) /> {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
