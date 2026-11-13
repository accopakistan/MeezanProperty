@php
    use App\Support\Lookup;
    // Layout: public/Refrence/Meezan Property Real Estate Homepage.png
    $showcase = $featured->isNotEmpty() ? $featured : $latest;
    $areaTile = fn (string $name) => Lookup::areas()->firstWhere('name', $name);
    $areaTiles = array_values(array_filter([
        ['DHA Lahore', 'area-dha', ($a = $areaTile('DHA Lahore')) ? route('properties.index', ['area' => $a->slug]) : route('properties.index', ['q' => 'DHA', 'city' => 'Lahore'])],
        ($a = $areaTile('Bahria Town')) ? ['Bahria Town', 'area-bahria', route('properties.index', ['area' => $a->slug])] : null,
        ($a = $areaTile('Lake City')) ? ['Lake City', 'area-lake-city', route('properties.index', ['area' => $a->slug])] : null,
        ($a = $areaTile('Gulberg')) ? ['Gulberg', 'area-lda', route('properties.index', ['area' => $a->slug])] : null,
        ($a = $areaTile('Johar Town')) ? ['Johar Town', 'area-johar', route('properties.index', ['area' => $a->slug])] : null,
        ($a = $areaTile('Model Town')) ? ['Model Town', 'area-model-town', route('properties.index', ['area' => $a->slug])] : null,
    ]));
    $typeTiles = [
        ['Houses', 'type-houses', route('properties.index', ['type' => 'house'])],
        ['Apartments', 'type-apartments', route('properties.index', ['type' => 'apartment'])],
        ['Plots', 'type-plots', route('properties.plots')],
        ['Commercial', 'type-commercial', route('properties.commercial')],
        ['Rentals', 'type-projects', route('properties.rent')],
    ];
    $popular = array_values(array_filter([
        ['DHA Lahore', ['q' => 'DHA']],
        ($a = $areaTile('Bahria Town')) ? ['Bahria Town', ['area' => $a->slug]] : null,
        ($a = $areaTile('Lake City')) ? ['Lake City', ['area' => $a->slug]] : null,
        ($a = $areaTile('Gulberg')) ? ['Gulberg', ['area' => $a->slug]] : null,
        ($a = $areaTile('Johar Town')) ? ['Johar Town', ['area' => $a->slug]] : null,
        ($a = $areaTile('Model Town')) ? ['Model Town', ['area' => $a->slug]] : null,
    ]));
