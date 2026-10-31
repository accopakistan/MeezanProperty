@php
    /** @var array $summary */
    $title = "Property for Sale & Rent in {$city}";
    $description = "Houses, apartments, plots and commercial property in {$city}"
        .($summary['listings'] ? ' — '.number_format($summary['listings']).' reviewed '.\Illuminate\Support\Str::plural('listing', $summary['listings']) : '')
        .'. Browse '.number_format($summary['locations'])." areas, housing societies and phases in {$city} on Meezan Property.";
    $groupLabels = ['homes' => ['Homes', 'home', 'homes'], 'plots' => ['Plots', 'plot', 'plots'], 'commercial' => ['Commercial', 'store', 'commercial']];
@endphp
<x-layouts.app :title="$title" :description="$description" :canonical="route('cities.show', $slug)">
    <x-slot:head>
        <script type="application/ld+json">
            {!! json_encode(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $title,
                'url' => route('cities.show', $slug),
                'about' => array_filter([
                    '@type' => 'City',
                    'name' => $city,
                    'containedInPlace' => ['@type' => 'AdministrativeArea', 'name' => $summary['province']],
                    'geo' => $model?->hasCoordinates() ? ['@type' => 'GeoCoordinates', 'latitude' => $model->latitude, 'longitude' => $model->longitude] : null,
                ]),
            ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
        </script>
    </x-slot:head>

    <section class="bg-gradient-to-r from-brand-800 to-brand-600">
        <div class="container-page py-10">
            <x-breadcrumbs :items="[['Cities', route('home').'#cities'], [$city, null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <p class="mt-6 text-sm font-semibold tracking-wide text-gold-300 uppercase">{{ $summary['province'] }}, Pakistan</p>
            <h1 class="mt-1 text-4xl text-white sm:text-5xl">Property in {{ $city }}</h1>
            <p class="mt-3 max-w-2xl text-white/85">Search reviewed listings across {{ $city }}’s neighbourhoods, housing societies and commercial areas.</p>

            <form method="GET" action="{{ route('properties.index') }}" class="mt-6 grid max-w-3xl gap-2.5 rounded-lg bg-white p-3 shadow-lift sm:grid-cols-[1fr_auto_auto]" role="search">
                <input type="hidden" name="city" value="{{ $city }}">
                <label for="city-location" class="sr-only">Area or society in {{ $city }}</label>
                <x-area-combobox id="city-location" :city="$city" input-class="input h-12" :placeholder="'Area, society or phase in '.$city" />
                <select name="purpose" class="input h-12" aria-label="Buy or rent">
                    <option value="sale">Buy</option>
                    <option value="rent">Rent</option>
                </select>
                <button class="btn btn-primary h-12 px-8">Search</button>
            </form>

            <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-white">
                <div><dt class="text-xs text-white/70">Published listings</dt><dd class="text-2xl font-bold">{{ number_format($summary['listings']) }}</dd></div>
                <div><dt class="text-xs text-white/70">Areas &amp; societies in our directory</dt><dd class="text-2xl font-bold">{{ number_format($summary['locations']) }}</dd></div>
                <div><dt class="text-xs text-white/70">Verified dealers</dt><dd class="text-2xl font-bold">{{ number_format($summary['dealers']) }}</dd></div>
            </dl>
        </div>
    </section>

    <div class="container-page py-10">
        <section aria-labelledby="browse-title">
            <h2 id="browse-title" class="section-title">Browse {{ $city }}</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <a href="{{ route('properties.index', ['city' => $city, 'purpose' => 'sale']) }}" class="card flex items-center gap-3 p-4 hover:shadow-lift"><x-icon name="home" class="size-6 text-brand-600" /><span class="font-semibold">Buy in {{ $city }}</span></a>
                <a href="{{ route('properties.index', ['city' => $city, 'purpose' => 'rent']) }}" class="card flex items-center gap-3 p-4 hover:shadow-lift"><x-icon name="key" class="size-6 text-brand-600" /><span class="font-semibold">Rent in {{ $city }}</span></a>
                @foreach ($groupLabels as $group => [$label, $icon])
                    <a href="{{ route('properties.index', ['city' => $city, 'group' => $group]) }}" class="card flex items-center justify-between gap-3 p-4 hover:shadow-lift">
                        <span class="flex items-center gap-3"><x-icon :name="$icon" class="size-6 text-brand-600" /><span class="font-semibold">{{ $label }}</span></span>
                        <span class="text-sm text-muted">{{ number_format($groups[$group] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
            <p class="mt-3 text-sm"><a href="{{ route('properties.map', ['city' => $city]) }}" class="link inline-flex items-center gap-1"><x-icon name="map-pin" class="size-4" /> Search {{ $city }} on the map</a></p>
        </section>

        <section class="mt-12" aria-labelledby="latest-title">
            <div class="flex items-end justify-between gap-3">
                <h2 id="latest-title" class="section-title">Latest listings in {{ $city }}</h2>
                @if ($latest->isNotEmpty())
                    <a href="{{ route('properties.index', ['city' => $city, 'sort' => 'newest']) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">See all <x-icon name="arrow-right" class="size-4" /></a>
                @endif
            </div>
            @if ($latest->isEmpty())
                <x-empty-state class="mt-5" icon="building" title="No published listings in {{ $city }} yet">
                    Meezan Property has just opened in {{ $city }}. Dealers and owners can list here now; every listing is reviewed before it appears.
                    <x-slot:actions>
                        <a href="{{ route('sell') }}" class="btn btn-primary">List a property</a>
                        <a href="{{ route('account.requirements.create') }}" class="btn btn-secondary">Tell us what you need</a>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($latest as $property)
                        <x-property-card :property="$property" />
                    @endforeach
                </div>
            @endif
        </section>

        @if ($popular->isNotEmpty())
            <section class="mt-12" aria-labelledby="popular-title">
                <h2 id="popular-title" class="section-title">Popular areas in {{ $city }}</h2>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($popular as $area)
                        <li>
                            <a href="{{ route('properties.index', ['area' => $area->slug]) }}" class="flex items-center justify-between rounded-lg border border-line bg-white px-4 py-3.5 text-sm font-medium transition hover:border-brand-300 hover:text-brand-700">
                                <span class="flex items-center gap-2"><x-icon name="map-pin" class="size-4 text-brand-500" /> {{ $area->name }}</span>
                                @if ($n = $listedCounts[$area->id] ?? 0)
                                    <span class="text-xs text-muted">{{ number_format($n) }} {{ \Illuminate\Support\Str::plural('listing', $n) }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-12" aria-labelledby="directory-title">
            <h2 id="directory-title" class="section-title">Areas and societies in {{ $city }}</h2>
            <p class="mt-2 max-w-3xl text-sm text-muted">
                Our location directory, compiled from development-authority lists and public map data. An authority status shown on an area guide
                repeats what that authority published on the date noted; always confirm approvals with the authority before buying.
            </p>
            @foreach ($directory as $label => $areas)
                <details class="group mt-4 rounded-lg border border-line bg-white" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 font-semibold">
                        {{ $label }} <span class="text-sm font-normal text-muted">{{ number_format($areas->count()) }}</span>
                    </summary>
                    <ul class="grid gap-x-6 gap-y-1.5 border-t border-line px-4 py-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($areas as $area)
                            <li><a href="{{ route('areas.show', $area) }}" class="hover:text-brand-700">{{ $area->name }}</a></li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </section>

        @if ($dealers->isNotEmpty())
            <section class="mt-12" aria-labelledby="dealers-title">
                <div class="flex items-end justify-between gap-3">
                    <h2 id="dealers-title" class="section-title">Verified dealers in {{ $city }}</h2>
                    <a href="{{ route('dealers.index', ['city' => $city]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">All dealers <x-icon name="arrow-right" class="size-4" /></a>
                </div>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($dealers as $dealer)
                        <li><a href="{{ route('dealers.show', $dealer) }}" class="card flex items-center gap-3 p-4 hover:shadow-lift"><x-icon name="briefcase" class="size-5 text-brand-600" /><span class="font-semibold">{{ $dealer->agency_name }}</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-12 rounded-xl bg-brand-50 p-6" aria-labelledby="other-title">
            <h2 id="other-title" class="text-lg font-bold">Property in other cities</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($otherCities as $other)
                    <li><a href="{{ route('cities.show', \App\Support\Cities::slug($other)) }}" class="inline-flex rounded-full border border-brand-200 bg-white px-4 py-1.5 text-sm font-medium hover:border-brand-400">{{ $other }}</a></li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.app>
