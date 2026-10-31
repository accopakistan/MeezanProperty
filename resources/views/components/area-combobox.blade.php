@props(['value' => null, 'slug' => null, 'placeholder' => 'City, area, society or phase', 'inputClass' => 'input', 'id' => 'location', 'city' => null])
{{-- Suggestions are fetched as you type (locations.suggest); the page never carries the full location list. --}}
<div class="relative" data-area-combobox="{{ route('locations.suggest') }}" @if ($city) data-city="{{ $city }}" @endif {{ $attributes }}>
    <input type="text" id="{{ $id }}" name="location" value="{{ $value }}" placeholder="{{ $placeholder }}" autocomplete="off"
           role="combobox" aria-autocomplete="list" aria-controls="{{ $id }}-list" aria-expanded="false" class="{{ $inputClass }}">
    <input type="hidden" name="area" value="{{ $slug }}">
    <ul id="{{ $id }}-list" role="listbox" class="absolute inset-x-0 top-full z-30 mt-2 max-h-80 overflow-auto rounded-xl border border-line bg-white py-1 shadow-lift" hidden></ul>
</div>
