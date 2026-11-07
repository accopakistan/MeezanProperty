<x-layouts.dashboard title="Evidence Gap Analysis" section="admin" heading="Investment Evidence Gap Analysis" subheading="Pinpoints missing evaluation factors for each development and identifies the primary verified sources needed to bridge the 60% ranking eligibility threshold.">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-line pb-4">
        {{-- Category & City Selectors --}}
        <form method="GET" action="{{ route('admin.investment-index.evidence-gaps') }}" class="flex flex-wrap items-center gap-3">
            <div>
                <select name="category" onchange="this.form.submit()" class="input text-sm h-10">
                    @foreach ($categories as $key => $cfg)
                        <option value="{{ $key }}" @selected($categoryKey === $key)>{{ $cfg['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="city" onchange="this.form.submit()" class="input text-sm h-10">
                    <option value="">All Cities</option>
                    @foreach ($cities as $c)
                        <option value="{{ $c }}" @selected($city === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-secondary btn-sm">Filter</button></noscript>
        </form>

        <div class="flex items-center gap-2 text-xs text-muted">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Verified (&ge;60%)
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500 ml-2"></span> Gap to Bridge (&lt;60%)
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500 ml-2"></span> Excluded (Legal)
        </div>
    </div>

    {{-- Methodology Requirement & Source Matrix Banner --}}
    <section class="card p-5 mb-6">
        <h2 class="text-base font-bold text-ink">Evaluation Weight Matrix & Verified Source Acquisition Channels</h2>
        <p class="mt-1 text-xs text-muted">Under methodology version 1, each category requires verified, non-demo evidence across &ge;60% of evaluation weight before an investment score or ranking can be published.</p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($sourceRecommendations as $fKey => $rec)
                <div class="rounded-lg border border-line bg-surface p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-ink">{{ $rec['label'] }}</span>
                        <span class="tag bg-brand-900 text-white text-[11px] font-bold">{{ $rec['weight'] }}%</span>
                    </div>
                    <p class="mt-2 text-xs text-muted leading-relaxed">
                        <strong class="text-ink">Required Source:</strong> {{ $rec['action'] }}
                    </p>
                    <div class="mt-2 text-[11px] text-brand-800">
                        Accepted types: {{ implode(', ', $rec['sources']) }}
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Projects Evidence Gap Table --}}
    <section class="card overflow-hidden">
        <div class="p-4 border-b border-line flex items-center justify-between">
            <h2 class="font-bold text-sm text-ink">Project Evidence Gap Register ({{ $scores->total() }} Developments)</h2>
            <span class="text-xs text-muted">Showing {{ $scores->firstItem() ?? 0 }} to {{ $scores->lastItem() ?? 0 }}</span>
        </div>

        <div class="table-container">
            <table class="table w-full text-xs">
                <thead>
                    <tr class="border-b border-line bg-surface text-left text-muted">
                        <th class="p-3">Project / Developer</th>
                        <th class="p-3">City</th>
                        <th class="p-3">Legal Status</th>
                        <th class="p-3 text-right">Current Coverage</th>
                        <th class="p-3 text-right">Gap to 60%</th>
                        <th class="p-3">Missing Evaluation Factors</th>
                        <th class="p-3">Recommended Acquisition Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line/60">
                    @forelse ($scores as $s)
                        @php
                            $project = $s->project;
                            $cov = (float) ($s->coverage ?? 0);
                            $gap = max(0, 0.6 - $cov);
                            $missingList = collect($s->factors ?? [])
                                ->reject(fn($f) => $f['display_only'] ?? false)
                                ->filter(fn($f) => ($f['score'] ?? null) === null)
                                ->keys();
                        @endphp
                        <tr class="hover:bg-surface/50">
                            <td class="p-3">
                                <a href="{{ route('rankings.project', $project) }}" class="font-bold text-ink hover:text-brand-700" target="_blank">{{ $project->name }}</a>
                                @if ($project->developer)
                                    <span class="block text-[11px] text-muted">{{ $project->developer }}</span>
                                @endif
                            </td>
                            <td class="p-3">{{ $project->city }}</td>
                            <td class="p-3">
                                <span class="tag {{ $s->legal_status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($s->legal_status === 'partially_approved' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }} text-[11px]">
                                    {{ ucfirst(str_replace('_', ' ', $s->legal_status)) }}
                                </span>
                            </td>
                            <td class="p-3 text-right font-bold tabular-nums {{ $cov >= 0.6 ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ number_format($cov * 100, 0) }}%
                            </td>
                            <td class="p-3 text-right tabular-nums text-muted font-medium">
                                @if ($gap > 0)
                                    <span class="text-amber-800 font-semibold">+{{ number_format($gap * 100, 0) }}% needed</span>
                                @else
                                    <span class="text-emerald-700 font-semibold">Eligible</span>
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($missingList as $mf)
                                        <span class="rounded bg-line/60 px-1.5 py-0.5 text-[10px] text-ink font-medium">
                                            {{ $sourceRecommendations[$mf]['label'] ?? $mf }} ({{ $sourceRecommendations[$mf]['weight'] ?? '' }}%)
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="p-3 text-muted text-[11px]">
                                @if ($missingList->contains('development_progress'))
                                    Site inspection survey (+20%)
                                @elseif ($missingList->contains('occupancy'))
                                    Resident census roll (+15%)
                                @elseif ($missingList->contains('rental_income'))
                                    Excise rental deed audit (+15-20%)
                                @else
                                    Primary document collection
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-muted">No developments found for the selected filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-line">
            {{ $scores->links() }}
        </div>
    </section>
</x-layouts.dashboard>
