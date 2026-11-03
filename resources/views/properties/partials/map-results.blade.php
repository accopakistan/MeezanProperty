@php
    use App\Services\PropertySearch;

    /** @var PropertySearch $search */
    $group = $search->get('group');
    $keep = request()->except(['page', 'selected']);
@endphp
{{-- Result list beside the map. Rendered by the page and by /map/data, so both stay identical. --}}
<div class="flex items-center justify-between gap-3">
    <h2 class="font-serif text-xl font-bold" data-map-count aria-live="polite">{{ number_format($properties->total()) }} {{ \Illuminate\Support\Str::plural('Property', $properties->total()) }} Found</h2>
</div>
<div class="mt-2 flex flex-wrap items-center gap-2">
    <a href="{{ route('properties.index', request()->except(['page', 'selected', 'north', 'south', 'east', 'west', 'follow', 'centres'])) }}" class="btn btn-secondary btn-sm" data-map-external><x-icon name="list" class="size-4" /> List view</a>
    @include('properties.partials.save-search')
    <form method="GET" action="{{ route('properties.map') }}" data-map-form>
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
        <a href="{{ route('properties.map', $params) }}" @class(['rounded-md px-1 py-1.5 text-center text-xs leading-tight', 'bg-brand-600 text-white' => $active, 'bg-page text-ink hover:bg-brand-50' => ! $active]) @if ($active) aria-current="true" @endif>{{ $label }}<span class="block font-semibold">{{ number_format($count) }}</span></a>
    @endforeach
</div>

@if ($search->hasBounds() || $search->polygon())
    <p class="mt-3 rounded-md bg-brand-50 px-3 py-2 text-xs text-ink/80">
        @if ($search->polygon())
            Showing listings with a confirmed location inside this drawn area.
        @elseif ($search->get('centres'))
            Showing listings in this map area, including those shown at their area's centre (approximate).
        @else
            Showing only listings with a confirmed location inside this map area.
        @endif
    </p>
@endif

@if ($properties->isEmpty())
    <x-empty-state compact class="mt-4" icon="map" title="No listings in this search">Try another area, zoom out or remove a filter.</x-empty-state>
@else
    <ul class="mt-3 divide-y divide-line" aria-label="Search results">
        @foreach ($properties as $property)
            <li class="py-2.5" data-map-card="{{ $property->id }}">
                <x-property-card :property="$property" layout="list" class="border-0 shadow-none hover:shadow-none" />
                <a href="{{ route('properties.map', array_merge($keep, ['selected' => $property->slug, 'page' => $properties->currentPage()])) }}"
                   class="mt-1.5 ml-[9.5rem] inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:underline sm:ml-[10.5rem]"
                   data-show-on-map="{{ $property->id }}" data-slug="{{ $property->slug }}"><x-icon name="map-pin" class="size-3.5" /> Show on map</a>
            </li>
        @endforeach
    </ul>
    <div class="mt-4" data-map-pagination>{{ $properties->onEachSide(1)->links() }}</div>
@endif
