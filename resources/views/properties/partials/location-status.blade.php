{{--
    How precise the public location is. Exact pins are set by the advertiser;
    "Location checked" appears only after a Meezan Property reviewer checked
    the pin against the map. Internal flags are never shown here.
--}}
@php
    [$label, $tone, $icon, $title] = match (true) {
        $property->showsExactLocation() && $property->location_status === 'verified' => ['Location checked', 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'badge-check', 'Pinned by the advertiser and checked against the map by Meezan Property'],
        $property->showsExactLocation() => ['Exact location', 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'map-pin', 'Pinned on the map by the advertiser'],
        $property->showsApproximateLocation() => ['Approximate location', 'bg-amber-50 text-amber-800 ring-amber-200', 'map', 'The advertiser shows the location to within about 500 m'],
        $property->hasCoordinates() && $property->location_privacy === 'area' => ['Area only', 'bg-amber-50 text-amber-800 ring-amber-200', 'map', 'The advertiser chose to show the area only'],
        (bool) $property->area?->hasCoordinates() => ['Approximate area', 'bg-amber-50 text-amber-800 ring-amber-200', 'map', 'Shown at the centre of '.$property->area->name.'; the exact location was not provided'],
        default => ['Location not provided', 'bg-page text-ink/70 ring-line', 'info', 'No map location was provided for this listing'],
    };
@endphp
<span class="badge gap-1 ring-1 {{ $tone }}" title="{{ $title }}">
    <x-icon :name="$icon" class="size-3.5" /> {{ $label }}<span class="sr-only">: {{ $title }}</span>
</span>
