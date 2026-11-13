@props([
    'title',
    'subtitle' => null,
    'city' => null,
    'category' => null,
    'image' => null,
    'breadcrumbs' => [],
    'run' => null,
    'version' => null,
    'publishedAt' => null,
    'kpis' => [],
    'showSearch' => true,
    'currentCity' => null,
    'currentCategory' => null,
    'currentQuery' => null,
    'badge' => null,
    'searchGroup' => null,
    'searchTab' => null,
    'layout' => 'default',
    'tiles' => [],
    'headline' => null,
    'tagline' => null,
])

@php
    use App\Services\InvestmentIndex\IndexHeroImage;
    use App\Support\Cities;

    $hero = $image
        ? ['url' => $image, 'alt' => $title, 'type' => 'custom']
        : IndexHeroImage::resolve($city, $category);

    $categoryName = $category ? IndexHeroImage::categoryLabel($category) : null;
    $cityName = $city && ! in_array(mb_strtolower($city), ['pakistan', 'all'], true) ? Cities::canonical($city) ?? $city : null;
    $citySlug = $cityName ? Cities::slug($cityName) : null;
    $province = $cityName ? Cities::province($cityName) : null;

    $cityPhoto = $cityName ? config('meezan.place_photos.'.$cityName) : null;

    // Build gold uppercase eyebrow matching cities/show.blade.php exactly
    if ($badge) {
        $eyebrow = $badge;
    } elseif ($province) {
        $eyebrow = "{$province}, Pakistan";
    } elseif ($cityName) {
        $eyebrow = "{$cityName}, Pakistan";
    } elseif ($categoryName) {
        $eyebrow = "{$categoryName} · Pakistan Benchmark";
    } else {
        $eyebrow = 'Meezan Investment Index · Pakistan';
    }

    // Prepare clean stats items for horizontal <dl> row
    $statItems = [];
    if (! empty($kpis['ranked_projects']) || ! empty($kpis['ranked'])) {
        $statItems[] = [
            'label' => 'Ranked developments',
            'value' => number_format($kpis['ranked_projects'] ?? $kpis['ranked'] ?? 0),
        ];
    }
    if (! empty($kpis['verified_projects']) || ! empty($kpis['verified_nocs']) || ! empty($kpis['legal_verified'])) {
        $statItems[] = [
            'label' => 'Verified legal NOCs',
            'value' => number_format($kpis['verified_projects'] ?? $kpis['verified_nocs'] ?? $kpis['legal_verified'] ?? 0),
        ];
    }
    if (! empty($kpis['avg_score'])) {
        $statItems[] = [
            'label' => 'Average score',
            'value' => number_format($kpis['avg_score'], 1) . ' / 100',
        ];
    }
    if (! empty($kpis['avg_confidence'])) {
        $statItems[] = [
            'label' => 'Data confidence',
            'value' => number_format($kpis['avg_confidence'], 0) . ' / 100',
        ];
    } elseif (! empty($kpis['total_projects'])) {
        $statItems[] = [
            'label' => 'Researched projects',
            'value' => number_format($kpis['total_projects']),
        ];
    }
    if (empty($statItems)) {
        $statItems = [
            ['label' => 'Evidence threshold', 'value' => '≥ 60% Required'],
            ['label' => 'Coverage', 'value' => '7 Major Cities'],
            ['label' => 'Legal checks', 'value' => 'Official NOCs Only'],
        ];
    }
@endphp

