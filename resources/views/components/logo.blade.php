@props(['variant' => 'dark', 'compact' => false])
{{--
    Meezan Property logo, taken from public/Refrence/Meezan Property Brand Style Guide.png
    (copies live in public/images/ref). "light" is the white version for dark surfaces.
--}}
@if ($compact)
    <img src="{{ asset('images/ref/logo-mark.png') }}" alt="Meezan Property" width="112" height="116" {{ $attributes->merge(['class' => 'h-10 w-auto']) }}>
@else
    <img src="{{ asset($variant === 'light' ? 'images/ref/logo-white.png' : 'images/ref/logo.png') }}" alt="Meezan Property — Your Property. Our Priority." width="322" height="116"
         {{ $attributes->merge(['class' => 'h-14 w-auto']) }}>
@endif
