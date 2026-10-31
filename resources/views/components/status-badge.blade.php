@props(['tone' => 'neutral', 'label' => null])
@php
    $tones = [
        'neutral' => 'bg-page text-ink/70 ring-line',
        'muted' => 'bg-zinc-100 text-zinc-600 ring-zinc-200',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-200',
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'danger' => 'bg-red-50 text-red-700 ring-red-200',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge ring-1 ring-inset '.($tones[$tone] ?? $tones['neutral'])]) }}>
    <span class="size-1.5 rounded-full bg-current opacity-70"></span>{{ $label ?? $slot }}
</span>
