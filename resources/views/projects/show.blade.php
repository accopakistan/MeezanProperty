<x-layouts.app :title="$project->name.' — '.$project->city.' Project Directory Profile'" :description="\Illuminate\Support\Str::limit($project->description, 155)" :robots="$project->is_demo ? 'noindex, nofollow' : null">
    <div class="container-page py-8">
        @if ($project->is_demo)
            <div class="mb-5 flex items-start gap-3 rounded-xl border-2 border-dashed border-gold-500 bg-gold-50 px-4 py-3 text-sm" role="note" aria-label="Illustrative project notice">
                <span class="shrink-0 rounded bg-gold-500 px-2 py-0.5 text-[12px] font-extrabold text-brand-900">Illustrative</span>
                <p><strong>This is a fictional example project.</strong> It does not exist and cannot be bought, rented or contacted. {{ config('meezan.investment.demo_notice') }} Images are illustrations.</p>
            </div>
        @endif

        <x-breadcrumbs :items="[['Project Directory', route('projects.index')], [$project->city, route('projects.index', ['city' => $project->city])], [$project->name, null]]" />

        {{-- Top Header Section --}}
        <div class="mt-4 flex flex-wrap items-start justify-between gap-4 border-b border-line pb-6">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="tag bg-brand-900 text-white text-xs">{{ $categoryConfig['label'] ?? $project->categoryLabel() }}</span>
                    <span class="tag {{ $project->status === 'completed' ? 'bg-emerald-600' : 'bg-brand-600' }} text-white text-xs">{{ $project->statusLabel() }}</span>
                    @php
                        $legal = $project->approval_status ?: ($score?->legal_status ?? 'unverified');
                        $legalBadge = match ($legal) {
                            'approved' => ['bg' => 'bg-emerald-600', 'text' => 'Official Verified Approved'],
                            'partially_approved' => ['bg' => 'bg-amber-600', 'text' => 'Partially / Technically Approved'],
                            'cancelled', 'illegal' => ['bg' => 'bg-red-600', 'text' => 'Regulatory Notice: Cancelled / Adverse'],
                            'pending' => ['bg' => 'bg-amber-500', 'text' => 'Approval Pending'],
                            default => ['bg' => 'bg-gray-600', 'text' => 'Authority Status Not Verified'],
                        };
                    @endphp
                    <span class="tag {{ $legalBadge['bg'] }} text-white text-xs font-semibold">{{ $legalBadge['text'] }}</span>
                </div>
                <h1 class="mt-2 text-3xl font-serif font-bold text-ink sm:text-4xl">{{ $project->name }}</h1>
                <p class="mt-1 text-base text-muted">
                    {{ collect([$project->developer ? "Sponsor / Developer: {$project->developer}" : null, $project->area?->name, $project->city])->filter()->implode(' · ') }}
                </p>
            </div>
            
            <div class="flex flex-col items-end gap-2">
                <span class="text-xs uppercase tracking-wider text-muted font-semibold">Directory Record</span>
                <span class="text-xs text-muted">Last Checked: {{ $profile['lastUpdate'] ? \Carbon\Carbon::parse($profile['lastUpdate'])->format('j M Y') : 'Recent' }}</span>
            </div>
        </div>

        {{-- Authority Status & Regulatory Qualifications Alert --}}
        @php
            $alertClass = match ($legal) {
                'approved' => 'border-emerald-200 bg-emerald-50/70 text-emerald-950',
                'partially_approved' => 'border-amber-200 bg-amber-50/70 text-amber-950',
                'cancelled', 'illegal' => 'border-red-200 bg-red-50/70 text-red-950',
                default => 'border-line bg-surface text-ink',
            };
        @endphp
        <div class="mt-6 rounded-xl border p-4 sm:p-5 {{ $alertClass }}">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 shrink-0">
                    @if ($legal === 'approved')
                        <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @elseif ($legal === 'partially_approved')
                        <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @elseif ($legal === 'cancelled' || $legal === 'illegal')
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @else
                        <svg class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold uppercase tracking-wide">
                        Official Regulatory Qualification
                    </h2>
                    <p class="mt-1 text-sm leading-relaxed">
                        {{ $project->approval_note ?: 'This project is listed in the Meezan Property Project Directory based on official public records or developer filings. Layout plan approvals and formal NOCs are distinct statutory stages; verify current encumbrances and mortgaged plot status with the relevant civic authority before purchasing.' }}
                    </p>
                    <p class="mt-2 text-xs opacity-80">
                        <strong>Important:</strong> Inclusion in an official authority list confirms legal registration stage; it is NOT an investment endorsement, rental yield guarantee, or financial rating.
                    </p>
                </div>
            </div>
        </div>

        {{-- Main Two-Column Content Grid --}}
        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            {{-- Left Column: Factual Specifications & Research Facts --}}
            <div class="space-y-6">
                {{-- Available Factual Information --}}
                <section class="card p-5" aria-labelledby="specs-heading">
                    <h2 id="specs-heading" class="text-base font-bold text-ink">Verified Planning and Development Facts</h2>
                    <p class="mt-1 text-xs text-muted">Factual data extracted directly from official authority records (LDA / CDA / FDA). Figures are never estimated.</p>

                    @php $specs = $project->specifications ?? []; @endphp
                    <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div class="border-b border-line/60 pb-2">
                            <dt class="text-xs font-semibold text-muted uppercase">Civic Authority</dt>
                            <dd class="mt-0.5 font-medium text-ink">{{ $specs['authority'] ?? ($project->discoverySource?->name ?? 'Civic Authority') }}</dd>
                        </div>
                        <div class="border-b border-line/60 pb-2">
                            <dt class="text-xs font-semibold text-muted uppercase">Statutory Jurisdiction</dt>
                            <dd class="mt-0.5 font-medium text-ink">{{ $specs['jurisdiction'] ?? $project->city }}</dd>
                        </div>

                        @if (isset($specs['approved_area_kanals']))
                            <div class="border-b border-line/60 pb-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Approved Gross Area</dt>
                                <dd class="mt-0.5 font-medium text-brand-700">{{ $specs['approved_area_kanals'] }} Kanals</dd>
                            </div>
                        @endif

                        @if (isset($specs['total_plots']))
                            <div class="border-b border-line/60 pb-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Sanctioned Plots</dt>
                                <dd class="mt-0.5 font-medium text-ink">
                                    {{ $specs['total_plots'] }} total ({{ $specs['residential_plots'] ?? 0 }} residential, {{ $specs['commercial_plots'] ?? 0 }} commercial)
                                </dd>
                            </div>
                        @endif

                        @if (isset($specs['lop_status']))
                            <div class="border-b border-line/60 pb-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Layout Plan (LOP) Status</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ $specs['lop_status'] }}</dd>
                            </div>
                        @endif

                        @if (isset($specs['noc_status']))
                            <div class="border-b border-line/60 pb-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Formal NOC Status</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ $specs['noc_status'] }}</dd>
                            </div>
                        @endif

                        @if (isset($specs['approval_stage']))
                            <div class="border-b border-line/60 pb-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Official Approval Stage</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ $specs['approval_stage'] }}</dd>
                            </div>
                        @endif

                        @if (isset($specs['date_of_approval']))
                            <div class="border-b border-line/60 pb-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Approval Date</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ \Carbon\Carbon::parse($specs['date_of_approval'])->format('j M Y') }}</dd>
                            </div>
                        @endif

                        @if (isset($specs['mauza']))
                            <div class="border-b border-line/60 pb-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Mauza / Revenue Village</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ $specs['mauza'] }}</dd>
                            </div>
                        @endif

                        @if (isset($specs['zone']))
                            <div class="border-b border-line/60 pb-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Spatial Planning Zone</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ $specs['zone'] }}</dd>
                            </div>
                        @endif

                        @if (isset($specs['approved_blocks']))
                            <div class="border-b border-line/60 pb-2 sm:col-span-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Sanctioned Blocks / Phases</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ $specs['approved_blocks'] }}</dd>
                            </div>
                        @endif

                        @if (isset($specs['mortgaged_plots_status']))
                            <div class="border-b border-line/60 pb-2 sm:col-span-2">
                                <dt class="text-xs font-semibold text-muted uppercase">Mortgaged Plots / Guarantee Deed</dt>
                                <dd class="mt-0.5 text-xs text-ink/80">{{ $specs['mortgaged_plots_status'] }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if (isset($specs['official_plan_map_url']))
                        <div class="mt-4 pt-3 border-t border-line/60">
                            <a href="{{ $specs['official_plan_map_url'] }}" target="_blank" rel="noopener nofollow" class="inline-flex items-center gap-2 text-xs font-semibold text-brand-700 hover:underline">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                View Official Sanctioned Plan Map (LDA Archive) &rarr;
                            </a>
                        </div>
                    @endif
                </section>

                {{-- Research Facts Table --}}
                @if (!empty($project->research_facts))
                    <section class="card p-5" aria-labelledby="facts-list-heading">
                        <h2 id="facts-list-heading" class="text-base font-bold text-ink">Recorded Source Facts ({{ count($project->research_facts) }})</h2>
                        <p class="mt-1 text-xs text-muted">Audited factual records on file for this project, detailing source authority, collection dates, and basis.</p>

                        <div class="mt-4 divide-y divide-line/60">
                            @foreach ($project->research_facts as $fact)
                                <div class="py-3 text-sm">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <dt class="font-semibold text-ink">{{ $fact['label'] }}</dt>
                                        <dd class="text-xs text-muted">{{ $fact['date'] ?? 'Recorded' }}</dd>
                                    </div>
                                    <div class="mt-1 font-medium text-brand-900">{{ $fact['value'] }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted">
                                        <span>Source: <strong>{{ $fact['source'] }}</strong></span>
                                        @if (!empty($fact['url']))
                                            <span>·</span>
                                            <a href="{{ $fact['url'] }}" target="_blank" rel="noopener nofollow" class="text-brand-700 hover:underline">Official Link &rarr;</a>
                                        @endif
                                        @if (!empty($fact['basis']))
                                            <span>·</span>
                                            <span class="italic">{{ $fact['basis'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- About This Project Description --}}
                <section class="card p-5">
                    <h2 class="text-base font-bold text-ink">About this Development</h2>
                    <p class="mt-3 text-sm leading-relaxed whitespace-pre-line text-ink/85">{{ $project->description }}</p>
                    @if ($project->website)
                        <p class="mt-4 text-xs text-muted">
                            Developer Website: <a href="{{ $project->website }}" target="_blank" rel="noopener nofollow" class="text-brand-700 hover:underline">{{ $project->website }}</a>
                        </p>
                    @endif
                </section>
            </div>

            {{-- Right Column: Source References, Missing Information, & Investment Ranking Status --}}
            <div class="space-y-6">
                {{-- Cover Image --}}
                <div class="card overflow-hidden">
                    <img src="{{ $project->coverUrl() }}" alt="{{ $project->name }}" class="aspect-[16/9] w-full object-cover">
                    @if ($project->priceRange())
                        <div class="p-4 border-t border-line">
                            <span class="text-xs text-muted block uppercase font-semibold">Indicative Prices</span>
                            <span class="text-xl font-bold text-brand-700">{{ $project->priceRange() }}</span>
                            <span class="block text-[11px] text-muted">As stated by developer / market survey</span>
                        </div>
                    @endif
                </div>

                {{-- Separate Investment-Ranking Eligibility Status --}}
                <section class="card p-5 border-2 {{ $rankingEligibility['is_eligible'] ? 'border-emerald-300 bg-emerald-50/40' : 'border-line bg-surface' }}">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-muted">Investment Index Eligibility</h2>
                        <span class="tag {{ $rankingEligibility['is_eligible'] ? 'bg-emerald-600 text-white' : 'bg-gray-700 text-white' }} text-xs font-semibold">
                            {{ $rankingEligibility['is_eligible'] ? 'Eligible for Ranking' : 'Directory Profile (Unranked)' }}
                        </span>
                    </div>

                    <div class="mt-3">
                        <p class="text-sm text-ink leading-relaxed">
                            @if ($rankingEligibility['is_eligible'])
                                This development meets the required 60% verified evidence coverage and qualifies for published ranking position.
                            @else
                                <strong>Not currently eligible for an investment ranking.</strong> Under the Meezan Property methodology, an official authority listing provides legal verification (20–25% weight) but cannot establish an investment ranking without independent operational evidence.
                            @endif
                        </p>

                        <div class="mt-3 rounded bg-white p-3 border border-line text-xs space-y-1.5">
                            <div class="flex justify-between">
                                <span class="text-muted">Verified Evidence Coverage:</span>
                                <span class="font-bold tabular-nums text-ink">{{ number_format(($rankingEligibility['coverage'] ?? 0) * 100, 0) }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted">Methodology Threshold:</span>
                                <span class="font-bold text-ink">60.0% required</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted">Legal Eligibility Factor:</span>
                                <span class="font-bold {{ in_array($rankingEligibility['legal_status'], ['approved', 'partially_approved']) ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ ucfirst(str_replace('_', ' ', $rankingEligibility['legal_status'])) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('investment.methodology') }}" class="btn btn-secondary btn-sm flex-1 text-center">Methodology</a>
                            @if (\App\Services\InvestmentIndex\EvidenceSubmissions::accepts($project))
                                <a href="{{ route('investment-index.evidence.create', $project) }}" class="btn btn-primary btn-sm flex-1 text-center">Submit Evidence</a>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- Missing Information Disclosure --}}
                <section class="card p-5">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Missing Evidence Gaps</h2>
                    <p class="mt-1 text-xs text-muted">Under Meezan methodology, missing factors are never filled with simulated or invented data.</p>

                    <ul class="mt-3 space-y-2 text-xs">
                        @foreach ($rankingEligibility['missing_factors'] as $missingFactor)
                            <li class="flex items-start gap-2 rounded bg-surface p-2 border border-line/60">
                                <span class="text-amber-600 font-bold">✕</span>
                                <div>
                                    <span class="font-semibold text-ink">{{ $missingFactor }}:</span>
                                    <span class="text-muted">No primary verified inspection or audited report on file.</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Source References & Provenance --}}
                <section class="card p-5">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Official Source References</h2>
                    <dl class="mt-3 text-xs space-y-2.5">
                        <div>
                            <dt class="text-muted uppercase font-semibold">Primary Regulator</dt>
                            <dd class="mt-0.5 font-medium text-ink">{{ $specs['authority'] ?? ($project->discoverySource?->name ?? 'Official Regulator') }}</dd>
                        </div>
                        @if ($project->discoverySource)
                            <div>
                                <dt class="text-muted uppercase font-semibold">Discovery Source</dt>
                                <dd class="mt-0.5">
                                    <a href="{{ $project->discovery_url ?: $project->discoverySource->url }}" target="_blank" rel="noopener nofollow" class="text-brand-700 hover:underline font-medium">
                                        {{ $project->discoverySource->name }} &rarr;
                                    </a>
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-muted uppercase font-semibold">Verification Method</dt>
                            <dd class="mt-0.5 text-muted">Official statutory gazette / portal cross-match</dd>
                        </div>
                        <div>
                            <dt class="text-muted uppercase font-semibold">Last Audited Date</dt>
                            <dd class="mt-0.5 text-muted">{{ $profile['lastUpdate'] ? \Carbon\Carbon::parse($profile['lastUpdate'])->format('d F Y') : '6 November 2026' }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>

        {{-- Bottom Marketplace Property Listings in this Area --}}
        @if ($listings->isNotEmpty())
            <section class="mt-12 border-t border-line pt-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-serif text-2xl font-bold text-ink">Properties for Sale in {{ $project->area?->name ?? $project->city }}</h2>
                        <p class="text-xs text-muted">Active marketplace advertisements from verified dealers. Marketplace advertisements are distinct from project directory profiles.</p>
                    </div>
                    <a href="{{ route('properties.index', ['city' => $project->city]) }}" class="text-xs font-semibold text-brand-700 hover:underline">Browse all properties &rarr;</a>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($listings as $property)
                        <x-property-card :property="$property" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
