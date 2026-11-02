@php
    use App\Support\Format;
    use App\Support\Seo;
    $label = $definition['label'];
    $total = $listings->total();
    $title = $label.' in '.$place;
    $description = $total
        ? number_format($total).' '.\Illuminate\Support\Str::plural('listing', $total).' — '.strtolower($label).' in '.$place.($priceRange ? ', asking '.Format::price($priceRange[0]).($priceRange[1] > $priceRange[0] ? ' to '.Format::price($priceRange[1]) : '') : '').'. Reviewed listings with photos, prices and dealer details on Meezan Property.'
        : 'No '.strtolower($label).' in '.$place.' right now. Save a search or ask the AI Property Finder to tell you when listings appear on Meezan Property.';
    $trail = array_values(array_filter([
        [$city, route('cities.show', $citySlug)],
        $area?->parent?->parent ? [$area->parent->parent->name, route('areas.show', $area->parent->parent->slug)] : null,
        $area?->parent ? [$area->parent->name, route('areas.show', $area->parent->slug)] : null,
        $area ? [$area->name, route('areas.show', $area->slug)] : null,
        [$label, null],
    ]));
@endphp
<x-layouts.app :title="$title" :description="$description" :canonical="$canonical" :robots="$indexable ? null : 'noindex, follow'">
    @if ($indexable)
        <x-slot:head>
            <script type="application/ld+json">
                {!! Seo::json([
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $title,
                    'url' => $canonical,
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'numberOfItems' => $total,
                        'itemListElement' => $listings->getCollection()->reject->is_demo->values()->map(fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => route('properties.show', $p), 'name' => $p->title])->all(),
                    ],
                ]) !!}
            </script>
        </x-slot:head>
    @endif

    <section class="bg-gradient-to-r from-brand-800 to-brand-600">
        <div class="container-page py-10">
            <x-breadcrumbs :items="$trail" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-5 text-3xl text-white sm:text-4xl">{{ $title }}</h1>
            <p class="mt-3 max-w-2xl text-white/85">
                @if ($total)
                    {{ number_format($total) }} reviewed {{ \Illuminate\Support\Str::plural('listing', $total) }}@if ($priceRange), asking {{ Format::price($priceRange[0]) }}{{ $priceRange[1] > $priceRange[0] ? ' to '.Format::price($priceRange[1]) : '' }}@endif.
                @else
                    There are no {{ strtolower($label) }} in {{ $place }} at the moment.
                @endif
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ $searchUrl }}" class="btn btn-gold"><x-icon name="sliders" class="size-4" /> Refine this search</a>
                <a href="{{ route('properties.map', array_filter(['city' => $area ? null : $city, 'area' => $area?->slug])) }}" class="btn btn-on-dark"><x-icon name="map" class="size-4" /> Map</a>
                <a href="{{ route('ai-finder') }}" class="btn btn-on-dark"><x-icon name="sparkles" class="size-4" /> AI Property Finder</a>
            </div>
        </div>
    </section>

    <div class="container-page py-10">
        @if ($listings->isEmpty())
            <x-empty-state icon="home" :title="'No '.strtolower($label).' in '.$place.' yet'">
                New listings are reviewed before they appear. Try nearby areas below, the whole of {{ $city }}, or let the AI Property Finder look for you.
            </x-empty-state>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($listings as $property)
                    <x-property-card :property="$property" />
                @endforeach
            </div>
            <div class="mt-8">{{ $listings->links() }}</div>
        @endif

        <div class="mt-12 grid gap-6 lg:grid-cols-2">
            @if ($related->isNotEmpty())
                <nav class="rounded-xl border border-line bg-white p-5" aria-labelledby="related-title">
                    <h2 id="related-title" class="text-base font-bold">More in {{ $place }}</h2>
                    <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                        @foreach ($related as $slug => $counts)
                            <li><a href="{{ $area ? route('areas.category', [$area->slug, $slug]) : route('cities.category', [$citySlug, $slug]) }}" class="btn btn-secondary btn-sm">{{ config('meezan.seo.categories.'.$slug.'.label') }} <span class="text-xs font-normal text-muted">{{ $counts['listings'] }}</span></a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            <nav class="rounded-xl border border-line bg-white p-5" aria-labelledby="places-title">
                <h2 id="places-title" class="text-base font-bold">{{ $area ? 'Nearby and wider' : $label.' by area' }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                    @if ($area)
                        <li><a href="{{ route('areas.show', $area->slug) }}" class="btn btn-secondary btn-sm">{{ $area->name }} area guide</a></li>
                        @if ($area->parent)<li><a href="{{ route('areas.category', [$area->parent->slug, $category]) }}" class="btn btn-secondary btn-sm">{{ $label }} in {{ $area->parent->name }}</a></li>@endif
                        @foreach ($childAreas->take(8) as $child)
                            <li><a href="{{ route('areas.category', [$child->slug, $category]) }}" class="btn btn-secondary btn-sm">{{ $child->name }}</a></li>
                        @endforeach
                        <li><a href="{{ route('cities.category', [$citySlug, $category]) }}" class="btn btn-secondary btn-sm">{{ $label }} in {{ $city }}</a></li>
                    @else
                        @forelse ($topAreas as $top)
                            <li><a href="{{ route('areas.category', [$top->slug, $category]) }}" class="btn btn-secondary btn-sm">{{ $top->name }} <span class="text-xs font-normal text-muted">{{ $top->total }}</span></a></li>
                        @empty
                            <li class="text-muted">No areas with these listings yet. <a href="{{ route('cities.show', $citySlug) }}" class="link">Browse {{ $city }}’s areas</a>.</li>
                        @endforelse
                    @endif
                </ul>
            </nav>
        </div>
    </div>
</x-layouts.app>
