@php
    use App\Services\Investment\InvestmentInsights;
    $place = $city ?? 'Pakistan';
    $pct = fn ($v) => $v === null ? 'Insufficient data' : InvestmentInsights::signed($v).'%';
    $metrics = [
        'Current price' => fn ($i) => $i['price'] ? $i['price']['label'].' ('.strtolower($i['price']['basis_label']).', '.$i['price']['date'].')' : 'No verified price',
        'Price per sq ft' => fn ($i) => ($i['price']['per_sqft'] ?? null) ? 'PKR '.number_format($i['price']['per_sqft']) : '—',
        '1-year change' => fn ($i) => $pct($i['change_1y']),
        '2-year change' => fn ($i) => $pct($i['change_2y']),
        '5-year change' => fn ($i) => $pct($i['change_5y']),
        'Rental yield (gross)' => fn ($i) => $i['yield'] !== null ? $i['yield'].'%' : 'No verified data',
        'MPIS score' => fn ($i) => $i['score'] !== null ? $i['score'].'/100'.($i['status'] === 'provisional' ? ' (provisional)' : '') : $i['status_label'],
        'Investment risk' => fn ($i) => $i['risk_label'],
        'Liquidity' => fn ($i) => $i['row']?->metric('indicators.liquidity.display') ?? 'No verified indicator',
        'Development status' => fn ($i) => $i['stage_label'] ?? ($i['development'] !== null ? $i['development'].'% developed' : 'No record'),
        'Data confidence' => fn ($i) => $i['confidence_label'],
        'Methodology' => fn ($i) => $i['row'] ? 'v'.$i['row']->methodology_version : '—',
    ];
    $winnerKey = ['MPIS score' => 'score', 'Rental yield (gross)' => 'yield', '1-year change' => 'change_1y', 'Investment risk' => 'risk'];
@endphp
<x-layouts.app :title="'Compare '.$seg['label'].' in '.$place" description="Side-by-side comparison of verified investment data." robots="noindex, follow">
    <div class="container-page py-8">
        <x-breadcrumbs :items="[['Investment Rankings', route('rankings.index')], [$place, route('rankings.city', $citySlug)], [$seg['label'], route('rankings.segment', [$citySlug, $group, $segmentKey])], ['Compare', null]]" />
        @include('investment.partials.demo-notice', ['class' => 'mt-4'])
        @unless ($calc)
            <div class="mt-4">@include('rankings.partials.insufficient', ['place' => $place])</div>
        @endunless
        <h1 class="mt-4 text-2xl font-bold">Compare {{ $seg['label'] }} in {{ $place }}</h1>
        <p class="mt-1 max-w-3xl text-sm text-muted">Every figure comes from the same published snapshot and methodology version. A metric is highlighted only when every entry has it.</p>
        @if ($mixedSizes)
            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[13px] text-amber-900" role="status">These entries are different sizes or bedroom counts, so prices are not directly comparable; compare scores, changes and yields instead.</p>
        @endif

        @if ($rows->isEmpty())
            <p class="card mt-6 p-6 text-sm text-muted">Choose up to four entries below to compare.</p>
        @else
            <div class="card relative mt-6 overflow-x-auto">
                <table class="table">
                    <thead><tr><th scope="col">Metric</th>@foreach ($rows as $i)<th scope="col" class="min-w-44 normal-case"><span class="text-[13px] text-ink">{{ $i['name'] }}</span><span class="block font-normal">{{ $i['location'] }}@if ($i['group_label']) · {{ $i['group_label'] }}@endif</span></th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($metrics as $label => $value)
                            <tr>
                                <th scope="row" class="text-[13px] font-semibold">{{ $label }}</th>
                                @foreach ($rows as $i)
                                    @php $win = isset($winnerKey[$label]) && ($winners[$winnerKey[$label]] ?? null) === $i['key']; @endphp
                                    <td @class(['text-[13px]', 'bg-emerald-50 font-semibold text-emerald-800' => $win])>{{ $value($i) }}@if ($win) <span class="sr-only">(best)</span>@endif</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="GET" class="card mt-6 p-4">
            <h2 class="text-base font-bold">Entries to compare (up to 4)</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rows->concat($candidates) as $i)
                    <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="items[]" value="{{ $i['key'] }}" class="checkbox" @checked($keys->contains($i['key']))>
                        <span>{{ $i['name'] }}<span class="text-muted"> · {{ $i['location'] }}@if ($i['group_label']) · {{ $i['group_label'] }}@endif · {{ $i['status_label'] }}</span></span></label>
                @endforeach
            </div>
            <button class="btn btn-primary mt-4">Compare</button>
        </form>
        <div class="mt-6">@include('investment.partials.disclaimer')</div>
    </div>
</x-layouts.app>
