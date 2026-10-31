@php
    use App\Enums\PropertyType;
    use App\Services\PropertySearch;
    use App\Support\Lookup;

    /** @var PropertySearch $search */
    $p = $prefix;
    $facets = $facets ?? $search->facets();
    $group = $search->get('group');
    $showRooms = ! $group || $group === 'homes';
    $purpose = $search->get('purpose');

    // Types with listings first, then the rest of the category; selected ones always shown.
    $typeList = collect($group ? PropertyType::inGroup($group) : PropertyType::cases())
        ->sortByDesc(fn ($t) => [($facets['types'][$t->value] ?? 0) > 0 || $search->hasType($t->value), $facets['types'][$t->value] ?? 0])
        ->values();
    $areaList = Lookup::areas($search->get('city'))
        ->concat($search->areas())->unique('id')
        ->sortByDesc(fn ($a) => [$search->hasArea($a), $facets['areas'][$a->id] ?? 0, -$a->sort_order])
        ->values();

    $section = 'group border-b border-line py-4 last:border-0';
    $summary = 'flex cursor-pointer list-none items-center justify-between text-[15px] font-semibold text-ink';
@endphp
<form method="GET" action="{{ $action ?? route('properties.index') }}" id="{{ $p }}-form" @if ($p === 'side') data-autosubmit-form @endif>
    @if ($search->get('sort'))
        <input type="hidden" name="sort" value="{{ $search->get('sort') }}">
    @endif
    @if ($group && ! $search->types())
        <input type="hidden" name="group" value="{{ $group }}">
    @endif

    <details class="{{ $section }} pt-0" open>
        <summary class="{{ $summary }}">Property Type <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
        <div class="mt-3 space-y-2">
            @foreach ($typeList->take(6) as $t)
                @include('properties.partials.filter-check', ['name' => 'type[]', 'value' => $t->value, 'label' => $t->label(), 'count' => $facets['types'][$t->value] ?? 0, 'checked' => $search->hasType($t->value)])
            @endforeach
            @if ($typeList->count() > 6)
                <details class="group/more">
                    <summary class="cursor-pointer list-none text-[13px] font-semibold text-brand-600">Show more <x-icon name="chevron-down" class="inline size-3.5" /></summary>
                    <div class="mt-2 space-y-2">
                        @foreach ($typeList->slice(6) as $t)
                            @include('properties.partials.filter-check', ['name' => 'type[]', 'value' => $t->value, 'label' => $t->label(), 'count' => $facets['types'][$t->value] ?? 0, 'checked' => $search->hasType($t->value)])
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </details>

    <details class="{{ $section }}" open>
        <summary class="{{ $summary }}">Price Range (PKR) <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <input type="number" name="min_price" min="0" step="100000" value="{{ $search->get('min_price') }}" placeholder="Min" class="input py-2" aria-label="Minimum price in PKR">
            <input type="number" name="max_price" min="0" step="100000" value="{{ $search->get('max_price') }}" placeholder="Max" class="input py-2" aria-label="Maximum price in PKR">
        </div>
        <p class="mt-1.5 text-xs text-muted">Full amount, e.g. 25000000 for 2.5 Crore</p>
    </details>

    <details class="{{ $section }}" open>
        <summary class="{{ $summary }}">Area (Marla / Kanal) <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
        <div class="mt-3 grid grid-cols-3 gap-1.5">
            @foreach (['' => ['label' => 'Any']] + PropertySearch::SIZES as $value => $s)
                <label class="cursor-pointer">
                    <input type="radio" name="size" value="{{ $value }}" class="peer sr-only" @checked((string) $search->get('size') === (string) $value)>
                    <span class="pill w-full px-1 text-xs whitespace-nowrap peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300">{{ $s['label'] }}</span>
                </label>
            @endforeach
        </div>
    </details>

    @if ($showRooms)
        @foreach (['beds' => 'Bedrooms', 'baths' => 'Bathrooms'] as $key => $legend)
            <details class="{{ $section }}" open>
                <summary class="{{ $summary }}">{{ $legend }} <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
                <div class="mt-3 grid grid-cols-6 gap-1">
                    @foreach (['' => 'Any', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5+'] as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="{{ $key }}" value="{{ $value }}" class="peer sr-only" @checked((string) $search->get($key) === (string) $value)>
                            <span class="pill w-full min-w-0 px-0 text-xs peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </details>
        @endforeach
    @endif

    <details class="{{ $section }}" open>
        <summary class="{{ $summary }}">Location <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
        <div class="mt-3" data-filter-list>
            <div class="relative mb-2.5">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                <input type="search" placeholder="Search areas…" class="input py-2 pl-9" aria-label="Filter areas" data-filter-input>
            </div>
            <div class="space-y-2">
                @foreach ($areaList as $i => $area)
                    <div @if ($i >= 6 && ! $search->hasArea($area)) data-extra hidden @endif data-filter-item="{{ mb_strtolower($area->name) }}">
                        @include('properties.partials.filter-check', ['name' => 'area[]', 'value' => $area->slug, 'label' => $area->name, 'count' => $facets['areas'][$area->id] ?? 0, 'checked' => $search->hasArea($area)])
                    </div>
                @endforeach
            </div>
            <button type="button" class="mt-2.5 inline-flex items-center gap-1 text-[13px] font-semibold text-brand-600" data-show-more>Show More <x-icon name="chevron-down" class="size-3.5" /></button>
        </div>
    </details>

    <details class="{{ $section }}" open>
        <summary class="{{ $summary }}">Purpose <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
        <div class="mt-3 flex gap-5">
            @foreach (['sale' => 'For Sale', 'rent' => 'For Rent'] as $value => $label)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="purpose[]" value="{{ $value }}" class="checkbox" @checked($purpose === $value)> {{ $label }}</label>
            @endforeach
        </div>
    </details>

    @if ($showRooms)
        <details class="{{ $section }}" open>
            <summary class="{{ $summary }}">Furnishing <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach (['' => 'Any'] + PropertySearch::FURNISHING as $value => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="furnishing" value="{{ $value }}" class="peer sr-only" @checked((string) $search->get('furnishing') === (string) $value)>
                        <span class="pill text-xs peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </details>
    @endif

    @if ($showRooms)
        <details class="{{ $section }}">
            <summary class="{{ $summary }}">Covered Area (sq ft) <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <input type="number" name="min_covered" min="0" step="50" value="{{ $search->get('min_covered') }}" placeholder="Min" class="input py-2" aria-label="Minimum covered area in square feet">
                <input type="number" name="max_covered" min="0" step="50" value="{{ $search->get('max_covered') }}" placeholder="Max" class="input py-2" aria-label="Maximum covered area in square feet">
            </div>
            <p class="mt-1.5 text-xs text-muted">Built-up area. Plot size is set above.</p>
        </details>
    @endif

    <details class="{{ $section }}" @if ($search->get('completion')) open @endif>
        <summary class="{{ $summary }}">Status <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
        <div class="mt-3 grid grid-cols-2 gap-1.5">
            @foreach (['' => 'Any'] + \App\Models\Property::COMPLETION as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="completion" value="{{ $value }}" class="peer sr-only" @checked((string) $search->get('completion') === (string) $value)>
                    <span class="pill w-full px-1 text-xs peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </details>

    <details class="{{ $section }}" @if ($search->get('features')) open @endif>
        <summary class="{{ $summary }}">Features &amp; Amenities <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
        <div class="mt-3 grid grid-cols-1 gap-2">
            @foreach (config('meezan.amenities') as $key => $label)
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="features[]" value="{{ $key }}" class="checkbox" @checked($search->hasFeature($key))> {{ $label }}</label>
            @endforeach
        </div>
    </details>

    <details class="{{ $section }}" open>
        <summary class="{{ $summary }}">Listing <x-icon name="chevron-down" class="size-4 rotate-180 transition group-open:rotate-0" /></summary>
        <div class="mt-3 space-y-2">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="verified" value="1" class="checkbox" @checked($search->get('verified'))> Verified dealers only</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="photos" value="1" class="checkbox" @checked($search->get('photos'))> With photos</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reduced" value="1" class="checkbox" @checked($search->get('reduced'))> Price reduced <span class="text-xs text-muted">(last {{ \App\Models\Property::REDUCED_DAYS }} days)</span></label>
            <div class="pt-1">
                <label for="filter-listed" class="mb-1 block text-xs font-semibold text-muted">Listed within</label>
                <select id="filter-listed" name="listed" class="input py-2 text-sm">
                    <option value="">Any time</option>
                    @foreach (\App\Services\PropertySearch::LISTED as $days => $label)
                        <option value="{{ $days }}" @selected((int) $search->get('listed') === $days)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </details>

    <button type="submit" class="btn btn-primary mt-4 w-full">Apply Filters</button>
</form>
