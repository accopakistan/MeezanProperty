@props(['variant' => 'dark', 'compact' => false, 'full' => false, 'priority' => false])
{{--
    Meezan Property logo.

    Brand logo: public/images/logo/Meezan Property Premium Logo.png (owner's final asset, used
    as supplied and never edited). It is a 1254x1254 square with an opaque white background
    and wide blank margins (artwork spans x 126-1128, y 220-1070). To keep it readable in
    small spaces, the frame below shows only the artwork plus a safety margin; the image
    itself keeps its proportions (object-fit: contain) and nothing of the artwork is cut.

    - default / full  the premium logo on a light surface (there is no version with the verse
                      yet, so "full" shows the same logo)
    - variant="light" dark surfaces (footer, admin sidebar): the asset is not transparent, so
                      it sits on its own white background with rounded corners
    - compact         the M mark (meezan-mark.png) for icon-sized spots such as the AI advisor
                      avatar, where the full logo would not be legible
    - priority        above-the-fold copies (the site header): the browser fetches it first
--}}
@php
    $light = $variant === 'light';
    // Size passed in (class) applies to the frame; one default when none is given.
    $attributes = $attributes->has('class') ? $attributes : $attributes->merge(['class' => 'h-14 w-auto']);

    if (! $compact) {
        $size = 1254;
        // Visible area of the square image, in its own pixels: [left, top, width, height].
        [$x, $y, $w, $h] = $light ? [62, 156, 1130, 978] : [102, 196, 1050, 898];
        $pct = fn (float $v) => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.').'%';
        $frame = "position:relative;display:inline-block;vertical-align:middle;overflow:hidden;aspect-ratio:{$w}/{$h};"
            .($light ? 'background:#fff;border-radius:0.75rem;' : '');
        $image = 'position:absolute;max-width:none;object-fit:contain;'
            .'left:-'.$pct($x / $w * 100).';top:-'.$pct($y / $h * 100).';'
            .'width:'.$pct($size / $w * 100).';height:'.$pct($size / $h * 100).';';
    }
@endphp
@if ($compact)
    <img src="{{ asset('images/logo/meezan-mark.png') }}" alt="Meezan Property" width="256" height="256" {{ $attributes }}>
@else
    <span {{ $attributes->merge(['style' => $frame]) }} data-brand-logo>
        <img src="{{ asset('images/logo/'.rawurlencode('Meezan Property Premium Logo.png')) }}" alt="Meezan Property" width="{{ $size }}" height="{{ $size }}" style="{{ $image }}" @if ($priority) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
    </span>
@endif
