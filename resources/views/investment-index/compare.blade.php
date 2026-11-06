@php
    $legal = fn ($s) => $s ? \App\Services\InvestmentIndex\IndexBrowser::legalLabel($s->legal_status) : 'Unknown / Unverified';
    $factor = fn ($s, $f) => $s->factors[$f]['value'] ?? 'Insufficient Verified Data';
    $metrics = [
        'Index status' => fn ($s, $p) => $s?->statusLabel() ?? 'Insufficient Verified Data',
        'Legal status' => fn ($s, $p) => $legal($s),
        'Investment score' => fn ($s, $p) => $s?->status === 'ranked' ? number_format($s->score, 1) : 'Not eligible',
        'Rank in this category' => fn ($s, $p) => ($r = \App\Services\InvestmentIndex\IndexBrowser::publicRank($s)) ? '#'.$r : ($s?->status === 'ranked' ? 'Outside the top '.\App\Services\InvestmentIndex\IndexBrowser::publicTop() : '—'),
        'Data confidence' => fn ($s, $p) => $s ? number_format($s->confidence_score, 0).' ('.ucfirst($s->confidence_level).')' : '—',
        'Risk' => fn ($s, $p) => $s?->riskLabel() ?? '—',
        'Development progress' => fn ($s, $p) => $s?->development_progress !== null ? number_format($s->development_progress, 0).'%' : 'Insufficient Verified Data',
        'Operational status' => fn ($s, $p) => $s?->operational_status ? config('investment_index.context_evidence.operational_status.levels.'.$s->operational_status, $s->operational_status) : 'No verified record',
        'Verified rental yield' => fn ($s, $p) => $s?->verified_yield !== null ? number_format($s->verified_yield, 2).'% gross' : 'Insufficient Verified Data',
        'Occupancy' => fn ($s, $p) => $s ? $factor($s, 'occupancy') : 'Insufficient Verified Data',
        'Historical performance' => fn ($s, $p) => $s && ($s->factors['market_performance']['value'] ?? null) ? $s->factors['market_performance']['value'] : 'Historical performance data unavailable.',
        'Last verified' => fn ($s, $p) => $s?->last_verified_at?->localTime()->format('j M Y') ?? 'Never',
    ];
@endphp
<x-layouts.app :title="'Compare '.$category['label'].' — Meezan Investment Index'" description="Side-by-side comparison of verified evidence for developments in one category." :noindex="true">
    <div class="container-page py-8">
        <x-breadcrumbs :items="[['Investment Rankings', route('rankings.index')], [$category['label'], route('rankings.category', $key)], ['Compare', null]]" />
        <h1 class="mt-4 text-2xl font-bold">Compare {{ mb_strtolower($category['label']) }}</h1>
        <p class="mt-1 max-w-3xl text-sm text-muted">Only developments of one category can be compared, because different kinds of asset aren't comparable by the same figures. Everything comes from the same published run. What isn't verified stays marked as missing.</p>

        @if ($rows->isEmpty())
            <p class="card mt-5 p-5 text-[14px] text-muted">Tick up to four developments on the <a class="link" href="{{ route('rankings.category', $key) }}">{{ mb_strtolower($category['label']) }} page</a> to compare them here.</p>
        @else
            <div class="card relative mt-5 overflow-x-auto p-0">
                <table class="table table-compact min-w-[720px]">
                    <thead>
                        <tr><th scope="col">Metric</th>@foreach ($rows as $r)<th scope="col"><a href="{{ route('rankings.project', $r['project']) }}" class="link">{{ $r['project']->name }}</a><span class="block text-[12px] font-normal normal-case text-muted">{{ $r['project']->city }}</span></th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach ($metrics as $label => $value)
                            <tr><th scope="row" class="font-semibold">{{ $label }}</th>@foreach ($rows as $r)<td class="text-[14px]">{{ $value($r['score'], $r['project']) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="mt-6 text-[13px] text-muted">{{ config('meezan.investment.disclaimer') }}</p>
    </div>
</x-layouts.app>
