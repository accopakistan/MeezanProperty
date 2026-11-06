<x-layouts.app title="Meezan Investment Index — Research, Verification & Investment Intelligence"
               description="The Meezan Investment Index researches housing societies, commercial plazas, apartment buildings, shopping malls and business towers across Pakistan, shows each project's legal status and evidence, and ranks only projects that verified evidence supports."
               :canonical="route('rankings.index')">
    @include('investment-index.partials.hero', [
        'title' => 'Meezan Investment Index — Research, Verification & Investment Intelligence',
        'intro' => 'Discover and compare real estate development projects across Pakistan using traceable market information, transparent investment analysis and verified project data. Where the evidence is not there, we say so.',
        'breadcrumbs' => [['Investment Rankings', null]],
        'run' => $run,
    ])

    <div class="container-page py-8">
        <section aria-labelledby="by-city-title">
            <h2 id="by-city-title" class="text-xl font-bold">Choose a city</h2>
            <p class="mt-1 text-[14px] text-muted">Actual records on file. This is what has been collected so far, not every development in each city.</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($cityCards as $c)
                    <a href="{{ route('rankings.city', $c['slug']) }}" class="card group flex min-w-0 flex-col overflow-hidden hover:ring-brand-300">
                        <span class="relative block h-24 bg-brand-900">
                            @if ($c['photo'])<img src="{{ asset($c['photo']['file']) }}" alt="" class="absolute inset-0 size-full object-cover opacity-80" style="object-position: {{ $c['photo']['position'] ?? 'center' }}" loading="lazy">@endif
                            <span class="absolute inset-0 bg-gradient-to-t from-brand-900/85 to-transparent"></span>
                            <span class="absolute bottom-2 left-3 text-lg font-bold text-white">{{ $c['name'] }}</span>
                        </span>
                        <dl class="grid grid-cols-2 gap-x-3 gap-y-1 p-3 text-[13px]">
                            <div><dt class="text-muted">Projects</dt><dd class="font-bold tabular-nums">{{ number_format($c['projects']) }}</dd></div>
                            <div><dt class="text-muted">Ranked</dt><dd class="font-bold tabular-nums">{{ number_format($c['ranked']) }}</dd></div>
                            <div><dt class="text-muted">Under research</dt><dd class="font-bold tabular-nums">{{ number_format($c['review'] + $c['insufficient']) }}</dd></div>
                            <div><dt class="text-muted">Excluded</dt><dd class="font-bold tabular-nums">{{ number_format($c['excluded']) }}</dd></div>
                        </dl>
                        <span class="mt-auto border-t border-line px-3 py-2 text-[13px] font-semibold text-brand-700 group-hover:underline">View {{ $c['name'] }} projects →</span>
                    </a>
                @endforeach
                <a href="{{ route('rankings.city', 'pakistan') }}" class="card flex flex-col justify-center gap-1 p-4 hover:ring-brand-300">
                    <span class="text-lg font-bold">All Pakistan</span>
                    <span class="text-[13px] text-muted">Dashboards across all seven cities</span>
                    <span class="mt-2 text-[13px] font-semibold text-brand-700">Open the dashboard →</span>
                </a>
            </div>

            <form method="GET" action="{{ route('rankings.index') }}" class="card mt-5 grid gap-3 p-4 sm:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                <x-field name="q" label="Search projects" placeholder="Project or developer name" />
                <x-field name="city" type="select" label="City" placeholder="All cities" :options="array_combine(\App\Support\Cities::names(), \App\Support\Cities::names())" />
                <x-field name="category" type="select" label="Category" required :options="collect($categories)->pluck('label', 'key')->all()" />
                <button class="btn btn-primary">Search</button>
            </form>
        </section>

        <section class="mt-10" aria-labelledby="leaderboards-title">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 id="leaderboards-title" class="text-xl font-bold">The five investment categories</h2>
                <p class="text-[13px] text-muted">{{ number_format($counts['assessed']) }} developments assessed · {{ number_format($counts['ranked']) }} ranked</p>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach (['Genuine projects on file' => $research['projects'], 'Ranked' => $research['ranked'], 'Under research' => $research['research'], 'Official legal status on file' => $research['legal_verified'], 'Awaiting our legal check' => $research['awaiting_check'], 'Excluded on legal grounds' => $research['excluded']] as $label => $value)
                    <div class="card p-3"><dt class="text-[12px] text-muted">{{ $label }}</dt><dd class="text-xl font-bold tabular-nums">{{ number_format($value) }}</dd></div>
                @endforeach
            </dl>
            @if ($research['published_at'])<p class="mt-2 text-[12px] text-muted">Latest calculation: {{ $research['published_at']->localTime()->format('j M Y') }}.</p>@endif
            <div class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($categories as $c)
                    <article class="card flex min-w-0 flex-col p-5">
                        <h3 class="text-base font-bold"><a href="{{ route('rankings.category', $c['key']) }}" class="hover:text-brand-700">{{ \App\Services\InvestmentIndex\IndexBrowser::leaderboardTitle($c['key'], min($c['counts']['ranked'], \App\Services\InvestmentIndex\IndexBrowser::publicTop())) }}</a></h3>
                        <p class="mt-0.5 text-[12px] text-muted">{{ $c['counts']['ranked'] }} ranked · {{ $c['counts']['insufficient'] }} insufficient data · {{ $c['counts']['excluded'] }} excluded</p>
                        @if ($c['top']->isEmpty() && $c['provisional']->isNotEmpty())
                            <p class="mt-3 text-[12px] font-semibold text-amber-800">Provisional research ranking: not fully verified, not a recommendation</p>
                            <ol class="mt-1 divide-y divide-line">
                                @foreach ($c['provisional'] as $s)
                                    <li class="flex items-center gap-3 py-2">
                                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-amber-50 text-[12px] font-bold text-amber-800">P{{ $s->provisional_rank }}</span>
                                        <span class="min-w-0 flex-1"><a href="{{ route('rankings.project', $s->project) }}" class="link block truncate font-semibold">{{ $s->project->name }}</a><span class="block truncate text-[12px] text-muted">{{ $s->project->city }} · evidence {{ number_format($s->provisional_coverage * 100, 0) }}% of weight</span></span>
                                        <span class="text-base font-bold tabular-nums">{{ number_format($s->provisional_score, 1) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @elseif ($c['top']->isEmpty() && $c['documented']->isNotEmpty())
                            <p class="mt-3 text-[12px] font-semibold text-muted">Most documented so far, not a ranking</p>
                            <ul class="mt-1 divide-y divide-line">
                                @foreach ($c['documented'] as $s)
                                    <li class="py-2"><a href="{{ route('rankings.project', $s->project) }}" class="link block truncate font-semibold">{{ $s->project->name }}</a><span class="block truncate text-[12px] text-muted">{{ $s->project->city }} · {{ \App\Services\InvestmentIndex\IndexBrowser::legalLine($s) }}</span></li>
                                @endforeach
                            </ul>
                        @elseif ($c['top']->isEmpty())
                            <p class="mt-4 rounded-lg bg-surface px-3 py-3 text-[13px]"><span class="font-semibold">Insufficient Verified Data.</span> <span class="text-muted">No {{ mb_strtolower($c['singular']) }} has enough verified evidence to be ranked yet.</span></p>
                        @else
                            <ol class="mt-3 divide-y divide-line">
                                @foreach ($c['top'] as $s)
                                    <li class="flex items-center gap-3 py-2">
                                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-[13px] font-bold text-brand-700">{{ $s->category_rank }}</span>
                                        <span class="min-w-0 flex-1"><a href="{{ route('rankings.project', $s->project) }}" class="link block truncate font-semibold">{{ $s->project->name }}</a><span class="text-[12px] text-muted">{{ $s->project->city }} · confidence {{ number_format($s->confidence_score, 0) }}</span></span>
                                        <span class="text-base font-bold tabular-nums">{{ number_format($s->score, 1) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                        <a href="{{ route('rankings.category', $c['key']) }}" class="mt-auto pt-4 text-[13px] font-semibold text-brand-700 hover:underline">View {{ mb_strtolower($c['label']) }} →</a>
                    </article>
                @endforeach

                <article class="card flex min-w-0 flex-col border-brand-200 bg-brand-50/40 p-5">
                    <h3 class="text-base font-bold"><a href="{{ route('rankings.overall') }}" class="hover:text-brand-700">Overall Meezan Investment Index</a></h3>
                    <p class="mt-0.5 text-[12px] text-muted">Every category on one scale: each project's standing within its own category, weighted by data confidence.</p>
                    @if ($overall->isEmpty())
                        <p class="mt-4 rounded-lg bg-white px-3 py-3 text-[13px]"><span class="font-semibold">Insufficient Verified Data.</span> <span class="text-muted">A category enters the Overall Index once it has enough ranked projects to compare.</span></p>
                    @else
                        <ol class="mt-3 divide-y divide-line">
                            @foreach ($overall->take(5) as $s)
                                <li class="flex items-center gap-3 py-2">
                                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-white text-[13px] font-bold text-brand-700">{{ $s->overall_rank }}</span>
                                    <span class="min-w-0 flex-1"><a href="{{ route('rankings.project', $s->project) }}" class="link block truncate font-semibold">{{ $s->project->name }}</a><span class="text-[12px] text-muted">{{ $s->categoryLabel() }} · {{ $s->project->city }}</span></span>
                                    <span class="text-base font-bold tabular-nums">{{ number_format($s->overall_index, 1) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    <a href="{{ route('rankings.overall') }}" class="mt-auto pt-4 text-[13px] font-semibold text-brand-700 hover:underline">View the Overall Index →</a>
                </article>
            </div>
        </section>

        <section class="mt-10 grid gap-5 lg:grid-cols-3" aria-label="How the Index works">
            <div class="card p-5">
                <h2 class="text-base font-bold">Evidence, not estimates</h2>
                <p class="mt-2 text-[14px] text-muted">Each factor is scored only from verified records with a source and date: official approval lists, site inspections, audited documents and owner documents checked by our staff. Asking prices never count as sales, rental yields need verified rent and price, and nothing is filled in by AI.</p>
            </div>
            <div class="card p-5">
                <h2 class="text-base font-bold">Legal safety first</h2>
                <p class="mt-2 text-[14px] text-muted">A project on an official illegal-schemes list, with cancelled approval or a serious legal finding, is excluded from recommendations. A legal status we could not verify is never shown as approved.</p>
            </div>
            <div class="card p-5">
                <h2 class="text-base font-bold">No paid placement</h2>
                <p class="mt-2 text-[14px] text-muted">Rankings cannot be bought: subscriptions, packages and featured listings play no part. Results are recalculated automatically whenever verified data changes, and every change is recorded with its reason.</p>
            </div>
        </section>

        <p class="mt-8 text-[13px] text-muted">{{ config('meezan.investment.disclaimer') }} <a href="{{ route('rankings.methodology') }}" class="link">Methodology</a></p>
    </div>
</x-layouts.app>
