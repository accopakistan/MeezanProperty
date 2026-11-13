{{--
    Dashboard investment map (owner's reference design): pins coloured by category, a category
    checklist on the map, and a Map/Satellite switch when satellite imagery is configured
    (config meezan.map.satellite_url). $markers (IndexBrowser::markers rows with 'category'),
    $city (or null for Pakistan), $title, $only (category key to start with, or null for all).
--}}
@php
    use App\Services\InvestmentIndex\IndexBrowser;

    $mapConfig = config('meezan.map');
    $b = $mapConfig['pakistan_bounds'];
    $centre = $city ? \App\Models\City::centre($city) : null;
    $config = [
        'style' => $mapConfig['style_url'] ?: null,
        'raster' => ['tiles' => $mapConfig['tile_url'], 'attribution' => $mapConfig['attribution']],
        'satellite' => $mapConfig['satellite_url'] ? ['tiles' => $mapConfig['satellite_url'], 'attribution' => $mapConfig['satellite_attribution']] : null,
        'pakistan' => [[$b['west'], $b['south']], [$b['east'], $b['north']]],
        'center' => $centre ? [(float) $centre[1], (float) $centre[0]] : null,
    ];
    $colours = ['housing-societies' => '#6d28d9', 'commercial-plazas' => '#f59e0b', 'shopping-malls' => '#dc2626', 'business-towers' => '#2563eb', 'apartment-buildings' => '#16a34a'];
    $labels = collect(IndexBrowser::categories())->map(fn ($c) => $c['singular'] ?? rtrim($c['label'], 's'));
    $only ??= null;
    $shown = $only ? array_values(array_filter($markers, fn ($m) => ($m['category'] ?? null) === $only)) : $markers;
@endphp
<section class="card overflow-hidden" aria-labelledby="dash-map-title">
    <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-3.5 pb-2">
        <h2 id="dash-map-title" class="text-lg font-bold">{{ $title }}</h2>
        <p class="text-[12px] text-muted">{{ count($shown) }} on the map</p>
    </div>
    @if (config('meezan.map.enabled'))
        <div class="relative mx-3 mb-3 h-80 overflow-hidden rounded-lg bg-page lg:h-[22rem]">
            <div class="size-full" data-investment-map="{{ json_encode($config, JSON_UNESCAPED_SLASHES) }}" data-markers="{{ json_encode($markers, JSON_UNESCAPED_SLASHES) }}" data-colour-by="category"
                 role="application" aria-label="Map of {{ strtolower($title) }}. Each pin links to the project's profile."></div>

            <fieldset class="absolute top-3 left-3 rounded-lg bg-white/95 px-3 py-2.5 text-[12.5px] shadow-card">
                <legend class="sr-only">Show on the map</legend>
                <label class="flex items-center gap-2 py-0.5"><input type="checkbox" class="size-3.5 rounded border-line text-brand-600" data-map-all @checked(! $only)> All</label>
                @foreach ($labels as $key => $label)
                    <label class="flex items-center gap-2 py-0.5">
                        <input type="checkbox" value="{{ $key }}" class="size-3.5 rounded border-line" style="accent-color: {{ $colours[$key] }}" data-map-category @checked(! $only || $only === $key)>
                        <span class="size-2.5 rounded-sm" style="background: {{ $colours[$key] }}"></span> {{ $label }}
                    </label>
                @endforeach
            </fieldset>

            @if ($config['satellite'])
                <label class="absolute top-3 right-14 rounded-md bg-white/95 shadow-card">
                    <span class="sr-only">Map style</span>
                    <select class="h-8 rounded-md border-0 bg-transparent pr-7 pl-2.5 text-[12.5px] font-semibold" data-map-base>
                        <option value="map">Map</option>
                        <option value="satellite">Satellite</option>
                    </select>
                </label>
            @endif

            @if (! $markers)
                <p class="absolute inset-x-4 bottom-4 rounded-lg bg-white/95 p-3 text-center text-[13px] text-muted shadow-card">No projects with a map position yet. Pins appear once staff add a location.</p>
            @endif
            <noscript><p class="absolute inset-4 grid place-items-center rounded-xl bg-white/95 p-6 text-center text-sm">The map needs JavaScript. Every project is also in the tables.</p></noscript>
        </div>
    @else
        <p class="p-4 text-sm text-muted">The map is switched off.</p>
    @endif
</section>
@if ($only)
    @pushOnce('scripts')
    {{-- Start with only this category shown; the checklist can add the others. --}}
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    document.addEventListener('DOMContentLoaded', function () {
        var fire = function () { document.querySelector('[data-map-category]:checked')?.dispatchEvent(new Event('change')); };
        setTimeout(fire, 1500);
    });
    </script>
    @endPushOnce
@endif
