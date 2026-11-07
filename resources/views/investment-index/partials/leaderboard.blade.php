{{-- Professional Institutional Leaderboard Table --}}
@php
    use App\Services\InvestmentIndex\IndexBrowser;
    $compact ??= false;
@endphp

<div class="relative overflow-x-auto rounded-lg border border-line">
    <table class="table min-w-[950px] divide-y divide-line">
        <thead class="bg-page/80">
            <tr>
                <th scope="col" class="w-14 py-3.5 pl-4 text-xs font-bold uppercase tracking-wider text-muted">Rank</th>
                <th scope="col" class="py-3.5 text-xs font-bold uppercase tracking-wider text-muted">Project & Developer</th>
                <th scope="col" class="py-3.5 text-right text-xs font-bold uppercase tracking-wider text-muted">Investment Score</th>
                <th scope="col" class="py-3.5 text-right text-xs font-bold uppercase tracking-wider text-muted">Data Confidence</th>
                <th scope="col" class="py-3.5 text-xs font-bold uppercase tracking-wider text-muted">Legal Status</th>
                <th scope="col" class="py-3.5 text-xs font-bold uppercase tracking-wider text-muted">Development</th>
                <th scope="col" class="py-3.5 text-xs font-bold uppercase tracking-wider text-muted">Risk</th>
                <th scope="col" class="py-3.5 text-right text-xs font-bold uppercase tracking-wider text-muted">Evidence Depth</th>
                @unless ($compact)
                    <th scope="col" class="py-3.5 text-right text-xs font-bold uppercase tracking-wider text-muted">Verified Yield</th>
                    <th scope="col" class="py-3.5 text-xs font-bold uppercase tracking-wider text-muted">Last Verified</th>
                @endunless
                <th scope="col" class="py-3.5 pr-4 text-right text-xs font-bold uppercase tracking-wider text-muted">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line bg-white">
            @foreach ($rows as $idx => $s)
                @php
                    $isTop1 = $s->category_rank === 1;
                    $isTop2 = $s->category_rank === 2;
                    $isTop3 = $s->category_rank === 3;
                    $rowBg = $isTop1 ? 'bg-amber-50/20' : ($isTop2 ? 'bg-slate-50/40' : ($isTop3 ? 'bg-amber-50/10' : ''));
                @endphp
                <tr>
                    {{-- Rank Column --}}
                    <td class="pl-4 py-3.5 {{ $rowBg }}">
                        <span @class([
                            'grid size-8 place-items-center rounded-full text-xs font-bold shadow-sm',
                            'bg-gradient-to-br from-amber-300 to-amber-500 text-brand-950 font-black ring-2 ring-amber-300/60' => $isTop1,
                            'bg-gradient-to-br from-slate-200 to-slate-400 text-slate-900 font-black ring-2 ring-slate-300/60' => $isTop2,
                            'bg-gradient-to-br from-amber-100 to-amber-300 text-amber-950 font-bold border border-amber-300' => $isTop3,
                            'bg-brand-50 text-brand-700 font-semibold' => ! $isTop1 && ! $isTop2 && ! $isTop3,
                        ])>
                            #{{ $s->category_rank }}
                        </span>
                    </td>

                    {{-- Project & Location --}}
                    <td class="py-3.5 min-w-[200px]">
                        <a href="{{ route('rankings.project', $s->project) }}" class="font-bold text-sm text-ink hover:text-brand-600 block">
                            {{ $s->project->name }}
                        </a>
                        <span class="text-xs text-muted block mt-0.5">
                            {{ collect([$s->project->area?->name, $s->project->city])->filter()->unique()->implode(', ') }}
                            @if ($s->project->developer) · <span class="font-medium text-ink/80">{{ $s->project->developer }}</span> @endif
                        </span>
                    </td>

                    {{-- Investment Score --}}
                    <td class="py-3.5 text-right">
                        <span @class([
                            'inline-flex items-center rounded-md px-2.5 py-1 text-xs font-extrabold tabular-nums shadow-sm',
                            'bg-emerald-100 text-emerald-900 border border-emerald-300' => $s->score >= 90,
                            'bg-brand-100 text-brand-900 border border-brand-300' => $s->score >= 80 && $s->score < 90,
                            'bg-amber-100 text-amber-900 border border-amber-300' => $s->score < 80,
                        ])>
                            {{ number_format($s->score, 1) }}
                        </span>
                        <span class="block text-[10px] text-muted">/100</span>
                    </td>

                    {{-- Data Confidence --}}
                    <td class="py-3.5 text-right">
                        <span class="text-xs font-bold tabular-nums text-ink">{{ number_format($s->confidence_score, 0) }}</span>
                        <span class="block text-[10px] font-medium text-muted">{{ ucfirst($s->confidence_level) }}</span>
                    </td>

                    {{-- Legal Status Badge --}}
                    <td class="py-3.5">
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11.5px] font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                            {{ IndexBrowser::legalLabel($s->legal_status) }}
                        </span>
                    </td>

                    {{-- Development Status --}}
                    <td class="py-3.5">
                        <span class="inline-flex items-center rounded-md bg-page px-2 py-0.5 text-[11.5px] font-medium text-ink/80 ring-1 ring-line">
                            {{ $s->project->statusLabel() }}
                        </span>
                    </td>

                    {{-- Risk Badge --}}
                    <td class="py-3.5">
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                            'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' => $s->risk_class === 'low',
                            'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20' => $s->risk_class === 'moderate',
                            'bg-orange-50 text-orange-700 ring-1 ring-orange-600/20' => $s->risk_class === 'elevated',
                            'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20' => $s->risk_class === 'excluded',
                        ])>
                            {{ $s->riskLabel() }}
                        </span>
                    </td>

                    {{-- Evidence Coverage Progress Bar --}}
                    <td class="py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <div class="w-16 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-brand-600 h-1.5 rounded-full" style="width: {{ min(round($s->coverage * 100, 0), 100) }}%"></div>
                            </div>
                            <span class="text-xs font-semibold tabular-nums text-muted">{{ round($s->coverage * 100, 0) }}%</span>
                        </div>
                    </td>

                    @unless ($compact)
                        {{-- Verified Yield --}}
                        <td class="py-3.5 text-right text-xs font-bold tabular-nums text-ink">
                            {{ $s->verified_yield !== null ? number_format($s->verified_yield, 2).'%' : '—' }}
                        </td>

                        {{-- Last Verified --}}
                        <td class="py-3.5 text-xs text-muted whitespace-nowrap">
                            {{ $s->last_verified_at?->localTime()->format('j M Y') ?? '—' }}
                        </td>
                    @endunless

                    {{-- Action --}}
                    <td class="py-3.5 pr-4 text-right">
                        <a href="{{ route('rankings.project', $s->project) }}" class="btn btn-secondary btn-sm shadow-sm hover:border-brand-400">
                            View Project
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
