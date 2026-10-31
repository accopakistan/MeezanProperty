@php use App\Support\Format; @endphp
@php
    $trail = array_values(array_filter([
        [$area->city, route('cities.show', \App\Support\Cities::slug($area->city))],
        $area->parent?->parent ? [$area->parent->parent->name, route('areas.show', $area->parent->parent)] : null,
        $area->parent ? [$area->parent->name, route('areas.show', $area->parent)] : null,
        [$area->name, null],
    ]));
@endphp
<x-layouts.app :title="$area->name.', '.$area->city.' — area guide'" :description="'Property in '.$area->name.', '.$area->city.': '.$insights['total'].' live listings, asking prices and nearby options on Meezan Property.'"
               :noindex="$insights['total'] === 0 && ! $area->is_popular">
    <section class="bg-gradient-to-r from-brand-800 to-brand-600">
        <div class="container-page py-10">
            <x-breadcrumbs :items="$trail" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-5 text-4xl text-white">{{ $area->name }}, {{ $area->city }}</h1>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('properties.index', ['area' => $area->slug, 'purpose' => 'sale']) }}" class="btn btn-gold">Buy in {{ $area->name }}</a>
                <a href="{{ route('properties.index', ['area' => $area->slug, 'purpose' => 'rent']) }}" class="btn btn-on-dark">Rent in {{ $area->name }}</a>
                <a href="{{ route('properties.map', ['area' => $area->slug]) }}" class="btn btn-on-dark"><x-icon name="map" class="size-4" /> Map</a>
                <a href="{{ route('dealers.index', ['area' => $area->slug]) }}" class="btn btn-on-dark"><x-icon name="briefcase" class="size-4" /> Dealers here</a>
            </div>
        </div>
    </section>

    <div class="container-page mt-8">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Live listings" :value="$insights['total']" icon="layers" />
            <x-stat-card label="For sale" :value="$insights['sale']" icon="tag" tone="gold" />
            <x-stat-card label="For rent" :value="$insights['rent']" icon="key" tone="info" />
            <x-stat-card label="Listed in last 30 days" :value="$insights['newThisMonth']" icon="calendar" tone="success" :hint="$dealerCount.' verified dealers serve this area'" />
        </div>

        @include('areas.partials.location-details')

        <section class="card card-body mt-6" aria-labelledby="prices-title">
            <h2 id="prices-title" class="font-serif text-xl font-bold">Asking prices in {{ $area->name }}</h2>
            <p class="mt-1 text-[13px] text-muted">Medians of current asking prices on Meezan Property — not sale prices or valuations. Shown only when at least {{ $insights['minSample'] }} comparable listings exist.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($insights['metrics'] as $m)
                    <div class="rounded-lg border border-line p-4">
                        <p class="text-sm font-semibold">{{ $m['label'] }}</p>
                        @if ($m['median'])
                            <p class="mt-1 text-2xl font-bold text-brand-600">{{ Format::price($m['median']) }}</p>
                            <p class="text-xs text-muted">Range {{ Format::price($m['low']) }} – {{ Format::price($m['high']) }} · {{ $m['sample'] }} listings</p>
                        @else
                            <p class="mt-1 text-sm text-muted">Not enough data yet ({{ $m['sample'] }} of {{ $insights['minSample'] }} listings needed).</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        @if ($insights['byType']->isNotEmpty())
            <section class="card card-body mt-6">
                <h2 class="font-serif text-xl font-bold">What’s listed</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($insights['byType'] as $label => $count)<span class="badge bg-brand-50 px-3 py-1 text-[13px] text-brand-700">{{ $label }} · {{ $count }}</span>@endforeach
                </div>
            </section>
        @endif

        @if ($latest->isNotEmpty())
            <section class="mt-10">
                <div class="flex items-end justify-between gap-3">
                    <h2 class="section-title text-2xl">Newest in {{ $area->name }}</h2>
                    <a href="{{ route('properties.index', ['area' => $area->slug]) }}" class="text-sm font-semibold text-brand-600">View all →</a>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach ($latest as $property)<x-property-card :property="$property" />@endforeach</div>
            </section>
        @endif

        @if ($projects->isNotEmpty())
            <section class="mt-10">
                <h2 class="section-title text-2xl">Projects in {{ $area->name }}</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-3">@foreach ($projects as $project)<li><a href="{{ route('projects.show', $project) }}" class="card block p-4 font-semibold hover:text-brand-700">{{ $project->name }} <span class="block text-xs font-normal text-muted">{{ $project->statusLabel() }}</span></a></li>@endforeach</ul>
            </section>
        @endif
    </div>
</x-layouts.app>
