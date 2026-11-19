{{--
    Investment dashboard, laid out after the owner's reference design (10 Nov 2026):
    city strip (overlapping the hero), category tabs, then three columns — leaderboards, map + featured project,
    advisor + outlook — and a closing row (compare, banner, upcoming projects).

    Real data only. Index figures come from the published run. Market figures (advertised price, price change,
    advertised yield) come from each project's sourced research facts and are labelled as advertised, never as
    verified sales. A figure nobody has sourced reads "—" / "Not available"; nothing is filled in.

    Works for a city page, the national page ($city null) and, with $dashNavOnly, as the city strip alone.
    $city, $citySlug, $indexCityCards (IndexBrowser::cityCards), $cityBoards (category key => ranked rows),
    $cityOverall, $markers, $upcoming, $advice, $segmentKey, $calc.
--}}
@php
    use App\Services\InvestmentIndex\IndexBrowser;

    $city ??= null; $citySlug ??= null; $tab ??= 'all'; $segmentKey ??= null; $calc ??= null; $advice ??= null;
    $dashNavOnly ??= false; $stripCategory ??= null; $stripOverlap ??= false;
    $cityBoards ??= []; $cityOverall ??= collect(); $markers ??= []; $upcoming ??= collect();
    $place = $city ?? 'Pakistan';
    $catUrl = fn (string $k) => $city ? route('rankings.city-category', [$citySlug, $k]) : route('rankings.category', $k);
    $cityUrl = fn (string $slug) => $stripCategory ? route('rankings.city-category', [$slug, $stripCategory]) : route('rankings.city', $slug);

    $categories = IndexBrowser::categories();
    $browser = app(IndexBrowser::class);
    // Market data mode: where nothing is ranked, a board lists researched projects with their public market
    // data and official legal status: no rank, no medal, no Index score, and labelled "Not ranked".
    $housing = $cityBoards['housing-societies'] ?? collect();
    $housingMarket = $housing->isEmpty();
    if ($housingMarket) {
        $housing = $browser->marketBoard('housing-societies', $city, 8);
    }
    $commercialKeys = ['shopping-malls', 'commercial-plazas', 'business-towers', 'apartment-buildings'];
    $boardMarket = collect($commercialKeys)->mapWithKeys(fn ($k) => [$k => ($cityBoards[$k] ?? collect())->isEmpty()])->all();
    foreach ($commercialKeys as $k) {
        if ($boardMarket[$k]) {
            $cityBoards[$k] = $browser->marketBoard($k, $city, 8);
        }
    }
    $firstCommercial = collect($commercialKeys)->first(fn ($k) => ! $boardMarket[$k]) ?? collect($commercialKeys)->first(fn ($k) => $cityBoards[$k]->isNotEmpty()) ?? $commercialKeys[0];
    // Category page: one category's leaderboard takes the first column and supplies the featured project.
    $focusCategory ??= null; $focusRows ??= collect();
    $focusLabel = $focusCategory ? ($categories[$focusCategory]['label'] ?? 'Projects') : null;
    // The featured project is always a ranked one (never a market-data row).
    $featured = $focusCategory ? $focusRows->first() : $cityOverall->first();
    $focusMarket = $focusCategory && $focusRows->isEmpty();
    if ($focusMarket) {
        $focusRows = $browser->marketBoard($focusCategory, $city, 10, $filters ?? []);
    }
    $notRanked = '<span class="inline-block rounded bg-page px-1.5 py-0.5 text-[10.5px] font-semibold whitespace-nowrap text-muted ring-1 ring-line" title="Not enough staff-verified evidence to be scored yet">Not ranked</span>';
    $compareRows = $focusCategory ? $focusRows : $cityOverall;
    $featuredFactors = $featured ? collect($featured->factors)->reject(fn ($f) => $f['display_only'] ?? false) : collect();
    $strengths = $featuredFactors->filter(fn ($f) => ($f['score'] ?? null) !== null)->sortByDesc('score')->take(6);
    $gaps = $featuredFactors->filter(fn ($f) => ($f['score'] ?? null) === null)->pluck('label');

    // Sourced market facts on a project (research_facts): the fact whose label starts with $prefix, or null.
    $fact = function ($project, string $prefix) {
        foreach ($project->research_facts ?? [] as $f) {
            if (str_starts_with((string) ($f['label'] ?? ''), $prefix)) {
                return $f;
            }
        }

        return null;
    };
    $dash = '<span class="text-muted" title="No sourced figure on file">—</span>';
    // Societies table: one price set per plot size (reference: 5 Marla / 10 Marla / 1 Kanal / Houses / Apartments).
    $sizes = ['5 marla' => '5 Marla', '10 marla' => '10 Marla', '1 kanal' => '1 Kanal', 'house' => 'Houses', 'apartment' => 'Apartments'];
    $sizeFact = fn ($project, string $what, string $size) => $fact($project, match ($what) {
        'price' => 'Advertised price ('.$size.' plot)',
        'y1' => 'Price change, 1 year ('.$size.' plot)',
        default => 'Price change, 2 years ('.$size.' plot)',
    // Small areas publish one average for all plot sizes: used for every plot-size tab, marked "all sizes".
    }) ?? (in_array($size, ['5 marla', '10 marla', '1 kanal'], true) && ($f = $fact($project, match ($what) {
        'price' => 'Advertised price (plot, all sizes)',
        'y1' => 'Price change, 1 year (plot, all sizes)',
        default => 'Price change, 2 years (plot, all sizes)',
    })) ? $f + ['all_sizes' => true] : null);
    $priceText = fn (?array $f) => $f ? '<span title="'.e($f['value'].' — '.$f['source']).'">'.e(str_replace('PKR ', 'Rs ', trim(explode('(', $f['value'])[0]))).'</span>' : null;
    // Category table: project status → the reference's Operational / Under Construction / Upcoming tabs.
    $statusTab = fn ($project) => match ($project->status) { 'completed' => 'operational', 'under_construction' => 'construction', 'planned', 'upcoming' => 'upcoming', default => 'other' };
    $change = fn (?array $f) => $f ? '<span class="font-semibold '.(str_starts_with(ltrim($f['value']), '-') ? 'text-red-600' : 'text-emerald-700').'">'.(str_starts_with(ltrim($f['value']), '-') ? '▼' : '▲').' '.e(ltrim($f['value'], '+-')).'</span>' : $dash;
    $scoreTone = fn (float $v) => $v >= 90 ? 'bg-emerald-600' : ($v >= 80 ? 'bg-emerald-500' : ($v >= 70 ? 'bg-amber-500' : 'bg-orange-500'));
    $riskTone = fn (?string $c) => match ($c) { 'low' => 'text-emerald-700', 'moderate' => 'text-amber-600', default => 'text-red-600' };
    $riskWord = fn ($s) => trim(str_ireplace('risk', '', $s->riskLabel()));
    $medal = fn (int $i) => match ($i) { 0 => 'bg-gold-500 text-brand-900', 1 => 'bg-slate-200 text-ink', 2 => 'bg-amber-200 text-amber-900', default => 'bg-brand-50 text-brand-700' };
    $tier = fn (float $v) => $v >= 90 ? 'Premium' : ($v >= 80 ? 'Popular' : 'Value');
    // A project's own photo when one is on file; otherwise the category illustration (never another place's photo).
    $thumb = fn ($project) => $project->coverUrl();
    // Card carousels (reference: ‹ › arrows over a row of four cards; up to ten cards scroll).
    $carouselList = 'flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth p-3 scrollbar-none';
    $carouselItem = 'w-[calc(50%-0.375rem)] shrink-0 snap-start overflow-hidden rounded-lg ring-1 ring-line sm:w-[calc(25%-0.5625rem)]';
    $cityPhoto = fn ($project) => $project->cover_path ? $project->coverUrl() : (config('meezan.place_photos.'.$project->city.'.file') ? asset(config('meezan.place_photos.'.$project->city.'.file')) : $project->coverUrl());
