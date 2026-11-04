{{--
    Investment map (MapLibre, shared Phase 13 setup in resources/js/geo-map/common.js).
    $markers (RankingBrowser::markers), $city, $title.
--}}
@php
    $mapConfig = config('meezan.map');
    $b = $mapConfig['pakistan_bounds'];
    $centre = \App\Models\City::centre($city);
    $config = [
        'style' => $mapConfig['style_url'] ?: null,
        'raster' => ['tiles' => $mapConfig['tile_url'], 'attribution' => $mapConfig['attribution']],
        'pakistan' => [[$b['west'], $b['south']], [$b['east'], $b['north']]],
        'center' => $centre ? [(float) $centre[1], (float) $centre[0]] : null,
    ];
@endphp
<section class="card overflow-hidden" aria-labelledby="map-title">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
        <h2 id="map-title" class="text-base font-bold">{{ $title }}</h2>
        <p class="text-[12px] text-muted">{{ count($markers) }} on the map</p>
    </div>
    @if (config('meezan.map.enabled'))
        <div class="relative h-80 bg-page lg:h-[22rem]">
            <div class="size-full" data-investment-map="{{ json_encode($config, JSON_UNESCAPED_SLASHES) }}" data-markers="{{ json_encode($markers, JSON_UNESCAPED_SLASHES) }}"
                 role="application" aria-label="Map of {{ strtolower($title) }}. Each marker links to the entry's performance."></div>
            <div class="pointer-events-none absolute top-3 left-3 rounded-lg bg-white/95 p-2.5 text-[12px] shadow-card">
                <p class="font-semibold">Legend</p>
                <ul class="mt-1 space-y-1">
                    <li class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-emerald-600"></span>Ranked</li>
                    <li class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-amber-500"></span>Provisional</li>
                    <li class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-[#8c66c8]"></span>Tracked, not scored</li>
                    <li class="flex items-center gap-1.5"><span class="size-3 rounded-full border-2 border-dashed border-brand-600"></span>Area centre (approximate)</li>
                </ul>
            </div>
            @if (! $markers)
                <p class="absolute inset-x-4 bottom-4 rounded-lg bg-white/95 p-3 text-center text-[13px] text-muted shadow-card">No entries with a map position yet. Projects appear once staff add their location.</p>
            @endif
            <noscript><p class="absolute inset-4 grid place-items-center rounded-xl bg-white/95 p-6 text-center text-sm">The map needs JavaScript. Every entry is also in the table.</p></noscript>
        </div>
    @else
        <p class="p-4 text-sm text-muted">The map is switched off.</p>
    @endif
</section>