<section class="relative isolate overflow-hidden bg-brand-900 text-white">
    {{-- Background Image with Fallback --}}
    <img src="{{ $hero['url'] }}"
         alt="{{ $hero['alt'] }}"
         width="1920" height="1200"
         class="absolute inset-0 -z-20 size-full object-cover"
         style="object-position: {{ $cityPhoto['position'] ?? 'center' }}"
         fetchpriority="high"
         loading="eager"
         onerror="this.onerror=null;this.src='{{ asset('images/investment-index/fallback.jpg') }}';" />

    {{-- Dark Purple Gradient Overlay matching cities/show.blade.php --}}
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900/95 via-brand-900/75 to-brand-900/25"></div>

    @if ($layout === 'dashboard')
    {{-- Dashboard hero (owner's reference design): headline left, search and stat tiles right. --}}
    <div class="container-page pt-6 pb-20 lg:pt-8 lg:pb-24">
        @if (! empty($breadcrumbs))
            <x-breadcrumbs :items="$breadcrumbs" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
        @endif
        <div class="mt-4 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] min-[1700px]:grid-cols-[minmax(0,0.8fr)_minmax(0,1.3fr)_minmax(0,0.45fr)]">
            <div>
                <p class="text-xs font-semibold tracking-[0.14em] text-white/80 uppercase">Investment Intelligence</p>
                <h1 class="mt-1.5 font-sans text-3xl leading-tight font-extrabold text-white sm:text-4xl">@if ($headline){!! $headline !!}@else Smart Real Estate <span class="text-gold-500">Investments</span><br>for a <span class="text-gold-500">Better Tomorrow</span>@endif<span class="sr-only">: {{ $eyebrow }}. {{ $title }}</span></h1>
                @if ($tagline)<p class="mt-1 text-xl font-semibold text-white">{{ $tagline }}</p>@endif
                <p class="mt-2 text-[15px] font-medium text-white">Real Data <span class="mx-2 text-white/50">|</span> Evidence-Based Analysis <span class="mx-2 text-white/50">|</span> Transparent Rankings</p>
                <p class="mt-2 max-w-xl text-[13px] leading-relaxed text-white/85">{{ $subtitle }}</p>
            </div>
            <div class="min-w-0">
                @if ($showSearch)
                    <x-search-bar :city="$cityName" :group="$searchGroup" :tab="$searchTab" />
                @endif
            </div>
            <div class="min-w-0 lg:col-span-2 min-[1700px]:col-span-1">
                @if ($tiles)
                    <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-white/20 bg-white/15 sm:grid-cols-4 min-[1700px]:grid-cols-2">
                        @foreach ($tiles as [$icon, $value, $label])
                            <div class="flex items-center gap-3 bg-brand-900/70 px-4 py-4">
                                <x-icon :name="$icon" class="size-6 shrink-0 text-gold-300" />
                                <div class="min-w-0"><dd class="text-lg leading-tight font-extrabold tabular-nums">{{ $value }}</dd><dt class="truncate text-[11px] text-white/80">{{ $label }}</dt></div>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>
    </div>
    @else
    <div class="container-page py-10 pb-12 lg:py-16">
        {{-- Breadcrumbs Navigation --}}
        @if (! empty($breadcrumbs))
            <x-breadcrumbs :items="$breadcrumbs" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
        @endif

        {{-- Gold Uppercase Eyebrow --}}
        <p class="mt-6 text-sm font-semibold tracking-wide text-gold-300 uppercase">{{ $eyebrow }}</p>

        {{-- Hero Heading --}}
        <h1 class="mt-1 text-4xl text-white sm:text-5xl font-bold">{{ $title }}</h1>

        {{-- Hero Subtitle --}}
        @if ($subtitle)
            <p class="mt-3 max-w-2xl text-white/85 text-[15px] sm:text-base leading-relaxed">{{ $subtitle }}</p>
        @endif

        {{-- Four-Tab Marketplace Property Search directly on hero background --}}
        @if ($showSearch)
            <x-search-bar :city="$cityName" :group="$searchGroup" :tab="$searchTab" class="mt-6 max-w-5xl" />
        @endif

        {{-- Horizontal Stats Row below search bar --}}
        @if (! empty($statItems))
            <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-white">
                @foreach ($statItems as $stat)
                    <div>
                        <dt class="text-xs text-white/70">{{ $stat['label'] }}</dt>
                        <dd class="text-2xl font-bold tabular-nums">{{ $stat['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    @endif

    @if ($cityPhoto)
        <x-photo-credit :photo="$cityPhoto" />
    @endif
</section>
