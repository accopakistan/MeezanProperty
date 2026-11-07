{{--
    Category navigation. $citySlug; $active: segment slug, category slug or special tab;
    $mode: 'city' (tabs on the city page) or 'segment' (links to dashboards).
--}}
@php
    use App\Services\Investment\RankingBrowser;
    use App\Services\InvestmentIndex\IndexBrowser;

    $icons = [
        'societies' => 'home', 'housing-societies' => 'home', 'plots' => 'plot', 'houses' => 'home',
        'apartments' => 'building', 'apartment-buildings' => 'building', 'shopping-malls' => 'store',
        'commercial-plazas' => 'building', 'business-towers' => 'building', 'office-spaces' => 'briefcase',
        'commercial-shops' => 'store', 'commercial-plots' => 'plot', 'mixed-use' => 'layers',
        'all' => 'grid', 'residential' => 'home', 'commercial' => 'building',
        'highest-yield' => 'chart', 'fastest-growing' => 'chart', 'new-upcoming' => 'star',
    ];

    $categoryKeyMap = [
        'societies' => 'housing-societies',
        'housing-societies' => 'housing-societies',
        'apartments' => 'apartment-buildings',
        'apartment-buildings' => 'apartment-buildings',
        'commercial-plazas' => 'commercial-plazas',
        'shopping-malls' => 'shopping-malls',
        'business-towers' => 'business-towers',
    ];

    $link = function (string $key) use ($citySlug, $categoryKeyMap) {
        if (isset($categoryKeyMap[$key])) {
            $cat = $categoryKeyMap[$key];
            return $citySlug && $citySlug !== 'pakistan'
                ? route('rankings.city-category', [$citySlug, $cat])
                : route('rankings.category', $cat);
        }

        if ($key === 'all') {
            return $citySlug && $citySlug !== 'pakistan'
                ? route('rankings.city', $citySlug)
                : route('rankings.index');
        }

        if (isset(RankingBrowser::SPECIAL_TABS[$key])) {
            return route('rankings.city', [$citySlug, 'tab' => $key]);
        }

        return route('rankings.city', $citySlug);
    };

    $entries = [
        'all' => 'All Investments',
        'housing-societies' => 'Housing Societies',
        'apartment-buildings' => 'Apartment Buildings',
        'shopping-malls' => 'Shopping Malls',
        'commercial-plazas' => 'Commercial Plazas',
        'business-towers' => 'Business Towers',
        'highest-yield' => 'Highest Rental Yield',
        'fastest-growing' => 'Fastest Growing',
        'new-upcoming' => 'New & Upcoming',
    ];

    $normalizedActive = $categoryKeyMap[$active] ?? $active;
@endphp
<nav class="container-page mt-5" aria-label="Investment categories">
    <ul class="flex gap-2 overflow-x-auto pb-1 scrollbar-none" @if ($mode === 'city') data-rankings-tabs @endif>
        @foreach ($entries as $key => $label)
            @php
                $on = ($normalizedActive === $key) || ($key === 'all' && ($active === 'all' || empty($active)));
                $isSpecialTab = isset(RankingBrowser::SPECIAL_TABS[$key]) && $key !== 'all';
            @endphp
            <li class="shrink-0">
                <a href="{{ $link($key) }}" @if ($mode === 'city' && $isSpecialTab) data-rankings-link @endif
                   @class(['flex items-center gap-2 rounded-lg px-3.5 py-2.5 text-[13px] font-semibold ring-1 transition', 'bg-brand-600 text-white ring-brand-600 shadow-card' => $on, 'bg-white text-ink ring-line hover:ring-brand-300' => ! $on])
                   @if ($on) aria-current="page" @endif>
                    <x-icon :name="$icons[$key] ?? 'grid'" @class(['size-4', 'text-gold-300' => $on, 'text-brand-600' => ! $on]) /> {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
