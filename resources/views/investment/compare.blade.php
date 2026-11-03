@php
    use App\Services\Investment\InvestmentInsights;
    $label = config('meezan.investment.categories.'.$category.'.label');
    $win = fn (string $metric, $r) => ($winners[$metric] ?? null) === $r->entity_type.':'.$r->entity_id;
@endphp
<x-layouts.app title="Compare investments" description="Compare investment scores, data confidence, historical performance, rental yield and risk side by side." robots="noindex, follow">
    <section class="bg-gradient-to-r from-brand-900 to-brand-700">
        <div class="container-page py-10">
            <x-breadcrumbs :items="[['Investment insights', route('investment.index')], ['Compare', null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-5 text-3xl text-white sm:text-4xl">Compare investments</h1>
            <p class="mt-2 text-white/85">{{ $label }} · {{ ucfirst($asset) }}. Winners are highlighted only when every entry has comparable data.</p>
        </div>
    </section>

    <div class="container-page py-8">
        @if (! $calc)
            <x-empty-state icon="chart" title="No published rankings yet">There is nothing to compare until verified data has been published.</x-empty-state>
        @else
            <form method="GET" class="card card-body" aria-label="Choose entries to compare">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="category" type="select" label="Ranking" :value="$category" :options="collect(config('meezan.investment.categories'))->map(fn ($c) => $c['label'])->all()" />
                    <x-field name="asset" type="select" label="Asset category" :value="$asset" :options="['residential' => 'Residential', 'commercial' => 'Commercial']" />
                </div>
                <fieldset class="mt-4">
                    <legend class="text-sm font-semibold">Entries (choose 2 to 4)</legend>
                    <div class="mt-2 grid max-h-64 gap-1.5 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($candidates as $c)
                            @php $key = $c->entity_type.':'.$c->entity_id; @endphp
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" name="items[]" value="{{ $key }}" @checked($items->contains($key))> {{ $c->entity_name }} <span class="text-muted">· {{ $c->city }}</span></label>
                        @empty
                            <p class="text-sm text-muted">No ranked entries in this category.</p>
                        @endforelse
                    </div>
                </fieldset>
                <button class="btn btn-primary mt-4">Compare</button>
            </form>

            @if ($rows->count() >= 2)
                <div class="card mt-6 relative overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-line">
                            <tr><th class="px-4 py-3 text-[13px] font-medium text-muted">Metric</th>
                                @foreach ($rows as $r)<th class="px-4 py-3"><a href="{{ route('investment.show', [$r->entity_type, $r->entity_id, 'category' => $category, 'asset' => $asset]) }}" class="font-bold hover:text-brand-700">{{ $r->entity_name }}</a><span class="block text-xs font-normal text-muted">{{ $r->entityTypeLabel() }} · {{ $r->city }}</span></th>@endforeach</tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @php
                                $metrics = [
                                    ['Investment score', 'score', fn ($r) => $r->isRanked() ? number_format($r->score, 1).'/100' : 'Not ranked'],
                                    ['Data confidence', null, fn ($r) => $r->confidenceLabel()],
                                    ['One-year change', 'growth', fn ($r) => ($c = $r->metric('change_1y')) ? InvestmentInsights::signed($c['percent']).'%' : 'Insufficient Historical Data'],
                                    ['Two-year change', null, fn ($r) => ($c = $r->metric('change_2y')) ? InvestmentInsights::signed($c['percent']).'%' : 'Insufficient Historical Data'],
                                    ['Est. gross yield', 'yield', fn ($r) => $r->metric('yield.gross') !== null ? $r->metric('yield.gross').'%' : 'No data'],
                                    ['Demand', null, fn ($r) => $r->metric('indicators.demand.display') ?? 'Insufficient Data'],
                                    ['Supply', null, fn ($r) => $r->metric('indicators.supply.display') ?? 'Insufficient Data'],
                                    ['Liquidity', null, fn ($r) => $r->metric('indicators.liquidity.display') ?? 'Insufficient Data'],
                                    ['Risk', 'risk', fn ($r) => $r->riskLabel().($r->metric('risk.score') !== null ? ' ('.$r->metric('risk.score').')' : '')],
                                    ['Development progress', null, fn ($r) => $r->metric('indicators.development_progress.display') ?? 'Insufficient Data'],
                                    ['Legal verification', null, fn ($r) => ucwords(str_replace('_', ' ', $r->metric('legal.status', 'unknown')))],
                                ];
                            @endphp
                            @foreach ($metrics as [$name, $winKey, $value])
                                <tr>
                                    <th scope="row" class="px-4 py-2.5 text-[13px] font-medium text-muted">{{ $name }}</th>
                                    @foreach ($rows as $r)
                                        <td @class(['px-4 py-2.5', 'bg-gold-50 font-bold' => $winKey && $win($winKey, $r)])>
                                            {{ $value($r) }} @if ($winKey && $win($winKey, $r))<span class="ml-1 rounded bg-gold-500 px-1.5 py-0.5 text-[10px] font-bold text-brand-900 uppercase">{{ ['score' => 'Highest', 'growth' => 'Highest growth', 'yield' => 'Best yield', 'risk' => 'Lowest risk'][$winKey] }}</span>@endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (empty(array_filter($winners)))
                    <p class="mt-3 text-[13px] text-muted">No winners are highlighted: at least one entry lacks comparable data or has insufficient confidence.</p>
                @endif
            @elseif ($items->isNotEmpty())
                <p class="mt-4 text-sm text-muted">Choose at least two entries that are published in this ranking.</p>
            @endif
        @endif
        @include('investment.partials.disclaimer')
    </div>
</x-layouts.app>
