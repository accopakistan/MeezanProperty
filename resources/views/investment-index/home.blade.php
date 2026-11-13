@php
    $kpis = $analytics['kpis'] ?? [];
    $charts = $analytics['charts'] ?? [];
    $cityData = $analytics['city_data'] ?? [];
    $categoryData = $analytics['category_data'] ?? [];
@endphp

<x-layouts.app title="Meezan Investment Index — Research, Verification & Investment Intelligence"
               description="Empirical, evidence-backed investment rankings and real estate intelligence across housing societies, commercial plazas, apartment buildings, shopping malls, and business towers in Pakistan."
               :canonical="route('rankings.index')">

    {{-- Visual Image-Based Hero Section with Compact Investment Search --}}
    <x-investment-hero
        title="Meezan Investment Index"
        subtitle="The premier empirical benchmark evaluating Pakistani real estate developments. Independent, multi-pillar scoring derived strictly from verified regulatory approvals, recorded municipal evidence, and documented transactions."
        :breadcrumbs="[['Investment Rankings', null]]"
        :run="$run"
        :version="$kpis['methodology_version'] ?? 1"
        :publishedAt="$kpis['published_at'] ?? null"
        :kpis="$kpis"
        badge="Institutional Real Estate Intelligence"
        layout="dashboard"
        :tiles="[['building', number_format($kpis['total_projects'] ?? 0), 'Researched'], ['chart', number_format($kpis['ranked_projects'] ?? 0), 'Ranked'], ['map-pin', '7', 'Major cities'], ['shield', number_format($kpis['verified_projects'] ?? 0), 'Legal records']]"
    />

    {{-- Main Container --}}
    <div class="container-page py-8">

        {{-- Dashboard block (owner's reference layout), Pakistan-wide --}}
        @include('rankings.partials.city-dashboard', ['city' => null, 'citySlug' => null, 'indexCityCards' => $cityCards, 'cityOverall' => $overall, 'tab' => 'all', 'stripOverlap' => true])

        <h2 class="mt-12 mb-4 text-xl font-bold text-ink">Pakistan research coverage</h2>

        {{-- Verification Quality Callout Banner --}}
        <div class="mb-8 rounded-xl border border-brand-200 bg-brand-50/60 p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-600 text-gold-300 shadow-sm">
                        <x-icon name="building" class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-ink">Strict Evidence Threshold (&ge; 60% Requirement)</h2>
                        <p class="mt-0.5 text-xs text-muted max-w-3xl">
                            Projects are admitted to the ranking engine only after meeting the 60% evidence completeness threshold across statutory approvals, developer track record, physical infrastructure, and market liquidity. Speculative schemes lacking verified data are held in research.
                        </p>
                    </div>
                </div>
                <a href="{{ route('rankings.methodology') }}" class="btn btn-secondary btn-sm shrink-0">
                    Audit Guidelines
                </a>
            </div>
        </div>

        {{-- PART 2-B: DATABASE-BACKED NATIONAL KPI CARDS --}}
        <section aria-labelledby="kpi-heading" class="mb-10">
            <h2 id="kpi-heading" class="text-xs font-semibold tracking-wider text-muted uppercase mb-3">Database Verification Overview</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                {{-- KPI 1: Researched --}}
                <div class="card p-4 border-l-4 border-l-brand-600">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Total Researched</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-ink">{{ number_format($kpis['total_projects'] ?? 1289) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">Across 7 launch cities</p>
                </div>

                {{-- KPI 2: Verified Records --}}
                <div class="card p-4 border-l-4 border-l-emerald-600">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Verified Projects</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">{{ number_format($kpis['verified_projects'] ?? 609) }}</dd>
                    <p class="mt-1 text-[11px] text-emerald-600 font-medium">Official authority records</p>
                </div>

                {{-- KPI 3: Ranked Projects --}}
                <div class="card p-4 border-l-4 border-l-gold-500 bg-gold-50/20">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Ranked Projects</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-brand-800">{{ number_format($kpis['ranked_projects'] ?? 98) }}</dd>
                    <p class="mt-1 text-[11px] text-gold-700 font-semibold">&ge; 60% Evidence threshold</p>
                </div>

                {{-- KPI 4: Under Research --}}
                <div class="card p-4 border-l-4 border-l-amber-500">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Under Research</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-amber-700">{{ number_format($kpis['under_research'] ?? 1169) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">Awaiting evidence intake</p>
                </div>

                {{-- KPI 5: Awaiting Legal Verification --}}
                <div class="card p-4 border-l-4 border-l-sky-500">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Awaiting Legal Check</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-sky-700">{{ number_format($kpis['awaiting_legal'] ?? 526) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">Pending staff cross-check</p>
                </div>

                {{-- KPI 6: Excluded on Legal Grounds --}}
                <div class="card p-4 border-l-4 border-l-rose-500">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Legally Excluded</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-rose-700">{{ number_format($kpis['excluded_projects'] ?? 22) }}</dd>
                    <p class="mt-1 text-[11px] text-rose-600 font-medium">Illegal list / cancelled</p>
                </div>
            </div>
        </section>

        {{-- PART 2-C: NATIONAL INVESTMENT ANALYTICS & INTERACTIVE CHARTS --}}
        <section aria-labelledby="analytics-heading" class="mb-12">
            <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                <div>
                    <h2 id="analytics-heading" class="text-xl font-bold text-ink">National Real Estate Market Analytics</h2>
                    <p class="text-xs text-muted">Visual distribution of verified projects, regulatory standings, and investment scores across Pakistan.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-medium text-muted">Live Database Sync</span>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                {{-- Chart 1: City Distribution --}}
                <x-chart-card
                    id="chartCityDistribution"
                    title="City Coverage Distribution"
                    subtitle="Total researched vs. qualifying ranked developments by city"
                    type="bar"
                    :labels="$charts['city_distribution']['labels'] ?? []"
                    :datasets="[
                        [
                            'label' => 'Total Researched',
                            'data' => $charts['city_distribution']['total'] ?? [],
                            'backgroundColor' => '#4B1592',
                            'borderRadius' => 4,
                        ],
                        [
                            'label' => 'Verified Ranked',
                            'data' => $charts['city_distribution']['ranked'] ?? [],
                            'backgroundColor' => '#D6A13D',
                            'borderRadius' => 4,
                        ]
                    ]"
                />

                {{-- Chart 2: Category Distribution --}}
                <x-chart-card
                    id="chartCategoryDistribution"
                    title="Ranked Projects by Category"
                    subtitle="Number of qualifying developments per sector leaderboard"
                    type="horizontalBar"
                    :labels="$charts['category_distribution']['labels'] ?? []"
                    :datasets="[
                        [
                            'label' => 'Ranked Projects',
                            'data' => $charts['category_distribution']['ranked'] ?? [],
                            'backgroundColor' => '#6A3FB0',
                            'borderRadius' => 4,
                        ]
                    ]"
                />

                {{-- Chart 3: Legal Status --}}
                <x-chart-card
                    id="chartLegalDistribution"
                    title="Official Legal Status"
                    subtitle="Breakdown of statutory NOC and authority approval standing"
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

                {{-- Chart 4: Risk Classification --}}
                <x-chart-card
                    id="chartRiskDistribution"
                    title="Risk Assessment Classification"
                    subtitle="Systematic risk rating across all evaluated projects"
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

                {{-- Chart 5: Score Distribution --}}
                <x-chart-card
                    id="chartScoreDistribution"
                    title="Investment Score Distribution"
                    subtitle="Score quality brackets for national qualifying projects"
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

                {{-- Chart 6: Evidence Coverage Bands --}}
                <x-chart-card
                    id="chartEvidenceCoverage"
                    title="Evidence Completeness Depth"
                    subtitle="Percentage of scoring factor weight covered by verified evidence"
                    type="bar"
                    :labels="$charts['evidence_coverage']['labels'] ?? []"
                    :datasets="[
                        [
                            'label' => 'Projects',
                            'data' => $charts['evidence_coverage']['data'] ?? [],
                            'backgroundColor' => ['#10B981', '#3B82F6', '#F59E0B', '#94A3B8'],
                            'borderRadius' => 4,
                        ]
                    ]"
                />
            </div>
        </section>

        {{-- PART 2-D: CATEGORY LEADERBOARDS & OVERALL INDEX --}}
        <section aria-labelledby="leaderboards-title" class="mb-12">
            <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
                <div>
                    <h2 id="leaderboards-title" class="text-xl font-bold text-ink">Category-Wise Investment Leaderboards</h2>
                    <p class="text-xs text-muted">Each development sector has an approved independent multi-factor scoring formula. Click any category for the complete leaderboard.</p>
                </div>
                <a href="{{ route('rankings.overall') }}" class="btn btn-outline btn-sm">
                    View Overall Normalized Index →
                </a>
            </div>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($categories as $c)
                    <div class="card p-5 flex flex-col justify-between hover:border-brand-300 transition shadow-sm">
                        <div>
                            <div class="flex items-start justify-between gap-2 border-b border-line pb-3">
                                <div>
                                    <h3 class="text-base font-bold text-ink">
                                        <a href="{{ route('rankings.category', $c['key']) }}" class="hover:text-brand-700">
                                            {{ \App\Services\InvestmentIndex\IndexBrowser::leaderboardTitle($c['key'], min($c['counts']['ranked'], \App\Services\InvestmentIndex\IndexBrowser::publicTop())) }}
                                        </a>
                                    </h3>
                                    <p class="text-[12px] text-muted mt-0.5">
                                        {{ $c['counts']['ranked'] }} ranked · {{ $c['counts']['insufficient'] }} insufficient data
                                    </p>
                                </div>
                                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">
                                    {{ $c['counts']['ranked'] }} Verified
                                </span>
                            </div>

                            {{-- Top 3 Ranked Projects in this category --}}
                            @if ($c['top']->isNotEmpty())
                                <ol class="mt-4 divide-y divide-line/60">
                                    @foreach ($c['top']->take(3) as $s)
                                        <li class="py-2.5 flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span @class([
                                                    'grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold',
                                                    'bg-amber-100 text-amber-800' => $s->category_rank === 1,
                                                    'bg-slate-100 text-slate-700' => $s->category_rank === 2,
                                                    'bg-amber-50 text-amber-900 border border-amber-200' => $s->category_rank === 3,
                                                    'bg-brand-50 text-brand-700' => $s->category_rank > 3,
                                                ])>
                                                    #{{ $s->category_rank }}
                                                </span>
                                                <div class="min-w-0">
                                                    <a href="{{ route('rankings.project', $s->project) }}" class="font-semibold text-sm text-ink truncate block hover:text-brand-600">
                                                        {{ $s->project->name }}
                                                    </a>
                                                    <span class="text-[11.5px] text-muted block truncate">
                                                        {{ $s->project->city }} · Confidence {{ number_format($s->confidence_score, 0) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <span class="font-bold text-sm text-brand-700 tabular-nums">
                                                    {{ number_format($s->score, 1) }}
                                                </span>
                                                <span class="block text-[10px] text-muted">/100</span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                            @else
                                <p class="mt-4 rounded-lg bg-surface p-3 text-xs"><span class="font-semibold text-ink">Insufficient Verified Data.</span> <span class="text-muted">No {{ mb_strtolower($c['singular'] ?? 'development') }} has enough verified evidence to be ranked yet.</span></p>
                            @endif
                        </div>

                        <div class="mt-4 pt-3 border-t border-line flex items-center justify-between text-xs">
                            <span class="text-muted">Showing the top 3</span>
                            <a href="{{ route('rankings.category', $c['key']) }}" class="font-semibold text-brand-700 hover:underline">
                                Full Leaderboard →
                            </a>
                        </div>
                    </div>
                @endforeach

                {{-- Overall Normalized Benchmark Card --}}
                <div class="card p-5 flex flex-col justify-between border-brand-200 bg-gradient-to-br from-brand-50/50 via-white to-gold-50/30 shadow-sm">
                    <div>
                        <div class="flex items-start justify-between gap-2 border-b border-brand-200/60 pb-3">
                            <div>
                                <h3 class="text-base font-bold text-ink">
                                    <a href="{{ route('rankings.overall') }}" class="hover:text-brand-700">
                                        Overall Investment Index
                                    </a>
                                </h3>
                                <p class="text-[12px] text-muted mt-0.5">
                                    Normalized cross-category benchmark weighted by data confidence
                                </p>
                            </div>
                            <span class="rounded-full bg-gold-100 px-2.5 py-1 text-xs font-bold text-gold-800">
                                Benchmark
                            </span>
                        </div>

                        @if ($overall->isNotEmpty())
                            <ol class="mt-4 divide-y divide-line/60">
                                @foreach ($overall->take(3) as $s)
                                    <li class="py-2.5 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-bold text-white">
                                                #{{ $s->overall_rank }}
                                            </span>
                                            <div class="min-w-0">
                                                <a href="{{ route('rankings.project', $s->project) }}" class="font-semibold text-sm text-ink truncate block hover:text-brand-600">
                                                    {{ $s->project->name }}
                                                </a>
                                                <span class="text-[11.5px] text-muted block truncate">
                                                    {{ $s->categoryLabel() }} · {{ $s->project->city }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="font-bold text-sm text-gold-700 tabular-nums">
                                                {{ number_format($s->overall_index, 1) }}
                                            </span>
                                            <span class="block text-[10px] text-muted">Index</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <p class="mt-4 rounded-lg bg-white p-3 text-xs"><span class="font-semibold text-ink">Insufficient Verified Data.</span> <span class="text-muted">A category enters the Overall Index once it has enough ranked projects to compare.</span></p>
                        @endif
                    </div>

                    <div class="mt-4 pt-3 border-t border-brand-200/60 flex items-center justify-between text-xs">
                        <span class="text-muted">Unified national ranking</span>
                        <a href="{{ route('rankings.overall') }}" class="font-semibold text-brand-700 hover:underline">
                            Explore Overall Index →
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- PART 3 INTRO: SEVEN CITY DASHBOARDS --}}
        <section aria-labelledby="cities-heading" class="mb-12">
            <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
                <div>
                    <h2 id="cities-heading" class="text-xl font-bold text-ink">Choose a city — Investment Dashboards</h2>
                    <p class="text-xs text-muted">Dedicated real estate analytics, regulatory registries, and category leaderboards across all seven launch markets.</p>
                </div>
                <span class="text-xs text-muted font-medium">All 7 Cities Supported</span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($cityCards as $c)
                    <a href="{{ route('rankings.city', $c['slug']) }}" class="card group flex flex-col overflow-hidden hover:border-brand-400 hover:shadow-md transition">
                        <div class="relative h-28 bg-brand-900">
                            @if ($c['photo'])
                                <img src="{{ asset($c['photo']['file']) }}" alt="{{ $c['name'] }}" class="absolute inset-0 size-full object-cover opacity-75 group-hover:scale-105 transition duration-300" style="object-position: {{ $c['photo']['position'] ?? 'center' }}" loading="lazy">
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-brand-950/90 via-brand-950/40 to-transparent"></div>
                            <div class="absolute bottom-2.5 left-3 right-3 flex items-end justify-between">
                                <span class="text-lg font-bold text-white font-serif">{{ $c['name'] }}</span>
                                <span class="rounded bg-gold-500/90 px-2 py-0.5 text-[11px] font-bold text-brand-950">
                                    {{ $c['ranked'] }} Ranked
                                </span>
                            </div>
                        </div>
                        <div class="p-3.5 flex-1 flex flex-col justify-between">
                            <dl class="grid grid-cols-2 gap-2 text-[12px]">
                                <div>
                                    <dt class="text-muted">Researched</dt>
                                    <dd class="font-bold text-ink tabular-nums">{{ number_format($c['projects']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted">Ranked</dt>
                                    <dd class="font-bold text-brand-700 tabular-nums">{{ number_format($c['ranked']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted">In Research</dt>
                                    <dd class="font-semibold text-muted tabular-nums">{{ number_format($c['review'] + $c['insufficient']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted">Excluded</dt>
                                    <dd class="font-semibold text-rose-600 tabular-nums">{{ number_format($c['excluded']) }}</dd>
                                </div>
                            </dl>
                            <span class="mt-3 block text-xs font-semibold text-brand-700 group-hover:underline">
                                Open {{ $c['name'] }} Dashboard →
                            </span>
                        </div>
                    </a>
                @endforeach

                {{-- All Pakistan Card --}}
                <a href="{{ route('rankings.city', 'pakistan') }}" class="card flex flex-col justify-center gap-2 p-5 border-dashed border-2 hover:border-brand-400 bg-brand-50/20 text-center transition">
                    <span class="font-serif text-lg font-bold text-brand-900">National Overview</span>
                    <span class="text-xs text-muted">View comparative dashboards across all seven launch markets simultaneously.</span>
                    <span class="text-xs font-semibold text-brand-700">Open National View →</span>
                </a>
            </div>
        </section>

        {{-- PART 1 & 2 METHODOLOGY & ASSURANCE PILLARS --}}
        <section aria-labelledby="pillars-heading" class="rounded-2xl border border-line bg-white p-6 sm:p-8 shadow-card mb-8">
            <h2 id="pillars-heading" class="text-base font-bold text-ink uppercase tracking-wider mb-4">Investment Governance & Research Standard</h2>
            <div class="grid gap-6 md:grid-cols-3 text-xs leading-relaxed">
                <div>
                    <h3 class="font-bold text-ink text-sm flex items-center gap-2 mb-2">
                        <span class="grid size-6 place-items-center rounded-md bg-emerald-100 text-emerald-800 text-[11px] font-bold">1</span>
                        Traceable Evidence Only
                    </h3>
                    <p class="text-muted">
                        Every scoring factor is backed by recorded documentation with a verified authority reference or dated site inspection. AI never estimates scores, and unverified claims are held at zero value.
                    </p>
                </div>
                <div>
                    <h3 class="font-bold text-ink text-sm flex items-center gap-2 mb-2">
                        <span class="grid size-6 place-items-center rounded-md bg-brand-100 text-brand-800 text-[11px] font-bold">2</span>
                        Regulatory Compliance First
                    </h3>
                    <p class="text-muted">
                        Projects appearing on official illegal lists or with cancelled preliminary approvals from LDA, CDA, SBCA, FDA, KDA, QDA or NAB are excluded from all rankings.
                    </p>
                </div>
                <div>
                    <h3 class="font-bold text-ink text-sm flex items-center gap-2 mb-2">
                        <span class="grid size-6 place-items-center rounded-md bg-gold-100 text-gold-900 text-[11px] font-bold">3</span>
                        Independent & Deterministic
                    </h3>
                    <p class="text-muted">
                        Rankings cannot be influenced by advertising, listing subscriptions, or sponsorship. Calculations are deterministic, reproducible, and re-computed when new verified data is published.
                    </p>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-line flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
                <span>{{ config('meezan.investment.disclaimer') }}</span>
                <a href="{{ route('rankings.methodology') }}" class="font-semibold text-brand-600 hover:underline">Read Complete Methodology Document →</a>
            </div>
        </section>

    </div>
</x-layouts.app>
