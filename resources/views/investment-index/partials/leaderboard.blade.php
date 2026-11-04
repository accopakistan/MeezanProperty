{{-- Ranked projects of one category. $rows: InvestmentIndexScore collection (with project). $compact: fewer columns. --}}
@php $compact ??= false; @endphp
<div class="relative overflow-x-auto">
    <table class="table table-compact {{ $compact ? 'min-w-[520px]' : 'min-w-[860px]' }}">
        <thead>
            <tr>
                <th scope="col" class="w-12">Rank</th>
                <th scope="col">Project</th>
                <th scope="col" class="text-right">Investment score</th>
                <th scope="col" class="text-right">Data confidence</th>
                <th scope="col">Risk</th>
                @unless ($compact)
                    <th scope="col">Legal status</th>
                    <th scope="col" class="text-right">Verified yield</th>
                    <th scope="col">Last verified</th>
                @endunless
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $s)
                <tr>
                    <td class="text-lg font-bold tabular-nums text-brand-700">{{ $s->category_rank }}</td>
                    <td class="min-w-0">
                        <a href="{{ route('rankings.project', $s->project) }}" class="link font-semibold">{{ $s->project->name }}</a>
                        <span class="block text-[12px] text-muted">{{ $s->project->city }}@if ($s->project->developer) · {{ $s->project->developer }}@endif</span>
                    </td>
                    <td class="text-right text-base font-bold tabular-nums">{{ number_format($s->score, 1) }}</td>
                    <td class="text-right tabular-nums">{{ number_format($s->confidence_score, 0) }} <span class="block text-[11px] text-muted">{{ ucfirst($s->confidence_level) }}</span></td>
                    <td class="text-[13px]">{{ $s->riskLabel() }}</td>
                    @unless ($compact)
                        <td class="text-[13px]">{{ config('investment_index.legal.statuses.'.$s->legal_status, $s->legal_status) }}</td>
                        <td class="text-right tabular-nums text-[13px]">{{ $s->verified_yield !== null ? number_format($s->verified_yield, 2).'%' : '—' }}</td>
                        <td class="text-[13px] text-muted">{{ $s->last_verified_at?->localTime()->format('j M Y') ?? '—' }}</td>
                    @endunless
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
