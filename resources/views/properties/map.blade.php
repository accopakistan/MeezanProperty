@php
    use App\Services\PropertySearch;
    use App\Support\Lookup;

    /** @var PropertySearch $search */
    // Layout: public/Refrence/Meezan Property Map Search Dashboard.png (+ "(1)" variant)
    $purpose = $search->get('purpose');
    $group = $search->get('group');
    $tab = $group === 'plots' ? 'plots' : ($group === 'commercial' ? 'commercial' : ($purpose === 'rent' ? 'rent' : 'sale'));
    $tabs = ['sale' => ['Buy', ['purpose' => 'sale']], 'rent' => ['Rent', ['purpose' => 'rent']], 'plots' => ['Plot', ['group' => 'plots']], 'commercial' => ['Commercial', ['group' => 'commercial']]];
    $keep = request()->except(['page', 'selected']);
    $p = $selected;
    $profile = $p?->dealer?->dealerProfile;
    $verified = $p?->hasVerifiedDealer();
@endphp
<x-layouts.app :title="'Map search — '.$search->headline()" description="Search property in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar on the map. See reviewed listings for sale and rent with prices by location." noindex>
    <section class="relative isolate overflow-hidden bg-brand-900">
        <img src="{{ asset('images/ref/hero-map.jpg') }}" alt="" class="absolute inset-y-0 right-0 -z-20 h-full w-full object-cover md:w-[58%]">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/85 to-brand-900/10"></div>
        <div class="mx-auto max-w-[1536px] px-4 pt-7 pb-20 sm:px-6 lg:px-[15%]">
            <h1 class="text-3xl text-white sm:text-[40px]">Find Your Dream Property <span class="text-gold-500">on Map</span></h1>
            <p class="mt-2 text-white/90 sm:text-lg">Explore houses, plots, apartments and commercial properties across {{ $search->get('city') ?? 'Pakistan' }}.</p>
            <div class="mt-4 inline-flex overflow-hidden rounded-md bg-brand-800/80 backdrop-blur">
                @foreach ($tabs as $value => [$label, $params])
                    <a href="{{ route('properties.map', $params) }}" @class(['px-6 py-2.5 text-[15px] font-medium sm:px-9', 'bg-gold-500 text-brand-900' => $tab === $value, 'text-white hover:bg-white/10' => $tab !== $value])>{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-[1536px] px-4 sm:px-5">
        <form method="GET" action="{{ route('properties.map') }}" class="relative z-10 -mt-10 grid gap-2.5 rounded-lg bg-white p-3.5 shadow-lift sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_1fr_1fr_auto]" role="search">
            @if ($purpose)<input type="hidden" name="purpose" value="{{ $purpose }}">@endif
            @if ($group && ! $search->types())<input type="hidden" name="group" value="{{ $group }}">@endif
            <div class="relative">
                <x-icon name="map-pin" class="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-600" />
                <select name="city" class="input h-12 pl-10 font-medium" aria-label="City">
                    <option value="">All cities</option>
                    @foreach (config('meezan.cities') as $city)<option value="{{ $city }}" @selected($search->get('city', $search->area()?->city) === $city)>{{ $city }}</option>@endforeach
                </select>
            </div>
            <div class="relative">
                <x-icon name="building" class="pointer-events-none absolute top-1/2 left-3 z-10 size-5 -translate-y-1/2 text-brand-600" />
                <select name="area" class="input h-12 pl-10" aria-label="Area or society">
                    <option value="">Area / Society</option>
                    @foreach (Lookup::areas($search->get('city'))->concat($search->areas())->unique('id') as $area)<option value="{{ $area->slug }}" @selected($search->area()?->id === $area->id)>{{ $area->name }}</option>@endforeach
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
            <div class="card order-2 p-3.5 lg:order-1">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-serif text-xl font-bold">{{ number_format($properties->total()) }} {{ \Illuminate\Support\Str::plural('Property', $properties->total()) }} Found</h2>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <a href="{{ route('properties.index', request()->except(['page', 'selected', 'north', 'south', 'east', 'west', 'follow'])) }}" class="btn btn-secondary btn-sm"><x-icon name="list" class="size-4" /> List view</a>
                    @include('properties.partials.save-search')
                    <form method="GET">
                        @foreach (request()->except(['sort', 'page', 'selected']) as $key => $value)
                            @foreach ((array) $value as $v)@if (is_scalar($v))<input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">@endif @endforeach
                        @endforeach
                        <label class="sr-only" for="map-sort">Sort by</label>
                        <select id="map-sort" name="sort" class="border-0 bg-transparent py-1 pr-7 text-[13px] font-semibold focus:ring-0" data-autosubmit>
                            @foreach (PropertySearch::SORTS as $value => $label)<option value="{{ $value }}" @selected($search->get('sort', 'newest') === $value)>Sort: {{ $label }}</option>@endforeach
                        </select>
                    </form>
                </div>
                <div class="mt-3 grid grid-cols-5 gap-1.5">
                    @foreach ($categories as [$label, $value, $count])
                        @php
                            $params = $value === null ? array_diff_key($keep, ['type' => 1, 'group' => 1]) : array_merge(array_diff_key($keep, ['type' => 1, 'group' => 1]), in_array($value, ['plots', 'commercial'], true) ? ['group' => $value] : ['type' => $value]);
                            $active = $value === null ? (! $search->types() && ! in_array($group, ['plots', 'commercial'], true)) : ($search->get('type') === $value || (! $search->types() && $group === $value));
                        @endphp
                        <a href="{{ route('properties.map', $params) }}" @class(['rounded-md px-1 py-1.5 text-center text-xs leading-tight', 'bg-brand-600 text-white' => $active, 'bg-page text-ink hover:bg-brand-50' => ! $active])>{{ $label }}<span class="block font-semibold">{{ number_format($count) }}</span></a>
                    @endforeach
                </div>

                @if ($properties->isEmpty())
                    <x-empty-state compact class="mt-4" icon="map" title="No listings in this search">Try another area or remove a filter.</x-empty-state>
                @else
                    <div class="mt-3 divide-y divide-line">
                        @foreach ($properties as $property)
                            <div class="py-2.5">
                                <x-property-card :property="$property" layout="list" class="border-0 shadow-none hover:shadow-none" />
                                <a href="{{ route('properties.map', array_merge($keep, ['selected' => $property->slug, 'page' => $properties->currentPage()])) }}" class="mt-1.5 ml-44 hidden text-xs font-semibold text-brand-600 2xl:inline-block">Show in panel →</a>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">{{ $properties->links() }}</div>
                @endif
            </div>

            {{-- Map --}}
            <div class="relative order-1 h-[50vh] overflow-hidden rounded-[var(--radius-card)] border border-line lg:order-2 lg:sticky lg:top-[104px] lg:h-[calc(100vh-120px)]">
                @include('properties.partials.map-panel', ['markers' => $markers, 'hideApproximateNote' => $search->hasBounds() || $search->polygon()])
                <div class="absolute top-3 left-14 z-[500] flex flex-wrap gap-2">
                    <label class="flex cursor-pointer items-center gap-2 rounded-md bg-white px-3 py-2 text-[13px] font-medium shadow">
                        <input type="checkbox" class="checkbox" data-map-follow @checked($search->hasBounds())> Search as I move the map
                    </label>
                    @php $exactPins = collect($markers)->where('kind', 'pin')->count(); $byArea = collect($markers)->where('kind', 'area')->sum('count'); @endphp
                    <span class="pointer-events-none hidden rounded-md bg-white px-3 py-2 text-[13px] text-muted shadow 2xl:inline">{{ $exactPins }} exact {{ \Illuminate\Support\Str::plural('pin', $exactPins) }} · {{ $byArea }} by area</span>
                </div>
                {{-- Draw-area search (reference: "Draw Area" control on the Map Search Dashboard) --}}
                <div class="absolute top-14 right-3 z-[500] flex gap-2 sm:top-3">
                    @if ($search->polygon())
                        <button type="button" class="btn btn-secondary btn-sm bg-white shadow" data-draw-clear><x-icon name="x" class="size-4" /> Clear Area</button>
                    @endif
                    <button type="button" class="btn btn-outline btn-sm bg-white shadow" data-draw-start><x-icon name="pencil" class="size-4" /> {{ $search->polygon() ? 'Redraw' : 'Draw Area' }}</button>
                </div>
                <div class="absolute inset-x-3 top-24 z-[600] sm:top-16 flex items-center justify-between gap-3 rounded-md bg-brand-600 px-4 py-2.5 text-sm text-white shadow-lift" data-draw-hint hidden role="status">
                    <span>Press and draw around the area you want, then release. Press Esc to cancel.</span>
                    <button type="button" class="btn btn-on-dark btn-sm" data-draw-cancel>Cancel</button>
                </div>

                @if ($search->hasBounds() || $search->polygon())
                    <p class="absolute inset-x-3 bottom-3 z-[500] rounded-md bg-white/95 px-3 py-2 text-center text-xs text-muted shadow">
                        Showing only listings with a confirmed location inside this {{ $search->polygon() ? 'drawn area' : 'map area' }}.
                        {{ $search->polygon() ? 'Clear the area' : 'Untick the box' }} to include listings without a map location.
                    </p>
                @endif
            </div>

            {{-- Detail panel --}}
            @if ($p)
                <aside class="card order-3 hidden self-start overflow-hidden 2xl:sticky 2xl:top-[104px] 2xl:block" aria-label="Selected property">
                    <div class="relative" data-gallery>
                        <img src="{{ $p->coverUrl() ?? $p->placeholderUrl() }}" alt="{{ $p->title }}" class="aspect-[2/1] w-full object-cover" data-gallery-main>
                        <span class="tag absolute top-3 left-3 {{ $p->is_featured ? 'bg-brand-600' : 'bg-brand-500' }}">{{ $p->is_featured ? 'Featured' : $p->purpose->label() }}</span>
                        <div class="absolute top-3 right-3 flex gap-1.5">
                            <x-save-button :property="$p" :saved="\App\Support\Lookup::isSaved($p->id)" class="rounded-md bg-white/90 [&_button]:text-red-500" style="plain" />
                            <button type="button" class="grid size-8 place-items-center rounded-md bg-white/90 text-brand-600" data-share="{{ route('properties.show', $p) }}" aria-label="Share"><x-icon name="share" class="size-4" /><span data-share-label class="sr-only">Share</span></button>
                        </div>
                        @if ($p->images->count() > 1)
                            <button type="button" class="absolute top-1/2 left-3 grid size-8 -translate-y-1/2 place-items-center rounded-full bg-white shadow" data-gallery-prev aria-label="Previous photo"><x-icon name="chevron-left" class="size-4" /></button>
                            <button type="button" class="absolute top-1/2 right-3 grid size-8 -translate-y-1/2 place-items-center rounded-full bg-white shadow" data-gallery-next aria-label="Next photo"><x-icon name="chevron-right" class="size-4" /></button>
                            <span class="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-ink/70 px-2.5 py-0.5 text-xs text-white" data-gallery-counter>1 / {{ $p->images->count() }}</span>
                            <div class="hidden">@foreach ($p->images as $image)<button type="button" data-gallery-thumb="{{ $image->url() }}"></button>@endforeach</div>
                        @endif
                    </div>
                    <div class="p-4">
                        <h2 class="font-serif text-xl leading-snug font-bold">{{ $p->title }}</h2>
                        <p class="mt-1 font-serif text-2xl font-bold text-brand-600">{{ $p->priceLabel() }}</p>
                        <p class="mt-2 flex items-center justify-between gap-2 text-sm text-ink/80"><span class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-4 text-brand-600" /> {{ $p->locationLabel() }}</span></p>
                        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-muted">
                            @if ($p->type->hasRooms() && $p->bedrooms)<li class="flex items-center gap-1"><x-icon name="bed" class="size-4" /> {{ $p->bedrooms }} Beds</li>@endif
                            @if ($p->type->hasRooms() && $p->bathrooms)<li class="flex items-center gap-1"><x-icon name="bath" class="size-4" /> {{ $p->bathrooms }} Baths</li>@endif
                            <li class="flex items-center gap-1"><x-icon name="area" class="size-4" /> {{ $p->areaLabel() }}</li>
                            <li class="flex items-center gap-1"><x-icon name="home" class="size-4" /> {{ $p->type->label() }}</li>
                        </ul>
                        <h3 class="mt-4 font-semibold">Overview</h3>
                        <p class="mt-1 line-clamp-3 text-[13px] leading-5 text-muted">{{ $p->description }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <a href="{{ route('properties.show', $p) }}" class="btn btn-primary btn-sm"><x-icon name="eye" class="size-4" /> View Details</a>
                            <a href="{{ route('properties.show', $p) }}#inquiry" class="btn btn-outline btn-sm"><x-icon name="message" class="size-4" /> Send Inquiry</a>
                        </div>
                        @if ($profile)
                            <div class="mt-4 border-t border-line pt-4">
                                <p class="text-sm font-semibold">Listed By</p>
                                <div class="mt-2 flex items-center gap-3">
                                    <span class="grid size-11 place-items-center rounded-full bg-ink text-sm font-bold text-white">{{ collect(explode(' ', $profile->agency_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold">{{ $profile->agency_name }}</p>
                                        <p class="text-xs {{ $verified ? 'text-emerald-700' : 'text-muted' }}">{{ $verified ? 'Verified Dealer' : 'Verification pending' }}</p>
                                    </div>
                                    <div class="text-center text-xs text-muted"><span class="block text-base font-bold text-ink">{{ $p->dealer->properties()->published()->count() }}</span>Listings</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </aside>
            @endif
        </div>
    </div>

    <x-slot:head>
        <style>body > footer { margin-top: 3rem }</style>
    </x-slot:head>
</x-layouts.app>