@endphp

{{-- City strip ----------------------------------------------------------------}}
<nav @class(['relative z-10', '-mt-[4.5rem] lg:-mt-20' => $stripOverlap, 'mt-6' => ! $stripOverlap]) aria-label="Choose a city">
    <ul class="flex snap-x gap-2.5 overflow-x-auto rounded-xl bg-white p-2 shadow-lift ring-1 ring-line scrollbar-none lg:grid lg:grid-cols-7 lg:overflow-visible">
        @foreach ($indexCityCards as $c)
            @php $isActive = $c['name'] === $city; @endphp
            <li class="w-44 shrink-0 snap-start lg:w-auto">
                <a href="{{ $cityUrl($c['slug']) }}" @class(['group relative flex h-[4.5rem] overflow-hidden rounded-lg text-white ring-2 transition', 'ring-gold-500' => $isActive, 'ring-transparent hover:ring-brand-300' => ! $isActive]) @if ($isActive) aria-current="page" @endif>
                    @if ($c['photo'])
                        <img src="{{ asset($c['photo']['file']) }}" alt="" class="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-105" style="object-position: {{ $c['photo']['position'] ?? 'center' }}" loading="lazy">
                    @endif
                    <span @class(['absolute inset-0', 'bg-brand-700/85' => $isActive, 'bg-gradient-to-r from-brand-900/85 via-brand-900/55 to-brand-900/25' => ! $isActive])></span>
                    <span class="relative flex w-full items-center justify-between gap-2 px-3">
                        <span class="min-w-0">
                            <span class="block text-[15px] leading-tight font-bold">{{ $c['name'] }}</span>
                            <span class="block truncate text-[11px] text-white/85">{{ number_format($c['projects']) }} Projects</span>
                        </span>
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-white text-brand-700"><x-icon name="arrow-right" class="size-3" /></span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>

@unless ($dashNavOnly)
{{-- Category tabs --------------------------------------------------------------}}
<div class="-mx-4 sm:-mx-6 lg:-mx-10">
    @include('rankings.partials.category-nav', ['citySlug' => $citySlug ?? 'pakistan', 'active' => $tab, 'mode' => 'city'])
</div>

