@php
    use App\Services\InvestmentIndex\IndexBrowser;
    use App\Support\Cities;
    $commercial = in_array($key, ['commercial-plazas', 'shopping-malls', 'business-towers'], true);
    $title = IndexBrowser::leaderboardTitle($key, $ranked->count()).($city ? ' in '.$city : ' in Pakistan');
    $kpis = $analytics['kpis'] ?? [];
    $charts = $analytics['charts'] ?? [];
    $topThree = $ranked->take(3);
    $dashHeadline = 'Top <span class="text-gold-500">'.e($category['label']).'</span> in '.e($city ?? 'Pakistan');
@endphp

<x-layouts.app :title="$title.' — Meezan Investment Index'"
               :description="$title.', ranked from verified evidence: legal approval, '.collect($category['factors'])->except('legal_approval')->pluck('label')->take(4)->map(fn ($l) => mb_strtolower($l))->implode(', ').' and more.'"
               :canonical="$city ? route('rankings.city-category', [Cities::slug($city), $key]) : route('rankings.category', $key)"
               :noindex="$ranked->isEmpty() || $filters !== []">

    {{-- Category Dashboard Hero --}}
    <x-investment-hero
        :title="$title"
        :subtitle="'Evaluated strictly by the '.mb_strtolower($category['label']).' scoring engine: '.collect($weights)->map(fn ($w, $f) => mb_strtolower($category['factors'][$f]['label'] ?? $f).' ('.$w.'%)')->implode(' · ').'.'"
        :breadcrumbs="[['Investment Rankings', route('rankings.index')], [$category['label'], $city ? route('rankings.category', $key) : null]] + ($city ? [2 => [$city, null]] : [])"
        :city="$city"
        :category="$key"
        :categoryLabel="$category['label']"
        :run="$run"
        :kpis="[
            'ranked' => $counts['ranked'],
            'avg_score' => $kpis['avg_score'] ?? 0,
            'avg_confidence' => $kpis['avg_confidence'] ?? 0,
            'legal_verified' => $kpis['legal_verified'] ?? 0,
        ]"
        :currentCity="$city"
        :currentCategory="$key"
        :currentQuery="$filters['q'] ?? null"
        :badge="$category['label'].' · '.($city ? $city.' Market' : 'Pakistan National')"
        :search-group="$commercial ? 'commercial' : ($key === 'apartment-buildings' ? 'rent' : null)"
        layout="dashboard"
        :headline="$dashHeadline"
        tagline="Ranked on verified evidence"
        :tiles="[['building', number_format($counts['assessed'] ?? 0), 'Researched'], ['chart', number_format($counts['ranked'] ?? 0), 'Ranked'], ['map-pin', '7', 'Major cities'], ['shield', number_format($kpis['legal_verified'] ?? 0), 'Legal records']]"
    />

    {{-- Main Content --}}
    <div class="container-page py-8">

        {{-- Dashboard block (owner's category reference design) --}}
        @include('rankings.partials.city-dashboard', ['stripOverlap' => true, 'stripCategory' => $key, 'citySlug' => $city ? Cities::slug($city) : null, 'tab' => $key, 'focusCategory' => $key, 'focusRows' => $ranked])

        <h2 id="full-leaderboard" class="mt-12 mb-4 scroll-mt-28 text-xl font-bold text-ink">{{ $category['label'] }} {{ $city ? 'in '.$city : 'in Pakistan' }}: full leaderboard and research coverage</h2>

        {{-- PART 4-B: CATEGORY KPI CARDS --}}
        <section aria-labelledby="cat-kpi-heading" class="mb-8">
            <h2 id="cat-kpi-heading" class="text-xs font-semibold tracking-wider text-muted uppercase mb-3">{{ $category['label'] }} Overview Metrics</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <div class="card p-4 border-l-4 border-l-brand-600">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Total Researched</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-ink">{{ number_format($kpis['total_assessed'] ?? $counts['assessed']) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">{{ $city ?? 'Pakistan' }} records</p>
                </div>
                <div class="card p-4 border-l-4 border-l-gold-500 bg-gold-50/20">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Qualifying Ranked</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-brand-800">{{ number_format($counts['ranked']) }}</dd>
                    <p class="mt-1 text-[11px] text-gold-700 font-semibold">&ge; 60% Verified</p>
                </div>
                <div class="card p-4 border-l-4 border-l-emerald-600">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Average Score</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">{{ number_format($kpis['avg_score'] ?? 0, 1) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">Ranked projects</p>
                </div>
                <div class="card p-4 border-l-4 border-l-indigo-500">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Data Confidence</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-indigo-700">{{ number_format($kpis['avg_confidence'] ?? 0, 0) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">Evidence depth score</p>
                </div>
                <div class="card p-4 border-l-4 border-l-amber-500">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Verified Legal Files</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-amber-700">{{ number_format($kpis['legal_verified'] ?? 0) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">Statutory NOCs</p>
                </div>
                <div class="card p-4 border-l-4 border-l-slate-400">
                    <dt class="text-[11.5px] font-medium text-muted uppercase">Under Research</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-700">{{ number_format($kpis['awaiting_evidence'] ?? $counts['insufficient']) }}</dd>
                    <p class="mt-1 text-[11px] text-muted">Awaiting &ge; 60%</p>
                </div>
            </div>
        </section>

        {{-- PART 4-D: VISUAL RANKING SECTION (TOP 3 PODIUM CARDS) --}}
        @if ($topThree->isNotEmpty())
            <section aria-labelledby="podium-heading" class="mb-10">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 id="podium-heading" class="text-xl font-bold text-ink">Leading Verified Investments</h2>
                        <p class="text-xs text-muted">Top-ranked developments with verified regulatory compliance, developer execution, and market evidence.</p>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($topThree as $idx => $s)
                        @php
                            $isFirst = $idx === 0;
                            $isSecond = $idx === 1;
                            $isThird = $idx === 2;
                        @endphp
                        <div @class([
                            'card p-5 relative flex flex-col justify-between overflow-hidden shadow-card transition',
                            'border-amber-300 bg-gradient-to-b from-amber-50/40 via-white to-white ring-1 ring-amber-300' => $isFirst,
                            'border-slate-300 bg-gradient-to-b from-slate-50/50 via-white to-white' => $isSecond,
                            'border-amber-200 bg-gradient-to-b from-amber-50/20 via-white to-white' => $isThird,
                        ])>
                            {{-- Top Banner --}}
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <span @class([
                                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider shadow-sm',
                                        'bg-gradient-to-r from-amber-400 to-amber-500 text-brand-950 ring-1 ring-amber-300' => $isFirst,
                                        'bg-gradient-to-r from-slate-200 to-slate-300 text-slate-900 ring-1 ring-slate-300' => $isSecond,
                                        'bg-gradient-to-r from-amber-100 to-amber-200 text-amber-950 border border-amber-300' => $isThird,
                                    ])>
                                        {{ $isFirst ? '★ Rank #1 Leader' : ($isSecond ? 'Rank #2' : 'Rank #3') }}
                                    </span>
                                    <div class="text-right">
                                        <span class="text-2xl font-bold font-serif text-brand-700 tabular-nums">
                                            {{ number_format($s->score, 1) }}
                                        </span>
                                        <span class="block text-[10px] text-muted">/100</span>
                                    </div>
                                </div>

                                <h3 class="text-lg font-bold text-ink">
                                    <a href="{{ route('rankings.project', $s->project) }}" class="hover:text-brand-600">
                                        {{ $s->project->name }}
                                    </a>
                                </h3>

                                <p class="text-xs text-muted mt-1">
                                    {{ collect([$s->project->area?->name, $s->project->city])->filter()->unique()->implode(', ') }}
                                    @if ($s->project->developer) · <span class="font-medium text-ink">{{ $s->project->developer }}</span> @endif
                                </p>

                                {{-- Badges --}}
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                                        {{ IndexBrowser::legalLabel($s->legal_status) }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {{ $s->riskLabel() }}
                                    </span>
                                    @if ($s->verified_yield !== null)
                                        <span class="rounded-full bg-gold-50 px-2.5 py-0.5 text-[11px] font-bold text-gold-800">
                                            {{ number_format($s->verified_yield, 2) }}% Yield
                                        </span>
                                    @endif
                                </div>

                                {{-- Metrics Grid --}}
                                <dl class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-page/70 p-3 text-xs">
                                    <div>
                                        <dt class="text-muted">Confidence</dt>
                                        <dd class="font-bold text-ink tabular-nums">{{ number_format($s->confidence_score, 0) }} ({{ ucfirst($s->confidence_level) }})</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted">Evidence Depth</dt>
                                        <dd class="font-bold text-brand-700 tabular-nums">{{ round($s->coverage * 100, 0) }}% of weight</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="mt-4 pt-3 border-t border-line flex items-center justify-between">
                                <span class="text-[11px] text-muted">
                                    Verified {{ $s->last_verified_at?->localTime()->format('M Y') ?? 'Recently' }}
                                </span>
                                <a href="{{ route('rankings.project', $s->project) }}" class="btn btn-secondary btn-sm">
                                    View Project Analysis →
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Interactive Analytics Charts for this Category --}}
        @if (! empty($charts) && ! empty($charts['score_comparison']['labels']))
            <section aria-labelledby="cat-charts-heading" class="mb-10">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 id="cat-charts-heading" class="text-xl font-bold text-ink">Score Comparison & Factor Weights</h2>
                        <p class="text-xs text-muted">Comparative visualization of investment scores and approved methodology weighting.</p>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)]">
                    {{-- Chart 1: Top 10 Score & Confidence Comparison --}}
                    <x-chart-card
                        id="catChartScores"
                        title="Top Ranked Projects: Score vs. Confidence"
                        subtitle="Comparison of empirical investment score and data confidence depth"
                        type="bar"
                        :labels="$charts['score_comparison']['labels'] ?? []"
                        :datasets="[
                            [
                                'label' => 'Investment Score',
                                'data' => $charts['score_comparison']['scores'] ?? [],
                                'backgroundColor' => '#4B1592',
                                'borderRadius' => 4,
                            ],
                            [
                                'label' => 'Data Confidence',
                                'data' => $charts['score_comparison']['confidence'] ?? [],
                                'backgroundColor' => '#D6A13D',
                                'borderRadius' => 4,
                            ]
                        ]"
                    />

                    {{-- Chart 2: Methodology Factor Weights --}}
                    <x-chart-card
                        id="catChartWeights"
                        title="Scoring Factor Weights"
                        subtitle="Approved category weights under Methodology v{{ $methodologyVersion }}"
                        type="doughnut"
                        :labels="$charts['factor_weights']['labels'] ?? []"
                        :datasets="[
                            [
                                'data' => $charts['factor_weights']['data'] ?? [],
                                'backgroundColor' => $charts['factor_weights']['colors'] ?? ['#4B1592', '#D6A13D', '#0D9488', '#2563EB', '#6366F1'],
                                'borderWidth' => 2,
                                'borderColor' => '#FFFFFF',
                            ]
                        ]"
                    />
                </div>
            </section>
        @endif

        {{-- Filter Form --}}
        <div class="mb-6 rounded-xl border border-line bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold text-muted uppercase tracking-wider mb-2">Filter Leaderboard & Directory</div>
            <form method="GET" action="{{ route('rankings.category', $key) }}" class="flex flex-wrap items-end gap-3">
                <x-field name="q" label="Search" :value="$filters['q'] ?? null" placeholder="Project or developer" class="min-w-44" />
                <x-field name="legal" type="select" label="Legal status" placeholder="Any legal status" :options="\App\Services\InvestmentIndex\IndexBrowser::legalFilters()" :value="$filters['legal'] ?? null" />
                <x-field name="status" type="select" label="Index status" placeholder="Any status" :options="config('investment_index.statuses')" :value="$filters['status'] ?? null" />
                <x-field name="development" type="select" label="Development" placeholder="Any stage" :options="\App\Models\Project::STATUSES" :value="$filters['development'] ?? null" />
                <x-field name="confidence" type="select" label="Confidence" placeholder="Any confidence" :options="['high' => 'High Confidence', 'medium' => 'Moderate Confidence', 'limited' => 'Low Confidence', 'insufficient' => 'Insufficient Evidence']" :value="$filters['confidence'] ?? null" />
                <x-field name="risk" type="select" label="Risk" placeholder="Any risk class" :options="['low' => 'Low', 'moderate' => 'Moderate', 'elevated' => 'Elevated', 'high' => 'High']" :value="$filters['risk'] ?? null" />
                <x-field name="city" type="select" label="City" :options="array_combine(\App\Support\Cities::names(), \App\Support\Cities::names())" :value="$city" placeholder="All of Pakistan" class="min-w-44" />
                <button class="btn btn-primary btn-sm">Filter Results</button>
                @if ($filters !== [] || $city)
                    <a href="{{ route('rankings.category', $key) }}" class="btn btn-secondary btn-sm">Reset</a>
                @endif
            </form>
        </div>

        {{-- PART 4-C: PROFESSIONAL SORTABLE LEADERBOARD TABLE --}}
        <section class="card min-w-0 p-5 mb-8 shadow-card" aria-labelledby="ranked-title">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-line pb-4 mb-4">
                <div>
                    <h2 id="ranked-title" class="text-lg font-bold text-ink">
                        Verified {{ $category['label'] }} Leaderboard
                    </h2>
                    <p class="text-xs text-muted mt-0.5">
                        {{ $counts['ranked'] }} ranked · {{ $counts['not_eligible'] }} not eligible · {{ $counts['insufficient'] }} insufficient verified data · {{ $counts['excluded'] }} excluded ({{ $city ?? 'Pakistan' }})
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">
                        Max 10 Public Ranks
                    </span>
                </div>
            </div>

            @if ($ranked->isEmpty())
                @include('investment-index.partials.insufficient', ['counts' => $counts])
            @else
                @include('investment-index.partials.leaderboard', ['rows' => $ranked])
                <p class="mt-4 text-xs text-muted">
                    Ranks are {{ $city ? $city.'-specific' : 'Pakistan-wide' }}. In compliance with publication standards, public category leaderboards display up to 10 top qualifying developments. Projects scored below the top 10 remain ranked on their individual profiles.
                </p>
            @endif
        </section>

        {{-- Provisional Rankings (If enabled) --}}
        @if ($provisional->isNotEmpty())
            <section class="card mb-8 p-5 shadow-card" aria-labelledby="provisional-title">
                <div class="border-b border-line pb-3 mb-4">
                    <h2 id="provisional-title" class="text-base font-bold text-ink">Provisional Research Ranking</h2>
                    <p class="mt-1 text-xs text-muted">
                        <strong class="text-ink">Not fully verified, and not an investment recommendation.</strong> Calculated by the same scoring from actual evidence, including records staff have not yet checked. Order can change as evidence arrives.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table min-w-[700px]">
                        <thead>
                            <tr>
                                <th scope="col">Position</th>
                                <th scope="col">Project</th>
                                <th scope="col" class="text-right">Provisional Score</th>
                                <th scope="col" class="text-right">Confidence</th>
                                <th scope="col" class="text-right">Evidence Depth</th>
                                <th scope="col">Legal Status</th>
                                <th scope="col">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($provisional as $s)
                                <tr>
                                    <td class="font-bold text-amber-800 tabular-nums">P{{ $s->provisional_rank }}</td>
                                    <td>
                                        <a href="{{ route('rankings.project', $s->project) }}" class="font-semibold text-ink hover:text-brand-600 block">
                                            {{ $s->project->name }}
                                        </a>
                                        <span class="text-xs text-muted block">{{ collect([$s->project->area?->name, $s->project->city])->filter()->unique()->implode(', ') }}</span>
                                    </td>
                                    <td class="text-right font-bold tabular-nums text-amber-800">{{ number_format($s->provisional_score, 1) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($s->provisional_confidence, 0) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($s->provisional_coverage * 100, 0) }}%</td>
                                    <td class="text-xs">{{ IndexBrowser::legalLabel($s->legal_status) }}</td>
                                    <td class="text-xs text-muted">{{ $s->created_at?->localTime()->format('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- PART 4-E: ASSESSED, NOT RANKED (PROJECT DIRECTORY PROFILES) --}}
        @if ($others->isNotEmpty())
            <form method="GET" action="{{ route('rankings.category-compare', $key) }}" class="card p-5 shadow-card" aria-labelledby="others-title">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4 mb-4">
                    <div>
                        <h2 id="others-title" class="text-base font-bold text-ink">Project Directory: Assessed, Awaiting Verified Evidence</h2>
                        <p class="mt-1 text-xs text-muted max-w-3xl">
                            These developments are undergoing research and do not meet the &ge; 60% evidence threshold for public ranking. Unranked status indicates insufficient documented evidence, not an unfavorable score.
                        </p>
                    </div>
                    <button class="btn btn-outline btn-sm">
                        Compare Selected Projects
                    </button>
                </div>

                <div class="divide-y divide-line">
                    @foreach ($others as $s)
                        <div class="py-3 flex flex-wrap items-center justify-between gap-3 hover:bg-page/40 transition">
                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                <input type="checkbox" name="projects[]" value="{{ $s->project->slug }}" class="checkbox mt-1" aria-label="Compare {{ $s->project->name }}">
                                <div class="min-w-0">
                                    <a href="{{ route('rankings.project', $s->project) }}" class="font-bold text-sm text-ink hover:text-brand-600 block">
                                        {{ $s->project->name }}
                                    </a>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                                        <span>{{ $s->project->city }}</span>
                                        @if ($s->project->developer)
                                            <span>· Dev: {{ $s->project->developer }}</span>
                                        @endif
                                        <span>· Legal: <strong class="text-ink">{{ IndexBrowser::legalLine($s) }}</strong></span>
                                        <span>· Stage: {{ $s->project->statusLabel() }}</span>
                                        <span>· Confidence: {{ number_format($s->confidence_score, 0) }}</span>
                                        <span>· Evidence: {{ round(($s->coverage ?? 0) * 100, 0) }}%</span>
                                    </div>
                                    @if ($s->risks)
                                        <p class="mt-1 text-xs text-amber-800">
                                            Notice: {{ \Illuminate\Support\Str::limit($s->risks[0], 120) }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                @include('investment-index.partials.status', ['status' => $s->status])
                                <a href="{{ route('rankings.project', $s->project) }}" class="btn btn-secondary btn-sm">
                                    Profile →
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 pt-3 border-t border-line flex items-center justify-between text-xs">
                    {{ $others->links() }}
                </div>
            </form>
        @elseif ($filters !== [])
            <p class="card mt-6 p-5 text-[14px] text-muted">Nothing here matches these filters.</p>
        @elseif ($ranked->isEmpty())
            <p class="card mt-6 p-5 text-[14px] text-muted"><span class="font-semibold text-ink">Coverage gap:</span> no {{ mb_strtolower($category['label']) }} are on file{{ $city ? ' for '.$city : '' }} yet. This reflects what has been researched so far, not what exists.</p>
        @endif

    </div>
</x-layouts.app>
