{{-- Investment overview counts for a city or Pakistan ($overview from RankingBrowser::overview, $place). All figures are live counts. --}}
@php
    $cards = [
        ['Published listings for sale and rent', $overview['listings'], 'home', 'Real listings only; sample listings excluded.'],
        ['Residential societies & schemes', $overview['societies'], 'map-pin', 'From the location directory.'],
        ['Commercial projects', $overview['commercial_projects'], 'building', 'Malls, plazas, towers and markets published by staff.'],
        ['Plots listed', $overview['plots'], 'plot', null],
        ['Houses & apartments listed', $overview['homes'], 'home', null],
        ['Shopping malls', $overview['shopping_malls'], 'store', null],
        ['Commercial plazas', $overview['commercial_plazas'], 'building', null],
        ['Business towers & offices', $overview['business_towers'], 'briefcase', null],
        ['New & upcoming projects', $overview['upcoming'], 'star', null],
        ['Verified market records', $overview['verified_observations'] + $overview['verified_indicators'], 'shield', 'Price, rent and indicator records that passed review.'],
        ...(($overview['demo_records'] ?? 0) > 0 ? [['Illustrative records', $overview['demo_records'], 'info', 'Not market data; shown for platform preview.']] : []),
    ];
@endphp
<section class="card overflow-hidden" aria-labelledby="overview-title">
    <div class="border-b border-line px-4 py-3"><h2 id="overview-title" class="text-base font-bold">Investment overview — {{ $place }}</h2></div>
    <dl class="grid grid-cols-2 gap-px bg-line sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($cards as [$label, $value, $icon, $note])
            <div class="flex gap-2.5 bg-white p-3">
                <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0 text-brand-600" />
                <div class="min-w-0"><dd class="text-xl font-bold tabular-nums">{{ number_format($value) }}</dd><dt class="text-[12px] leading-tight text-muted">{{ $label }}</dt>@if ($note)<dd class="mt-0.5 text-[11px] text-muted/80">{{ $note }}</dd>@endif</div>
            </div>
        @endforeach
    </dl>
    <div class="grid gap-px border-t border-line bg-line sm:grid-cols-2">
        @php $demoData = ($overview['demo_records'] ?? 0) > 0; @endphp
        @foreach (['highest_yield' => [$demoData ? 'Highest rental yield (illustrative data)' : 'Highest verified rental yield', fn ($r) => $r->metric('yield.gross').'% gross'], 'fastest_growing' => [$demoData ? 'Fastest growing (illustrative data)' : 'Fastest growing (verified history)', fn ($r) => \App\Services\Investment\InvestmentInsights::signed($r->metric('annualised')).'% a year']] as $key => [$label, $value])
            <div class="bg-white p-4">
                <h3 class="text-[13px] font-bold">{{ $label }}</h3>
                @if ($overview[$key]->isEmpty())
                    <p class="mt-1 text-[12px] text-muted">Not available: needs verified {{ $key === 'highest_yield' ? 'rent and sale records' : 'price history' }}.</p>
                @else
                    <ol class="mt-1 space-y-1 text-[13px]">@foreach ($overview[$key] as $r)<li class="flex justify-between gap-2"><a href="{{ route('investment.show', [$r->entity_type, $r->entity_id]) }}" class="link">{{ $r->entity_name }}</a><span class="font-semibold">{{ $value($r) }}</span></li>@endforeach</ol>
                @endif
            </div>
        @endforeach
    </div>
</section>
