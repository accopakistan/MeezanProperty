@props(['label', 'value', 'icon' => 'chart', 'tone' => 'brand', 'href' => null, 'hint' => null])
{{-- Stat card as in the dealer and buyer references: round icon tile, large number, label below. --}}
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'gold' => 'bg-gold-100 text-gold-600',
        'success' => 'bg-emerald-50 text-emerald-600',
        'warning' => 'bg-amber-50 text-amber-600',
        'danger' => 'bg-red-50 text-red-500',
        'info' => 'bg-sky-50 text-sky-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card flex items-center gap-4 p-4 transition '.($href ? 'hover:border-brand-200 hover:shadow-lift' : '')]) }}>
    <span class="grid size-14 shrink-0 place-items-center rounded-full {{ $tones[$tone] ?? $tones['brand'] }}">
        <x-icon :name="$icon" class="size-7" />
    </span>
    <div class="min-w-0">
        <p class="text-[26px] leading-none font-bold text-ink tabular-nums">{{ $value }}</p>
        <p class="mt-1.5 text-[13px] text-muted">{{ $label }}</p>
        @if ($hint)
            <p class="mt-0.5 text-xs text-muted/80">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
