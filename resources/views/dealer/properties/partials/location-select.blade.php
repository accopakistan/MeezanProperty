@php
    /**
     * City → area/society → phase → sector/block. Each level is a select named
     * area_path[]; the deepest consistent choice becomes area_id (PropertyRequest).
     * Further levels load on demand from locations.children / locations.options.
     */
    $levels = \App\Support\LocationLevels::for(old('city', $property->city), (int) old('area_id', $property->area_id) ?: null);
    $optionData = fn ($a) => ['name' => $a->name, 'lat' => $a->latitude, 'lng' => $a->longitude, 'children' => $a->children_count];
@endphp
<div class="grid gap-5 sm:grid-cols-2" data-location-cascade data-options-url="{{ route('locations.options') }}" data-children-url="{{ url('/locations/__ID__/children') }}">
    <x-field name="city" type="select" label="City" required :value="old('city', $property->city)" :options="\App\Support\Cities::options()" />
    @foreach ($levels as $i => $level)
        <div data-location-level-wrap="{{ $i }}">
            <label for="area-level-{{ $i }}" class="label">{{ $level['label'] }}@if ($i === 0) <span class="text-red-600" aria-hidden="true">*</span>@endif</label>
            <select id="area-level-{{ $i }}" name="area_path[]" class="input @error('area_id') input-error @enderror" data-location-level="{{ $i }}" @if ($i === 0) required @endif>
                <option value="">{{ $i === 0 ? 'Choose an area' : 'Whole '.$levels[$i - 1]['options']->firstWhere('id', $levels[$i - 1]['selected'])?->name }}</option>
                @foreach ($level['options'] as $a)
                    <option value="{{ $a->id }}" @selected($level['selected'] === $a->id)
                            data-name="{{ $a->name }}" data-lat="{{ $a->latitude }}" data-lng="{{ $a->longitude }}" data-children="{{ $a->children_count }}">{{ $a->name }}</option>
                @endforeach
            </select>
            @if ($i === 0)
                @error('area_id')<p class="field-error">{{ $message }}</p>@enderror
                <p class="field-hint">Can’t find it? Choose the nearest area and add the details under Street / block.</p>
            @endif
        </div>
    @endforeach
</div>
