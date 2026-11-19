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
            return route('rankings.city', [$citySlug ?: 'pakistan', 'tab' => $key]);
        }

        // Property-type dashboards (plots, houses, office spaces …) and the commercial group page.
        if ($key === 'commercial') {
            return route('rankings.group', [$citySlug ?: 'pakistan', 'commercial']);
        }
        if ($segment = RankingBrowser::publicSegment($key)) {
            return route('rankings.segment', [$citySlug ?: 'pakistan', $segment['group'], $key]);
        }
        // Property types the Index does not rank yet (plots, houses, shops, offices, mixed use): no page.
        if (isset(RankingBrowser::segments()[$key])) {
            return null;
        }

        return route('rankings.city', $citySlug ?: 'pakistan');
    };

    // The tab set follows the page (reference design): commercial pages list the commercial
    // property types, every other page the residential-first set.
    $normalizedActive = $categoryKeyMap[$active] ?? $active;
    $commercialPage = in_array($normalizedActive, ['shopping-malls', 'commercial-plazas', 'business-towers', 'office-spaces', 'commercial-shops', 'commercial-plots', 'mixed-use', 'commercial'], true);
    $entries = $commercialPage ? [
        'all' => 'All Investments',
        'shopping-malls' => 'Shopping Malls',
        'commercial-plazas' => 'Commercial Plazas',
        'business-towers' => 'Business Towers',
        'office-spaces' => 'Office Spaces',
        'commercial-shops' => 'Commercial Shops',
        'commercial-plots' => 'Commercial Plots',
        'mixed-use' => 'Mixed Use',
    ] : [
        'all' => 'All Investments',
        'housing-societies' => 'Residential Societies',
        'commercial' => 'Commercial Projects',
        'plots' => 'Plots',
        'houses' => 'Houses',
        'apartment-buildings' => 'Apartments',
        'shopping-malls' => 'Shopping Malls',
        'business-towers' => 'Business Towers',
    ];
    $entries += [
        'highest-yield' => 'Highest Rental Yield',
        'fastest-growing' => 'Fastest Growing',
        'new-upcoming' => 'New & Upcoming',
    ];
@endphp
<nav class="container-page mt-5" aria-label="Investment categories">
    <ul class="flex gap-2 overflow-x-auto pb-1 scrollbar-none" @if ($mode === 'city') data-rankings-tabs @endif>
        @foreach ($entries as $key => $label)
            @php
                $on = ($normalizedActive === $key) || ($key === 'all' && ($active === 'all' || empty($active)));
                $isSpecialTab = isset(RankingBrowser::SPECIAL_TABS[$key]) && $key !== 'all';
            @endphp
            <li class="shrink-0">
                @if (($href = $link($key)) === null)
                    <span class="flex cursor-default items-center gap-2 rounded-lg bg-white/60 px-3.5 py-2.5 text-[13px] font-semibold text-muted ring-1 ring-line" aria-disabled="true"
                          title="Not ranked yet: the Meezan Investment Index ranks whole developments; {{ mb_strtolower($label) }} get their own ranking once enough verified evidence is on file.">
                        <x-icon :name="$icons[$key] ?? 'grid'" class="size-4 text-muted" /> {{ $label }}
                        <span class="rounded bg-page px-1 text-[10px] font-bold tracking-wide uppercase ring-1 ring-line">Soon</span>
                    </span>
                @else
                <a href="{{ $href }}" @if ($mode === 'city' && $isSpecialTab) data-rankings-link @endif
                   @class(['flex items-center gap-2 rounded-lg px-3.5 py-2.5 text-[13px] font-semibold ring-1 transition', 'bg-brand-600 text-white ring-brand-600 shadow-card' => $on, 'bg-white text-ink ring-line hover:ring-brand-300' => ! $on])
                   @if ($on) aria-current="page" @endif>
                    <x-icon :name="$icons[$key] ?? 'grid'" @class(['size-4', 'text-gold-300' => $on, 'text-brand-600' => ! $on]) /> {{ $label }}
                </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
