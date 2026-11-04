@php
    use App\Models\City;
    use App\Services\PropertySearch;
    use App\Support\Lookup;
    use App\Support\Polygon;

    /** @var PropertySearch $search */
    // Layout: public/Refrence/Meezan Property Map Search Dashboard.png (+ "(1)" variant)
    $purpose = $search->get('purpose');
    $group = $search->get('group');
    $tab = $group === 'plots' ? 'plots' : ($group === 'commercial' ? 'commercial' : ($purpose === 'rent' ? 'rent' : 'sale'));
    $tabs = ['sale' => ['Buy', ['purpose' => 'sale']], 'rent' => ['Rent', ['purpose' => 'rent']], 'plots' => ['Plots', ['group' => 'plots']], 'commercial' => ['Commercial', ['group' => 'commercial']]];
    $activeCity = $search->get('city', $search->area()?->city);

    $mapConfig = config('meezan.map');
    $b = $mapConfig['pakistan_bounds'];
    $centre = City::centre($activeCity);
    $area = $search->area();
    $geoMap = [
        // Vector style (MapLibre); the raster tiles are the fallback if it cannot load.
        'style' => $mapConfig['style_url'] ?: null,
        'raster' => ['tiles' => $mapConfig['tile_url'], 'attribution' => $mapConfig['attribution']],
        'pakistan' => [[$b['west'], $b['south']], [$b['east'], $b['north']]],
        // [lng, lat] for MapLibre.
        'center' => $area?->hasCoordinates() ? [(float) $area->longitude, (float) $area->latitude] : ($centre ? [(float) $centre[1], (float) $centre[0]] : null),
        'zoom' => $area?->hasCoordinates() ? 13 : 11,
        'bounds' => $search->hasBounds() ? [[(float) request('west'), (float) request('south')], [(float) request('east'), (float) request('north')]] : null,
        'polygon' => Polygon::parse(request('poly'))?->points,
        'cities' => collect($cities)->filter(fn ($c) => $c['centre'])->mapWithKeys(fn ($c) => [$c['name'] => [(float) $c['centre'][1], (float) $c['centre'][0]]]),
        'mode' => $map['mode'],
        'urls' => [
            'page' => route('properties.map'),
            'data' => route('properties.map.data'),
            'preview' => route('properties.map.preview', '__SLUG__'),
            'boundaries' => route('map.boundaries'),
            'boundary' => route('map.boundaries.show', 0),
        ],
        'boundaryMinZoom' => $mapConfig['boundary_min_zoom'],
    ];
