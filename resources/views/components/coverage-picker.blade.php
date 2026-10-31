@props(['homeCity' => null, 'cities' => [], 'areas' => collect(), 'suggestions' => collect()])
{{--
    Cities and areas a dealer serves. Popular areas of the ticked cities are
    quick picks; any other area is found with the search box (locations.suggest).
    $areas: the currently selected Area models (with name and city).
--}}
@php
    $cities = array_values(array_unique(array_filter(array_merge([$homeCity], $cities))));
    $selectedIds = $areas->pluck('id')->all();
@endphp
<div class="space-y-4" data-coverage-picker data-suggest-url="{{ route('locations.suggest') }}">
    <fieldset>
        <legend class="label">Cities you serve</legend>
        <div class="flex flex-wrap gap-2">
            @foreach (config('meezan.cities') as $city)
                <label class="flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-[13px] has-checked:border-brand-400 has-checked:bg-brand-50">
                    <input type="checkbox" name="cities[]" value="{{ $city }}" class="checkbox" data-coverage-city @checked(in_array($city, $cities, true))> {{ $city }}
                </label>
            @endforeach
        </div>
        <p class="field-hint">Your head-office city is always included. Buyer leads are only sent to you for the cities you tick.</p>
        @error('cities.*')<p class="field-error">{{ $message }}</p>@enderror
    </fieldset>

    <fieldset>
        <legend class="label">Areas you operate in <span class="font-normal text-muted">(up to 10)</span></legend>
        <div class="max-h-56 space-y-3 overflow-y-auto rounded-md border border-line p-2">
            <div class="grid grid-cols-2 gap-1" data-coverage-chosen>
                @foreach ($areas as $area)
                    <label class="flex items-center gap-2 rounded px-1.5 py-1 text-[13px] hover:bg-brand-50">
                        <input type="checkbox" name="areas[]" value="{{ $area->id }}" class="checkbox" checked> {{ $area->name }} <span class="text-muted">· {{ $area->city }}</span>
                    </label>
                @endforeach
            </div>
            @foreach ($suggestions as $city => $list)
                <div data-city-group="{{ $city }}" @if (! in_array($city, $cities, true)) hidden @endif>
                    <p class="px-1.5 text-[11px] font-semibold tracking-wide text-muted uppercase">{{ $city }}</p>
                    <div class="grid grid-cols-2 gap-1">
                        @foreach ($list->reject(fn ($a) => in_array($a->id, $selectedIds, true)) as $area)
                            <label class="flex items-center gap-2 rounded px-1.5 py-1 text-[13px] hover:bg-brand-50">
                                <input type="checkbox" name="areas[]" value="{{ $area->id }}" class="checkbox"> {{ $area->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <div class="relative mt-2">
            <label for="coverage-search" class="sr-only">Find another area</label>
            <input type="search" id="coverage-search" class="input py-2 text-sm" placeholder="Find another area, society or phase…" autocomplete="off" data-coverage-search hidden>
            <ul class="absolute inset-x-0 top-full z-30 mt-1 max-h-60 overflow-auto rounded-xl border border-line bg-white py-1 shadow-lift" role="listbox" data-coverage-results hidden></ul>
        </div>
        @error('areas')<p class="field-error">{{ $message }}</p>@enderror
        @error('areas.*')<p class="field-error">Choose areas in the cities you serve.</p>@enderror
    </fieldset>
</div>
