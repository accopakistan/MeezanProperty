@php
    /**
     * Exact-location picker for the listing form. Writes to the existing
     * latitude/longitude inputs; without JavaScript those inputs still work.
     * Area centres only position the map and are never written to the inputs.
     *
     * @var \App\Models\Property $property
     */
    $mapConfig = config('meezan.map');
    $lat = old('latitude', $property->latitude);
    $lng = old('longitude', $property->longitude);
    $hasPin = filled($lat) && filled($lng);
    $pickerConfig = json_encode([
        'tiles' => $mapConfig['tile_url'],
        'attribution' => $mapConfig['attribution'],
        'center' => \App\Models\City::centre(old('city', $property->city)) ?? $mapConfig['center'],
        'cityCentres' => collect(config('meezan.cities'))->mapWithKeys(fn ($c) => [$c => \App\Models\City::centre($c)])->filter(),
        'zoom' => 12,
        'bounds' => $mapConfig['pakistan_bounds'],
        'warnKm' => $mapConfig['area_warning_km'],
        'searchUrl' => route('dealer.location-search'),
    ], JSON_UNESCAPED_SLASHES);
@endphp
<div class="-mx-2 space-y-3 rounded-xl border border-line p-3 sm:mx-0 sm:p-4" data-location-picker="{{ $pickerConfig }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold">Exact map location <span class="font-normal text-muted">(recommended)</span></p>
            <p class="mt-0.5 text-[13px] text-muted">Click or tap the map to drop a pin on the property, then drag it to the exact spot.</p>
        </div>
        <p data-location-status role="status" aria-live="polite" @class([
            'badge shrink-0',
            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' => $hasPin,
            'bg-amber-50 text-amber-800 ring-1 ring-amber-200' => ! $hasPin,
        ])>{{ $hasPin ? 'Exact location set' : 'Exact location not set' }}</p>
    </div>

    @if ($mapConfig['enabled'])
        <div class="flex gap-2">
            <label for="location-query" class="sr-only">Search for a street, block or landmark</label>
            <input id="location-query" type="search" class="input" placeholder="Search a street, block or landmark" autocomplete="off" enterkeyhint="search" maxlength="120" data-location-query>
            <button type="button" class="btn btn-secondary shrink-0" data-location-find><x-icon name="search" class="size-4" /><span class="hidden sm:inline">Find</span></button>
        </div>
        <ul data-location-results hidden class="divide-y divide-line overflow-hidden rounded-lg border border-line bg-white text-sm" aria-label="Search results"></ul>
        <p data-location-message class="text-[13px] text-muted" aria-live="polite"></p>

        <div class="relative isolate overflow-hidden rounded-lg border border-line">
            <div data-location-map class="h-72 w-full bg-brand-50 sm:h-96" role="application" aria-label="Property location map. Click to place the pin; use the latitude and longitude fields below to enter it with the keyboard."></div>
            <div class="absolute top-3 right-3 z-[500] flex flex-col items-end gap-2 sm:flex-row">
                <button type="button" class="btn btn-secondary btn-sm bg-white shadow" data-location-locate><x-icon name="target" class="size-4" /> Use my location</button>
                <button type="button" class="btn btn-secondary btn-sm bg-white text-red-600 shadow" data-location-clear @unless ($hasPin) hidden @endunless><x-icon name="x" class="size-4" /> Clear pin</button>
            </div>
            <p data-location-fallback hidden class="absolute inset-0 z-[600] grid place-items-center bg-page p-6 text-center text-sm text-muted">The map could not be loaded. You can still enter the coordinates below.</p>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="latitude" label="Latitude" :value="$property->latitude" inputmode="decimal" autocomplete="off" placeholder="e.g. 31.4697000" data-location-lat />
        <x-field name="longitude" label="Longitude" :value="$property->longitude" inputmode="decimal" autocomplete="off" placeholder="e.g. 74.4106000" data-location-lng />
    </div>
    <p data-location-warning hidden class="rounded-lg bg-amber-50 px-3 py-2 text-[13px] text-amber-900"></p>
    <p class="text-xs text-muted">The area centre only positions the map and is never saved as your property’s location. Without a pin, the listing still appears in search and on the map grouped by area, marked as approximate.</p>

    <fieldset class="rounded-lg border border-line p-3">
        <legend class="px-1 text-sm font-semibold">What buyers see on the map</legend>
        @php $privacy = old('location_privacy', $property->location_privacy ?? 'exact'); @endphp
        <div class="mt-1 grid gap-2 sm:grid-cols-3">
            @foreach (\App\Models\Property::LOCATION_PRIVACY as $value => $label)
                <label class="flex items-start gap-2 rounded-md border border-line p-2.5 text-[13px] has-checked:border-brand-400 has-checked:bg-brand-50">
                    <input type="radio" name="location_privacy" value="{{ $value }}" class="mt-0.5" @checked($privacy === $value)>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-muted">Your exact pin is always kept private to you and Meezan Property reviewers when you choose “approximate” or “area only”. It is never shown, used for map search or for nearby places.</p>
        @error('location_privacy')<p class="field-error">{{ $message }}</p>@enderror
    </fieldset>

    @if ($property->exists && $property->location_status === 'flagged')
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-[13px] text-amber-900"><strong>Please check the pin:</strong> {{ $property->location_flag_reason }} Reviewers will confirm it before publishing.</p>
    @endif
</div>