@endphp
@php $heroPhoto = config('meezan.place_photos.home'); @endphp
<x-layouts.app>
    <x-slot:head>
        <link rel="preload" as="image" href="{{ asset($heroPhoto['file']) }}">
        <script type="application/ld+json">
            {!! json_encode([
                '@'.'context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => 'Meezan Property',
                'url' => route('home'),
                'potentialAction' => ['@type' => 'SearchAction', 'target' => route('properties.index').'?location={search_term_string}', 'query-input' => 'required name=search_term_string'],
            ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
        </script>
        <script type="application/ld+json">{!! \App\Support\Seo::json(\App\Support\Seo::organization()) !!}</script>
    </x-slot:head>

    {{-- Hero ---------------------------------------------------------------}}
    <section class="relative isolate overflow-hidden bg-brand-900">
        {{-- Pakistan's landmark photo (config meezan.place_photos.home), credited below as its licence requires. --}}
        <img src="{{ asset($heroPhoto['file']) }}" alt="{{ $heroPhoto['alt'] }}" width="1920" height="1163" fetchpriority="high"
             class="absolute inset-y-0 right-0 -z-20 h-full w-full object-cover opacity-70 md:w-[72%] md:opacity-100" style="object-position: {{ $heroPhoto['position'] }}">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/85 to-brand-900/10 md:via-brand-900/70"></div>
        <div class="absolute inset-x-0 bottom-0 -z-10 h-24 bg-gradient-to-t from-brand-900/80 to-transparent"></div>

        <div class="mx-auto grid max-w-[1440px] gap-8 px-4 pt-10 pb-8 sm:px-6 lg:px-10 xl:grid-cols-[minmax(0,1fr)_300px] xl:pt-9">
            <div class="max-w-[1090px]">
                <p class="flex items-center gap-3 text-[12px] font-medium tracking-[0.12em] text-gold-300 uppercase">
                    Property across Pakistan <span class="h-px w-24 bg-gold-500"></span>
                </p>
                <h1 class="mt-4 text-[44px] leading-[1.02] text-white sm:text-6xl lg:text-[68px]">Find Your<br>Perfect Property</h1>
                <p class="mt-4 max-w-lg text-[17px] leading-7 text-white/90">Houses, apartments, plots and commercial property in Lahore, Karachi, Islamabad and four more cities — every listing reviewed before it goes live.</p>

                <x-search-bar class="mt-7" />

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="mr-1 text-[13px] font-semibold text-white">Popular Searches:</span>
                    @foreach ($popular as [$label, $params])
                        <a href="{{ route('properties.index', $params) }}" class="chip-dark">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <aside class="hidden self-start rounded-xl border border-white/15 bg-brand-900/55 p-2 backdrop-blur-md xl:mt-6 xl:block" aria-label="Why Meezan Property">
                @foreach ([
                    ['home', 'Reviewed Listings', 'Every listing checked before it goes live'],
                    ['shield', 'Trusted Dealers', 'Verified badge only after our checks'],
                    ['sparkles', 'AI Property Match', 'Find properties that match your needs'],
                    ['whatsapp', 'WhatsApp Support', 'Chat with our team on WhatsApp'],
                ] as [$icon, $title, $body])
                    <div class="flex items-center gap-4 border-b border-white/10 px-4 py-3.5 last:border-0">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white/10 text-gold-300 ring-1 ring-white/15"><x-icon :name="$icon" class="size-5" /></span>
                        <div>
                            <p class="text-[15px] font-semibold text-white">{{ $title }}</p>
                            <p class="text-xs leading-5 text-white/75">{{ $body }}</p>
                        </div>
                    </div>
                @endforeach
            </aside>
        </div>
        <x-photo-credit :photo="$heroPhoto" />
    </section>

    {{-- Feature strip -----------------------------------------------------}}
    <section class="bg-white" aria-label="Highlights">
        <div class="mx-auto grid max-w-[1440px] grid-cols-2 px-4 py-6 sm:px-6 md:grid-cols-3 lg:grid-cols-6 lg:px-10">
            @foreach ([
                ['home', 'Wide Selection', 'Houses, plots, apartments and commercial', route('properties.index')],
                ['badge-check', 'Verified Dealers', 'Professional and trusted dealer network', route('pages.how-it-works')],
                ['sparkles', 'AI Property Match', 'Smart recommendations based on your needs', route('ai-finder')],
                ['whatsapp', 'WhatsApp Support', 'Chat with our team', \App\Support\Contact::whatsappUrl() ?? route('pages.contact')],
                ['map', 'Map Search', 'Explore listings city by city', route('properties.map')],
                ['shield', 'Reviewed Listings', 'Checked by our team before publishing', route('pages.how-it-works')],
            ] as [$icon, $title, $body, $url])
                <a href="{{ $url }}" class="group flex flex-col items-center px-3 py-3 text-center lg:border-r lg:border-line lg:last:border-0">
                    <span class="grid size-12 place-items-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-brand-100 transition group-hover:bg-brand-600 group-hover:text-white"><x-icon :name="$icon" class="size-6" /></span>
                    <span class="mt-2 text-[15px] font-semibold text-ink">{{ $title }}</span>
                    <span class="mt-0.5 max-w-44 text-[13px] leading-5 text-muted">{{ $body }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-10">
        {{-- Featured properties + promo -------------------------------------}}
        <section class="mt-10 grid gap-6 xl:grid-cols-[minmax(0,1fr)_470px]" aria-labelledby="featured-title">
            <div class="min-w-0">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="featured-title" class="section-title">{{ $featured->isNotEmpty() ? 'Featured Properties' : 'Latest Properties' }}</h2>
                    @if ($showcase->isNotEmpty())
                        <a href="{{ route('properties.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-800">View All Properties <x-icon name="arrow-right" class="size-4" /></a>
                    @endif
                </div>
                @if ($showcase->isEmpty())
                    <x-empty-state class="mt-5" icon="home" title="Listings are on their way">
                        We review every listing before it is published, so there is nothing to show just yet.
                        <x-slot:actions>
                            <a href="{{ route('dealer.register') }}" class="btn btn-primary">Register as a dealer</a>
                            <a href="{{ route('ai-finder') }}" class="btn btn-secondary">Save what you need</a>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($showcase->take(4) as $property)
                            <x-property-card :property="$property" />
                        @endforeach
                    </div>
                @endif
            </div>

            <a href="{{ route('ai-finder') }}" class="group relative isolate flex min-h-64 overflow-hidden rounded-xl bg-brand-800 p-7 text-white">
                <img src="{{ asset('images/ref/hero-map.jpg') }}" alt="" class="absolute inset-0 -z-20 size-full object-cover opacity-60">
                <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/85 to-brand-800/30"></div>
                <div class="flex max-w-60 flex-col">
                    <p class="font-serif text-[34px] leading-[1.05] font-bold">Search Smarter, with AI</p>
                    <p class="mt-4 text-sm leading-6 text-white/85">Describe what you need and get the best-fitting reviewed listings — with every match explained.</p>
                    <span class="btn btn-gold mt-auto w-max pt-2.5"><x-icon name="sparkles" class="size-4" /> Try AI Property Finder</span>
                </div>
                <div class="absolute top-6 right-6 hidden w-44 rounded-2xl border-4 border-ink/80 bg-white p-3 text-ink shadow-lift sm:block" aria-hidden="true">
                    <x-logo class="h-10 w-auto" />
                    <div class="mt-3 space-y-2 text-[10px]">
                        <p class="rounded-lg rounded-br-sm bg-brand-600 px-2 py-1.5 text-white">10 marla house in DHA under 6 crore</p>
                        <p class="rounded-lg rounded-bl-sm bg-page px-2 py-1.5">Here are the best options I found…</p>
                        <div class="grid grid-cols-2 gap-1.5">
                            <img src="{{ asset('images/ref/demo-house-3.jpg') }}" alt="" class="aspect-[4/3] rounded object-cover">
                            <img src="{{ asset('images/ref/demo-house-5.jpg') }}" alt="" class="aspect-[4/3] rounded object-cover">
                        </div>
                    </div>
                </div>
            </a>
        </section>

        {{-- Explore by type + popular areas ----------------------------------}}
        <div class="mt-10 grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.72fr)]">
            <section aria-labelledby="types-title">
                <h2 id="types-title" class="section-title text-2xl sm:text-[26px]">Explore by Property Type</h2>
                <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-5">
                    @foreach ($typeTiles as [$label, $image, $url])
                        <a href="{{ $url }}" class="group relative block aspect-[16/10] overflow-hidden rounded-lg">
                            <img src="{{ asset('images/ref/'.$image.'.jpg') }}" alt="{{ $label }}" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
                            <span class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent"></span>
                            <span class="absolute inset-x-3 bottom-2.5 flex items-center justify-between text-[15px] font-semibold text-white">
                                {{ $label }}
                                <span class="grid size-6 place-items-center rounded-full bg-white text-ink"><x-icon name="arrow-right" class="size-3.5" /></span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section aria-labelledby="areas-title">
                <div class="flex items-end justify-between gap-3">
                    <h2 id="areas-title" class="section-title text-2xl sm:text-[26px]">Popular Areas in Lahore</h2>
                    <a href="{{ route('properties.map') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">View All Areas <x-icon name="arrow-right" class="size-4" /></a>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2.5 sm:grid-cols-6">
                    @foreach ($areaTiles as [$label, $image, $url])
                        <a href="{{ $url }}" class="group block overflow-hidden rounded-lg border border-line bg-white shadow-card">
                            <img src="{{ asset('images/ref/'.$image.'.jpg') }}" alt="{{ $label }}" loading="lazy" class="aspect-[6/5] w-full object-cover transition duration-500 group-hover:scale-105">
                            <span class="block px-1.5 py-1.5 text-center text-xs leading-tight font-medium text-ink">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Launch cities: real counts only ------------------------------------}}
        <section id="cities" class="mt-12 scroll-mt-28" aria-labelledby="cities-title">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="cities-title" class="section-title">Explore Properties Across Pakistan</h2>
                    <p class="mt-1 text-[15px] text-muted">Seven cities, one standard: every listing reviewed before it is published.</p>
                </div>
            </div>
            <ul class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-7">
                @foreach ($cities as $c)
                    <li>
                        <a href="{{ route('cities.show', $c['slug']) }}" class="group flex h-full flex-col rounded-lg border border-line bg-white p-4 shadow-card transition hover:border-brand-300 hover:shadow-lift">
                            <span class="flex items-center justify-between">
                                <span class="text-lg font-bold text-ink group-hover:text-brand-700">{{ $c['name'] }}</span>
                                <x-icon name="arrow-right" class="size-4 text-brand-500 transition group-hover:translate-x-0.5" />
                            </span>
                            <span class="text-xs text-muted">{{ $c['province'] }}</span>
                            <span class="mt-3 text-sm font-medium {{ $c['listings'] ? 'text-ink' : 'text-muted' }}">
                                {{ $c['listings'] ? number_format($c['listings']).' '.\Illuminate\Support\Str::plural('listing', $c['listings']) : 'New on Meezan' }}
                            </span>
                            <span class="text-xs text-muted">{{ number_format($c['locations']) }} areas &amp; societies</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Newly listed (below the reference's first screen) ----------------}}
        @if ($featured->isNotEmpty() && $latest->isNotEmpty())
            <section class="mt-12" aria-labelledby="latest-title">
                <div class="flex items-end justify-between gap-3">
                    <h2 id="latest-title" class="section-title">Newly Listed</h2>
                    <a href="{{ route('properties.index', ['sort' => 'newest']) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">See all new listings <x-icon name="arrow-right" class="size-4" /></a>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($latest->take(4) as $property)
                        <x-property-card :property="$property" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Dealer call-to-action -------------------------------------------}}
        <section class="mt-12 overflow-hidden rounded-xl bg-brand-800 text-white" aria-labelledby="dealer-title">
            <div class="grid items-center gap-6 p-7 sm:p-10 lg:grid-cols-[1fr_auto]">
                <div>
                    <h2 id="dealer-title" class="font-serif text-3xl font-bold text-white">Are you a property dealer?</h2>
                    <p class="mt-2 max-w-2xl text-white/80">List your inventory, get verified, and receive buyer leads matched to the areas you work in.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('dealer.register') }}" class="btn btn-gold btn-lg">Join as a Dealer</a>
                    <a href="{{ route('login') }}" class="btn btn-on-dark btn-lg">Dealer Login</a>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
