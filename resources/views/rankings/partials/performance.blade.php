{{--
    Selected investment performance (replaced in place when another entry or chart period is chosen).
    $selected (RankingBrowser item or null), $insight (InvestmentInsights::for), $chart (PriceChart::build), $period, $seg.
--}}
@php
    use App\Services\Investment\InvestmentInsights;
    use App\Support\PriceChart;
    $i = $selected;
    $r = $i['row'] ?? null;
    $pct = fn ($v) => $v === null ? null : InvestmentInsights::signed($v).'%';
    $periodUrl = fn ($p) => request()->fullUrlWithQuery(['period' => $p, 'part' => null]).'#performance';
    $scenarios = $r?->metric('scenarios');
    $gallery = collect($i['project']?->gallery ?? [])->map(fn ($path) => asset('storage/'.$path))->when($i['image'] ?? null, fn ($c) => $c->prepend($i['image']))->unique()->values();
@endphp
<section id="performance" class="card scroll-mt-24 overflow-hidden" data-rankings-performance aria-labelledby="perf-title">
    @if (! $i)
        <div class="p-6 text-sm text-muted"><h2 id="perf-title" class="text-base font-bold text-ink">Investment performance</h2><p class="mt-2">Choose an entry to see its verified performance.</p></div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
            <h2 id="perf-title" class="text-lg font-bold text-ink">{{ $i['name'] }}@if ($i['group_label'])<span class="text-muted"> · {{ $i['group_label'] }}</span>@endif <span class="font-normal text-muted">— Investment Performance</span></h2>
            <span class="flex items-center gap-3">@include('rankings.partials.badges', ['badge' => 'demo', 'item' => $i])
                @if ($r)<a href="{{ route('investment.show', [$r->entity_type, $r->entity_id]) }}" class="link text-[13px]">Full analysis →</a>@endif</span>
        </div>

        <div class="grid gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <div class="relative min-h-40 overflow-hidden rounded-lg bg-gradient-to-br from-brand-700 to-brand-900">
                @if ($gallery->isNotEmpty())<img src="{{ $gallery->first() }}" alt="{{ $i['name'] }}" class="absolute inset-0 size-full object-cover" loading="lazy">@endif
                <div class="absolute top-3 right-3 rounded-lg bg-white/95 p-2 text-center shadow-card">
                    @if ($i['score'] !== null)
                        <span class="block text-2xl font-bold text-emerald-700 tabular-nums">{{ rtrim(rtrim(number_format($i['score'], 1), '0'), '.') }}</span><span class="block text-[11px] font-semibold text-muted">MPIS Score</span>
                    @else
                        <span class="block max-w-24 text-[11px] font-semibold text-muted">{{ $i['status_label'] }}</span>
                    @endif
                </div>
                @if ($i['score'] !== null && $i['rank'])<span class="absolute bottom-3 left-3 rounded-md bg-gold-500 px-2.5 py-1 text-[12px] font-bold text-white">#{{ $i['rank'] }} in Pakistan · {{ $seg['label'] }}</span>@endif
                @if ($gallery->isEmpty())<span class="absolute inset-0 grid place-items-center text-sm text-white/70">No project image</span>@endif
            </div>
            <dl class="divide-y divide-line text-[13px]">
                <div class="flex justify-between gap-3 py-1.5"><dt class="text-muted">Current price @if ($i['price'])({{ $i['price']['basis_label'] }})@endif</dt><dd class="text-right font-bold">{{ $i['price']['label'] ?? 'No verified price' }}@if ($i['price']['per_sqft'] ?? null)<span class="block text-[11px] font-normal text-muted">PKR {{ number_format($i['price']['per_sqft']) }} / sq ft</span>@endif</dd></div>
                @foreach (['change_1y' => '1 Year Change', 'change_2y' => '2 Year Change', 'change_5y' => '5 Year Change'] as $k => $label)
                    <div class="flex justify-between gap-3 py-1.5"><dt class="text-muted">{{ $label }}</dt>
                        <dd @class(['font-semibold', 'text-emerald-700' => ($i[$k] ?? -1) >= 0 && $i[$k] !== null, 'text-red-600' => $i[$k] !== null && $i[$k] < 0, 'text-muted font-normal' => $i[$k] === null])>{{ $i[$k] === null ? 'Insufficient Historical Data' : ($i[$k] >= 0 ? '▲ ' : '▼ ').$pct($i[$k]) }}</dd></div>
                @endforeach
                <div class="flex justify-between gap-3 py-1.5"><dt class="text-muted">Rental Yield (Annual, gross)</dt><dd class="font-semibold">{{ $i['yield'] !== null ? $i['yield'].'%' : 'No verified data' }}</dd></div>
                @if (in_array('occupancy', $seg['columns'], true))<div class="flex justify-between gap-3 py-1.5"><dt class="text-muted">Occupancy Rate</dt><dd>{{ $i['occupancy'] !== null ? $i['occupancy'].'%' : 'Not verified' }}</dd></div>@endif
                <div class="flex justify-between gap-3 py-1.5"><dt class="text-muted">Investment Risk</dt><dd class="font-semibold">{{ $i['risk_label'] }}</dd></div>
                <div class="flex justify-between gap-3 py-1.5"><dt class="text-muted">Development Status</dt><dd>{{ $i['stage_label'] ?? ($i['development'] !== null ? $i['development'].'% developed' : 'No record') }}</dd></div>
                <div class="flex justify-between gap-3 py-1.5"><dt class="text-muted">Data Confidence</dt><dd>{{ $i['confidence_label'] }}</dd></div>
            </dl>
        </div>

        {{-- Tabs: all panels are shown without JavaScript; the page script turns them into tabs. --}}
        <div data-tabs>
            <div class="flex gap-1 overflow-x-auto border-y border-line bg-page/60 px-3 py-2 scrollbar-none" role="tablist" aria-label="Performance details" data-tab-list hidden>
                @foreach (['overview' => 'Overview', 'trend' => 'Price Trend', 'yield' => 'Rental Yield', 'forecast' => 'Future Forecast', 'gallery' => 'Gallery', 'location' => 'Location', 'details' => 'Details'] as $k => $label)
                    <button type="button" role="tab" id="tab-{{ $k }}" aria-controls="panel-{{ $k }}" class="shrink-0 rounded-md px-3 py-1.5 text-[13px] font-semibold aria-selected:bg-brand-600 aria-selected:text-white" data-tab="{{ $k }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="space-y-6 p-4">
                <div id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" data-panel="overview">
                    <h3 class="text-sm font-bold">Overview</h3>
                    @if ($insight)
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-[13px]">@foreach (array_merge($insight['interpretation'], $insight['calculated']) as $line)<li>{{ $line }}</li>@endforeach</ul>
                    @else
                        <p class="mt-2 text-[13px] text-muted">{{ $i['status'] === 'review' ? 'Market records for this entry are waiting for review; it is not scored until they are verified.' : 'Forecast unavailable — insufficient verified data. No market records have been verified for this entry yet, so it is listed without a score.' }}</p>
                    @endif
                    @if ($i['project']?->description)<p class="mt-3 text-[13px] text-muted">{{ \Illuminate\Support\Str::limit($i['project']->description, 320) }}</p>@endif
                </div>

                <div id="panel-trend" role="tabpanel" aria-labelledby="tab-trend" data-panel="trend">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-bold">Price Trend @if ($i['price'])({{ $i['price']['basis_label'] }}{{ $i['price']['size'] ? ', '.$i['price']['size'] : '' }})@endif</h3>
                        <nav class="flex gap-1" aria-label="Chart period">
                            @foreach (PriceChart::PERIODS as $k => [$label])
                                <a href="{{ $periodUrl($k) }}" data-performance-link @class(['rounded-md px-2.5 py-1 text-[12px] font-semibold ring-1', 'bg-brand-600 text-white ring-brand-600' => $period === $k, 'bg-white ring-line' => $period !== $k]) @if ($period === $k) aria-current="true" @endif>{{ $label }}</a>
                            @endforeach
                        </nav>
                    </div>
                    @if ($chart)
                        <figure class="mt-3">
                            <svg viewBox="0 0 {{ PriceChart::WIDTH }} {{ PriceChart::HEIGHT }}" class="h-auto w-full" role="img" aria-label="Verified {{ $i['price']['basis_label'] ?? '' }} prices from {{ $chart['points'][0]['date'] }} to {{ end($chart['points'])['date'] }}">
                                <defs><linearGradient id="pc-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#6a3fb0" stop-opacity=".35"/><stop offset="1" stop-color="#6a3fb0" stop-opacity="0"/></linearGradient></defs>
                                @foreach ($chart['ticks'] as $t)
                                    <line x1="64" x2="{{ PriceChart::WIDTH - 16 }}" y1="{{ $t['y'] }}" y2="{{ $t['y'] }}" stroke="#e8e3ef"/>
                                    <text x="58" y="{{ $t['y'] + 4 }}" text-anchor="end" font-size="11" fill="#6e6878">{{ $t['label'] }}</text>
                                @endforeach
                                <path d="{{ $chart['area'] }}" fill="url(#pc-fill)"/>
                                <path d="{{ $chart['path'] }}" fill="none" stroke="#4b2e83" stroke-width="2.5" stroke-linejoin="round"/>
                                @foreach ($chart['points'] as $p)<circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.5" fill="#4b2e83"><title>{{ $p['date'] }}: {{ $p['label'] }}</title></circle>@endforeach
                                @foreach ($chart['labels'] as $l)<text x="{{ $l['x'] }}" y="{{ PriceChart::HEIGHT - 8 }}" text-anchor="{{ $l['anchor'] }}" font-size="11" fill="#6e6878">{{ $l['label'] }}</text>@endforeach
                            </svg>
                            <figcaption class="mt-1 text-[12px] text-muted">{{ $chart['count'] }} {{ $i['is_demo'] ? 'illustrative' : 'verified' }} observations of one comparable series (same type, size, currency and price basis). Asking prices, transactions and valuations are never mixed.</figcaption>
                        </figure>
                    @else
                        <p class="mt-3 rounded-lg bg-page p-4 text-[13px] text-muted">Insufficient historical data for this period: at least two verified, comparable price observations are needed. No chart is drawn from estimates.</p>
                    @endif
                </div>

                <div id="panel-yield" role="tabpanel" aria-labelledby="tab-yield" data-panel="yield">
                    <h3 class="text-sm font-bold">Rental Yield</h3>
                    @if ($y = $r?->metric('yield'))
                        <p class="mt-2 text-[13px]">Estimated gross yield <strong>{{ $y['gross'] }}%</strong>: {{ $i['is_demo'] ? 'illustrative' : 'verified' }} rent {{ $y['rent']['label'] }}/month ({{ $y['rent']['date'] }}) × 12 ÷ {{ $i['is_demo'] ? 'illustrative' : 'verified' }} price {{ $y['sale']['label'] }} ({{ $y['sale']['date'] }}). Gross, before maintenance, vacancy and taxes.</p>
                    @else
                        <p class="mt-2 text-[13px] text-muted">No verified rent observation matches a verified sale price of the same type and size within 12 months, so no yield is shown.</p>
                    @endif
                    <a href="{{ route('investment.yield') }}" class="link mt-2 inline-block text-[13px]">Rental yield calculator</a>
                </div>

                <div id="panel-forecast" role="tabpanel" aria-labelledby="tab-forecast" data-panel="forecast">
                    @include('rankings.partials.forecast', ['scenarios' => $scenarios, 'name' => $i['name'], 'r' => $r])
                </div>

                <div id="panel-gallery" role="tabpanel" aria-labelledby="tab-gallery" data-panel="gallery">
                    <h3 class="text-sm font-bold">Gallery</h3>
                    @if ($gallery->isNotEmpty())
                        <ul class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">@foreach ($gallery as $src)<li><img src="{{ $src }}" alt="{{ $i['name'] }} photo {{ $loop->iteration }}" class="aspect-[4/3] w-full rounded-md object-cover" loading="lazy"></li>@endforeach</ul>
                    @else
                        <p class="mt-2 text-[13px] text-muted">No photos have been added for this entry.</p>
                    @endif
                </div>

                <div id="panel-location" role="tabpanel" aria-labelledby="tab-location" data-panel="location">
                    <h3 class="text-sm font-bold">Location</h3>
                    <p class="mt-2 text-[13px]">{{ $i['location'] }}, {{ $i['city'] }}.
                        @if ($i['coordinates']) {{ $i['project']?->hasCoordinates() ? 'Shown on the map at the project position.' : 'Shown on the map at the area centre (approximate).' }} @else No map position recorded. @endif</p>
                    @if ($i['project']?->area_id)<a href="{{ route('properties.map', ['area' => $i['project']->area_id]) }}" class="link mt-1 inline-block text-[13px]">Listings nearby on the map</a>@endif
                </div>

                <div id="panel-details" role="tabpanel" aria-labelledby="tab-details" data-panel="details">
                    <h3 class="text-sm font-bold">Details</h3>
                    @if ($r)
                        <p class="mt-1 text-[12px] text-muted">MPIS methodology v{{ $r->methodology_version }} · weights of “{{ config('meezan.investment.categories.'.($r->factors['weights_from'] ?? 'overall').'.label') }}” · factor coverage {{ round(($r->factors['coverage'] ?? 0) * 100) }}%</p>
                        <div class="relative mt-2 overflow-x-auto">
                            <table class="table table-compact text-[12px]">
                                <thead><tr><th scope="col">Factor</th><th scope="col">Weight</th><th scope="col">Score</th><th scope="col">Basis</th></tr></thead>
                                <tbody>@foreach ($r->factors['items'] ?? [] as $k => $f)<tr><td>{{ config('meezan.investment.factors.'.$k) }}</td><td>{{ $f['weight'] }}%</td><td>{{ $f['score'] ?? '—' }}</td><td class="min-w-56">{{ $f['basis'] }}</td></tr>@endforeach</tbody>
                            </table>
                        </div>
                        <h4 class="mt-3 text-[13px] font-bold">Sources</h4>
                        <ul class="mt-1 list-disc pl-5 text-[12px]">
                            @forelse ($r->metric('sources', []) as $s)<li>{{ $s['name'] }} ({{ $s['type'] }}, reliability {{ $s['reliability'] }})@if ($s['last_checked']) — checked {{ $s['last_checked'] }}@endif @if ($s['url'])<a href="{{ $s['url'] }}" class="link" rel="nofollow noopener" target="_blank">source</a>@endif</li>@empty<li class="text-muted">No sources recorded.</li>@endforelse
                        </ul>
                        @if ($insight && $insight['missing'])<h4 class="mt-3 text-[13px] font-bold">Missing data</h4><ul class="mt-1 list-disc pl-5 text-[12px] text-muted">@foreach ($insight['missing'] as $m)<li>{{ $m }}</li>@endforeach</ul>@endif
                    @endif
                    @if ($specs = $i['project']?->specifications)
                        <h4 class="mt-3 text-[13px] font-bold">Project specifications (as provided by the developer)</h4>
                        <dl class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1 text-[12px]">@foreach ($specs as $k => $v)<dt class="text-muted">{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach</dl>
                    @endif
                    @if ($i['project']?->approval_note)<p class="mt-2 text-[12px]">Approval (as stated by the developer): {{ $i['project']->approval_note }} — verify with the approving authority.</p>@endif
                </div>
            </div>
        </div>
    @endif
</section>
