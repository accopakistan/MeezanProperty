@props(['name' => 'area_ids', 'max' => 5, 'selected' => collect(), 'suggestions' => collect(), 'city' => null, 'legend' => 'Preferred areas'])
{{--
    Several areas in the city chosen in the same form's [name=city] select:
    popular areas of that city as quick picks, plus a search box for any other.
    $selected: Area models already chosen.
--}}
@php $selectedIds = $selected->pluck('id')->all(); @endphp
<fieldset data-area-picker data-name="{{ $name }}[]" data-suggest-url="{{ route('locations.suggest') }}">
    <legend class="label">{{ $legend }} <span class="font-normal text-muted">(up to {{ $max }})</span></legend>
    <div class="max-h-56 space-y-3 overflow-y-auto rounded-xl border border-line p-3">
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4" data-area-picker-chosen>
            @foreach ($selected as $area)
                <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-brand-50">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $area->id }}" class="checkbox" checked> {{ $area->name }}
                </label>
            @endforeach
        </div>
        @foreach ($suggestions as $groupCity => $list)
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4" data-city-group="{{ $groupCity }}" @if ($groupCity !== $city) hidden @endif>
                @foreach ($list->reject(fn ($a) => in_array($a->id, $selectedIds, true)) as $area)
                    <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-brand-50">
                        <input type="checkbox" name="{{ $name }}[]" value="{{ $area->id }}" class="checkbox" @disabled($groupCity !== $city)> {{ $area->name }}
                    </label>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="relative mt-2">
        <label for="{{ $name }}-search" class="sr-only">Find another area</label>
        <input type="search" id="{{ $name }}-search" class="input py-2 text-sm" placeholder="Find another area, society or phase…" autocomplete="off" data-area-picker-search hidden>
        <ul class="absolute inset-x-0 top-full z-30 mt-1 max-h-60 overflow-auto rounded-xl border border-line bg-white py-1 shadow-lift" role="listbox" data-area-picker-results hidden></ul>
    </div>
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="field-error">Choose areas in the selected city.</p>@enderror
</fieldset>
