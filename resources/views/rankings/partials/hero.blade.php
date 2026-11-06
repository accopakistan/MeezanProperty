{{--
    Investment Rankings hero (layout: Investment Rankings reference dashboards).
    $title, $highlight (gold part of the title), $subtitle, $intro, $photo (config place_photos entry),
    $stats: list of [value, label, icon]; $city (null = Pakistan), $segmentKey (optional), $breadcrumbs.
--}}
@php
    $photo ??= config('meezan.place_photos.home');
    $segmentKey ??= null;
    $filters ??= [];
    $segments = \App\Services\Investment\RankingBrowser::segments();
    $current = $segmentKey ? $segments[$segmentKey] : null;
@endphp
<section class="relative isolate overflow-hidden bg-brand-900 text-white">
    @if ($photo)
        <img src="{{ asset($photo['file']) }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-35" style="object-position: {{ $photo['position'] ?? 'center' }}" fetchpriority="high">
    @endif
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/90 to-brand-800/60"></div>
    <div class="container-page pt-6 pb-20 lg:pb-24">
        <x-breadcrumbs :items="$breadcrumbs" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
        <div class="mt-5 grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)_auto] lg:items-start">
            <div class="min-w-0">
                <p class="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-gold-300 uppercase">Investment intelligence</p>
                <h1 class="mt-2 font-sans text-3xl leading-tight font-bold text-white sm:text-4xl">
                    {{ $title }} @if (! empty($highlight))<span class="text-gold-300">{{ $highlight }}</span>@endif
                </h1>
                @if (! empty($subtitle))<p class="mt-2 text-lg font-semibold text-white/95">{{ $subtitle }}</p>@endif
                <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-semibold text-white/90">
                    <span>Real Data</span><span aria-hidden="true" class="text-white/40">|</span><span>AI Analysis</span><span aria-hidden="true" class="text-white/40">|</span><span>Transparent Research</span>
                </p>
                <p class="mt-3 max-w-xl text-sm text-white/80">{{ $intro }}</p>
            </div>

            {{-- The shared four-tab search (Buy | Rent | Plots | Commercial) in investment mode. --}}
            @php
                $searchTab = match (true) {
                    in_array($segmentKey, ['plots', 'commercial-plots'], true) => 'plots',
                    ($current && in_array($current['group'], ['commercial', 'mixed-use'], true)) || in_array(request()->route('group'), ['commercial', 'mixed-use'], true) => 'commercial',
                    ($filters['sort'] ?? null) === 'yield' => 'rent',
                    default => 'sale',
                };
            @endphp
            <x-search-bar mode="investment" layout="compact" :tab="$searchTab" :city="$city" :segment="$segmentKey" :max-price="$filters['budget'] ?? null"
                          :min-yield="$filters['min_yield'] ?? null" :size="$filters['size'] ?? null" :stage="$filters['stage'] ?? null" :q="$filters['q'] ?? null"
                          class="min-w-0 text-ink" />

            @if (! empty($stats))
                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-white/15 text-white ring-1 ring-white/20 lg:w-72">
                    @foreach ($stats as [$value, $label, $icon])
                        <div class="flex items-center gap-2.5 bg-brand-900/70 p-3 backdrop-blur-sm">
                            <x-icon :name="$icon" class="size-6 shrink-0 text-gold-300" />
                            <div class="min-w-0"><dt class="sr-only">{{ $label }}</dt><dd class="text-xl leading-tight font-bold tabular-nums">{{ $value }}</dd><dd class="text-[12px] leading-tight text-white/80">{{ $label }}</dd></div>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>
    @if (! empty($photo['author']))
        <p class="absolute top-1.5 right-3 hidden text-[10px] text-white/55 sm:block">Photo: {{ $photo['alt'] }} — {{ $photo['author'] }}, <a href="{{ $photo['license_url'] }}" class="underline" rel="license noopener">{{ $photo['license'] }}</a></p>
    @endif
</section>
