@php
    use App\Services\Investment\RankingBrowser;
    use App\Services\InvestmentIndex\IndexBrowser;
    $title = 'Investment Rankings & Real Estate Analytics in '.$city;
    $kpis = $cityAnalytics['kpis'] ?? [];
    $charts = $cityAnalytics['charts'] ?? [];
@endphp

<x-layouts.app :title="$title"
               :description="'Housing societies, apartment buildings, shopping malls, commercial plazas and business towers in '.$city.', compared by verified statutory approvals, empirical transaction records and the Meezan Investment Index.'"
               :canonical="route('rankings.city', $citySlug)"
               :robots="$indexable && $tab === 'all' ? null : 'noindex, follow'">

    {{-- PART 3-A: CITY HEADER & HERO --}}
    <x-investment-hero
        :title="'Meezan Investment Index in '.$city"
        :subtitle="'Factual evaluation of housing schemes, commercial complexes, apartment developments, and business towers in '.$city.'. Scored deterministically from municipal records, development authority NOCs, and verified transactions.'"
        :breadcrumbs="[['Investment Rankings', route('rankings.index')], [$city, null]]"
        :city="$city"
        :citySlug="$citySlug"
        :run="$run"
        :kpis="$kpis"
        :badge="'Smart Real Estate Investments in '.$city"
        :search-group="$segmentKey ? 'commercial' : null"
    />

    {{-- Main Content --}}
    <div class="container-page py-8" data-rankings-page>
        @include('investment.partials.demo-notice')

        {{-- PART 3-B: CITY KPI CARDS --}}
        <section aria-labelledby="city-kpi-heading" class="mb-10">
            <h2 id="city-kpi-heading" class="text-xs font-semibold tracking-wider text-muted uppercase mb-3">{{ $city }} Database Coverage</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <div class="card p-4 border-l-4 border-l-brand-600">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Total Researched</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-ink">{{ number_format($kpis['total_projects'] ?? 0) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">{{ $city }} schemes on record</p>
                </div>
                <div class="card p-4 border-l-4 border-l-gold-500 bg-gold-50/20">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Ranked Projects</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-brand-800">{{ number_format($kpis['ranked_projects'] ?? 0) }}</dd>
                    <p class="mt-1 text-[11px] text-gold-700 font-semibold">&ge; 60% Evidence threshold</p>
                </div>
                <div class="card p-4 border-l-4 border-l-emerald-600">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Verified Legal Files</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">{{ number_format($kpis['verified_projects'] ?? 0) }}</dd>
                    <p class="mt-1 text-[11px] text-emerald-600 font-medium">Authority approved</p>
                </div>
                <div class="card p-4 border-l-4 border-l-amber-500">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Under Research</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-amber-700">{{ number_format($kpis['under_research'] ?? 0) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">Awaiting data intake</p>
                </div>
                <div class="card p-4 border-l-4 border-l-sky-500">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Awaiting Legal Check</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-sky-700">{{ number_format($kpis['awaiting_legal'] ?? 0) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">Pending staff cross-check</p>
                </div>
                <div class="card p-4 border-l-4 border-l-rose-500">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Legally Excluded</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-rose-700">{{ number_format($kpis['excluded_projects'] ?? 0) }}</dd>
                    <p class="mt-1 text-[11px] text-rose-600 font-medium">Illegal list / unapproved</p>
                </div>
            </div>
        </section>

        {{-- Category Jump Navigation --}}
        <div class="mb-8">
            <h2 class="text-xs font-semibold tracking-wider text-muted uppercase mb-3">{{ $city }} Category Portals</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach (config('investment_index.categories') as $catKey => $cat)
                    @php
                        $catCount = $categoryCounts[$catKey]['ranked'] ?? 0;
                    @endphp
                    <a href="{{ route('rankings.city-category', [$citySlug, $catKey]) }}"
                       class="card p-3.5 hover:border-brand-400 hover:shadow-sm transition flex items-center justify-between group">
                        <div>
                            <span class="block text-sm font-bold text-ink group-hover:text-brand-700">{{ $cat['label'] }}</span>
                            <span class="block text-[11.5px] text-muted">{{ $catCount }} ranked in {{ $city }}</span>
                        </div>
                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700 group-hover:bg-brand-600 group-hover:text-white transition">
                            →
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- PART 3-C: CITY CHARTS & ANALYTICS --}}
        @if (! empty($charts))
            <section aria-labelledby="city-charts-heading" class="mb-10">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 id="city-charts-heading" class="text-xl font-bold text-ink">{{ $city }} Market Structure Analytics</h2>
                        <p class="text-xs text-muted">Visual analytics derived from empirical data recorded for {{ $city }}.</p>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Chart 1: Category Distribution --}}
                    <x-chart-card
                        id="cityChartCategory"
                        title="Category Breakdown"
                        subtitle="Total vs. ranked projects by sector in {{ $city }}"
                        type="bar"
                        :labels="$charts['category_distribution']['labels'] ?? []"
                        :datasets="[
                            [
                                'label' => 'Total',
                                'data' => $charts['category_distribution']['total'] ?? [],
                                'backgroundColor' => '#4B1592',
                                'borderRadius' => 4,
                            ],
                            [
                                'label' => 'Ranked',
                                'data' => $charts['category_distribution']['ranked'] ?? [],
                                'backgroundColor' => '#D6A13D',
                                'borderRadius' => 4,
                            ]
                        ]"
                    />

                    {{-- Chart 2: Legal Status --}}
                    <x-chart-card
                        id="cityChartLegal"
                        title="Legal Status Standing"
                        subtitle="Regulatory approval verification in {{ $city }}"
                        type="doughnut"
                        :labels="$charts['legal_distribution']['labels'] ?? []"
                        :datasets="[
                            [
                                'data' => $charts['legal_distribution']['data'] ?? [],
                                'backgroundColor' => $charts['legal_distribution']['colors'] ?? ['#10B981', '#F59E0B', '#0EA5E9', '#94A3B8', '#F43F5E'],
                                'borderWidth' => 2,
                                'borderColor' => '#FFFFFF',
                            ]
                        ]"
                    />

                    {{-- Chart 3: Risk Assessment --}}
                    <x-chart-card
                        id="cityChartRisk"
                        title="Risk Classification"
                        subtitle="Risk assessment distribution for {{ $city }}"
                        type="doughnut"
                        :labels="$charts['risk_distribution']['labels'] ?? []"
                        :datasets="[
                            [
                                'data' => $charts['risk_distribution']['data'] ?? [],
                                'backgroundColor' => $charts['risk_distribution']['colors'] ?? ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
                                'borderWidth' => 2,
                                'borderColor' => '#FFFFFF',
                            ]
                        ]"
                    />

                    {{-- Chart 4: Score Distribution --}}
                    <x-chart-card
                        id="cityChartScore"
                        title="Score Distribution"
                        subtitle="Investment score tiers for ranked projects in {{ $city }}"
                        type="bar"
                        :labels="$charts['score_distribution']['labels'] ?? []"
                        :datasets="[
                            [
                                'label' => 'Ranked Projects',
                                'data' => $charts['score_distribution']['data'] ?? [],
                                'backgroundColor' => ['#10B981', '#4B1592', '#D6A13D', '#64748B'],
                                'borderRadius' => 4,
                            ]
                        ]"
                    />
                </div>
            </section>
        @endif

        {{-- PART 3-D: OVERALL CITY INVESTMENT LEADERBOARD --}}
        @if ($cityOverall->isNotEmpty())
            <section class="card min-w-0 p-5 mb-8 shadow-card" aria-labelledby="city-overall-title">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4 mb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-brand-600 px-2 py-0.5 text-xs font-bold text-white uppercase">Overall City Index</span>
                            <h2 id="city-overall-title" class="text-lg font-bold text-ink">Overall Investment Index in {{ $city }}</h2>
                        </div>
                        <p class="mt-1 text-xs text-muted">
                            Top qualifying developments in {{ $city }} compared across sectors on a normalized scale, weighted by data confidence and verified empirical evidence.
                        </p>
                    </div>
                    <a href="{{ route('rankings.overall') }}" class="btn btn-secondary btn-sm">
                        View National Overall Index →
                    </a>
                </div>

                <div class="relative overflow-x-auto">
                    <table class="table min-w-[900px]">
                        <thead>
                            <tr>
                                <th scope="col" class="w-14">City Rank</th>
                                <th scope="col">Project Name & Developer</th>
                                <th scope="col">Category</th>
                                <th scope="col" class="text-right">Investment Score</th>
                                <th scope="col" class="text-right">Confidence</th>
                                <th scope="col">Legal Standing</th>
                                <th scope="col">Risk Class</th>
                                <th scope="col" class="text-right">Evidence Coverage</th>
                                <th scope="col">Last Verified</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($cityOverall as $idx => $s)
                                <tr class="hover:bg-brand-50/30 transition">
                                    {{-- City Rank --}}
                                    <td>
                                        <span @class([
                                            'grid size-8 place-items-center rounded-full text-xs font-bold',
                                            'bg-amber-100 text-amber-900 ring-2 ring-amber-400' => $idx === 0,
                                            'bg-slate-100 text-slate-800 ring-2 ring-slate-300' => $idx === 1,
                                            'bg-amber-50 text-amber-900 border border-amber-300' => $idx === 2,
                                            'bg-brand-50 text-brand-700' => $idx > 2,
                                        ])>
                                            #{{ $idx + 1 }}
                                        </span>
                                    </td>

                                    {{-- Project --}}
                                    <td>
                                        <a href="{{ route('rankings.project', $s->project) }}" class="font-bold text-sm text-ink hover:text-brand-600 block">
                                            {{ $s->project->name }}
                                        </a>
                                        <span class="text-xs text-muted block">
                                            {{ collect([$s->project->area?->name, $s->project->city])->filter()->unique()->implode(', ') }}
                                            @if ($s->project->developer) · {{ $s->project->developer }} @endif
                                        </span>
                                    </td>

                                    {{-- Category --}}
                                    <td>
                                        <a href="{{ route('rankings.city-category', [$citySlug, $s->category]) }}" class="inline-flex items-center rounded-md bg-page px-2 py-1 text-xs font-semibold text-brand-700 ring-1 ring-line hover:ring-brand-300">
                                            {{ $s->categoryLabel() }}
                                        </a>
                                    </td>

                                    {{-- Investment Score --}}
                                    <td class="text-right">
                                        <span @class([
                                            'inline-flex items-center rounded px-2.5 py-0.5 text-xs font-bold tabular-nums',
                                            'bg-emerald-100 text-emerald-800' => $s->score >= 90,
                                            'bg-brand-100 text-brand-800' => $s->score >= 80 && $s->score < 90,
                                            'bg-amber-100 text-amber-800' => $s->score < 80,
                                        ])>
                                            {{ number_format($s->score, 1) }}
                                        </span>
                                    </td>

                                    {{-- Data Confidence --}}
                                    <td class="text-right">
                                        <span class="text-xs font-bold tabular-nums text-ink">{{ number_format($s->confidence_score, 0) }}</span>
                                        <span class="block text-[10px] text-muted">{{ ucfirst($s->confidence_level) }}</span>
                                    </td>

                                    {{-- Legal Status --}}
                                    <td>
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11.5px] font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                                            {{ IndexBrowser::legalLabel($s->legal_status) }}
                                        </span>
                                    </td>

                                    {{-- Risk --}}
                                    <td>
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold',
                                            'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' => $s->risk_class === 'low',
                                            'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20' => $s->risk_class === 'moderate',
                                            'bg-orange-50 text-orange-700 ring-1 ring-orange-600/20' => $s->risk_class === 'elevated',
                                            'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20' => $s->risk_class === 'excluded',
                                        ])>
                                            {{ $s->riskLabel() }}
                                        </span>
                                    </td>

                                    {{-- Evidence Coverage Progress --}}
                                    <td class="text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                                <div class="bg-brand-600 h-1.5 rounded-full" style="width: {{ min(round($s->coverage * 100, 0), 100) }}%"></div>
                                            </div>
                                            <span class="text-xs font-semibold tabular-nums text-muted">{{ round($s->coverage * 100, 0) }}%</span>
                                        </div>
                                    </td>

                                    {{-- Updated --}}
                                    <td class="text-xs text-muted whitespace-nowrap">
                                        {{ $s->last_verified_at?->localTime()->format('j M Y') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Category Breakdown Boards --}}
        @include('rankings.partials.index-boards', ['city' => $city])

        {{-- Dynamic Results Target for Test Assertions & Legacy Filters --}}
        <div class="mt-6" data-rankings-target>
            @include('rankings.partials.city-results')
        </div>

        {{-- AI Investment Advisor --}}
        <div class="mt-8">
            @include('rankings.partials.advisor', ['segmentKey' => $segmentKey])
        </div>

    </div>
</x-layouts.app>
