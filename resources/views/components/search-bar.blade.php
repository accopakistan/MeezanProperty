@props([
    'variant' => 'tabs', 'tab' => null, 'group' => null, 'type' => null, 'area' => null, 'maxPrice' => null, 'beds' => null, 'purpose' => null,
    'sizeLabel' => null, 'size' => null, 'city' => null, 'mode' => 'property', 'segment' => null, 'stage' => null, 'minYield' => null, 'q' => null,
    'layout' => 'row',
])
{{--
    The one property search used across the site, always with the same four
    tabs: Buy | Rent | Plots | Commercial (component: search-tabs).

    variant  tabs: hero panel (tabs over a white panel); bar: one-row bar under the tabs
    layout   row (default): fields in one row from tablet width; compact: two or three columns (narrow hero columns)
    mode     property (default): listings; the Commercial tab also offers commercial
                       investments (malls, plazas, towers …) from Investment Rankings
             investment: Investment Rankings pages: Buy = residential investments,
                       Rent = rental income (ranked by yield), Plots = plot rankings,
                       Commercial = commercial investments
    Each field is marked with the tabs it belongs to (data-search-for). Fields of the
    other tabs are hidden and disabled, so they are not sent; the page script swaps
    them when the tab changes (without JavaScript the starting tab's fields are used).
    Everything submits to /search, which sends each tab to its results page.
--}}
@php
    use App\Enums\PropertyType;
    use App\Services\Investment\RankingBrowser;
    use App\Support\Lookup;

    $tab ??= match (true) {
        in_array(request('tab'), ['sale', 'rent', 'plots', 'commercial'], true) && $mode === 'property' => request('tab'),
        $group === 'plots' => 'plots',
        $group === 'commercial' => 'commercial',
        ($purpose ?? null) === 'rent' => 'rent',
        default => 'sale',
    };
    $areaModel = $area ? \App\Models\Area::where('slug', $area)->first(['id', 'name', 'slug', 'city']) : null;
    $cityValue = $city ?? request('city');
    $field = 'h-12 w-full rounded-[var(--radius-control)] border border-line bg-white px-3.5 text-sm text-ink focus:border-brand-400 focus:ring-3 focus:ring-brand-100 focus:outline-none';
    $select = 'input h-12';
    $id = 'search-'.substr(md5($variant.$mode.$tab.($cityValue ?? '')), 0, 6);

    // A field for some tabs only: hidden and disabled when another tab is selected.
    $for = function (array $tabs) use ($tab) {
        $on = in_array($tab, $tabs, true);

        return new \Illuminate\View\ComponentAttributeBag(array_filter(['data-search-for' => implode(' ', $tabs), 'hidden' => ! $on, 'disabled' => ! $on]));
    };

    $homeTypes = ['Homes' => PropertyType::inGroup('homes')];
    $typeSets = [
        'sale rent' => $homeTypes + ['Commercial' => PropertyType::inGroup('commercial')] + ['Plots' => PropertyType::inGroup('plots')],
        'plots' => ['Plots' => PropertyType::inGroup('plots')],
        'commercial' => ['Commercial' => array_merge(PropertyType::inGroup('commercial'), [PropertyType::CommercialPlot])],
    ];
    $investmentSets = [
        'sale' => RankingBrowser::segmentsIn('residential'),
        'rent' => array_intersect_key(RankingBrowser::segments(), array_flip(['apartments', 'houses', 'shopping-malls', 'commercial-plazas', 'office-spaces', 'commercial-shops'])),
        'plots' => array_intersect_key(RankingBrowser::segments(), array_flip(['plots', 'commercial-plots'])),
        'commercial' => RankingBrowser::segmentsIn('commercial') + RankingBrowser::segmentsIn('mixed-use'),
    ];
    $commercialInvestments = $investmentSets['commercial'];
@endphp

<form method="GET" action="{{ route('search') }}" {{ $attributes->merge(['class' => $variant === 'bar' ? '' : '']) }} role="search" data-search-form>
    <input type="hidden" name="mode" value="{{ $mode }}">
    @if ($cityValue)<input type="hidden" name="city" value="{{ $cityValue }}">@endif

    <x-search-tabs :active="$tab" :name="$id" :compact="$variant === 'bar' || $layout === 'compact'" />

    <div @class(['grid gap-2.5 bg-white p-3 shadow-lift sm:p-3.5', 'rounded-tr-lg rounded-b-lg' => $variant !== 'bar', 'rounded-b-lg rounded-tr-lg' => $variant === 'bar',
        'md:grid-flow-col md:auto-cols-[minmax(0,1fr)]' => $layout === 'row', 'grid-cols-2 sm:grid-cols-3 [&>button]:col-span-full sm:[&>button]:col-span-1' => $layout === 'compact'])>
        @if ($mode === 'investment')
            <div @class(['relative', 'md:col-span-2' => $layout === 'row', 'col-span-full' => $layout === 'compact'])>
                <label for="{{ $id }}-q" class="sr-only">Search society, project or area</label>
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-5 -translate-y-1/2 text-ink/70" />
                <input id="{{ $id }}-q" name="q" value="{{ $q }}" maxlength="80" class="{{ $field }} pl-11" placeholder="Search society, project or area…">
            </div>
            <label class="sr-only" for="{{ $id }}-city">City</label>
            <select id="{{ $id }}-city" name="investment_city" class="{{ $select }}">
                <option value="pakistan">All Pakistan</option>
                @foreach (\App\Support\Cities::names() as $c)<option value="{{ \App\Support\Cities::slug($c) }}" @selected($cityValue === $c)>{{ $c }}</option>@endforeach
            </select>
            @foreach ($investmentSets as $t => $set)
                <select name="segment" class="{{ $select }}" aria-label="Investment type" {{ $for([$t]) }}>
                    <option value="">{{ ['sale' => 'All residential', 'rent' => 'All rental investments', 'plots' => 'All plots', 'commercial' => 'All commercial'][$t] }}</option>
                    @foreach ($set as $slug => $s)<option value="{{ $slug }}" @selected($segment === $slug)>{{ $s['label'] }}</option>@endforeach
                </select>
            @endforeach
            <select name="budget" class="{{ $select }}" aria-label="Budget (up to)">
                <option value="">Any budget</option>
                @foreach (Lookup::priceOptions('sale') as $value => $label)<option value="{{ $value }}" @selected((int) $maxPrice === $value)>Up to {{ $label }}</option>@endforeach
            </select>
            <select name="size" class="{{ $select }}" aria-label="Plot size" {{ $for(['plots']) }}>
                <option value="">Any size</option>
                @foreach (['3 Marla', '5 Marla', '7 Marla', '8 Marla', '10 Marla', '1 Kanal', '2 Kanal'] as $s)<option value="{{ \Illuminate\Support\Str::slug($s) }}" @selected($size === \Illuminate\Support\Str::slug($s))>{{ $s }}</option>@endforeach
            </select>
            <select name="min_yield" class="{{ $select }}" aria-label="Expected return (minimum gross yield)" {{ $for(['rent', 'commercial']) }}>
                <option value="">Any return</option>
                @foreach ([3, 4, 5, 6, 8] as $y)<option value="{{ $y }}" @selected((float) $minYield === (float) $y)>{{ $y }}%+ yield</option>@endforeach
            </select>
            <select name="stage" class="{{ $select }}" aria-label="Development status" {{ $for(['sale', 'commercial']) }}>
                <option value="">All status</option>
                @foreach (RankingBrowser::PROJECT_STAGES as $k => $l)<option value="{{ $k }}" @selected($stage === $k)>{{ $l }}</option>@endforeach
            </select>
        @else
            <div class="relative md:col-span-2">
                <label for="{{ $id }}-location" class="sr-only">Location</label>
                <x-icon name="{{ $variant === 'bar' ? 'map-pin' : 'search' }}" class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-5 -translate-y-1/2 {{ $variant === 'bar' ? 'text-brand-600' : 'text-ink/70' }}" />
                <x-area-combobox :id="$id.'-location'" :city="$cityValue" :value="$areaModel?->name ?? ($variant === 'bar' && ! $city ? $cityValue : null)" :slug="$areaModel?->slug" :input-class="$field.' pl-11'"
                                 :placeholder="$cityValue ? 'Area, society or phase in '.$cityValue : 'Search location, society or property…'" />
            </div>
            @foreach ($typeSets as $tabs => $set)
                <select name="type" class="{{ $select }}" aria-label="Property type" {{ $for(explode(' ', $tabs)) }}>
                    <option value="">{{ ['sale rent' => 'Property Type', 'plots' => 'Plot Type', 'commercial' => 'Commercial Type'][$tabs] }}</option>
                    @foreach ($set as $label => $list)
                        <optgroup label="{{ $label }}">
                            @foreach ($list as $t)<option value="{{ $t->value }}" @selected($type === $t->value)>{{ $t->label() }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
            @endforeach
            <select name="investment" class="{{ $select }}" aria-label="Investment category" {{ $for(['commercial']) }}>
                <option value="">All commercial property</option>
                @foreach ($commercialInvestments as $slug => $s)<option value="{{ $slug }}" @selected($segment === $slug)>{{ $s['label'] }} (rankings)</option>@endforeach
            </select>
            @foreach (['sale plots commercial' => 'sale', 'rent' => 'rent'] as $tabs => $pricePurpose)
                <select name="max_price" class="{{ $select }}" aria-label="{{ $pricePurpose === 'rent' ? 'Monthly rent (up to)' : 'Budget (up to)' }}" {{ $for(explode(' ', $tabs)) }}>
                    <option value="">{{ $pricePurpose === 'rent' ? 'Rent Range' : 'Price Range' }}</option>
                    @foreach (Lookup::priceOptions($pricePurpose) as $value => $label)<option value="{{ $value }}" @selected((int) $maxPrice === $value)>Up to {{ $label }}{{ $pricePurpose === 'rent' ? '/month' : '' }}</option>@endforeach
                </select>
            @endforeach
            <select name="beds" class="{{ $select }}" aria-label="Bedrooms" {{ $for(['sale', 'rent']) }}>
                <option value="">Beds</option>
                @foreach ([1, 2, 3, 4, 5] as $n)<option value="{{ $n }}" @selected((int) $beds === $n)>{{ $n }}+ Beds</option>@endforeach
            </select>
            <select name="size" class="{{ $select }}" aria-label="{{ $sizeLabel ?? 'Size' }}" {{ $for(['plots', 'commercial']) }}>
                <option value="">{{ $sizeLabel ?? ($tab === 'plots' ? 'Plot Size' : 'Size') }}</option>
                @foreach (\App\Services\PropertySearch::SIZES as $value => $s)<option value="{{ $value }}" @selected($size === $value)>{{ $s['label'] }}</option>@endforeach
            </select>
            <select name="stage" class="{{ $select }}" aria-label="Development status" {{ $for(['commercial']) }}>
                <option value="">Development Status</option>
                @foreach (RankingBrowser::PROJECT_STAGES as $k => $l)<option value="{{ $k }}" @selected($stage === $k)>{{ $l }}</option>@endforeach
            </select>
        @endif
        <button type="submit" class="btn btn-primary h-12 px-8 text-[15px]">Search</button>
    </div>
</form>