{{-- Three columns --------------------------------------------------------------}}
<div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1.1fr)_minmax(0,0.85fr)]">

    {{-- Column 1: leaderboards --}}
    <div class="min-w-0 space-y-5">
        @if ($focusCategory)
        <section class="card overflow-hidden" aria-labelledby="dash-focus-title">
            <div class="flex items-center justify-between gap-2 px-4 pt-3.5">
                <h2 id="dash-focus-title" class="text-lg font-bold">{{ $focusMarket ? $focusLabel.' in '.$place.': Market Data' : 'Top '.$focusLabel.' in '.$place }}</h2>
                <a href="#full-leaderboard" class="link flex items-center gap-1 text-[13px]">View All <x-icon name="arrow-right" class="size-3.5" /></a>
            </div>
            @if ($focusMarket && $focusRows->isNotEmpty())
                <p class="mx-4 mt-2 rounded-md bg-gold-50 px-3 py-2 text-[12px] text-ink ring-1 ring-gold-200"><span class="font-semibold">Not a ranking.</span> None of these has enough staff-verified evidence to be scored yet. Listed: researched {{ strtolower($focusLabel) }} with what is on public record{{ $focusCategory === 'housing-societies' ? ', largest 1-year advertised price change first' : '' }}.</p>
            @endif
            @if ($focusRows->isEmpty())
                <p class="p-4 text-[13px] text-muted">No {{ strtolower($focusLabel) }} in {{ $place }} have enough verified evidence to be ranked yet.</p>
            @else
                <div class="mt-3 grid grid-cols-2 gap-1 px-3 sm:grid-cols-4" role="tablist" aria-label="Development status" data-status-tabs>
                    @foreach (['all' => 'All '.$focusLabel, 'operational' => 'Operational', 'construction' => 'Under Construction', 'upcoming' => 'Upcoming'] as $key => $label)
                        <button type="button" role="tab" aria-selected="{{ $key === 'all' ? 'true' : 'false' }}" data-status-tab="{{ $key }}"
                                class="rounded-md px-1 py-1.5 text-[12px] font-semibold text-ink ring-1 ring-line aria-selected:bg-brand-600 aria-selected:text-white aria-selected:ring-brand-600">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="mt-2 overflow-x-auto" data-status-table>
                    <table class="w-full text-[12px]">
                        <thead class="border-y border-line bg-page/60 text-[10.5px] leading-tight text-muted">
                            <tr>
                                <th scope="col" class="w-9 px-3 py-2 text-left font-semibold">#</th>
                                <th scope="col" class="px-1 py-2 text-left font-semibold">Name</th>
                                <th scope="col" class="hidden px-1.5 py-2 text-left font-semibold sm:table-cell">Location</th>
                                <th scope="col" class="px-1.5 py-2 text-right font-semibold">Advertised<br>Price</th>
                                <th scope="col" class="px-1.5 py-2 text-right font-semibold">Rental Yield<br>(Annual)</th>
                                <th scope="col" class="px-1.5 py-2 text-center font-semibold text-brand-700">Index<br>Score</th>
                                <th scope="col" class="px-1.5 py-2 text-right font-semibold">Occupancy</th>
                                <th scope="col" class="px-2 py-2 text-right font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($focusRows->take(10) as $i => $s)
                                @php
                                    $p = $s->project; $occ = $s->factors['occupancy']['value'] ?? null; $price = $sizeFact($p, 'price', '10 marla') ?? $fact($p, 'Advertised price');
                                    // Market data mode: what is on public record, each figure labelled with its basis.
                                    $loc = ($fact($p, 'Location advertised') ?? $fact($p, 'Location on the official list') ?? $fact($p, 'Location'))['value'] ?? null;
                                    $loc = $loc ? (str_contains($loc, $p->city) || $city ? $loc : $loc.', '.$p->city) : ($p->area?->name ?? $p->city);
                                    $areaPrice = $price ? null : $fact($p, 'Area flat price');
                                    $areaYield = $s->verified_yield === null ? $fact($p, 'Rental yield') : null;
                                    $stated = $occ ? null : ($s->market_occupancy ?? null);
                                    // One more public fact for the name cell (size, certification, official record …).
                                    $keyFact = $focusMarket ? collect($p->research_facts ?? [])->first(fn ($f) => ! preg_match('/^(Location|Types advertised|Advertised price|Area flat|Rental yield|Price change)/', (string) ($f['label'] ?? ''))) : null;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2" data-status="{{ $statusTab($p) }}">@if ($focusMarket)<span class="block size-1.5 rounded-full bg-line"></span>@else<span @class(['grid size-6 place-items-center rounded-full text-[11px] font-bold', $medal($i)])>{{ $i + 1 }}</span>@endif</td>
                                    <td class="px-1 py-2"><a href="{{ route('rankings.project', $p) }}" class="leading-tight font-bold text-brand-700 hover:underline">{{ $p->name }}</a>
                                        @if ($keyFact)<span class="mt-0.5 block text-[10.5px] leading-snug text-muted" title="{{ $keyFact['source'] ?? '' }}">{{ $keyFact['label'] }}: {{ \Illuminate\Support\Str::limit((string) $keyFact['value'], 60) }}</span>@endif
                                    </td>
                                    <td class="hidden max-w-40 px-1.5 py-2 text-muted sm:table-cell"><span class="line-clamp-2" title="{{ $loc }}">{{ $loc }}</span></td>
                                    <td class="px-1.5 py-2 text-right font-semibold tabular-nums">
                                        @if ($price)
                                            <span title="{{ $price['source'] }}">{{ str_replace('PKR ', 'Rs ', trim(explode('(', $price['value'])[0])) }}</span>
                                        @elseif ($areaPrice)
                                            <span title="{{ $areaPrice['label'].': '.$areaPrice['value'].' — '.$areaPrice['source'] }}">{{ str_replace(['PKR ', ' per sq ft'], ['Rs ', ''], trim(preg_replace('/\(.*\)/', '', explode(';', $areaPrice['value'])[1] ?? ''))) }}</span><span class="block text-[10px] font-normal text-muted">per sq ft, area flats</span>
                                        @else
                                            {!! $dash !!}
                                        @endif
                                    </td>
                                    <td class="px-1.5 py-2 text-right font-semibold text-emerald-700 tabular-nums">
                                        @if ($s->verified_yield !== null)
                                            {{ number_format($s->verified_yield, 1) }}%
                                        @elseif ($areaYield)
                                            <span title="{{ $areaYield['value'].' — '.$areaYield['source'].'. Rent per sq ft × 12 ÷ price per sq ft, area averages, advertised.' }}">{{ trim(explode('gross', $areaYield['value'])[0]) }}</span><span class="block text-[10px] font-normal text-muted">area, advertised</span>
                                        @else
                                            {!! $dash !!}
                                        @endif
                                    </td>
                                    <td class="px-1.5 py-2 text-center">@if ($focusMarket){!! $notRanked !!}@else<span @class(['inline-block min-w-9 rounded px-1.5 py-1 text-[12px] font-bold text-white tabular-nums', $scoreTone($s->score)])>{{ number_format($s->score, 0) }}</span>@endif</td>
                                    <td class="px-1.5 py-2 text-right font-semibold text-emerald-700 tabular-nums">
                                        @if ($occ){{ $occ }}
                                        @elseif ($stated)<span title="Stated by {{ $stated['source'] }}{{ $stated['checked'] ? '' : '; not yet checked by staff' }}">{{ $stated['value'] }}</span><span class="block text-[10px] font-normal text-muted">{{ $stated['checked'] ? 'verified' : 'stated, unchecked' }}</span>
                                        @else{!! $dash !!}@endif
                                    </td>
                                    <td class="px-2 py-2 text-right"><a href="{{ route('rankings.project', $p) }}" class="rounded px-2 py-1 text-[12px] font-semibold text-brand-700 ring-1 ring-brand-300 hover:bg-brand-50" aria-label="View {{ $p->name }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="hidden px-4 py-3 text-[13px] text-muted" data-status-empty>None of the ranked {{ strtolower($focusLabel) }} here has this status on record.</p>
                <p class="border-t border-line px-4 py-2 text-[11px] text-muted">Prices are advertised (asking) figures from the source named on each project page. “—” means no sourced figure is on file. Status comes from the project's record; most projects have no confirmed status yet and appear under “All” only.</p>
            @endif
        </section>

        @if ($focusRows->isNotEmpty() && ! $focusMarket)
            <section class="card overflow-hidden" aria-labelledby="dash-focus-featured-title">
                <div class="flex items-center justify-between gap-2 px-4 pt-3.5">
                    <h2 id="dash-focus-featured-title" class="flex items-center gap-2 text-lg font-bold"><x-icon name="building" class="size-5 text-brand-600" /> Featured {{ $focusLabel }} in {{ $place }}</h2>
                </div>
                <div class="relative" data-carousel>
                <ul class="{{ $carouselList }}" data-carousel-track>
                    @foreach ($focusRows->take(10) as $s)
                        @php $p = $s->project; @endphp
                        <li class="{{ $carouselItem }}">
                            <a href="{{ route('rankings.project', $p) }}" class="block hover:bg-brand-50/40">
                                <img src="{{ $thumb($p) }}" alt="" class="aspect-[4/3] w-full object-cover" loading="lazy">
                                <span class="block p-2">
                                    <span class="block truncate text-[12.5px] font-bold text-ink">{{ $p->name }}</span>
                                    <span class="flex items-center gap-1 text-[11px] text-muted"><x-icon name="map-pin" class="size-3" /> {{ $p->area?->name ?? $p->city }}</span>
                                    <span class="block text-[11.5px] font-semibold text-ink">Index Score: {{ number_format($s->score, 0) }}</span>
                                    <span class="block text-[11.5px] text-emerald-700">Rental Yield: {!! $s->verified_yield !== null ? number_format($s->verified_yield, 1).'%' : '—' !!}</span>
                                    <span class="mt-1 inline-block rounded bg-emerald-50 px-1.5 py-px text-[10.5px] font-semibold text-emerald-700">{{ IndexBrowser::legalLabel($s->legal_status) }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                @include('rankings.partials.carousel-arrows', ['label' => $focusLabel])
                </div>
            </section>
        @endif
        @else
        <section class="card overflow-hidden" aria-labelledby="dash-housing-title">
            <div class="flex items-center justify-between gap-2 px-4 pt-3.5">
                <h2 id="dash-housing-title" class="text-lg font-bold">{{ $housingMarket ? 'Residential Societies in '.$place.': Market Data' : 'Top Residential Societies in '.$place }}</h2>
                <a href="{{ $catUrl('housing-societies') }}" class="link flex items-center gap-1 text-[13px]">View All <x-icon name="arrow-right" class="size-3.5" /></a>
            </div>
            @if ($housingMarket && $housing->isNotEmpty())
                <p class="mx-4 mt-2 rounded-md bg-gold-50 px-3 py-2 text-[12px] text-ink ring-1 ring-gold-200"><span class="font-semibold">Not a ranking.</span> No society here has enough staff-verified evidence to be scored yet. Listed: researched societies with public market data, largest 1-year advertised price change first.</p>
            @endif
            @if ($housing->isEmpty())
                <p class="p-4 text-[13px] text-muted">No housing society in {{ $place }} has enough verified evidence to be ranked yet.</p>
            @else
                <div class="mt-3 grid grid-cols-5 gap-1 px-3" role="tablist" aria-label="Plot size" data-size-tabs>
                    @foreach ($sizes as $key => $label)
                        <button type="button" role="tab" aria-selected="{{ $key === '10 marla' ? 'true' : 'false' }}" data-size-tab="{{ $key }}"
                                class="rounded-md px-1 py-1.5 text-[12px] font-semibold text-ink ring-1 ring-line aria-selected:bg-brand-600 aria-selected:text-white aria-selected:ring-brand-600">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="mt-2 overflow-x-auto" data-size-table>
                    <table class="w-full text-[12px]">
                        <thead class="text-[10.5px] leading-tight text-muted">
                            <tr class="border-y border-line bg-page/60">
                                <th scope="col" class="w-9 px-3 py-2 text-left font-semibold">#</th>
                                <th scope="col" class="px-1 py-2 text-left font-semibold">Society</th>
                                <th scope="col" class="px-1.5 py-2 text-right font-semibold">Advertised Price<br>@foreach ($sizes as $key => $label)<span data-size="{{ $key }}" @if ($key !== '10 marla') hidden @endif>({{ $label }})</span>@endforeach</th>
                                <th scope="col" class="px-1.5 py-2 text-right font-semibold">1 Yr<br>Change</th>
                                <th scope="col" class="px-1.5 py-2 text-right font-semibold">2 Yr<br>Change</th>
                                <th scope="col" class="px-1.5 py-2 text-right font-semibold">Rental Yield<br>(Annual)</th>
                                <th scope="col" class="px-1.5 py-2 text-center font-semibold text-brand-700">Index<br>Score</th>
                                <th scope="col" class="px-1.5 py-2 text-left font-semibold">{!! $housingMarket ? 'Legal<br>record' : 'Risk' !!}</th>
                                <th scope="col" class="px-2 py-2 text-right font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($housing as $i => $s)
                                @php $p = $s->project; $yield = $s->verified_yield !== null ? number_format($s->verified_yield, 1).'%' : ($fact($p, 'Rental yield')['value'] ?? null); @endphp
                                <tr>
                                    <td class="px-3 py-2">@if ($housingMarket)<span class="block size-1.5 rounded-full bg-line"></span>@else<span @class(['grid size-6 place-items-center rounded-full text-[11px] font-bold', $medal($i)])>{{ $i + 1 }}</span>@endif</td>
                                    <td class="px-1 py-2">
                                        <a href="{{ route('rankings.project', $p) }}" class="flex items-center gap-2">
                                            <span class="min-w-0">
                                                <span class="block leading-tight font-bold text-brand-700">{{ $p->name }}</span>
                                                @unless ($housingMarket)<span class="mt-0.5 inline-block rounded bg-brand-50 px-1.5 py-px text-[10px] font-semibold text-brand-700">{{ $tier($s->score) }}</span>@endunless
                                            </span>
                                        </a>
                                    </td>
                                    @foreach (['price', 'y1', 'y2'] as $what)
                                        <td @class(['px-1.5 py-2 text-right tabular-nums whitespace-nowrap', 'font-semibold' => $what === 'price'])>
                                            @foreach ($sizes as $key => $label)
                                                @php $f = $sizeFact($p, $what, $key); @endphp
                                                <span data-size="{{ $key }}" @if ($key !== '10 marla') hidden @endif>{!! $what === 'price' ? ($priceText($f) ?? $dash) : $change($f) !!}@if ($what === 'price' && ($f['all_sizes'] ?? false))<span class="block text-[10px] font-normal text-muted">all sizes</span>@endif</span>
                                            @endforeach
                                        </td>
                                    @endforeach
                                    <td class="px-1.5 py-2 text-right tabular-nums">{!! $yield ? e($yield) : $dash !!}</td>
                                    @if ($housingMarket)
                                        <td class="px-1.5 py-2 text-center">{!! $notRanked !!}</td>
                                        <td class="px-1.5 py-2 text-[11px] leading-tight text-muted">{{ IndexBrowser::legalLabel($s->legal_status) }}</td>
                                    @else
                                    <td class="px-1.5 py-2 text-center"><span @class(['inline-block min-w-9 rounded px-1.5 py-1 text-[12px] font-bold text-white tabular-nums', $scoreTone($s->score)])>{{ number_format($s->score, 0) }}</span></td>
                                    <td @class(['px-1.5 py-2 font-semibold whitespace-nowrap', $riskTone($s->risk_class)])>{{ $riskWord($s) }}</td>
                                    @endif
                                    <td class="px-2 py-2 text-right"><a href="{{ route('rankings.project', $p) }}" class="rounded px-2 py-1 text-[12px] font-semibold text-brand-700 ring-1 ring-brand-300 hover:bg-brand-50" aria-label="View {{ $p->name }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-line px-4 py-2 text-[11px] text-muted">Prices and changes are advertised (asking) plot prices from Zameen.com's price index, named on each project page; not verified sales. No house or apartment price index is on file yet. “—” means no sourced figure is on file.</p>
            @endif
        </section>

        <section class="card overflow-hidden" aria-labelledby="dash-commercial-title" data-dash-tabs>
            <div class="flex items-center justify-between gap-2 px-4 pt-3.5">
                <h2 id="dash-commercial-title" class="text-lg font-bold">{{ collect($boardMarket)->every(fn ($m) => $m) ? 'Commercial Projects – '.$place.': Market Data' : 'Top Commercial Projects – '.$place }}</h2>
                <a href="{{ $catUrl($firstCommercial) }}" class="link flex items-center gap-1 text-[13px]">View All <x-icon name="arrow-right" class="size-3.5" /></a>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-1.5 px-3 sm:grid-cols-4" role="tablist" aria-label="Commercial categories">
                @foreach ($commercialKeys as $k)
                    <button type="button" role="tab" id="dash-tab-{{ $k }}" aria-controls="dash-panel-{{ $k }}" aria-selected="{{ $k === $firstCommercial ? 'true' : 'false' }}" data-dash-tab="{{ $k }}"
                            class="rounded-md px-2 py-1.5 text-[12px] font-semibold text-ink ring-1 ring-line aria-selected:bg-brand-600 aria-selected:text-white aria-selected:ring-brand-600">{{ $categories[$k]['label'] ?? $k }}</button>
                @endforeach
            </div>
            @foreach ($commercialKeys as $k)
                @php $rows = $cityBoards[$k] ?? collect(); @endphp
                <div id="dash-panel-{{ $k }}" role="tabpanel" aria-labelledby="dash-tab-{{ $k }}" data-dash-panel="{{ $k }}" class="p-3" @if ($k !== $firstCommercial) hidden @endif>
                    @if ($rows->isEmpty())
                        <p class="px-1 py-2 text-[13px] text-muted">No {{ strtolower($categories[$k]['label'] ?? 'project') }} in {{ $place }} have enough verified evidence to be ranked yet.</p>
                    @else
                        @if ($boardMarket[$k])
                            <p class="mb-2 text-[11.5px] text-muted"><span class="font-semibold text-ink">Not a ranking:</span> none has enough staff-verified evidence yet. Researched {{ strtolower($categories[$k]['label'] ?? 'projects') }} with what is on public record.</p>
                        @endif
                        <div class="relative -m-3" data-carousel>
                        <ul class="{{ $carouselList }}" data-carousel-track>
                            @foreach ($rows->take(10) as $s)
                                @php $p = $s->project; @endphp
                                <li class="{{ $carouselItem }}">
                                    <a href="{{ route('rankings.project', $p) }}" class="block hover:bg-brand-50/40">
                                        <img src="{{ $thumb($p) }}" alt="" class="aspect-[4/3] w-full object-cover" loading="lazy">
                                        <span class="block p-2">
                                            <span class="block truncate text-[12.5px] font-bold text-ink">{{ $p->name }}</span>
                                            <span class="flex items-center gap-1 text-[11px] text-muted"><x-icon name="map-pin" class="size-3" /> {{ $p->area?->name ?? $p->city }}</span>
                                            @if ($boardMarket[$k])
                                                <span class="mt-0.5 block">{!! $notRanked !!}</span>
                                                @php $cardPrice = $fact($p, 'Advertised price'); @endphp
                                                @if ($cardPrice)<span class="block truncate text-[11.5px] font-semibold text-ink" title="{{ $cardPrice['source'] }}">{{ str_replace('PKR ', 'Rs ', trim(explode('(', $cardPrice['value'])[0])) }}</span>@endif
                                                @if ($s->market_occupancy ?? null)<span class="block text-[11px] text-emerald-700" title="Stated by {{ $s->market_occupancy['source'] }}; not yet checked by staff">Occupancy {{ $s->market_occupancy['value'] }} (stated)</span>@endif
                                                @if ($p->status && $p->status !== 'unverified')<span class="block text-[11px] text-ink">{{ $p->statusLabel() }}</span>@endif
                                            @else
                                            <span class="block text-[11.5px] font-semibold text-ink">Index Score: {{ number_format($s->score, 0) }}</span>
                                            <span class="block text-[11.5px] text-emerald-700">Rental Yield: {!! $s->verified_yield !== null ? number_format($s->verified_yield, 1).'%' : '—' !!}</span>
                                            @endif
                                            <span class="block truncate text-[11px] text-muted" title="{{ IndexBrowser::legalLabel($s->legal_status) }}">{{ IndexBrowser::legalLabel($s->legal_status) }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @include('rankings.partials.carousel-arrows', ['label' => $categories[$k]['label'] ?? 'projects'])
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
        @endif
    </div>

    {{-- Column 2: map and the top project --}}
    <div class="min-w-0 space-y-5">
        @php
            // The map opens the card of the best-ranked project that has a map position (reference design).
            $pinned = collect($dashMarkers ?? [])->pluck('key')->flip();
            $openKey = $compareRows->concat(collect($cityBoards)->flatten(1)->sortByDesc('score'))
                ->map(fn ($s) => 'project:'.$s->project_id)->first(fn ($k) => $pinned->has($k));
        @endphp
        @include('rankings.partials.dash-map', ['title' => 'Investment Map – '.($focusLabel ? $focusLabel.' in ' : '').$place, 'markers' => $dashMarkers ?? [], 'only' => $focusCategory, 'openKey' => $openKey])

        @if ($featured)
            @php $fp = $featured->project; $fsize = $sizeFact($fp, 'price', '10 marla') ? '10 marla' : (collect(['5 marla', '1 kanal'])->first(fn ($z) => $sizeFact($fp, 'price', $z)) ?? '10 marla'); $price = $sizeFact($fp, 'price', $fsize) ?? $fact($fp, 'Advertised price'); @endphp
            <section class="card overflow-hidden" aria-labelledby="dash-featured-title">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-3.5">
                    <h2 id="dash-featured-title" class="text-lg font-bold">{{ $fp->name }} – Investment Performance</h2>
                    <a href="{{ route('rankings.project', $fp) }}" class="link flex items-center gap-1 text-[13px]">View Full Details <x-icon name="arrow-right" class="size-3.5" /></a>
                </div>
                <div class="grid gap-4 p-4 sm:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
                    <div class="relative min-h-40 overflow-hidden rounded-lg bg-brand-900">
                        <img src="{{ $cityPhoto($fp) }}" alt="" class="absolute inset-0 size-full object-cover" loading="lazy">
                        <span class="absolute top-2 right-2 rounded-md bg-emerald-600 px-3 py-1.5 text-center text-white shadow-card"><span class="block text-xl leading-none font-extrabold tabular-nums">{{ number_format($featured->score, 0) }}</span><span class="block text-[10px] font-semibold">Index Score</span></span>
                        <span class="absolute bottom-2 left-2 rounded bg-gold-500 px-2 py-1 text-[12px] font-bold text-brand-900">#1 in {{ $place }}</span>
                    </div>
                    <dl class="divide-y divide-line text-[12.5px]">
                        @foreach ([
                            ['Advertised price'.($sizeFact($fp, 'price', $fsize) ? ' ('.$fsize.')' : ''), $price ? e(trim(explode('(', $price['value'])[0])) : $dash],
                            ['1 Year Change', $change($sizeFact($fp, 'y1', $fsize))],
                            ['2 Year Change', $change($sizeFact($fp, 'y2', $fsize))],
                            ['Rental Yield (Annual)', $featured->verified_yield !== null ? number_format($featured->verified_yield, 1).'%' : $dash],
                            ['Data Confidence', number_format($featured->confidence_score, 0).' / 100'],
                            ['Investment Risk', '<span class="rounded bg-emerald-50 px-1.5 py-px font-semibold '.$riskTone($featured->risk_class).'">'.e($riskWord($featured)).'</span>'],
                            ['Legal Status', '<span class="rounded bg-emerald-50 px-1.5 py-px font-semibold text-emerald-700">'.e(IndexBrowser::legalLabel($featured->legal_status)).'</span>'],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-3 py-1.5"><dt class="text-muted">{{ $label }}</dt><dd class="text-right font-semibold">{!! $value !!}</dd></div>
                        @endforeach
                    </dl>
                </div>
                @php
                    $fpTabs = ['Overview' => route('rankings.project', $fp), 'Price Trend' => route('rankings.project', $fp).'#price-records', 'Rental Yield' => route('rankings.project', $fp).'#price-records',
                        'Future Forecast' => route('rankings.project', $fp).'#price-records'];
                    // Location: the project highlighted on its city's investment map (only with a map position).
                    if (collect($dashMarkers ?? [])->contains('key', 'project:'.$fp->id) && \App\Support\Cities::slug((string) $fp->city)) {
                        $fpTabs['Location'] = route('rankings.city', [\App\Support\Cities::slug($fp->city), 'selected' => 'project:'.$fp->id]).'#dash-map-title';
                    }
                    $fpTabs['Details'] = route('rankings.project', $fp).'#score-breakdown-heading';
                @endphp
                <div @class(['grid grid-cols-3 gap-1.5 px-4', 'sm:grid-cols-6' => count($fpTabs) === 6, 'sm:grid-cols-5' => count($fpTabs) === 5])>
                    @foreach ($fpTabs as $label => $href)
                        <a href="{{ $href }}" @class(['rounded-md px-1.5 py-1.5 text-center text-[12px] font-semibold ring-1', 'bg-brand-600 text-white ring-brand-600' => $loop->first, 'text-ink ring-line hover:ring-brand-300' => ! $loop->first])>{{ $label }}</a>
                    @endforeach
                </div>
                @php
                    // Price trend: the featured project's advertised price index; when it has none on file, the
                    // best-ranked project on this page that has one (named in the chart, never passed off as the featured one).
                    $trendSubject = collect([$featured])->concat($focusCategory ? $focusRows : $housing)
                        ->first(fn ($s) => \App\Support\PriceIndexTrend::forFacts($s->project->research_facts) !== []);
                @endphp
                <div class="border-t border-line px-4 py-3 mt-3">
                    @if ($trendSubject)
                        @include('rankings.partials.price-trend', ['project' => $trendSubject->project, 'trends' => \App\Support\PriceIndexTrend::forFacts($trendSubject->project->research_facts),
                            'intro' => $trendSubject->project_id !== $fp->id ? 'No price index is on file for '.$fp->name.'. Shown: '.$trendSubject->project->name.', the best-ranked '.($focusCategory ? \Illuminate\Support\Str::singular(mb_strtolower($focusLabel)) : 'housing society').' here with one.' : null])
                    @else
                        <h3 class="text-base font-bold">Price Trend</h3>
                        <p class="mt-1 text-[13px] text-muted">Not available. No advertised price index is on file for {{ $fp->name }} or the other {{ $focusCategory ? mb_strtolower($focusLabel) : 'projects' }} ranked here yet.</p>
                    @endif
                </div>
            </section>
        @else
            {{-- Nothing ranked: a market snapshot of the researched project with the fullest public price record (no rank, no score). --}}
            @php
                $snap = ($focusCategory ? $focusRows : $housing)->sortByDesc(fn ($s) => collect(\App\Support\PriceIndexTrend::forFacts($s->project->research_facts))->sum(fn ($t) => count($t['points'])))
                    ->first(fn ($s) => \App\Support\PriceIndexTrend::forFacts($s->project->research_facts) !== []);
            @endphp
            @if ($snap)
                @php $sp = $snap->project; @endphp
                <section class="card overflow-hidden" aria-labelledby="dash-snapshot-title">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-3.5">
                        <h2 id="dash-snapshot-title" class="text-lg font-bold">Market Snapshot – {{ $sp->name }}</h2>
                        <a href="{{ route('rankings.project', $sp) }}" class="link flex items-center gap-1 text-[13px]">Project details <x-icon name="arrow-right" class="size-3.5" /></a>
                    </div>
                    <dl class="mx-4 mt-2 grid grid-cols-2 gap-x-4 text-[12.5px] sm:grid-cols-3">
                        <div class="py-1"><dt class="text-muted">Location</dt><dd class="font-semibold">{{ $sp->area?->name ?? $sp->city }}</dd></div>
                        <div class="py-1"><dt class="text-muted">Legal record</dt><dd class="font-semibold">{{ IndexBrowser::legalLabel($snap->legal_status) }}</dd></div>
                        <div class="py-1"><dt class="text-muted">Index status</dt><dd>{!! $notRanked !!}</dd></div>
                    </dl>
                    <div class="mt-2 border-t border-line px-4 py-3">
                        @include('rankings.partials.price-trend', ['project' => $sp, 'trends' => \App\Support\PriceIndexTrend::forFacts($sp->research_facts),
                            'intro' => 'Chosen for having the fullest public price record here, not for performance.'])
                    </div>
                </section>
            @endif
        @endif
    </div>

    {{-- Column 3: advisor and outlook --}}
    <div class="min-w-0 space-y-5">
        @include('rankings.partials.advisor', ['segmentKey' => $segmentKey, 'topPicks' => $focusMarket ? collect() : $compareRows->take(3)->values(),
            'topPicksQuestion' => 'Which '.strtolower($focusLabel ? \Illuminate\Support\Str::singular($focusLabel) : 'project').' is best for investment in '.$place.'?',
            'topPicksCompare' => route('rankings.category-compare', $featured?->category ?? ($focusCategory ?? 'housing-societies'))])

        @if ($featured)
            <section class="card p-4" aria-labelledby="dash-forecast-title">
                <h2 id="dash-forecast-title" class="text-lg font-bold">Future Growth Forecast – {{ $featured->project->name }}</h2>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    @foreach ([['Conservative', 'text-brand-700 bg-brand-50'], ['Moderate', 'text-brand-700 bg-brand-50'], ['Optimistic', 'text-amber-700 bg-amber-50']] as [$label, $tone])
                        <div @class(['rounded-lg px-2 py-3', $tone])>
                            <p class="text-[12px] font-semibold">{{ $label }}</p>
                            <p class="mt-1 text-2xl font-extrabold">—</p>
                            <p class="text-[10.5px] text-muted">(Next 2 Years)</p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-[11.5px] text-muted">Forecasts are not published: Meezan Property does not project prices without a sourced price history.</p>

                <h3 id="dash-why-title" class="mt-4 text-sm font-bold">Why {{ $featured->project->name }} Ranks #1?</h3>
                <ul class="mt-2 grid gap-x-3 gap-y-1.5 text-[12px] sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    @foreach ($strengths as $f)
                        <li class="flex items-start gap-1.5"><x-icon name="badge-check" class="mt-px size-4 shrink-0 text-emerald-600" /><span>{{ $f['label'] }} <span class="text-muted">({{ number_format($f['score'], 0) }}/100)</span></span></li>
                    @endforeach
                </ul>
                @if ($gaps->isNotEmpty())
                    <p class="mt-2 text-[11.5px] text-muted">Not yet evidenced: {{ $gaps->implode(', ') }}.</p>
                @endif
            </section>
        @endif
    </div>
</div>

{{-- Closing row ----------------------------------------------------------------}}
<div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)_minmax(0,1fr)]">
    <section class="card p-4" aria-labelledby="dash-compare-title">
        <h2 id="dash-compare-title" class="flex items-center gap-2 text-lg font-bold"><x-icon name="layers" class="size-5 text-brand-600" /> Compare {{ $focusLabel ?? 'Investments' }}</h2>
        @php
            // The comparison page compares one category: on a category page that one; otherwise the
            // category with the most ranked projects here (the featured project's on a tie), top four.
            $compareCategory = $focusCategory ?? collect($cityBoards)->map->count()
                ->sortDesc()->filter(fn ($n, $k) => $n > 0)
                ->sortBy(fn ($n, $k) => [-$n, $k === $featured?->category ? 0 : 1])->keys()->first() ?? ($featured?->category ?? 'housing-societies');
            $compareSet = ($focusCategory ? $focusRows : ($cityBoards[$compareCategory] ?? $cityOverall->where('category', $compareCategory)))->take(4)->values();
            $compareBase = route('rankings.category-compare', $compareCategory);
        @endphp
        <form method="GET" action="{{ $compareBase }}" data-dash-compare>
            <ul class="mt-3 flex flex-wrap items-stretch gap-2">
                @foreach ($compareSet as $s)
                    <li class="relative w-[calc(25%-0.375rem)] min-w-24 overflow-hidden rounded-lg ring-1 ring-line hover:ring-brand-300" data-dash-compare-item>
                        <input type="hidden" name="projects[]" value="{{ $s->project->slug }}">
                        <a href="{{ route('rankings.project', $s->project) }}" class="block">
                            <img src="{{ $thumb($s->project) }}" alt="" class="aspect-[16/9] w-full object-cover" loading="lazy">
                            <span class="block truncate px-1.5 py-1 text-[11px] font-semibold text-ink">{{ $s->project->name }}</span>
                        </a>
                        <button type="button" data-dash-compare-remove aria-label="Remove {{ $s->project->name }} from the comparison"
                                class="absolute top-1 right-1 grid size-5 place-items-center rounded-full bg-white/95 text-ink shadow-card ring-1 ring-line hover:bg-red-50 hover:text-red-700"><x-icon name="x" class="size-3" /></button>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 hidden text-[12px] text-muted" data-dash-compare-empty>Nothing selected. Compare Now opens the comparison page, where you can pick projects.</p>
            <button class="btn btn-primary mt-3 inline-flex items-center gap-1.5">Compare Now <x-icon name="arrow-right" class="size-4" /></button>
        </form>
    </section>

    @php $bannerPhoto = config('meezan.place_photos.'.($city ?? 'Lahore')); @endphp
    <section class="relative isolate grid place-items-center overflow-hidden rounded-xl bg-brand-900 p-5 text-center text-white">
        @if ($bannerPhoto)
            <img src="{{ asset($bannerPhoto['file']) }}" alt="" class="absolute inset-0 -z-20 size-full object-cover" style="object-position: {{ $bannerPhoto['position'] ?? 'center' }}" loading="lazy">
            <span class="absolute inset-0 -z-10 bg-brand-900/75" aria-hidden="true"></span>
        @endif
        <div>
            <h2 class="text-xl leading-tight font-bold text-white">Evidence-Based Investment Analysis<br>for a Smarter Future</h2>
            <ul class="mt-4 grid grid-cols-4 gap-2 text-[11px] text-white/90">
                <li class="flex flex-col items-center gap-1"><x-icon name="chart" class="size-5 text-gold-300" /> Real Market Data</li>
                <li class="flex flex-col items-center gap-1"><x-icon name="bot" class="size-5 text-gold-300" /> AI Advisor</li>
                <li class="flex flex-col items-center gap-1"><x-icon name="shield" class="size-5 text-gold-300" /> Transparent Rankings</li>
                <li class="flex flex-col items-center gap-1"><x-icon name="badge-check" class="size-5 text-gold-300" /> No Paid Rankings</li>
            </ul>
            <a href="{{ route('rankings.methodology') }}" class="mt-3 inline-block text-[12px] font-semibold text-gold-300 hover:underline">How projects are scored →</a>
        </div>
    </section>

    @include('rankings.partials.upcoming', ['title' => 'Upcoming & New Projects', 'upcoming' => $upcoming->take(4)])
</div>
@endunless

@pushOnce('scripts')
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
document.querySelectorAll('[data-size-tabs]').forEach(function (bar) {
    var table = bar.nextElementSibling;
    bar.querySelectorAll('[data-size-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            bar.querySelectorAll('[data-size-tab]').forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
            table.querySelectorAll('[data-size]').forEach(function (cell) { cell.hidden = cell.dataset.size !== tab.dataset.sizeTab; });
        });
    });
});
document.querySelectorAll('[data-status-tabs]').forEach(function (bar) {
    var table = bar.nextElementSibling, empty = table.parentElement.querySelector('[data-status-empty]');
    bar.querySelectorAll('[data-status-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            bar.querySelectorAll('[data-status-tab]').forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
            var shown = 0;
            table.querySelectorAll('tbody td[data-status]').forEach(function (cell) {
                var row = cell.parentElement;
                row.hidden = tab.dataset.statusTab !== 'all' && cell.dataset.status !== tab.dataset.statusTab;
                if (! row.hidden) shown++;
            });
            if (empty) empty.classList.toggle('hidden', shown > 0);
        });
    });
});
// Card carousels: ‹ › scroll one card at a time; the arrows show only when the row overflows.
document.querySelectorAll('[data-carousel]').forEach(function (box) {
    var track = box.querySelector('[data-carousel-track]'), prev = box.querySelector('[data-carousel-prev]'), next = box.querySelector('[data-carousel-next]');
    if (!track || !prev || !next) return;
    var step = function () { var card = track.firstElementChild; return card ? card.getBoundingClientRect().width + 12 : 200; };
    var update = function () {
        var overflow = track.scrollWidth > track.clientWidth + 2;
        prev.hidden = next.hidden = !overflow;
        prev.disabled = track.scrollLeft < 4;
        next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
    };
    prev.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: 'smooth' }); });
    next.addEventListener('click', function () { track.scrollBy({ left: step(), behavior: 'smooth' }); });
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    // Panels start hidden (commercial tabs): measure again when one is shown.
    new IntersectionObserver(update).observe(track);
    update();
});
// Compare tray: ✕ takes a project out of the comparison sent by Compare Now.
document.querySelectorAll('[data-dash-compare]').forEach(function (tray) {
    tray.querySelectorAll('[data-dash-compare-remove]').forEach(function (button) {
        button.addEventListener('click', function () {
            button.closest('[data-dash-compare-item]').remove();
            tray.querySelector('[data-dash-compare-empty]')?.classList.toggle('hidden', tray.querySelector('[data-dash-compare-item]') !== null);
        });
    });
});
document.querySelectorAll('[data-dash-tabs]').forEach(function (box) {
    box.querySelectorAll('[data-dash-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            box.querySelectorAll('[data-dash-tab]').forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
            box.querySelectorAll('[data-dash-panel]').forEach(function (p) { p.hidden = p.dataset.dashPanel !== tab.dataset.dashTab; });
        });
    });
});
</script>
@endPushOnce
