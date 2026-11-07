@php
    use App\Services\InvestmentIndex\IndexBrowser;
    use App\Support\Cities;
    $s = $score;
    $categoryConfig = $category ? config("investment_index.categories.$category") : null;
    $legalLabel = $s ? IndexBrowser::legalLabel($s->legal_status) : 'Not verified';
    $operational = $s?->operational_status ? config('investment_index.context_evidence.operational_status.levels.'.$s->operational_status, $s->operational_status) : null;
    $marketHistory = $s ? ($s->factors['market_performance'] ?? null) : null;
    $publicRank = IndexBrowser::publicRank($s);
@endphp

<x-layouts.app :title="$project->name.' — Investment Intelligence Profile'"
               :description="$project->name.' ('.($categoryConfig['singular'] ?? 'development').', '.$project->city.'): legal verification, investment score, data confidence, risk and sources in the Meezan Investment Index.'"
               :canonical="route('rankings.project', $project)"
               :noindex="! $s || $s->status !== 'ranked'">

    {{-- Executive Project Header Hero with Photographic Background --}}
    @php
        $hero = \App\Services\InvestmentIndex\IndexHeroImage::resolve($project->city, $category);
        $province = $project->city ? \App\Support\Cities::province($project->city) : null;
        $cityPhoto = $project->city ? config('meezan.place_photos.'.$project->city) : null;
        $eyebrow = $province ? "{$province}, Pakistan" : ($project->city ? "{$project->city}, Pakistan" : 'Pakistan Real Estate Benchmark');
    @endphp
    <section class="relative isolate overflow-hidden bg-brand-900 text-white">
        <img src="{{ $hero['url'] }}"
             alt="{{ $hero['alt'] }}"
             width="1920" height="1200"
             class="absolute inset-0 -z-20 size-full object-cover"
             style="object-position: {{ $cityPhoto['position'] ?? 'center' }}"
             fetchpriority="high"
             loading="eager"
             onerror="this.onerror=null;this.src='{{ asset('images/investment-index/fallback.jpg') }}';" />
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900/95 via-brand-900/75 to-brand-900/25"></div>

        <div class="container-page py-10 pb-12 lg:py-16">
            <x-breadcrumbs :items="[
                ['Investment Rankings', route('rankings.index')],
                [$categoryConfig['label'] ?? 'Projects', $category ? ($project->city ? route('rankings.city-category', [Cities::slug($project->city), $category]) : route('rankings.category', $category)) : null],
                [$project->name, null]
            ]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />

            <p class="mt-6 text-sm font-semibold tracking-wide text-gold-300 uppercase">{{ $eyebrow }}</p>

            <h1 class="mt-1 text-4xl text-white sm:text-5xl font-bold">
                {{ $project->name }}
            </h1>

            <p class="mt-3 max-w-2xl text-white/85 text-[15px] sm:text-base leading-relaxed">
                {{ $categoryConfig['singular'] ?? $project->categoryLabel() }} in {{ collect([$project->area?->name, $project->city])->filter()->unique()->implode(', ') }}
                @if ($project->developer) · Developer: <strong class="text-white">{{ $project->developer }}</strong>@endif
                @if ($project->website) · <a href="{{ $project->website }}" target="_blank" rel="noopener nofollow" class="underline hover:text-gold-300">Official Site</a>@endif
            </p>

            <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-white">
                <div>
                    <dt class="text-xs text-white/70">Investment Score</dt>
                    <dd class="text-2xl font-bold tabular-nums">
                        {{ $s && $s->status === 'ranked' ? number_format($s->score, 1) . ' / 100' : 'Under Audit' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-white/70">Category Rank</dt>
                    <dd class="text-2xl font-bold tabular-nums">
                        @if ($publicRank)
                            #{{ $publicRank }}
                        @elseif ($s && $s->status === 'ranked')
                            Ranked, outside the published top 10
                        @else
                            Unranked
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-white/70">Data Confidence</dt>
                    <dd class="text-2xl font-bold tabular-nums">
                        {{ $s ? number_format($s->confidence_score, 0) . ' / 100' : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-white/70">Risk Rating</dt>
                    <dd class="text-2xl font-bold">
                        {{ $s?->publicRiskLabel() ?? 'Not assessed' }}
                    </dd>
                </div>
            </dl>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if ($category && $project->city)
                    <a href="{{ route('rankings.city-category', [Cities::slug($project->city), $category]) }}" class="btn btn-gold btn-sm">
                        View {{ $project->city }} {{ $categoryConfig['label'] ?? 'Leaderboard' }} →
                    </a>
                @endif
                <a href="{{ route('rankings.methodology') }}" class="btn btn-on-dark btn-sm">
                    How Scored
                </a>
                @if ($canSubmit)
                    <a href="{{ route('investment-index.evidence.create', $project) }}" class="btn btn-on-dark btn-sm">
                        Submit Evidence
                    </a>
                @endif
            </div>
        </div>
        @if ($cityPhoto)
            <x-photo-credit :photo="$cityPhoto" />
        @endif
    </section>

    {{-- Main Container --}}
    <div class="container-page py-8">

        {{-- Verification Status Callout --}}
        @if (! $s || $s->status !== 'ranked')
            <div class="mb-8 rounded-xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm">
                <div class="flex items-start gap-3.5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-amber-500 text-white shadow-sm">
                        <x-icon name="chart" class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-amber-950">Project Directory Profile — Research in Progress</h2>
                        <p class="mt-0.5 text-xs text-amber-900 leading-relaxed">
                            This development is published as an authentic factual directory profile. It has not yet been awarded a public ranking because verified documentation on file covers {{ number_format(($s?->coverage ?? 0) * 100, 0) }}% of the required scoring weight (&ge; 60% required). Being unranked reflects a documentation gap rather than a negative assessment.
                        </p>
                        @if ($s && $s->reasons)
                            <ul class="mt-2 text-xs text-amber-900 list-disc pl-4 space-y-0.5">
                                @foreach (collect($s->reasons)->reject(fn ($r) => str_starts_with($r, 'Confidence:'))->take(3) as $r)
                                    <li>{{ $r }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1.8fr)_minmax(0,1.2fr)]">

            {{-- LEFT COLUMN: DETAILED ANALYSIS --}}
            <div class="space-y-8 min-w-0">

                {{-- Data Status & Origin Section --}}
                <section class="card p-6 shadow-card" aria-labelledby="data-title">
                    <h2 id="data-title" class="text-base font-bold text-ink uppercase tracking-wider mb-4">
                        Data status and origin
                    </h2>
                    <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-[180px_1fr]">
                        <dt class="text-muted">Data status</dt>
                        <dd class="font-semibold text-ink">{{ $dataStatus ?? 'Unverified' }}</dd>

                        @if ($s && $s->status === 'ranked')
                            <dt class="text-muted">Category standing</dt>
                            <dd class="font-semibold text-ink">
                                {{ $publicRank ? '#'.$publicRank.' in '.($categoryConfig['label'] ?? 'Sector') : 'Ranked, outside the published top 10' }}
                                @if ($s->score !== null)
                                    · Score: {{ number_format($s->score, 1) }}/100
                                @endif
                                · Confidence: {{ number_format($s->confidence_score, 0) }}/100 ({{ ucfirst($s->confidence_level) }})
                            </dd>
                        @endif

                        @if ($project->discoverySource)
                            <dt class="text-muted">First found in</dt>
                            <dd class="text-ink">
                                @if (IndexBrowser::safeUrl($project->discovery_url))
                                    <a href="{{ $project->discovery_url }}" class="link" target="_blank" rel="noopener nofollow">{{ $project->discoverySource->name }}</a>
                                @else
                                    {{ $project->discoverySource->name }}
                                @endif
                                <span class="block text-xs text-muted">{{ $project->discoverySource->typeLabel() }}@if ($project->discovered_at), read {{ $project->discovered_at->format('j M Y') }}@endif</span>
                            </dd>
                        @endif

                        <dt class="text-muted">Legal verification status</dt>
                        <dd class="font-semibold text-ink">{{ $legalLabel }}</dd>

                        <dt class="text-muted">Reported development status</dt>
                        <dd class="text-ink">
                            {{ $project->statusLabel() }}
                            @if ($project->status !== 'unverified')
                                <span class="text-xs text-muted">(as reported by the source, not independently verified)</span>
                            @endif
                        </dd>

                        <dt class="text-muted">Operational status</dt>
                        <dd class="text-ink">{{ $operational ?? 'No verified record' }}</dd>

                        @if ($project->website)
                            <dt class="text-muted">Website</dt>
                            <dd>
                                <a href="{{ $project->website }}" class="link break-all" target="_blank" rel="noopener nofollow">{{ $project->website }}</a>
                                <span class="text-xs text-muted">(developer-reported information)</span>
                            </dd>
                        @endif
                    </dl>

                    @php
                        $missing = $s ? collect($s->factors)->reject(fn ($f) => $f['display_only'] ?? false)->filter(fn ($f) => ($f['score'] ?? null) === null)->pluck('label') : collect();
                    @endphp
                    @if ($missing->isNotEmpty())
                        <div class="mt-4 pt-3 border-t border-line text-xs text-muted">
                            <span class="font-semibold text-ink">Missing information:</span> {{ $missing->implode(', ') }}. Each stays missing until verified evidence exists; nothing is estimated.
                        </div>
                    @endif
                </section>

                {{-- PART 5-B: INVESTMENT SCORE BREAKDOWN (METHODOLOGY FACTORS) --}}
                @if ($s && ! empty($s->factors))
                    <section class="card p-6 shadow-card" aria-labelledby="score-breakdown-heading">
                        <div class="border-b border-line pb-4 mb-4">
                            <div class="flex items-center justify-between">
                                <h2 id="score-breakdown-heading" class="text-base font-bold text-ink uppercase tracking-wider">
                                    Approved Methodology Score Breakdown
                                </h2>
                                <span class="rounded bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700">
                                    {{ $categoryConfig['label'] ?? 'Category' }} Formula
                                </span>
                            </div>
                            <p class="text-xs text-muted mt-1">
                                Every pillar is evaluated strictly from documented evidence. Zero speculative points. Missing factors remain un-scored.
                            </p>
                        </div>

                        <div class="space-y-4">
                            @foreach (collect($s->factors)->reject(fn ($f) => $f['display_only'] ?? false) as $fKey => $f)
                                @php
                                    $factorScore = $f['score'] ?? null;
                                    $weight = $f['weight'] ?? 25;
                                    $contribution = $factorScore !== null ? round(($factorScore * $weight) / 100, 1) : null;
                                @endphp
                                <div class="rounded-xl border border-line bg-page/40 p-4">
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <div>
                                            <span class="text-sm font-bold text-ink">{{ $f['label'] }}</span>
                                            <span class="ml-2 rounded-full bg-brand-100 text-brand-800 px-2 py-0.5 text-[11px] font-bold">
                                                Weight: {{ $weight }}%
                                            </span>
                                        </div>
                                        <div class="text-right">
                                            @if ($factorScore !== null)
                                                <span class="text-base font-bold tabular-nums text-brand-700">
                                                    {{ number_format($factorScore, 1) }}
                                                </span>
                                                <span class="text-xs text-muted">/100</span>
                                                <span class="block text-[11px] text-emerald-600 font-semibold">+{{ $contribution }} pts</span>
                                            @else
                                                <span class="text-xs font-semibold text-muted bg-slate-100 px-2 py-0.5 rounded">
                                                    Awaiting Verified Evidence
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Progress Bar --}}
                                    <div class="w-full bg-slate-200 rounded-full h-2 mb-2 overflow-hidden">
                                        <div @class([
                                            'h-2 rounded-full transition-all duration-500',
                                            'bg-emerald-500' => $factorScore >= 80,
                                            'bg-brand-600' => $factorScore >= 60 && $factorScore < 80,
                                            'bg-amber-500' => $factorScore > 0 && $factorScore < 60,
                                            'bg-slate-300' => $factorScore === null,
                                        ]) style="width: {{ $factorScore ?? 0 }}%"></div>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted mt-2">
                                        <span>Verified Value: <strong class="text-ink">{{ $f['value'] ?? 'Insufficient verified evidence' }}</strong></span>
                                        @if (! empty($f['missing']))
                                            <span class="text-amber-700 font-medium">Gap: {{ $f['missing'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- PART 5-D: DEVELOPMENT & OCCUPANCY EVIDENCE --}}
                <section class="card p-6 shadow-card" aria-labelledby="dev-status-heading">
                    <div class="border-b border-line pb-4 mb-4">
                        <h2 id="dev-status-heading" class="text-base font-bold text-ink uppercase tracking-wider">
                            Development, Infrastructure & Operational Status
                        </h2>
                        <p class="text-xs text-muted mt-1">
                            Audited on-ground progress and municipal utility availability based on verified physical observations.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-line bg-page/40 p-4">
                            <span class="text-xs font-semibold text-muted uppercase">Development Progress</span>
                            <div class="mt-2 flex items-baseline gap-2">
                                <span class="text-3xl font-bold font-serif text-ink tabular-nums">
                                    {{ $s?->development_progress !== null && $s ? number_format($s->development_progress, 0).'%' : 'Unverified' }}
                                </span>
                                @if ($s?->development_progress !== null)
                                    <span class="text-xs font-semibold text-emerald-600">Physical completion</span>
                                @endif
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2 mt-2 overflow-hidden">
                                <div class="bg-brand-600 h-2 rounded-full" style="width: {{ $s?->development_progress ?? 0 }}%"></div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-line bg-page/40 p-4">
                            <span class="text-xs font-semibold text-muted uppercase">Operational & Occupancy</span>
                            <div class="mt-2">
                                <span class="text-lg font-bold text-ink">
                                    {{ $operational ?? ($project->statusLabel() ?? 'Status Not Verified') }}
                                </span>
                                <span class="block text-xs text-muted mt-1">
                                    @if ($s && ! empty($s->factors['occupancy']['value']))
                                        Verified occupancy: <strong class="text-ink">{{ $s->factors['occupancy']['value'] }}</strong>
                                    @else
                                        Occupancy records undergoing collection
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- PART 5-C: MARKET PERFORMANCE & PRICE RECORDS --}}
                <section class="card p-6 shadow-card" aria-labelledby="price-records-heading">
                    <div class="border-b border-line pb-4 mb-4">
                        <h2 id="price-records-heading" class="text-base font-bold text-ink uppercase tracking-wider">
                            Empirical Price & Rental Records
                        </h2>
                        <p class="text-xs text-muted mt-1">
                            Asking prices, verified transactions, and rental records are strictly segregated. An asking price is never recorded as a sale price.
                        </p>
                    </div>

                    @php
                        $anyPrices = collect($priceSeries)->contains(fn ($rows) => $rows->isNotEmpty());
                    @endphp

                    @if (! $anyPrices)
                        <div class="rounded-lg bg-surface p-4 text-xs text-muted">
                            <strong class="text-ink">Historical performance data unavailable.</strong> No verified price, transaction, or valuation records are currently published for this project.
                        </div>
                    @else
                        <div class="space-y-6">
                            @foreach ($priceSeries as $label => $rows)
                                @if ($rows->isNotEmpty())
                                    <div>
                                        <h3 class="text-xs font-bold text-ink uppercase tracking-wider mb-2 flex items-center gap-2">
                                            <span class="size-2 rounded-full bg-brand-600"></span>
                                            {{ $label }}
                                        </h3>
                                        <div class="overflow-x-auto rounded-lg border border-line">
                                            <table class="table min-w-[500px]">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">Date Observed</th>
                                                        <th scope="col">Property Type</th>
                                                        <th scope="col" class="text-right">Observed Value</th>
                                                        <th scope="col">Source / Basis</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($rows as $o)
                                                        <tr>
                                                            <td class="text-xs text-muted">{{ $o->observed_on?->format('j M Y') ?? '—' }}</td>
                                                            <td class="font-semibold text-xs text-ink">{{ \Illuminate\Support\Str::headline($o->property_type) }}</td>
                                                            <td class="text-right font-bold text-xs text-brand-700 tabular-nums">
                                                                {{ $o->currency }} {{ number_format($o->average_amount) }} per {{ $o->size_value == 1 ? $o->size_unit : $o->sizeLabel() }}
                                                            </td>
                                                            <td class="text-xs text-muted">
                                                                {{ $o->source?->name ?? 'Verified record' }}
                                                                @if ($o->status !== 'verified')
                                                                    <span class="text-amber-700">(under check)</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- PART 5-F: RISKS, DATA GAPS & LIMITATIONS --}}
                <section class="card p-6 shadow-card border-l-4 border-l-amber-500 bg-amber-50/15" aria-labelledby="risks-heading">
                    <h2 id="risks-heading" class="text-base font-bold text-ink uppercase tracking-wider mb-2">
                        Risk Analysis & Audit Findings
                    </h2>
                    <p class="text-xs text-muted mb-4">
                        Comprehensive summary of potential regulatory risks, missing evidence points, and evaluation boundaries.
                    </p>

                    @if ($s && ! empty($s->risks))
                        <ul class="space-y-2 text-xs text-ink">
                            @foreach ($s->risks as $risk)
                                <li class="flex items-start gap-2">
                                    <span class="text-amber-600 font-bold shrink-0">⚠</span>
                                    <span>{{ $risk }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-muted">No high-severity regulatory or title risks have been registered against this development.</p>
                    @endif

                    @php
                        $missing = $s ? collect($s->factors)->reject(fn ($f) => $f['display_only'] ?? false)->filter(fn ($f) => ($f['score'] ?? null) === null)->pluck('label') : collect();
                    @endphp
                    @if ($missing->isNotEmpty())
                        <div class="mt-4 pt-3 border-t border-amber-200/60">
                            <span class="text-xs font-bold text-ink">Identified Evidence Gaps:</span>
                            <p class="text-xs text-muted mt-0.5">
                                Documentation missing for: <strong class="text-ink">{{ $missing->implode(', ') }}</strong>. Factors remain omitted from scoring without estimation until authentic documentation is submitted.
                            </p>
                        </div>
                    @endif
                </section>

            </div>

            {{-- RIGHT COLUMN: SOURCES, OFFICIAL REGISTRY & AUDIT LOGS --}}
            <aside class="space-y-8 min-w-0">

                {{-- Official Legal Records --}}
                <section class="card p-6 shadow-card" aria-labelledby="legal-registry-heading">
                    <h2 id="legal-registry-heading" class="text-base font-bold text-ink uppercase tracking-wider mb-2">
                        Official Legal Registry
                    </h2>
                    <p class="text-xs text-muted mb-4">
                        Regulatory approvals recorded by municipal and development authorities (LDA, CDA, SBCA, FDA, NAB).
                    </p>

                    @forelse ($legal as $r)
                        <div class="rounded-lg border border-line bg-page/50 p-3 mb-3 last:mb-0">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-bold text-sm text-ink">{{ $r->authority }}</span>
                                <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[11px] font-bold">
                                    {{ $r->statusLabel() }}
                                </span>
                            </div>
                            <p class="text-xs text-muted mt-1">
                                {{ config('investment_index.legal.record_types.'.$r->record_type) }}
                                @if ($r->reference) · Ref: <strong class="text-ink">{{ $r->reference }}</strong> @endif
                            </p>
                            <div class="mt-2 flex items-center justify-between text-[11.5px] text-muted">
                                <span>Issued: {{ $r->effectiveDate()->format('j M Y') }}</span>
                                @if (in_array($r->source?->type, IndexBrowser::PUBLIC_SOURCE_TYPES, true) && ($link = IndexBrowser::safeUrl($r->source_url)))
                                    <a href="{{ $link }}" target="_blank" rel="noopener nofollow" class="link text-xs font-semibold">Authority List ↗</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg bg-surface p-3 text-xs text-muted">
                            No verified statutory record is currently registered on file for this project.
                        </div>
                    @endforelse
                </section>

                {{-- PART 5-E: DATA SOURCES & CITATIONS --}}
                <section class="card p-6 shadow-card" aria-labelledby="sources-heading">
                    <h2 id="sources-heading" class="text-base font-bold text-ink uppercase tracking-wider mb-2">
                        Verified Data Sources
                    </h2>
                    <p class="text-xs text-muted mb-4">
                        Traceable public and audited sources utilized in evaluating this profile.
                    </p>

                    <div class="divide-y divide-line">
                        @forelse ($sources as $src)
                            <div class="py-2.5 flex items-start justify-between gap-3 text-xs">
                                <div>
                                    <span class="font-bold text-ink block">{{ $src['name'] }}</span>
                                    <span class="text-muted block mt-0.5">{{ $src['type'] }}</span>
                                    @if ($src['note'])
                                        <span class="text-[11px] text-muted block">{{ $src['note'] }}</span>
                                    @endif
                                </div>
                                @if ($src['url'])
                                    <a href="{{ $src['url'] }}" target="_blank" rel="noopener nofollow" class="btn btn-secondary btn-sm shrink-0">
                                        Source ↗
                                    </a>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-muted py-2">No external sources logged.</p>
                        @endforelse
                    </div>
                </section>

                {{-- Historical Score Changes --}}
                @if ($changes->isNotEmpty())
                    <section class="card p-6 shadow-card" aria-labelledby="changes-heading">
                        <h2 id="changes-heading" class="text-base font-bold text-ink uppercase tracking-wider mb-2">
                            Ranking & Score Audit Log
                        </h2>
                        <div class="divide-y divide-line text-xs">
                            @foreach ($changes->take(5) as $c)
                                <div class="py-2.5">
                                    <div class="flex items-center justify-between text-muted">
                                        <span>{{ $c->created_at->localTime()->format('j M Y') }}</span>
                                        <span class="font-semibold text-ink">
                                            {{ ['entered' => 'Entered ranking', 'left' => 'Left ranking', 'moved_up' => 'Moved up', 'moved_down' => 'Moved down', 'rescored' => 'Rescored', 'status' => 'Status change'][$c->change] ?? $c->change }}
                                            @if ($c->from_rank || $c->to_rank)
                                                ({{ $c->from_rank ? '#'.$c->from_rank : '—' }} → {{ $c->to_rank ? '#'.$c->to_rank : '—' }})
                                            @endif
                                        </span>
                                    </div>
                                    @if ($c->reasons)
                                        <p class="text-muted mt-1">
                                            {{ $c->reasons[0] ?? '' }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Disclaimers & Governance --}}
                <div class="rounded-xl border border-line bg-page/40 p-4 text-xs text-muted leading-relaxed">
                    <h3 class="font-bold text-ink mb-1">Standard Investment Disclaimer</h3>
                    <p>{{ config('meezan.investment.disclaimer') }}</p>
                    <p class="mt-2">Legal status shows only what official records on file say. "Unknown / Unverified" means no official record has been found here; it does not mean a project is illegal.</p>
                </div>

            </aside>
        </div>

    </div>
</x-layouts.app>
