{{-- Future growth scenarios ($scenarios from the snapshot metrics, $name). Never shown without enough verified history. --}}
@php use App\Services\Investment\InvestmentInsights; @endphp
<h3 class="text-base font-bold text-ink">Future Growth Forecast{{ $name ? ' — '.$name : '' }}</h3>
@if (! empty($scenarios['available']))
    <ul class="mt-3 grid gap-2 sm:grid-cols-3">
        @foreach (['conservative' => ['Conservative', 'text-brand-600', 'bg-brand-50'], 'expected' => ['Moderate', 'text-brand-700', 'bg-brand-50'], 'high' => ['Optimistic', 'text-gold-700', 'bg-gold-50']] as $k => [$label, $tone, $bg])
            <li class="rounded-lg {{ $bg }} p-3 text-center">
                <p class="text-[13px] font-semibold {{ $tone }}">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold {{ $tone }} tabular-nums">{{ InvestmentInsights::signed($scenarios['rates'][$k]) }}%</p>
                <p class="text-[11px] text-muted">a year, for {{ $scenarios['years'] }} years → about PKR {{ number_format($scenarios['ranges'][$k]) }}</p>
            </li>
        @endforeach
    </ul>
    <dl class="mt-3 space-y-1 text-[12px]">
        <div><dt class="inline font-semibold">Data basis:</dt> <dd class="inline">{{ ($r ?? null)?->is_demo ? 'illustrative prices (hypothetical, not market data)' : 'verified comparable prices' }}, {{ $scenarios['period'] }}.</dd></div>
        <div><dt class="inline font-semibold">Confidence:</dt> <dd class="inline">{{ ($r ?? null)?->confidenceLabel() ?? 'see data confidence' }}.</dd></div>
        <div><dt class="font-semibold">Assumptions:</dt><dd><ul class="list-disc pl-5">@foreach ($scenarios['assumptions'] as $a)<li>{{ $a }}</li>@endforeach</ul></dd></div>
        @if (($r ?? null)?->is_demo)
            <div class="rounded bg-gold-50 px-2 py-1 font-semibold text-gold-700 ring-1 ring-gold-200">Hypothetical illustration built from illustrative prices. Not a market forecast and not an expected return.</div>
        @endif
        <div><dt class="inline font-semibold">Key risks:</dt> <dd class="inline">past rates may not continue; approvals, supply, interest rates and the wider economy can change prices. Scenarios are not guaranteed returns.</dd></div>
    </dl>
@else
    <p class="mt-2 rounded-lg bg-page p-4 text-[13px] text-muted"><strong class="text-ink">Forecast unavailable — insufficient verified data.</strong> {{ $scenarios['reason'] ?? 'Growth scenarios need at least two years of verified, comparable price history.' }}</p>
@endif
