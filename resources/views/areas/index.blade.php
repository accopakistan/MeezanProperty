<x-layouts.app title="Area guides" description="Neighbourhood and housing society guides for Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar, with live listing counts and asking-price summaries.">
    <div class="container-page py-8">
        <x-breadcrumbs :items="[['Area guides', null]]" />
        <h1 class="mt-5 text-4xl">Area Guides</h1>
        <p class="mt-2 text-muted">Popular areas and areas with live listings in each city. Every city page lists the full directory of areas, societies and phases.</p>
        @foreach ($cities as $city => $areas)
            <div class="mt-8 flex flex-wrap items-end justify-between gap-2">
                <h2 class="section-title text-2xl">{{ $city }}</h2>
                <a href="{{ route('cities.show', \App\Support\Cities::slug($city)) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">All areas in {{ $city }} <x-icon name="arrow-right" class="size-4" /></a>
            </div>
            <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($areas as $area)
                    <li>
                        <a href="{{ route('areas.show', $area) }}" class="flex items-center justify-between rounded-lg border border-line bg-white px-4 py-3.5 text-sm font-medium transition hover:border-brand-300 hover:text-brand-700">
                            <span class="flex items-center gap-2"><x-icon name="map-pin" class="size-4 text-brand-500" /> {{ $area->name }}</span>
                            <span class="text-xs text-muted">{{ number_format($area->listings_count) }} {{ \Illuminate\Support\Str::plural('listing', $area->listings_count) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </div>
</x-layouts.app>
