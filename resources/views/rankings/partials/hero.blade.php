{{--
    Investment Rankings hero (matching Meezan Property cities/show.blade.php style).
    $title, $highlight (gold part of the title), $subtitle, $intro, $photo (config place_photos entry),
    $stats: list of [value, label, icon]; $city (null = Pakistan), $segmentKey (optional), $breadcrumbs.
--}}
@php
    $photo ??= config('meezan.place_photos.home');
    $segmentKey ??= null;
    $filters ??= [];
    $segments = \App\Services\Investment\RankingBrowser::segments();
    $current = $segmentKey ? $segments[$segmentKey] : null;
    $cityName = $city && ! in_array(mb_strtolower($city), ['pakistan', 'all'], true) ? \App\Support\Cities::canonical($city) ?? $city : null;
    $province = $cityName ? \App\Support\Cities::province($cityName) : null;
    $eyebrow = $province ? "{$province}, Pakistan" : ($cityName ? "{$cityName}, Pakistan" : 'Investment Intelligence · Pakistan');

    $searchTab = match (true) {
        in_array($segmentKey, ['plots', 'commercial-plots'], true) => 'plots',
        ($current && in_array($current['group'], ['commercial', 'mixed-use'], true)) || in_array(request()->route('group'), ['commercial', 'mixed-use'], true) => 'commercial',
        ($filters['sort'] ?? null) === 'yield' => 'rent',
        default => 'sale',
    };
@endphp
<section class="relative isolate overflow-hidden bg-brand-900 text-white">
    @if ($photo)
        <img src="{{ asset($photo['file']) }}" alt="{{ $photo['alt'] ?? '' }}"
             width="1920" height="1200"
             class="absolute inset-0 -z-20 size-full object-cover"
             style="object-position: {{ $photo['position'] ?? 'center' }}"
             fetchpriority="high">
    @endif
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900/95 via-brand-900/75 to-brand-900/25"></div>

    <div class="container-page py-10 pb-12 lg:py-16">
        @if (! empty($breadcrumbs))
            <x-breadcrumbs :items="$breadcrumbs" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
        @endif

        <p class="mt-6 text-sm font-semibold tracking-wide text-gold-300 uppercase">{{ $eyebrow }}</p>

        <h1 class="mt-1 text-4xl text-white sm:text-5xl font-bold">
            {{ $title }} @if (! empty($highlight))<span class="text-gold-300">{{ $highlight }}</span>@endif
        </h1>

        @if (! empty($subtitle))
            <p class="mt-2 text-lg font-semibold text-white/95">{{ $subtitle }}</p>
        @endif

        @if (! empty($intro))
            <p class="mt-3 max-w-2xl text-white/85 text-[15px] sm:text-base leading-relaxed">{{ $intro }}</p>
        @endif

        {{-- Four-tab search bar placed directly on hero background --}}
        <x-search-bar :city="$cityName" :tab="$searchTab" class="mt-6 max-w-5xl" />

        @if (! empty($stats))
            <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-white">
                @foreach ($stats as [$value, $label, $icon])
                    <div>
                        <dt class="text-xs text-white/70">{{ $label }}</dt>
                        <dd class="text-2xl font-bold tabular-nums">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    @if (! empty($photo))
        <x-photo-credit :photo="$photo" />
    @endif
</section>
