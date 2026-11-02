@props(['label', 'used', 'limit' => null, 'hint' => null])
{{-- One allowance: used of limit (null limit = no limit), with near/over-limit colouring. --}}
@php
    $percent = $limit ? min(100, (int) round($used / max(1, $limit) * 100)) : 0;
    $over = $limit !== null && $used > $limit;
    $full = $limit !== null && $used >= $limit;
    $bar = $over ? 'bg-red-500' : ($full ? 'bg-amber-500' : ($percent >= 80 ? 'bg-gold-500' : 'bg-brand-600'));
@endphp
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="flex items-baseline justify-between gap-2">
        <p class="text-[13px] font-semibold text-ink">{{ $label }}</p>
        <p class="text-sm tabular-nums {{ $over ? 'font-bold text-red-600' : 'text-muted' }}">
            <span class="font-bold text-ink">{{ number_format($used) }}</span>
            {{ $limit === null ? '· no limit' : 'of '.number_format($limit) }}
        </p>
    </div>
    <div class="mt-2 h-2 overflow-hidden rounded-full bg-page" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $limit ?? $used }}" aria-valuenow="{{ $used }}">
        <div class="h-full rounded-full {{ $bar }}" style="width: {{ $limit === null ? 0 : max($used > 0 ? 3 : 0, $percent) }}%"></div>
    </div>
    @if ($over)
        <p class="mt-1.5 text-xs font-semibold text-red-600">Over the limit by {{ number_format($used - $limit) }}. Nothing is removed; new ones are blocked until you are under it.</p>
    @elseif ($full)
        <p class="mt-1.5 text-xs font-semibold text-amber-700">Limit reached.</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
