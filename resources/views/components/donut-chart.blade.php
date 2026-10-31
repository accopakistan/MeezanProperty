@props(['rows', 'label' => 'Donut chart'])
{{-- rows: list of ['label' => string, 'count' => int, 'color' => '#hex'] --}}
@php
    $total = collect($rows)->sum('count');
    $offset = 0;
    $stops = collect($rows)->map(function ($r) use (&$offset, $total) {
        $from = $offset;
        $offset += $total ? $r['count'] / $total * 100 : 0;

        return $r['color'].' '.$from.'% '.$offset.'%';
    })->implode(', ');
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-5']) }}>
    @if ($total)
        <div class="relative size-32 shrink-0 rounded-full" style="background: conic-gradient({{ $stops }})" role="img" aria-label="{{ $label }}">
            <div class="absolute inset-[22%] rounded-full bg-white"></div>
        </div>
        <ul class="flex-1 space-y-2 text-[13px]">
            @foreach ($rows as $r)
                <li class="flex items-center justify-between gap-2"><span class="flex items-center gap-2"><span class="size-2.5 rounded-full" style="background: {{ $r['color'] }}"></span>{{ $r['label'] }}</span><span class="font-semibold">{{ round($r['count'] / $total * 100) }}%</span></li>
            @endforeach
        </ul>
    @else
        <p class="w-full py-8 text-center text-sm text-muted">No data yet.</p>
    @endif
</div>
