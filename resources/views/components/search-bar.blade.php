@props(['variant' => 'tabs', 'tab' => 'sale', 'group' => null, 'type' => null, 'area' => null, 'maxPrice' => null, 'beds' => null, 'purpose' => null, 'sizeLabel' => null])
{{--
    tabs: hero search from the Homepage reference — Buy / Rent / Plots / Commercial tabs
          over a white panel (location, type, price, beds, Search).
    bar:  single-row search from the category and search-results references
          (Lahore | type | price | beds | More filters | Search).
--}}
@php
    use App\Enums\PropertyType;
    use App\Support\Lookup;

    $areaModel = $area ? \App\Models\Area::where('slug', $area)->first(['id', 'name', 'slug', 'city']) : null;
    $cityValue = request('city');
    $types = $group ? [PropertyType::groupLabel($group) => PropertyType::inGroup($group)] : Lookup::typesByGroup();
    $pricePurpose = ($purpose ?? $tab) === 'rent' ? 'rent' : 'sale';
    $tabs = ['sale' => 'Buy', 'rent' => 'Rent', 'plots' => 'Plots', 'commercial' => 'Commercial'];
    $field = 'h-12 w-full rounded-[var(--radius-control)] border border-line bg-white px-3.5 text-sm text-ink focus:border-brand-400 focus:ring-3 focus:ring-brand-100 focus:outline-none';
@endphp

@if ($variant === 'tabs')
    <form method="GET" action="{{ route('properties.index') }}" {{ $attributes }} role="search">
        <div class="flex w-max max-w-full overflow-x-auto rounded-t-lg bg-white scrollbar-none" role="radiogroup" aria-label="Search for">
            @foreach ($tabs as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="tab" value="{{ $value }}" class="peer sr-only" @checked($tab === $value)>
                    <span class="block border-r border-line px-6 py-3 text-[15px] font-medium text-ink transition last:border-0 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 sm:px-10">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <div class="grid gap-2.5 rounded-tr-lg rounded-b-lg bg-white p-3 shadow-lift sm:p-3.5 md:grid-cols-[1.9fr_1fr_1fr_0.8fr_auto]">
            <div class="relative">
                <label for="hero-location" class="sr-only">Location</label>
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-5 -translate-y-1/2 text-ink/70" />
                <x-area-combobox id="hero-location" :value="$areaModel?->name" :slug="$areaModel?->slug" :input-class="$field.' pl-11'" placeholder="Search location, society or property…" />
            </div>
            <select name="type" class="input h-12" aria-label="Property type">
                <option value="">Property Type</option>
                @foreach ($types as $label => $list)
                    <optgroup label="{{ $label }}">
                        @foreach ($list as $t)
                            <option value="{{ $t->value }}" @selected($type === $t->value)>{{ $t->label() }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <select name="max_price" class="input h-12" aria-label="Price range">
                <option value="">Price Range</option>
                @foreach (Lookup::priceOptions($pricePurpose) as $value => $label)
                    <option value="{{ $value }}" @selected((int) $maxPrice === $value)>Up to {{ $label }}</option>
                @endforeach
            </select>
            <select name="beds" class="input h-12" aria-label="Bedrooms">
                <option value="">Beds</option>
                @foreach ([1, 2, 3, 4, 5] as $n)
                    <option value="{{ $n }}" @selected((int) $beds === $n)>{{ $n }}+ Beds</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary h-12 px-10 text-[15px]">Search</button>
        </div>
    </form>
@else
    <form method="GET" action="{{ route('properties.index') }}" {{ $attributes->merge(['class' => 'rounded-lg bg-white p-3 shadow-lift']) }} role="search">
        @if ($purpose)<input type="hidden" name="purpose" value="{{ $purpose }}">@endif
        @if ($group)<input type="hidden" name="group" value="{{ $group }}">@endif
        <div class="grid grid-cols-[1fr_auto] gap-2.5 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_auto]">
            <div class="relative">
                <label for="bar-location" class="sr-only">Location</label>
                <x-icon name="map-pin" class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-5 -translate-y-1/2 text-brand-600" />
                <x-area-combobox id="bar-location" :value="$areaModel?->name ?? $cityValue" :slug="$areaModel?->slug" :input-class="$field.' pl-11 font-medium'" placeholder="City or area" />
            </div>
            <select name="type" class="input h-12 max-sm:hidden" aria-label="Property type">
                <option value="">{{ $group ? PropertyType::groupLabel($group) : 'Property Type' }}</option>
                @foreach ($types as $label => $list)
                    <optgroup label="{{ $label }}">
                        @foreach ($list as $t)
                            <option value="{{ $t->value }}" @selected($type === $t->value)>{{ $t->label() }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <select name="max_price" class="input h-12 max-sm:hidden" aria-label="Price range">
                <option value="">Price Range</option>
                @foreach (Lookup::priceOptions($pricePurpose) as $value => $label)
                    <option value="{{ $value }}" @selected((int) $maxPrice === $value)>Up to {{ $label }}</option>
                @endforeach
            </select>
            @if ($group === 'plots' || $group === 'commercial')
                <select name="size" class="input h-12 max-sm:hidden" aria-label="{{ $sizeLabel ?? 'Size' }}">
                    <option value="">{{ $sizeLabel ?? 'Size' }}</option>
                    @foreach (\App\Services\PropertySearch::SIZES as $value => $s)
                        <option value="{{ $value }}">{{ $s['label'] }}</option>
                    @endforeach
                </select>
            @else
                <select name="beds" class="input h-12 max-sm:hidden" aria-label="Bedrooms">
                    <option value="">Beds</option>
                    @foreach ([1, 2, 3, 4, 5] as $n)
                        <option value="{{ $n }}" @selected((int) $beds === $n)>{{ $n }}+ Beds</option>
                    @endforeach
                </select>
            @endif
            <a href="{{ route('properties.index', array_filter(['purpose' => $purpose, 'group' => $group])) }}#filters" class="input flex h-12 items-center justify-between text-ink max-sm:hidden">More Filters <x-icon name="sliders" class="size-4 text-muted" /></a>
            <button type="submit" class="btn btn-primary h-12 px-5 text-[15px] sm:px-9">Search</button>
        </div>
    </form>
@endif
