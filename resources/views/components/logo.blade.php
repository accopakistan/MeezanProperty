@props(['variant' => 'dark', 'compact' => false, 'full' => false])
{{--
    Meezan Property logo. Source: public/images/logo/Meezan Logo.png (kept unchanged);
    web copies with a transparent background are in the same folder:
    - meezan-logo.png       the M and "MEEZAN PROPERTY" (default)
    - meezan-logo-full.png  with the verse and its translation (full: large spaces only, where it is readable)
    - meezan-mark.png       the M on its own (compact)
    "light" is for dark surfaces: the same logo with white lettering (the M and the
    gold details keep their colours): meezan-logo-light.png / meezan-logo-full-light.png.
--}}
@php
    $light = $variant === 'light';
    [$src, $width, $height] = match (true) {
        $compact => ['images/logo/meezan-mark.png', 256, 256],
        $full => [$light ? 'images/logo/meezan-logo-full-light.png' : 'images/logo/meezan-logo-full.png', 900, 710],
        default => [$light ? 'images/logo/meezan-logo-light.png' : 'images/logo/meezan-logo.png', 374, 240],
    };
    // A size passed in replaces the default one (two height classes would clash).
    $attributes = $attributes->has('class') ? $attributes : $attributes->merge(['class' => 'h-14 w-auto']);
    $alt = $full ? 'Meezan Property — “And give full measure and weight in justice” (Surah Al-An‘am 6:152)' : 'Meezan Property';
@endphp
<img src="{{ asset($src) }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}" {{ $attributes }}>
