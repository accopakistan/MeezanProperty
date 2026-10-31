@php
    $mapConfig = config('meezan.map');
    // Frame the searched city; with no city, the whole country (midpoint of the Pakistan bounds).
    $mapCity = $city ?? request('city') ?? (isset($search) ? $search->area()?->city : null);
    $cityCentre = \App\Models\City::centre($mapCity);
    $b = $mapConfig['pakistan_bounds'];
    $mapPayload = json_encode([
        'tiles' => $mapConfig['tile_url'],
        'attribution' => $mapConfig['attribution'],
        'center' => $cityCentre ?? [($b['south'] + $b['north']) / 2, ($b['west'] + $b['east']) / 2],
        'zoom' => $cityCentre ? ($zoom ?? $mapConfig['zoom']) : 5,
        // Viewport search: reopen the map on the area the user searched.
        'bounds' => request()->filled(['north', 'south', 'east', 'west'])
            ? [[(float) request('south'), (float) request('west')], [(float) request('north'), (float) request('east')]]
            : null,
        // Drawn search area, outlined on the map and used to frame it.
        'polygon' => \App\Support\Polygon::parse(request('poly'))?->points,
    ], JSON_UNESCAPED_SLASHES);
    $markerPayload = json_encode($markers, JSON_UNESCAPED_SLASHES);
    $approximate = collect($markers)->whereIn('kind', ['area', 'approx']);
@endphp
<div class="relative isolate overflow-hidden {{ $class ?? 'h-full' }}">
    @if ($mapConfig['enabled'])
        <div class="size-full bg-brand-50" data-map="{{ $mapPayload }}" data-markers="{{ $markerPayload }}" role="region" aria-label="Map of listings"></div>
        <p data-map-status hidden class="absolute inset-x-4 top-4 z-[500] rounded-xl bg-white px-4 py-3 text-sm font-medium text-ink shadow-lift"></p>
        @if (empty($markers))
            <p class="pointer-events-none absolute inset-x-4 bottom-4 z-[500] rounded-xl bg-white/95 px-4 py-3 text-center text-[13px] text-muted shadow-card">
                {{ $emptyText ?? 'None of these listings have a confirmed map location yet.' }}
            </p>
        @elseif ($approximate->isNotEmpty() && ! ($hideApproximateNote ?? false))
            <p class="pointer-events-none absolute bottom-3 left-3 z-[500] flex max-w-[calc(100%-5rem)] items-center gap-2 rounded-md bg-white/95 px-3 py-1.5 text-[11.5px] text-muted shadow-card">
                <span class="inline-block h-3.5 w-5 shrink-0 rounded border-[1.5px] border-dashed border-white bg-brand-600 ring-1 ring-brand-600"></span>
                {{ $approximateText ?? 'Dashed pins group listings by area centre; their exact location was not provided.' }}
            </p>
        @endif
    @else
        <div class="grid size-full place-items-center bg-brand-50 p-6 text-center">
            <div>
                <x-icon name="map" class="mx-auto size-8 text-brand-400" />
                <p class="mt-2 text-sm font-semibold">Map is not available</p>
                <p class="mt-1 text-[13px] text-muted">Listings are still shown in the list.</p>
            </div>
        </div>
    @endif
</div>
