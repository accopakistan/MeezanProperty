{{--
    Price trend chart (owner's reference design; data decision 19 Nov 2026): the advertised (asking)
    price index on file for one project, per plot size, over one or two years. Drawn as SVG on the
    server; buttons switch size and range. Always labelled as asking prices with the source named.
    $trends (PriceIndexTrend::forFacts), $project (the Project the figures belong to), $intro (optional line).
--}}
@php
    use App\Support\PriceIndexTrend;

    $sizes = array_intersect_key(PriceIndexTrend::SIZES, $trends);
    $start = array_key_exists('10 marla', $trends) ? '10 marla' : array_key_first($trends);
    $uid = 'trend-'.$project->id;
    // Sized for the dashboard's middle column (~440 px), so 11-unit text renders at about 11 px.
    $W = 440; $L = 66; $R = 420; $T = 30; $B = 176;
    // Month number of a "Sep 2026" label, so points sit by date (the series can skip months).
    $monthNo = function (string $label) {
        try {
            $d = \Illuminate\Support\Carbon::createFromFormat('!M Y', $label);

            return (int) $d->year * 12 + (int) $d->month;
        } catch (\Throwable) {
            return null;
        }
    };
    // "2y": every point on file; "1y": the last 12 months (or the last two points when only yearly figures exist).
    $lastYear = function (array $points) use ($monthNo) {
        $end = $monthNo(end($points)['label']);
        $recent = array_values(array_filter($points, fn ($p) => $end !== null && ($m = $monthNo($p['label'])) !== null && $m >= $end - 12));

        return count($recent) >= 2 ? $recent : array_slice($points, -2);
    };
    $chart = function (array $points) use ($L, $R, $T, $B, $monthNo) {
        $values = array_column($points, 'amount');
        $lo = min($values); $hi = max($values);
        $pad = max(($hi - $lo) * 0.25, $hi * 0.04);
        $min = max(0, $lo - $pad); $max = $hi + $pad;
        $at = array_map(fn ($p, $i) => $monthNo($p['label']) ?? $i, $points, array_keys($points));
        $span = max(1, max($at) - min($at));
        $x = fn ($i) => round($L + ($R - $L) * ($at[$i] - min($at)) / $span, 1);
        $y = fn ($v) => round($B - ($v - $min) / ($max - $min) * ($B - $T), 1);
        $line = collect($points)->map(fn ($p, $i) => $x($i).','.$y($p['amount']))->implode(' ');
        // Axis labels: first, last, and others at least ~70 px apart.
        $shown = []; $lastX = -999;
        foreach (array_keys($points) as $i) {
            if ($i === array_key_last($points) || ($x($i) - $lastX >= 70 && $R - $x($i) >= 70)) {
                $shown[$i] = true; $lastX = $x($i);
            }
        }

        return ['points' => $points, 'x' => $x, 'y' => $y, 'line' => $line, 'area' => $line.' '.$x(count($points) - 1).','.$B.' '.$x(0).','.$B, 'shown' => $shown,
            'grid' => collect(range(0, 4))->map(fn ($k) => ['y' => round($B - ($B - $T) * $k / 4, 1), 'v' => $min + ($max - $min) * $k / 4])->all()];
    };
@endphp
<div data-price-trend id="{{ $uid }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-base font-bold">Price Trend
            @foreach ($sizes as $key => $label)<span data-trend-label="{{ $key }}" @if ($key !== $start) hidden @endif>({{ $key === 'all sizes' ? 'all plot sizes' : $label.' plot' }})</span>@endforeach
        </h3>
        <div class="flex gap-1" role="group" aria-label="Period">
            @foreach (['1y' => '1Y', '2y' => '2Y'] as $range => $label)
                <button type="button" data-trend-range="{{ $range }}" aria-pressed="{{ $range === '2y' ? 'true' : 'false' }}"
                        class="rounded-md px-2.5 py-1 text-[12px] font-semibold text-ink ring-1 ring-line aria-pressed:bg-brand-600 aria-pressed:text-white aria-pressed:ring-brand-600">{{ $label }}</button>
            @endforeach
        </div>
    </div>
    @if (count($sizes) > 1)
        <div class="mt-2 flex gap-1" role="group" aria-label="Plot size">
            @foreach ($sizes as $key => $label)
                <button type="button" data-trend-size="{{ $key }}" aria-pressed="{{ $key === $start ? 'true' : 'false' }}"
                        class="rounded-md px-2.5 py-1 text-[12px] font-semibold text-ink ring-1 ring-line aria-pressed:bg-brand-50 aria-pressed:text-brand-800 aria-pressed:ring-brand-300">{{ $label }}</button>
            @endforeach
        </div>
    @endif
    @if (! empty($intro))<p class="mt-1 text-[12px] text-muted">{{ $intro }}</p>@endif

    @foreach ($trends as $size => $trend)
        @foreach (['2y' => $trend['points'], '1y' => $lastYear($trend['points'])] as $range => $points)
            @php $c = $chart($points); $last = end($points); $lx = ($c['x'])(count($points) - 1); $ly = ($c['y'])($last['amount']); $gid = $uid.'-'.str_replace(' ', '-', $size).'-'.$range; @endphp
            <figure data-trend="{{ $size }}|{{ $range }}" @if ($size !== $start || $range !== '2y') hidden @endif class="mt-2">
                <svg viewBox="0 0 {{ $W }} 206" class="w-full" role="img"
                     aria-label="Advertised price, {{ PriceIndexTrend::SIZES[$size] }} plot, {{ $project->name }}: {{ collect($points)->map(fn ($p) => $p['label'].' '.PriceIndexTrend::format($p['amount']))->implode(', ') }}">
                    <defs>
                        <linearGradient id="{{ $gid }}" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#6a3fb0" stop-opacity="0.32" /><stop offset="100%" stop-color="#6a3fb0" stop-opacity="0.02" />
                        </linearGradient>
                    </defs>
                    @foreach ($c['grid'] as $g)
                        <line x1="{{ $L }}" x2="{{ $R }}" y1="{{ $g['y'] }}" y2="{{ $g['y'] }}" stroke="#e8e3ef" />
                        <text x="{{ $L - 8 }}" y="{{ $g['y'] + 3.5 }}" text-anchor="end" font-size="11" fill="#6e6878">{{ PriceIndexTrend::format((int) $g['v']) }}</text>
                    @endforeach
                    <polygon points="{{ $c['area'] }}" fill="url(#{{ $gid }})" />
                    <polyline points="{{ $c['line'] }}" fill="none" stroke="#6a3fb0" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" />
                    @foreach ($points as $i => $p)
                        <circle cx="{{ ($c['x'])($i) }}" cy="{{ ($c['y'])($p['amount']) }}" r="{{ count($points) > 6 ? 3 : 4.5 }}" fill="{{ $p['derived'] ? '#ffffff' : '#6a3fb0' }}" stroke="#6a3fb0" stroke-width="2">
                            <title>{{ $p['label'] }}: {{ PriceIndexTrend::format($p['amount']) }}{{ $p['derived'] ? ' (from the published change)' : ' (published asking price)' }}</title>
                        </circle>
                        @if (isset($c['shown'][$i]))
                            <text x="{{ ($c['x'])($i) }}" y="198" text-anchor="{{ $i === 0 ? 'start' : ($loop->last ? 'end' : 'middle') }}" font-size="11" fill="#6e6878">{{ $p['label'] }}</text>
                        @endif
                    @endforeach
                    <rect x="{{ $lx - 78 }}" y="{{ max(2, $ly - 30) }}" width="78" height="20" rx="4" fill="#4b2e83" />
                    <text x="{{ $lx - 39 }}" y="{{ max(2, $ly - 30) + 14 }}" text-anchor="middle" font-size="11.5" font-weight="700" fill="#ffffff">{{ PriceIndexTrend::format($last['amount']) }}</text>
                </svg>
            </figure>
        @endforeach
    @endforeach

    <p class="mt-1 text-[11px] leading-snug text-muted">
        <span class="font-semibold text-ink">Advertised (asking) prices, not sales.</span>
        Source:@foreach ($trends as $size => $t)<span data-trend-label="{{ $size }}" @if ($size !== $start) hidden @endif> {!! $t['url'] ? '<a href="'.e($t['url']).'" class="link" rel="nofollow noopener" target="_blank">'.e($t['source']).'</a>' : e($t['source']) !!}</span>@endforeach.
        @foreach ($trends as $size => $t)<span data-trend-label="{{ $size }}" @if ($size !== $start) hidden @endif>{{ collect($t['points'])->contains('derived', true)
            ? 'The latest point is the published price; earlier points (hollow) follow from the published 1- and 2-year changes.'
            : 'Every point is a monthly average the source publishes.' }}</span>@endforeach
        No forecast.
    </p>
</div>

@pushOnce('scripts')
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
document.querySelectorAll('[data-price-trend]').forEach(function (box) {
    var state = { size: (box.querySelector('[data-trend-size][aria-pressed="true"]') || {}).dataset?.trendSize, range: '2y' };
    if (!state.size) { var first = box.querySelector('[data-trend]:not([hidden])'); state.size = first ? first.dataset.trend.split('|')[0] : null; }
    var show = function () {
        box.querySelectorAll('[data-trend]').forEach(function (f) { f.hidden = f.dataset.trend !== state.size + '|' + state.range; });
        box.querySelectorAll('[data-trend-label]').forEach(function (l) { l.hidden = l.dataset.trendLabel !== state.size; });
        box.querySelectorAll('[data-trend-size]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.trendSize === state.size ? 'true' : 'false'); });
        box.querySelectorAll('[data-trend-range]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.trendRange === state.range ? 'true' : 'false'); });
    };
    box.querySelectorAll('[data-trend-size]').forEach(function (b) { b.addEventListener('click', function () { state.size = b.dataset.trendSize; show(); }); });
    box.querySelectorAll('[data-trend-range]').forEach(function (b) { b.addEventListener('click', function () { state.range = b.dataset.trendRange; show(); }); });
});
</script>
@endPushOnce
