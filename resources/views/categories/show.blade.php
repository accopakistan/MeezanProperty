@php
    use Illuminate\Support\Str;

    // Layout: public/Refrence/Purple Property Pages Collage.png (one panel per category)
    $hero = ['buy' => 'hero-buy', 'rent' => 'hero-rent', 'plots' => 'hero-plots', 'commercial' => 'hero-commercial'][$key];
    $p = fn (array $params) => route('properties.index', $params);

    $tiles = match ($key) {
        'buy' => [
            ['home', '5 Marla', $p(['purpose' => 'sale', 'group' => 'homes', 'size' => '5m'])],
            ['home', '10 Marla', $p(['purpose' => 'sale', 'group' => 'homes', 'size' => '10m'])],
            ['home', '1 Kanal', $p(['purpose' => 'sale', 'group' => 'homes', 'size' => '1k'])],
            ['home', '2 Kanal+', $p(['purpose' => 'sale', 'group' => 'homes', 'size' => '2k'])],
            ['home', 'Houses', $p(['purpose' => 'sale', 'type' => 'house'])],
            ['building', 'Apartments', $p(['purpose' => 'sale', 'type' => 'apartment'])],
            ['layers', 'Portions', $p(['purpose' => 'sale', 'type' => ['upper_portion', 'lower_portion']])],
            ['plot', 'Farmhouses', $p(['purpose' => 'sale', 'type' => 'farmhouse'])],
        ],
        'rent' => [
            ['home', 'Houses for Rent', $p(['purpose' => 'rent', 'type' => 'house'])],
            ['building', 'Apartments for Rent', $p(['purpose' => 'rent', 'type' => 'apartment'])],
            ['layers', 'Portions for Rent', $p(['purpose' => 'rent', 'type' => ['upper_portion', 'lower_portion']])],
            ['sofa', 'Furnished Rentals', $p(['purpose' => 'rent', 'furnishing' => 'furnished'])],
            ['home', 'Penthouses', $p(['purpose' => 'rent', 'type' => 'penthouse'])],
            ['store', 'Commercial Rentals', $p(['purpose' => 'rent', 'group' => 'commercial'])],
            ['briefcase', 'Office Spaces', $p(['purpose' => 'rent', 'type' => 'office'])],
            ['store', 'Shops for Rent', $p(['purpose' => 'rent', 'type' => 'shop'])],
        ],
        'plots' => [
            ['plot', 'Residential Plots', $p(['type' => 'residential_plot'])],
            ['building', 'Commercial Plots', $p(['type' => 'commercial_plot'])],
            ['file-text', 'Plot Files', $p(['type' => 'plot_file'])],
            ['map', 'Agricultural Land', $p(['type' => 'agricultural_land'])],
            ['plot', '5 Marla Plots', $p(['group' => 'plots', 'size' => '5m'])],
            ['plot', '10 Marla Plots', $p(['group' => 'plots', 'size' => '10m'])],
            ['plot', '1 Kanal Plots', $p(['group' => 'plots', 'size' => '1k'])],
            ['chart', '2 Kanal+', $p(['group' => 'plots', 'size' => '2k'])],
        ],
        'commercial' => [
            ['store', 'Shops for Sale', $p(['purpose' => 'sale', 'type' => 'shop'])],
            ['store', 'Shops for Rent', $p(['purpose' => 'rent', 'type' => 'shop'])],
            ['briefcase', 'Office Spaces', $p(['type' => 'office'])],
            ['building', 'Buildings & Plazas', $p(['type' => 'building'])],
            ['layers', 'Warehouses', $p(['type' => 'warehouse'])],
            ['building', 'Factories', $p(['type' => 'factory'])],
            ['plot', 'Commercial Plots', $p(['type' => 'commercial_plot'])],
            ['key', 'Commercial for Rent', $p(['purpose' => 'rent', 'group' => 'commercial'])],
        ],
    };

    $featuredTitle = ['buy' => 'Featured Houses for Sale', 'rent' => 'Featured Rental Properties', 'plots' => 'Featured Plots', 'commercial' => 'Featured Commercial Properties'][$key];
    $viewAll = ['buy' => 'View All Houses', 'rent' => 'View All Rentals', 'plots' => 'View All Plots', 'commercial' => 'View All Commercial'][$key];
