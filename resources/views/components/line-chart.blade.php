@props(['labels', 'series', 'label' => 'Line chart'])
{{-- series: [name => ['color' => '#hex', 'values' => [int, ...]]]; labels align with values. --}}
@php
    $n = max(1, count($labels) - 1);
    // At least 4 so the five gridlines carry distinct whole-number labels.
    $max = max(4, ...array_merge([1], ...array_map(fn ($s) => $s['values'], array_values($series))));
    $pt = fn ($v, $i) => round($i * 560 / $n, 1).','.round(180 - $v / $max * 160, 1);
    $step = max(1, (int) ceil(count($labels) / 7));
@endphp
<div {{ $attributes }}>
    <div class="flex flex-wrap gap-4 text-[13px]">
        @foreach ($series as $name => $s)
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: {{ $s['color'] }}"></span> {{ $name }}</span>
        @endforeach
    </div>
    <svg viewBox="-30 -10 606 220" class="mt-3 w-full" role="img" aria-label="{{ $label }}">
        @foreach ([0, 0.25, 0.5, 0.75, 1] as $f)
            <line x1="0" x2="560" y1="{{ 180 - $f * 160 }}" y2="{{ 180 - $f * 160 }}" stroke="#e8e3ef" />
            <text x="-8" y="{{ 184 - $f * 160 }}" text-anchor="end" font-size="10" fill="#6e6878">{{ (int) round($max * $f) }}</text>
        @endforeach
        @foreach ($series as $name => $s)
            <polyline points="{{ collect($s['values'])->map($pt)->implode(' ') }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2.2" stroke-linejoin="round" />
        @endforeach
        @foreach ($labels as $i => $l)
            @if ($i % $step === 0 || $i === count($labels) - 1)
                <text x="{{ $i * 560 / $n }}" y="202" text-anchor="middle" font-size="10" fill="#6e6878">{{ $l }}</text>
            @endif
        @endforeach
    </svg>
</div>
