@php
    /** @var \App\Services\PropertySearch $search */
    $headline = $search->headline();
    $total = $properties->total();
    $facets = $search->facets();
    $area = $search->area();
@endphp
{{-- Layout: public/Refrence/Meezan Property Search Results.png --}}
<x-layouts.app :title="$headline" :description="'Browse '.number_format($total).' '.strtolower($headline).'. Reviewed listings with prices, sizes and dealer details on Meezan Property.'"
               :canonical="route('properties.index', array_filter(['area' => $search->get('area'), 'page' => $properties->currentPage() > 1 ? $properties->currentPage() : null]))"
               :noindex="$search->activeFilterCount() > 2">
    <section class="bg-gradient-to-r from-brand-700 via-brand-600 to-brand-700 py-3">
        <div class="mx-auto max-w-[1240px] px-4 sm:px-6">
            <x-search-bar variant="bar" :purpose="$search->get('purpose')" :area="$search->get('area')" :type="$search->get('type')" :max-price="$search->get('max_price')" :beds="$search->get('beds')" class="p-2.5" />
        </div>
    </section>

    <div class="mx-auto max-w-[1536px] px-4 sm:px-6">
        <x-breadcrumbs class="py-3" :items="array_values(array_filter([
            [$search->get('purpose') === 'rent' ? 'Rent' : 'Buy', route($search->get('purpose') === 'rent' ? 'properties.rent' : 'properties.buy')],
            count($search->types()) === 1 ? [\App\Enums\PropertyType::from($search->types()[0])->label().'s', null] : null,
            $search->get('city') && $area ? [$search->get('city'), route('cities.show', \App\Support\Cities::slug($search->get('city')))] : null,
            [$area?->name ?? $search->get('city') ?? 'Pakistan', null],
        ]))" />

        <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)] xl:grid-cols-[260px_minmax(0,1fr)_330px]">
            {{-- Filters --}}
            <aside class="hidden lg:block" aria-label="Filters" id="filters">
                <div class="card sticky top-[104px] max-h-[calc(100vh-120px)] overflow-y-auto px-4 py-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-bold">Filters</h2>
                        <a href="{{ route('properties.index', array_filter(['purpose' => $search->get('purpose')])) }}" class="text-[13px] font-semibold text-brand-600">Clear All</a>
                    </div>
                    @include('properties.partials.filters', ['prefix' => 'side', 'facets' => $facets])
                </div>
            </aside>

            {{-- Results --}}
            <div class="min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-[28px]">{{ number_format($total) }} {{ $search->headline($total) }}</h1>
                        <p class="mt-1 text-[15px] text-muted">
                            Reviewed listings in {{ $area ? $area->name.', '.$area->city : ($search->get('city') ?? 'Lahore, Karachi, Islamabad and more') }}
                            @if ($area)
                                · <a href="{{ route('areas.show', $area) }}" class="link">{{ $area->name }} area guide</a>
                            @endif
                        </p>
                    </div>
                    @include('properties.partials.save-search')
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn btn-secondary lg:hidden" data-dialog-open="filters-dialog">
                            <x-icon name="sliders" class="size-4" /> Filters
                            @if ($search->activeFilterCount())
                                <span class="grid size-5 place-items-center rounded-full bg-brand-600 text-[11px] text-white">{{ $search->activeFilterCount() }}</span>
                            @endif
                        </button>
                        <span class="btn btn-primary btn-sm" aria-current="true"><x-icon name="list" class="size-4" /> List View</span>
                        <a href="{{ route('properties.map', request()->except('page')) }}" class="btn btn-secondary btn-sm"><x-icon name="map-pin" class="size-4" /> Map View</a>
                        <form method="GET">
                            @foreach (request()->except(['sort', 'page']) as $key => $value)
                                @foreach ((array) $value as $v)
                                    @if (is_scalar($v))<input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">@endif
                                @endforeach
                            @endforeach
                            <label class="flex items-center gap-1 rounded-[var(--radius-control)] border border-line bg-white pl-3 text-[13px] text-muted">
                                Sort by:
                                <select name="sort" class="border-0 bg-transparent py-2 pr-8 pl-1 text-[13px] font-semibold text-ink focus:ring-0" data-autosubmit>
                                    @foreach (\App\Services\PropertySearch::SORTS as $value => $label)
                                        <option value="{{ $value }}" @selected($search->get('sort', 'newest') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <noscript><button class="btn btn-secondary btn-sm">Apply</button></noscript>
                        </form>
                    </div>
                </div>

                @if ($search->suggestions()->isNotEmpty())
                    <div class="mt-4 rounded-lg border border-gold-200 bg-gold-50 px-4 py-3 text-sm" role="status">
                        <span class="font-semibold">“{{ $search->get('q') }}” matches more than one place.</span> Did you mean:
                        @foreach ($search->suggestions() as $s)
                            <a href="{{ route('properties.index', array_filter(['purpose' => $search->get('purpose'), 'area' => $s->slug])) }}" class="link font-medium">{{ $s->name }}, {{ $s->city }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </div>
                @endif

                @if ($chips = $search->chips())
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @foreach ($chips as $chip)
                            <a href="{{ $search->urlWithout($chip) }}" class="chip border-brand-200 bg-brand-50 text-brand-700">{{ $chip['label'] }} <x-icon name="x" class="size-3.5" /><span class="sr-only">Remove filter</span></a>
                        @endforeach
                    </div>
                @endif

                @if ($properties->isEmpty())
                    <x-empty-state class="mt-6" icon="search" title="No listings match these filters">
                        Try widening your budget, choosing a nearby area, or removing a filter. You can also tell our AI finder what you need and save it.
                        <x-slot:actions>
                            <a href="{{ route('properties.index', array_filter(['purpose' => $search->get('purpose')])) }}" class="btn btn-secondary">Clear filters</a>
                            <a href="{{ route('ai-finder') }}" class="btn btn-primary"><x-icon name="sparkles" class="size-4" /> Use AI Property Finder</a>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <div class="mt-5 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($properties as $property)
                            <x-property-card :property="$property" dealer />
                        @endforeach
                    </div>
                    <div class="mt-8">{{ $properties->links() }}</div>
                @endif
            </div>

            {{-- Map --}}
            <aside class="hidden xl:block" aria-label="Map">
                <div class="sticky top-[104px] h-[calc(100vh-120px)] overflow-hidden rounded-[var(--radius-card)] border border-line">
                    @include('properties.partials.map-panel', ['markers' => $markers])
                    <a href="{{ route('properties.map', request()->except('page')) }}" class="btn btn-outline absolute right-4 bottom-4 z-[500] bg-white shadow-lift"><x-icon name="search" class="size-4" /> Open full map</a>
                </div>
            </aside>
        </div>
    </div>

    {{-- Mobile filters --}}
    <dialog id="filters-dialog" class="m-0 mt-auto max-h-[90vh] w-full max-w-none rounded-t-2xl bg-white p-0 backdrop:bg-ink/50 sm:m-auto sm:max-w-lg sm:rounded-2xl" aria-label="Filters">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-line bg-white px-5 py-4">
            <h2 class="text-base font-bold">Filters</h2>
            <button type="button" class="btn btn-ghost px-2" data-dialog-close aria-label="Close filters"><x-icon name="x" /></button>
        </div>
        <div class="px-5 pb-5">
            @include('properties.partials.filters', ['prefix' => 'mobile', 'facets' => $facets])
        </div>
    </dialog>
</x-layouts.app>