@endphp
<x-layouts.app :title="$page['title']" :description="$page['seo']">
    <section class="relative isolate overflow-hidden bg-brand-900">
        <img src="{{ asset('images/ref/'.$hero.'.jpg') }}" alt="" class="absolute inset-y-0 right-0 -z-20 h-full w-full object-cover md:w-[62%]">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/85 to-brand-900/20 md:via-brand-900/70"></div>
        <div class="container-page pt-6 pb-24 sm:pb-28">
            <x-breadcrumbs :items="[[$page['eyebrow'], null]]" class="[&_a]:text-white/75 [&_a:hover]:text-white [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-8 text-[34px] leading-tight text-white sm:text-5xl">{{ $page['title'] }}</h1>
            <p class="mt-3 text-base text-white/90 sm:text-lg">{{ $page['intro'] }}</p>
            <p class="mt-2 text-sm text-white/70">{{ number_format($total) }} published {{ Str::plural('listing', $total) }}</p>
        </div>
    </section>

    <div class="container-page relative z-10 -mt-16">
        <x-search-bar variant="bar" :purpose="$page['purpose']" :group="$page['group'] ?? ($key === 'buy' ? 'homes' : null)"
                      :size-label="$key === 'plots' ? 'Plot Size' : ($key === 'commercial' ? 'Property Size' : null)" class="max-w-[1100px]" />

        <nav class="mt-5 grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-8" aria-label="Browse {{ strtolower($page['eyebrow']) }}">
            @foreach ($tiles as [$icon, $label, $url])
                <a href="{{ $url }}" class="group flex flex-col items-center justify-center rounded-lg border border-line bg-white px-2 py-5 text-center shadow-card transition hover:border-brand-300 hover:shadow-lift">
                    <x-icon :name="$icon" class="size-8 text-brand-600 transition group-hover:scale-110" />
                    <span class="mt-2.5 text-[13px] font-medium leading-tight text-ink">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    {{-- Listings --}}
    <section class="container-page mt-10" aria-labelledby="listings-title">
        <div class="flex items-end justify-between gap-3">
            <h2 id="listings-title" class="section-title">{{ $featuredTitle }}</h2>
            @if ($listings->isNotEmpty())
                <a href="{{ route('properties.index', $searchParams) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">{{ $viewAll }} <x-icon name="arrow-right" class="size-4" /></a>
            @endif
        </div>
        @if ($listings->isEmpty())
            <x-empty-state class="mt-5" :icon="$key === 'plots' ? 'plot' : ($key === 'commercial' ? 'store' : 'home')" title="No published listings here yet">
                New listings appear as soon as they pass our review. Tell our AI finder what you need and we’ll match you when something fits.
                <x-slot:actions>
                    <a href="{{ route('ai-finder') }}" class="btn btn-primary"><x-icon name="sparkles" class="size-4" /> Save my requirement</a>
                    <a href="{{ route('properties.index') }}" class="btn btn-secondary">Browse all property</a>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($listings as $property)
                    <x-property-card :property="$property" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Locations --}}
    @if ($locations->isNotEmpty())
        <section class="container-page mt-12" aria-labelledby="areas-title">
            <h2 id="areas-title" class="section-title text-2xl">{{ $page['eyebrow'] }} by Location</h2>
            <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($locations as $area)
                    <li>
                        <a href="{{ route('properties.index', array_merge($searchParams, ['area' => $area->slug])) }}" class="flex items-center justify-between rounded-lg border border-line bg-white px-4 py-3.5 text-sm font-medium transition hover:border-brand-300 hover:text-brand-700">
                            <span class="flex items-center gap-2"><x-icon name="map-pin" class="size-4 text-brand-500" /> {{ $area->name }}</span>
                            <span class="text-xs text-muted">{{ number_format($area->listings_count) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Guidance --}}
    <section class="container-page mt-12" aria-labelledby="tips-title">
        <div class="rounded-xl bg-brand-50 p-6 sm:p-9">
            <p class="eyebrow">Good to know</p>
            <h2 id="tips-title" class="section-title mt-2">{{ ['buy' => 'Before you buy', 'rent' => 'Before you rent', 'plots' => 'Before you buy a plot', 'commercial' => 'Choosing commercial space'][$key] }}</h2>
            <div class="mt-6 grid gap-4 md:grid-cols-3">
                @foreach ($page['tips'] as $tip)
                    <div class="rounded-lg bg-white p-6 shadow-card">
                        <x-icon :name="$tip['icon']" class="size-8 text-gold-600" />
                        <h3 class="mt-3 font-semibold">{{ $tip['title'] }}</h3>
                        <p class="mt-1.5 text-sm leading-6 text-muted">{{ $tip['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.app>