@endphp
<x-layouts.app :title="'Map search — '.$search->headline()" description="Search property in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar on the map. See reviewed listings for sale and rent with prices by location." robots="noindex, follow">
    <section class="relative isolate overflow-hidden bg-brand-900">
        <img src="{{ asset('images/ref/hero-map.jpg') }}" alt="" class="absolute inset-y-0 right-0 -z-20 h-full w-full object-cover md:w-[58%]">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/85 to-brand-900/10"></div>
        <div class="mx-auto max-w-[1536px] px-4 pt-6 pb-20 sm:px-6 lg:px-[15%]">
            <h1 class="text-3xl text-white sm:text-[40px]">Find Your Dream Property <span class="text-gold-500">on Map</span></h1>
            <p class="mt-2 text-white/90 sm:text-lg">Explore houses, plots, apartments and commercial properties across {{ $activeCity ?? 'Pakistan' }}.</p>
            <x-search-tabs class="mt-4" :active="$tab" :links="collect($tabs)->map(fn ($t) => route('properties.map', array_merge(array_filter(['city' => $activeCity]), $t[1])))->all()" />
        </div>
    </section>

    <div class="mx-auto max-w-[1536px] px-4 sm:px-5" data-map-layout>
        <form method="GET" action="{{ route('properties.map') }}" class="relative z-10 -mt-10 grid scroll-mt-24 gap-2.5 rounded-lg bg-white p-3.5 shadow-lift sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_1fr_1fr_auto]" role="search" aria-label="Map search filters" data-map-form data-map-filters>
            @if ($purpose)<input type="hidden" name="purpose" value="{{ $purpose }}">@endif
            @if ($group && ! $search->types())<input type="hidden" name="group" value="{{ $group }}">@endif
            <div class="relative">
                <x-icon name="map-pin" class="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-600" />
                <select name="city" class="input h-12 pl-10 font-medium" aria-label="City" data-map-city-field>
                    <option value="">All cities</option>
                    @foreach (config('meezan.cities') as $city)<option value="{{ $city }}" @selected($activeCity === $city)>{{ $city }}</option>@endforeach
                </select>
            </div>
            <div class="relative">
                <x-icon name="building" class="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-600" />
                <select name="area" class="input h-12 pl-10" aria-label="Area or society">
                    <option value="">Area / Society</option>
                    @foreach (Lookup::areas($search->get('city'))->concat($search->areas())->unique('id') as $option)<option value="{{ $option->slug }}" @selected($area?->id === $option->id)>{{ $option->name }}</option>@endforeach
                </select>
            </div>
            <div class="relative">
                <x-icon name="home" class="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-600" />
                <select name="type" class="input h-12 pl-10" aria-label="Property type">
                    <option value="">Property Type</option>
                    @foreach (Lookup::typesByGroup() as $label => $types)
                        <optgroup label="{{ $label }}">@foreach ($types as $t)<option value="{{ $t->value }}" @selected($search->get('type') === $t->value)>{{ $t->label() }}</option>@endforeach</optgroup>
                    @endforeach
                </select>
            </div>
            <div class="relative">
                <x-icon name="layers" class="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-600" />
                <select name="max_price" class="input h-12 pl-10" aria-label="Price range">
                    <option value="">Price Range</option>
                    @foreach (Lookup::priceOptions($purpose === 'rent' ? 'rent' : 'sale') as $value => $label)<option value="{{ $value }}" @selected((int) $search->get('max_price') === $value)>Up to {{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="relative">
                <x-icon name="area" class="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-600" />
                <select name="size" class="input h-12 pl-10" aria-label="Size">
                    <option value="">Size (Marla / Kanal)</option>
                    @foreach (PropertySearch::SIZES as $value => $s)<option value="{{ $value }}" @selected($search->get('size') === $value)>{{ $s['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="relative">
                <x-icon name="bed" class="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-600" />
                <select name="beds" class="input h-12 pl-10" aria-label="Bedrooms">
                    <option value="">Bedrooms</option>
                    @foreach ([1, 2, 3, 4, 5] as $n)<option value="{{ $n }}" @selected((int) $search->get('beds') === $n)>{{ $n }}+ Beds</option>@endforeach
                </select>
            </div>
            <button class="btn btn-primary h-12 px-9 text-[15px]"><x-icon name="search" class="size-5" /> Search</button>
        </form>

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,400px)_minmax(0,1fr)] 2xl:grid-cols-[400px_minmax(0,1fr)_400px]">
            {{-- Results list --}}
            <section class="card order-2 p-3.5 lg:order-1" aria-label="Listings" data-map-list>
                <div data-map-results aria-busy="false">
                    @include('properties.partials.map-results')
                </div>
            </section>

            {{-- Map --}}
            <section class="relative order-1 h-[calc(100svh-150px)] min-h-[420px] overflow-hidden rounded-[var(--radius-card)] border border-line bg-brand-50 lg:order-2 lg:sticky lg:top-[104px] lg:h-[calc(100vh-120px)]" aria-label="Map" data-map-pane>
                @if ($mapConfig['enabled'])
                    <div class="size-full" data-geo-map="{{ json_encode($geoMap, JSON_UNESCAPED_SLASHES) }}" data-markers="{{ json_encode($map['markers'], JSON_UNESCAPED_SLASHES) }}"
                         role="application" aria-label="Interactive map of listings. Use the arrow keys to pan and plus or minus to zoom when the map is focused."></div>
                    <noscript><p class="absolute inset-4 grid place-items-center rounded-xl bg-white/95 p-6 text-center text-sm">The map needs JavaScript. All results are listed beside it.</p></noscript>

                    {{-- Top-left: city, follow mode --}}
                    <div class="absolute top-3 left-3 z-10 flex max-w-[calc(100%-4.5rem)] flex-wrap items-center gap-2">
                        <label class="sr-only" for="map-city">Go to city</label>
                        <select id="map-city" class="h-9 rounded-md border-0 bg-white py-1 pr-8 pl-3 text-[13px] font-semibold shadow focus:ring-2 focus:ring-brand-500" data-map-city>
                            <option value="">Pakistan</option>
                            @foreach (config('meezan.cities') as $city)<option value="{{ $city }}" @selected($activeCity === $city)>{{ $city }}</option>@endforeach
                        </select>
                        <label class="flex h-9 cursor-pointer items-center gap-2 rounded-md bg-white px-3 text-[13px] font-medium shadow">
                            <input type="checkbox" class="checkbox" data-map-follow @checked($search->hasBounds() && request()->boolean('follow'))> <span class="hidden 2xl:inline">Search as I move the map</span><span class="2xl:hidden">Search as I move</span>
                        </label>
                    </div>

                    {{-- Top-right: drawn-area search (reference: "Draw Area" control) --}}
                    <div class="absolute top-14 right-14 z-10 flex gap-2 xl:top-3">
                        <button type="button" class="btn btn-secondary btn-sm bg-white shadow" data-draw-clear @unless ($search->polygon()) hidden @endunless><x-icon name="x" class="size-4" /> Clear Area</button>
                        <button type="button" class="btn btn-outline btn-sm bg-white shadow" data-draw-start><x-icon name="pencil" class="size-4" /> <span data-draw-label>{{ $search->polygon() ? 'Redraw' : 'Draw Area' }}</span></button>
                    </div>
                    <div class="absolute inset-x-3 top-24 z-20 flex items-center justify-between gap-3 rounded-md bg-brand-600 px-4 py-2.5 text-sm text-white shadow-lift sm:top-16" data-draw-hint hidden role="status">
                        <span>Press and draw around the area you want, then release. Press Esc to cancel.</span>
                        <button type="button" class="btn btn-on-dark btn-sm" data-draw-cancel>Cancel</button>
                    </div>

                    {{-- Appears after the map is moved --}}
                    <button type="button" class="btn btn-primary btn-sm absolute top-[6.5rem] left-1/2 z-10 -translate-x-1/2 shadow-lift xl:top-[3.75rem]" data-map-search-area hidden>
                        <x-icon name="search" class="size-4" /> Search this area
                    </button>

                    <p class="absolute inset-x-3 top-1/2 z-20 mx-auto max-w-sm -translate-y-1/2 rounded-xl bg-white px-4 py-3 text-center text-sm font-medium shadow-lift" data-map-status role="alert" hidden></p>
                    <p class="pointer-events-none absolute top-[9rem] left-1/2 z-10 -translate-x-1/2 rounded-md bg-white/95 px-2.5 py-1 text-xs font-medium text-muted shadow" data-map-loading hidden>Loading…</p>

                    {{-- Bottom-left: what the markers mean (real counts) --}}
                    <p class="pointer-events-none absolute bottom-9 left-3 z-10 flex max-w-[calc(100%-1.5rem)] flex-wrap items-center gap-x-3 gap-y-1 rounded-md bg-white/95 px-3 py-1.5 text-[11.5px] text-muted shadow-card" data-map-summary>
                        @include('properties.partials.map-summary', ['located' => $map['located'], 'approximate' => $map['approximate'], 'mode' => $map['mode']])
                    </p>
                @else
                    <div class="grid size-full place-items-center p-6 text-center">
                        <div>
                            <x-icon name="map" class="mx-auto size-8 text-brand-400" />
                            <p class="mt-2 text-sm font-semibold">Map is not available</p>
                            <p class="mt-1 text-[13px] text-muted">Listings are still shown in the list.</p>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Detail panel (wide screens) --}}
            <aside class="card order-3 hidden self-start overflow-hidden 2xl:sticky 2xl:top-[104px] 2xl:block" aria-label="Selected property" aria-live="polite" data-map-detail>
                @if ($selected)
                    @include('properties.partials.map-detail', ['p' => $selected])
                @else
                    <p class="p-6 text-center text-sm text-muted">Select a listing on the map or in the list to see it here.</p>
                @endif
            </aside>
        </div>

        {{-- Phones: switch between the map and the list; filters are above. --}}
        <nav class="sticky bottom-3 z-30 mx-auto mt-4 flex w-max gap-1 rounded-full bg-brand-900 p-1 shadow-lift lg:hidden" aria-label="Map or list" data-map-switch hidden>
            <button type="button" class="rounded-full px-4 py-2 text-sm font-semibold text-white" data-map-view="map" aria-pressed="true"><x-icon name="map" class="mr-1 inline size-4" /> Map</button>
            <button type="button" class="rounded-full px-4 py-2 text-sm font-semibold text-white" data-map-view="list" aria-pressed="false"><x-icon name="list" class="mr-1 inline size-4" /> List</button>
            <button type="button" class="rounded-full px-4 py-2 text-sm font-semibold text-white" data-map-view="filters"><x-icon name="sliders" class="mr-1 inline size-4" /> Filters</button>
        </nav>
    </div>

    <x-slot:head>
        <style>body > footer { margin-top: 3rem }</style>
    </x-slot:head>
</x-layouts.app>
