@php
    use App\Services\Investment\InvestmentInsights;
    use App\Support\Format;
    /** @var \App\Models\InvestmentRanking|null $row */
    $m = $row?->metrics ?? [];
    $name = $row?->entity_name ?? 'Investment analysis';
    $series = collect($m['series'] ?? []);
    $indicators = $m['indicators'] ?? [];
    $level = fn (string $k) => $indicators[$k]['display'] ?? 'Insufficient Data';
@endphp
<x-layouts.app :title="$name.' — investment analysis'" :description="'Investment analysis for '.$name.': score, data confidence, historical prices, rental yield, risk and sources.'" robots="noindex, follow">
    <section class="bg-gradient-to-r from-brand-900 to-brand-700">
        <div class="container-page py-10">
            <x-breadcrumbs :items="[['Investment insights', route('investment.index')], [$name, null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            @if ($row)
                <p class="mt-5 text-sm font-semibold tracking-wide text-gold-300 uppercase">{{ $row->entityTypeLabel() }} · {{ $row->city }} · {{ ucfirst($row->asset_class) }}</p>
                <h1 class="mt-1 text-3xl text-white sm:text-4xl">{{ $name }}</h1>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    @if ($row->isRanked())
                        <p class="text-white"><span class="font-serif text-4xl font-bold">{{ number_format($row->score, 1) }}</span><span class="text-white/70">/100 · {{ $row->categoryLabel() }} · #{{ $row->rank }} among {{ ucfirst($row->asset_class) }}</span></p>
                    @else
                        <p class="rounded-lg bg-white/10 px-3 py-2 text-white">Not ranked in {{ $row->categoryLabel() }}: {{ $row->factors['reason'] ?? 'insufficient data' }}</p>
                    @endif
                    <x-investment-badge kind="confidence" :level="$row->confidence" />
                    <x-investment-badge kind="risk" :level="$row->risk" />
                </div>
                <p class="mt-3 text-sm text-white/70">Calculated with methodology v{{ $row->methodology_version }}, published {{ $calc->published_at?->localTime()->format('j M Y') }}.</p>
            @else
                <h1 class="mt-5 text-3xl text-white sm:text-4xl">No published investment data</h1>
            @endif
        </div>
    </section>

    <div class="container-page py-8">
        @include('investment.partials.demo-notice')
        @if (! $row)
            <x-empty-state icon="chart" title="Nothing published for this location yet">
                There is no published ranking for this {{ $entityType }}. Rankings are only published from reviewed, verified market data.
                <x-slot:actions><a href="{{ route('investment.index') }}" class="btn btn-primary">All rankings</a></x-slot:actions>
            </x-empty-state>
        @else
            {{-- Category / asset class --}}
            <nav class="flex flex-wrap gap-2" aria-label="Ranking category">
                @if ($assets->count() > 1)
                    @foreach ($assets as $a)
                        <a href="{{ route('investment.show', [$entityType, $entityId, 'asset' => $a]) }}" @class(['rounded-full px-3 py-1.5 text-sm font-semibold ring-1', 'bg-ink text-white ring-ink' => $a === $asset, 'bg-white ring-line' => $a !== $asset])>{{ ucfirst($a) }}</a>
                    @endforeach
                    <span class="mx-1 border-l border-line"></span>
                @endif
                @foreach ($rows as $r)
                    <a href="{{ route('investment.show', [$entityType, $entityId, 'asset' => $asset, 'category' => $r->category]) }}"
                       @class(['rounded-full px-3 py-1.5 text-sm font-semibold ring-1', 'bg-brand-600 text-white ring-brand-600' => $r->category === $category, 'bg-white ring-line hover:ring-brand-300' => $r->category !== $category])>
                        {{ $r->categoryLabel() }} {{ $r->isRanked() ? '· '.number_format($r->score, 1) : '· n/a' }}</a>
                @endforeach
            </nav>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0 space-y-6">
                    {{-- Overview --}}
                    <section class="card card-body" aria-labelledby="overview">
                        <h2 id="overview" class="text-lg font-bold">Overview</h2>
                        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div><dt class="text-muted">Latest observed price</dt><dd class="text-base font-bold">{{ $m['current']['label'] ?? 'No data' }}</dd>
                                @if ($c = $m['current'] ?? null)<dd class="text-xs text-muted">{{ $c['basis_label'] }} · {{ trim(($c['size'] ?? '').' '.str_replace('_', ' ', $c['type'])) }} · {{ $c['date'] }} · sample {{ $c['sample'] }}</dd>@endif</div>
                            <div><dt class="text-muted">One-year change <span class="text-[11px]">(historical)</span></dt><dd class="text-base font-bold">{{ ($c1 = $m['change_1y'] ?? null) ? InvestmentInsights::signed($c1['percent']).'%' : 'Insufficient Historical Data' }}</dd></div>
                            <div><dt class="text-muted">Two-year change <span class="text-[11px]">(historical)</span></dt><dd class="text-base font-bold">{{ ($c2 = $m['change_2y'] ?? null) ? InvestmentInsights::signed($c2['percent']).'%' : 'Insufficient Historical Data' }}</dd></div>
                            <div><dt class="text-muted">Estimated gross yield</dt><dd class="text-base font-bold">{{ isset($m['yield']) ? $m['yield']['gross'].'%' : 'No data' }}</dd></div>
                        </dl>
                        @if (($m['platform_listings'] ?? null) !== null)
                            <p class="mt-4 text-xs text-muted">On Meezan Property: {{ number_format($m['platform_listings']) }} live {{ \Illuminate\Support\Str::plural('listing', $m['platform_listings']) }} (platform figure only, not total market supply).</p>
                        @endif
                    </section>

                    {{-- Score breakdown --}}
                    <section class="card card-body" aria-labelledby="breakdown">
                        <h2 id="breakdown" class="text-lg font-bold">Score breakdown</h2>
                        <p class="mt-1 text-[13px] text-muted">Data covers {{ round(($row->factors['coverage'] ?? 0) * 100) }}% of this ranking's weight. Factors without verified data are left out, never filled in; missing weight lowers the score slightly.</p>
                        <div class="mt-4 relative overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="text-[13px] text-muted"><tr><th class="py-2 font-medium">Factor</th><th class="py-2 font-medium">Weight</th><th class="py-2 font-medium">Factor score</th><th class="py-2 font-medium">Basis</th></tr></thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($row->factors['items'] ?? [] as $factor => $fct)
                                        <tr>
                                            <td class="py-2.5 pr-3 font-medium">{{ config('meezan.investment.factors.'.$factor) }}</td>
                                            <td class="py-2.5 pr-3 tabular-nums">{{ $fct['weight'] }}%</td>
                                            <td class="py-2.5 pr-3">
                                                @if ($fct['score'] === null)
                                                    <span class="text-muted">No data</span>
                                                @else
                                                    <span class="flex items-center gap-2"><span class="h-2 w-20 overflow-hidden rounded-full bg-brand-50"><span class="block h-full bg-brand-600" style="width: {{ $fct['score'] }}%"></span></span><span class="tabular-nums">{{ $fct['score'] }}</span></span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 text-[13px] text-muted">{{ $fct['basis'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- Historical prices --}}
                    <section class="card card-body" aria-labelledby="history">
                        <h2 id="history" class="text-lg font-bold">Historical prices <span class="text-sm font-normal text-muted">(historical data)</span></h2>
                        @if ($series->count() >= 2)
                            <x-line-chart class="mt-3" :labels="$series->map(fn ($p) => \Illuminate\Support\Carbon::parse($p['date'])->format('M y'))->all()"
                                          :series="['Average price (PKR lakh)' => ['color' => '#4b2e83', 'values' => $series->map(fn ($p) => (int) round($p['amount'] / 100000))->all()]]"
                                          label="Average observed price over time, in lakh rupees" />
                        @else
                            <p class="mt-3 rounded-lg bg-page px-4 py-3 text-sm font-semibold">Insufficient Historical Data</p>
                        @endif
                        @if ($series->isNotEmpty())
                            <table class="mt-4 w-full text-left text-sm">
                                <caption class="sr-only">Observations used</caption>
                                <thead class="text-[13px] text-muted"><tr><th class="py-1.5 font-medium">Observed</th><th class="py-1.5 font-medium">Average</th><th class="py-1.5 font-medium">Sample</th><th class="py-1.5 font-medium">Basis</th></tr></thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($series->reverse() as $p)
                                        <tr><td class="py-1.5">{{ $p['date'] }}</td><td class="py-1.5">{{ Format::price($p['amount']) }}</td><td class="py-1.5">{{ $p['sample'] }}</td><td class="py-1.5">{{ config('meezan.investment.price_bases.'.$p['basis']) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="mt-2 text-xs text-muted">One comparable series: same property type, size, currency and price basis. Changes use ((current − historical) ÷ historical) × 100.</p>
                        @endif
                    </section>

                    {{-- Rental yield --}}
                    <section class="card card-body" aria-labelledby="yield">
                        <h2 id="yield" class="text-lg font-bold">Rental yield <span class="text-sm font-normal text-muted">(estimate)</span></h2>
                        @if ($y = $m['yield'] ?? null)
                            <p class="mt-2 text-sm">Estimated <strong>gross</strong> yield: <strong class="text-brand-700">{{ $y['gross'] }}%</strong> = monthly rent {{ $y['rent']['label'] }} × 12 ÷ price {{ $y['sale']['label'] }} × 100.</p>
                            <p class="mt-1 text-xs text-muted">Rent observed {{ $y['rent']['date'] }} ({{ strtolower($y['rent']['basis_label']) }}); price observed {{ $y['sale']['date'] }} ({{ strtolower($y['sale']['basis_label']) }}). Gross yield is before maintenance, vacancy, tax and other costs; it is not a net return.</p>
                        @else
                            <p class="mt-2 text-sm text-muted">No matching verified rent and sale observations (same type, size and currency within 12 months).</p>
                        @endif
                        <a href="{{ route('investment.yield') }}" class="link mt-3 inline-block text-sm">Try the yield calculator →</a>
                    </section>

                    {{-- Scenarios --}}
                    <section class="card card-body" aria-labelledby="scenarios">
                        <h2 id="scenarios" class="text-lg font-bold">Future growth scenarios <span class="text-sm font-normal text-muted">(forward-looking, not a forecast)</span></h2>
                        @if (! empty($m['scenarios']['available']))
                            @php $s = $m['scenarios']; @endphp
                            <p class="mt-2 text-[13px] text-muted">Based on {{ $s['period'] }}; horizon {{ $s['years'] }} years from {{ Format::price($s['base']) }}.</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                @foreach (['conservative' => 'Conservative', 'expected' => 'Expected', 'high' => 'High growth'] as $k => $label)
                                    <div class="rounded-lg border border-line p-3">
                                        <p class="text-xs font-semibold text-muted uppercase">{{ $label }}</p>
                                        <p class="mt-1 text-lg font-bold">{{ InvestmentInsights::signed($s['rates'][$k]) }}% / year</p>
                                        <p class="text-[13px] text-muted">≈ {{ Format::price($s['ranges'][$k]) }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-muted">@foreach ($s['assumptions'] as $a)<li>{{ $a }}</li>@endforeach</ul>
                            <p class="mt-2 text-xs text-muted">Data confidence: {{ $row->confidenceLabel() }}.</p>
                        @else
                            <p class="mt-2 text-sm text-muted">No numerical scenario: {{ $m['scenarios']['reason'] ?? 'not enough comparable history' }}</p>
                        @endif
                    </section>

                    {{-- AI insight --}}
                    @if ($insight)
                        <section class="card card-body" aria-labelledby="insight">
                            <h2 id="insight" class="text-lg font-bold">Insight</h2>
                            @foreach (['verified' => 'Verified data', 'calculated' => 'Calculated metrics', 'interpretation' => $insight['interpretation_source'] === 'llm' ? 'AI-generated interpretation' : 'Automated interpretation', 'scenarios' => 'Forward-looking scenarios', 'missing' => 'Missing information'] as $key => $label)
                                @if (! empty($insight[$key]))
                                    <h3 class="mt-4 text-sm font-semibold">{{ $label }}</h3>
                                    <ul class="mt-1 list-disc space-y-1 pl-5 text-[13.5px] text-ink/85">@foreach ($insight[$key] as $line)<li>{{ $line }}</li>@endforeach</ul>
                                @endif
                            @endforeach
                        </section>
                    @endif
                </div>

                <div class="min-w-0 space-y-6">
                    {{-- Market indicators --}}
                    <section class="card card-body" aria-labelledby="market">
                        <h2 id="market" class="text-lg font-bold">Market indicators</h2>
                        <dl class="mt-3 space-y-2 text-sm">
                            @foreach (['demand' => 'Demand', 'supply' => 'Supply', 'liquidity' => 'Liquidity', 'development_progress' => 'Development progress', 'infrastructure' => 'Infrastructure', 'developer_status' => 'Developer / project status', 'growth_drivers' => 'Growth indicators'] as $k => $label)
                                <div class="flex justify-between gap-3"><dt class="text-muted">{{ $label }}</dt><dd class="text-right font-semibold">{{ $level($k) }}@isset($indicators[$k])<span class="block text-[11px] font-normal text-muted">{{ $indicators[$k]['date'] }} · {{ $indicators[$k]['source'] }}</span>@endisset</dd></div>
                            @endforeach
                        </dl>
                        <p class="mt-3 text-xs text-muted">Indicators come from reviewed evidence with a stated method, not from Meezan Property listing counts.</p>
                    </section>

                    {{-- Legal --}}
                    <section class="card card-body" aria-labelledby="legal">
                        <h2 id="legal" class="text-lg font-bold">Legal verification</h2>
                        @php $legal = $m['legal'] ?? ['status' => 'unknown', 'basis' => 'No record']; @endphp
                        <p class="mt-2 text-sm font-semibold">{{ ucwords(str_replace('_', ' ', $legal['status'])) }}</p>
                        <p class="text-[13px] text-muted">{{ $legal['basis'] }}@if ($legal['date']) · checked {{ $legal['date'] }}@endif</p>
                        @if ($legal['url'])<a href="{{ $legal['url'] }}" class="link text-[13px]" rel="noopener nofollow" target="_blank">Source</a>@endif
                        @if ($legal['status'] !== 'verified')
                            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-900" role="alert">Verification warning: approvals are not fully verified. Check the development authority's records before investing.</p>
                        @endif
                    </section>

                    {{-- Risk --}}
                    <section class="card card-body" aria-labelledby="risk">
                        <h2 id="risk" class="text-lg font-bold">Risk assessment</h2>
                        <div class="mt-2"><x-investment-badge kind="risk" :level="$row->risk" /> @if ($m['risk']['score'] !== null)<span class="ml-1 text-[13px] text-muted">{{ $m['risk']['score'] }}/100 (higher is riskier)</span>@endif</div>
                        @if (! empty($m['risk']['components']))
                            <ul class="mt-3 space-y-1 text-[13px]">@foreach ($m['risk']['components'] as $name => $comp)<li class="flex justify-between gap-2"><span>{{ $comp['basis'] }}</span><span class="tabular-nums text-muted">{{ $comp['risk'] }}</span></li>@endforeach</ul>
                        @endif
                        @if ($m['risk']['level'] === 'insufficient')<p class="mt-2 text-[13px] text-muted">Not enough evidence (at least three risk factors) to classify risk.</p>@endif
                        @foreach ($m['risk']['warnings'] ?? [] as $w)<p class="mt-2 text-[13px] text-amber-800">{{ $w }}</p>@endforeach
                    </section>

                    {{-- Confidence --}}
                    <section class="card card-body" aria-labelledby="confidence">
                        <h2 id="confidence" class="text-lg font-bold">Data confidence</h2>
                        <div class="mt-2"><x-investment-badge kind="confidence" :level="$row->confidence" /> <span class="ml-1 text-[13px] text-muted">{{ $m['confidence']['score'] ?? 0 }}/100</span></div>
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-[13px] text-ink/85">@foreach ($m['confidence']['reasons'] ?? [] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                        <p class="mt-2 text-xs text-muted">A high investment score does not mean high confidence: they are measured separately.</p>
                    </section>

                    {{-- Sources --}}
                    <section class="card card-body" aria-labelledby="sources">
                        <h2 id="sources" class="text-lg font-bold">Sources</h2>
                        <ul class="mt-3 space-y-2 text-[13px]">
                            @forelse ($m['sources'] ?? [] as $src)
                                <li><span class="font-semibold">{{ $src['name'] }}</span> <span class="text-muted">· {{ $src['type'] }} · reliability {{ $src['reliability'] }}@if ($src['last_checked']) · checked {{ $src['last_checked'] }}@endif</span>
                                    @if ($src['url'])<a href="{{ $src['url'] }}" class="link block" rel="noopener nofollow" target="_blank">{{ \Illuminate\Support\Str::limit($src['url'], 60) }}</a>@endif
                                    @if ($src['license'])<span class="block text-muted">Licence: {{ $src['license'] }}</span>@endif</li>
                            @empty
                                <li class="text-muted">No sources recorded.</li>
                            @endforelse
                        </ul>
                    </section>

                    {{-- Score history --}}
                    @if ($history->count() > 1)
                        <section class="card card-body" aria-labelledby="snapshots">
                            <h2 id="snapshots" class="text-lg font-bold">Published history</h2>
                            <ul class="mt-3 space-y-1 text-[13px]">
                                @foreach ($history->reverse() as $h)
                                    <li class="flex justify-between"><span>{{ $h->calculation?->published_at?->localTime()->format('j M Y') }} · v{{ $h->methodology_version }}</span><span class="font-semibold">{{ $h->status === 'ranked' ? number_format($h->score, 1) : 'n/a' }}</span></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <section class="card card-body text-[13px]">
                        <h2 class="text-base font-bold">Methodology</h2>
                        <p class="mt-1 text-muted">Weights, normalisation and data rules are versioned and public.</p>
                        <a href="{{ route('investment.methodology') }}" class="link mt-2 inline-block">How rankings work →</a>
                    </section>
                </div>
            </div>
        @endif
        @include('investment.partials.disclaimer')
    </div>
</x-layouts.app>
