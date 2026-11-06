@php
    $shown = $published ?? $active;
    $factors = config('meezan.investment.factors');
    $categories = config('meezan.investment.categories');
@endphp
<x-layouts.app title="Market insight score (MPIS) methodology" description="How the Meezan Property market insight score (MPIS) is calculated from verified price and rent records: factors, weights, data confidence, risk, rental yield and growth scenarios." :canonical="route('investment.methodology')">
    <section class="bg-gradient-to-r from-brand-900 to-brand-700">
        <div class="container-page py-10">
            <x-breadcrumbs :items="[['Investment insights', route('investment.index')], ['Methodology', null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-5 text-3xl text-white sm:text-4xl">Market insight score (MPIS) methodology</h1>
            <p class="mt-2 max-w-3xl text-white/85">This page covers the market insight score used on the Investment Insights pages. The project rankings of the Meezan Investment Index follow a separate methodology: <a href="{{ route('rankings.methodology') }}" class="font-semibold text-gold-300 underline">how the Investment Index works</a>.</p>
            <p class="mt-2 max-w-3xl text-white/85">Every number on our investment pages comes from a recorded, reviewed source, or is calculated from those records with the formulas below. When the data is not enough, we show "Insufficient Data" instead of a score.</p>
        </div>
    </section>

    <div class="container-page grid gap-6 py-8 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-6">
            <section class="card card-body prose-listing">
                <h2 class="text-lg font-bold">Data</h2>
                <p class="mt-2 text-sm leading-6">Prices, rents and market indicators are entered or imported by Meezan Property staff with their source, observation date, sample size and method, then submitted, reviewed and verified before they are used. Asking prices, verified transaction prices and professional valuations are always labelled and never mixed in one comparison. Meezan Property's own listing counts are shown as platform figures, not as market supply or demand.</p>
                <h2 class="mt-5 text-lg font-bold">Formulas</h2>
                <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm">
                    <li>Price change = ((current price − historical price) ÷ historical price) × 100, using one comparable series (same property type, size, currency and price basis). One-year compares with an observation 9–15 months earlier, two-year with one 21–27 months earlier.</li>
                    <li>Estimated gross rental yield = monthly rent × 12 ÷ property value × 100. Net yield is calculated only when costs are known, and shown separately.</li>
                    <li>Investment score (0–100) = weighted average of the factors that have data, reduced by up to {{ ($shown?->parameter('coverage_penalty') ?? 0.15) * 100 }}% for weight without data. At least {{ ($shown?->parameter('min_coverage') ?? 0.7) * 100 }}% of a ranking's weight needs data, or it is not ranked.</li>
                    <li>Data confidence (separate from the score) combines the number of observations, how recent they are, source reliability, source independence, consistency and factor coverage.</li>
                    <li>Risk (Low / Medium / High) averages the available evidence on volatility, development status, legal verification, oversupply, liquidity and infrastructure; at least three are needed. Anything not fully legally verified is never shown as Low risk.</li>
                    <li>Growth scenarios repeat historical annual rates (weakest, average, strongest) over a stated horizon. They need at least two years of comparable history and are not forecasts.</li>
                </ul>
            </section>

            @if ($shown)
                <section class="card card-body">
                    <h2 class="text-lg font-bold">Weights · methodology v{{ $shown->version }}</h2>
                    <p class="mt-1 text-[13px] text-muted">{{ $shown->name }}@if ($shown->notes) — {{ $shown->notes }}@endif</p>
                    <div class="mt-4 relative overflow-x-auto">
                        <table class="w-full min-w-[700px] text-left text-[13px]">
                            <thead><tr><th class="py-2 font-medium text-muted">Factor</th>@foreach ($categories as $c)<th class="py-2 font-medium">{{ str_replace('Best ', '', $c['label']) }}</th>@endforeach</tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($factors as $key => $flabel)
                                    <tr><th scope="row" class="py-2 pr-2 font-medium">{{ $flabel }}</th>@foreach ($categories as $ckey => $c)<td class="py-2 tabular-nums">{{ $shown->weightsFor($ckey)[$key] ?? 0 }}%</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-muted">These weights are the current methodology, not permanent rules. Each published ranking keeps the version it was calculated with.</p>
                </section>
            @endif
        </div>
        <aside class="min-w-0 space-y-6">
            <section class="card card-body text-sm">
                <h2 class="text-base font-bold">Markets</h2>
                <ul class="mt-2 space-y-1.5">
                    @foreach (config('meezan.investment.markets') as $country => $mk)
                        <li class="flex justify-between gap-2"><span>{{ $country }} <span class="text-muted">({{ $mk['currency'] }})</span></span><span @class(['font-semibold', 'text-emerald-700' => $mk['status'] === 'active', 'text-muted' => $mk['status'] !== 'active'])>{{ $mk['status'] === 'active' ? 'Active' : 'Planned — no data yet' }}</span></li>
                    @endforeach
                </ul>
            </section>
            <section class="card card-body text-sm">
                <h2 class="text-base font-bold">Labels you will see</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-[13px]">
                    <li><strong>Historical</strong>: recorded observations.</li>
                    <li><strong>Asking price</strong> / <strong>transaction price</strong>: never mixed.</li>
                    <li><strong>Estimate</strong>: calculated, e.g. gross yield.</li>
                    <li><strong>Scenario</strong>: forward-looking illustration, not a forecast.</li>
                    <li><strong>Interpretation</strong>: automated or AI wording based only on the data shown.</li>
                </ul>
            </section>
        </aside>
    </div>
    <div class="container-page pb-4">@include('investment.partials.disclaimer')</div>
</x-layouts.app>
