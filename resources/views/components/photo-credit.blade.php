@props(['photo'])
{{-- Credit required by the photo's Creative Commons licence (config meezan.place_photos). --}}
@if ($photo)
    <p {{ $attributes->merge(['class' => 'absolute right-3 bottom-2 z-10 max-w-[calc(100%-1.5rem)] text-right text-[10.5px] leading-snug text-white/70 sm:truncate']) }}>
        Photo: <a href="{{ $photo['source'] }}" class="underline decoration-white/40 hover:text-white" target="_blank" rel="noopener">{{ $photo['alt'] }}</a>
        by {{ $photo['author'] }},
        <a href="{{ $photo['license_url'] }}" class="underline decoration-white/40 hover:text-white" target="_blank" rel="noopener license">{{ $photo['license'] }}</a>, via Wikimedia Commons
    </p>
@endif
