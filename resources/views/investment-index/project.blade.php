@php
    $s = $score;
    $categoryConfig = $category ? config("investment_index.categories.$category") : null;
    $legalLabel = $s ? \App\Services\InvestmentIndex\IndexBrowser::legalLabel($s->legal_status) : 'Not verified';
    $operational = $s?->operational_status ? config('investment_index.context_evidence.operational_status.levels.'.$s->operational_status, $s->operational_status) : null;
    $marketHistory = $s ? ($s->factors['market_performance'] ?? null) : null;
@endphp
<x-layouts.app :title="$project->name.' — Meezan Investment Index profile'"
               :description="$project->name.' ('.($categoryConfig['singular'] ?? 'development').', '.$project->city.'): legal verification, investment score, data confidence, risk and sources in the Meezan Investment Index.'"
               :canonical="route('rankings.project', $project)" :noindex="! $s || $s->status !== 'ranked'">
    <div class="border-b border-line bg-white">
        <div class="container-page py-6">
            <x-breadcrumbs :items="[['Investment Rankings', route('rankings.index')], [$categoryConfig['label'] ?? 'Projects', $category ? route('rankings.category', $category) : null], [$project->name, null]]" />
            <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-[0.14em] text-brand-700 uppercase">{{ $categoryConfig['singular'] ?? $project->categoryLabel() }} · {{ $project->city }}</p>
                    <h1 class="mt-1 text-3xl font-bold">{{ $project->name }}</h1>
                    <p class="mt-1 text-muted">{{ collect([$project->area?->name, $project->city])->filter()->implode(', ') }}@if ($project->developer) · Developer: {{ $project->developer }}@endif</p>
                </div>
                @include('investment-index.partials.status', ['status' => $s?->status ?? 'insufficient'])
            </div>
        </div>
    </div>

    <div class="container-page grid gap-6 py-8 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <section class="card p-5" aria-labelledby="summary-title">
                <h2 id="summary-title" class="text-base font-bold">Investment Index summary</h2>
                @if (! $s)
                    <p class="mt-3 rounded-lg bg-surface px-4 py-3 text-[14px]"><span class="font-semibold">Insufficient Verified Data — Research in Progress.</span> <span class="text-muted">This development has not been assessed in a published run yet.</span></p>
                @endif
                <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div><dt class="text-[12px] text-muted">Investment score</dt><dd class="text-2xl font-bold tabular-nums">{{ $s && $s->score !== null && $s->status === 'ranked' ? number_format($s->score, 1) : '—' }}</dd>
                        @if ($s && $s->status === 'not_eligible' && $s->score !== null)<dd class="text-[12px] text-muted">Scored {{ number_format($s->score, 1) }}; not ranked</dd>@endif</div>
                    <div><dt class="text-[12px] text-muted">Ranking position</dt><dd class="text-2xl font-bold tabular-nums">{{ $s?->category_rank ? '#'.$s->category_rank : '—' }}</dd>
                        @if ($s?->overall_rank)<dd class="text-[12px] text-muted">Overall Index #{{ $s->overall_rank }}</dd>@endif</div>
                    <div><dt class="text-[12px] text-muted">Data confidence</dt><dd class="text-2xl font-bold tabular-nums">{{ $s ? number_format($s->confidence_score, 0) : '—' }}</dd><dd class="text-[12px] text-muted">{{ $s ? \App\Services\InvestmentIndex\IndexBrowser::confidenceLabel($s->confidence_level) : 'No evidence' }}</dd>
                        @if ($s)<dd class="text-[12px] text-muted">Evidence covers {{ number_format(($s->coverage ?? 0) * 100, 0) }}% of the weight</dd>@endif</div>
                    <div><dt class="text-[12px] text-muted">Risk</dt><dd class="text-base font-bold">{{ $s?->riskLabel() ?? '—' }}</dd></div>
                </dl>
                @if ($s && $s->provisional_rank && \App\Services\InvestmentIndex\IndexBrowser::provisionalPublic())
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[14px] text-amber-900"><strong>Provisional research position P{{ $s->provisional_rank }}, score {{ number_format($s->provisional_score, 1) }}.</strong> Not fully verified and not an investment recommendation. Evidence covers {{ number_format($s->provisional_coverage * 100, 0) }}% of the weight, including {{ $s->provisional_unchecked }} {{ \Illuminate\Support\Str::plural('record', (int) $s->provisional_unchecked) }} staff have not yet checked.</p>
                @endif
                @if ($s && $s->status !== 'ranked')
                    <p class="mt-4 text-[14px]"><span class="font-semibold">Insufficient Verified Data — Research in Progress.</span> <span class="text-muted">An unranked project has not scored low: there is not yet enough verified evidence to compare it.</span></p>
                    <ul class="mt-3 space-y-1 rounded-lg bg-surface px-4 py-3 text-[14px]">
                        @foreach (collect($s->reasons)->reject(fn ($r) => str_starts_with($r, 'Confidence:'))->take(4) as $reason)<li>{{ $reason }}</li>@endforeach
                    </ul>
                @endif
            </section>

            <section class="card p-5" aria-labelledby="data-title">
                <h2 id="data-title" class="text-base font-bold">Data status and origin</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-2 text-[14px] sm:grid-cols-[auto_1fr]">
                    <dt class="text-muted">Data status</dt><dd class="font-semibold">{{ $dataStatus }}</dd>
                    <dt class="text-muted">Last data update</dt><dd>{{ $lastUpdate ? \Illuminate\Support\Carbon::parse($lastUpdate)->localTime()->format('j M Y') : 'Not recorded' }}</dd>
                    @if ($project->discoverySource)
                        <dt class="text-muted">First found in</dt>
                        <dd>@if (\App\Services\InvestmentIndex\IndexBrowser::safeUrl($project->discovery_url))<a href="{{ $project->discovery_url }}" class="link" target="_blank" rel="noopener nofollow">{{ $project->discoverySource->name }}</a>@else{{ $project->discoverySource->name }}@endif
                            <span class="block text-[12px] text-muted">{{ $project->discoverySource->typeLabel() }}@if ($project->discovered_at), read {{ $project->discovered_at->format('j M Y') }}@endif</span></dd>
                    @endif
                    @if ($project->category_basis === 'inferred')
                        <dt class="text-muted">Category</dt><dd class="text-[13px]">Inferred from the project's name; the source does not state it. Needs checking.</dd>
                    @endif
                    @if ($project->aliases->isNotEmpty())
                        <dt class="text-muted">Also listed as</dt><dd>{{ $project->aliases->pluck('alias')->implode(', ') }}</dd>
                    @endif
                    <dt class="text-muted">Reported development status</dt><dd>{{ $project->statusLabel() }}@if ($project->status !== 'unverified') <span class="text-[12px] text-muted">(as reported by the source, not independently verified)</span>@endif</dd>
                    @if ($project->website)
                        <dt class="text-muted">Website</dt><dd><a href="{{ $project->website }}" class="link break-all" target="_blank" rel="noopener nofollow">{{ $project->website }}</a> <span class="text-[12px] text-muted">(developer-reported information)</span></dd>
                    @endif
                </dl>
                @php $missing = $s ? collect($s->factors)->reject(fn ($f) => $f['display_only'] ?? false)->filter(fn ($f) => ($f['score'] ?? null) === null)->pluck('label') : collect(); @endphp
                @if ($missing->isNotEmpty())
                    <p class="mt-3 text-[13px] text-muted"><span class="font-semibold text-ink">Missing information:</span> {{ $missing->implode(', ') }}. Each stays missing until verified evidence exists; nothing is estimated.</p>
                @endif
            </section>

            <section class="card p-5" aria-labelledby="facts-title">
                <h2 id="facts-title" class="text-base font-bold">Verified facts</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-2 text-[14px] sm:grid-cols-[auto_1fr]">
                    <dt class="text-muted">Category</dt><dd>{{ $categoryConfig['label'] ?? $project->categoryLabel() }}</dd>
                    <dt class="text-muted">City</dt><dd>{{ $project->city }}</dd>
                    <dt class="text-muted">Developer</dt><dd>{{ $project->developer ?: 'Not stated' }}</dd>
                    <dt class="text-muted">Legal verification</dt><dd class="font-semibold">{{ $legalLabel }}</dd>
                    <dt class="text-muted">Operational status</dt><dd>{{ $operational ?? 'No verified record' }}</dd>
                    <dt class="text-muted">Development progress</dt><dd>{{ $s?->development_progress !== null && $s ? number_format($s->development_progress, 0).'%' : 'No verified record' }}</dd>
                    <dt class="text-muted">Verified rental yield</dt><dd>{{ $s?->verified_yield !== null && $s ? number_format($s->verified_yield, 2).'% gross' : 'Insufficient Verified Data' }}</dd>
                    <dt class="text-muted">Historical performance</dt><dd>{{ $marketHistory && $marketHistory['score'] !== null ? $marketHistory['value'].' (verified transactions and valuations)' : 'Historical performance data unavailable.' }}</dd>
                    <dt class="text-muted">Last verification</dt><dd>{{ $s?->last_verified_at?->localTime()->format('j M Y') ?? 'Never' }}</dd>
                </dl>
            </section>

            @if ($facts->isNotEmpty())
                <section class="card p-5" aria-labelledby="stated-title">
                    <h2 id="stated-title" class="text-base font-bold">What the sources state</h2>
                    <p class="mt-1 text-[13px] text-muted">Copied from each source, with its date. These facts describe the project and are not used in any score.</p>
                    <dl class="mt-3 space-y-3 text-[14px]">
                        @foreach ($facts as $fact)
                            <div>
                                <dt class="text-muted">{{ $fact['label'] }}</dt>
                                <dd>{{ $fact['value'] }}
                                    <span class="block text-[12px] text-muted">@if (\App\Services\InvestmentIndex\IndexBrowser::safeUrl($fact['url']))<a href="{{ $fact['url'] }}" class="link" target="_blank" rel="noopener nofollow">{{ $fact['source'] }}</a>@else{{ $fact['source'] }}@endif · {{ $fact['basis'] }} · {{ \Illuminate\Support\Carbon::parse($fact['date'])->format('j M Y') }}</span></dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            @if ($reported->isNotEmpty() || $pendingLegal->isNotEmpty())
                <section class="card p-5" aria-labelledby="reported-title">
                    <h2 id="reported-title" class="text-base font-bold">Reported, awaiting our check</h2>
                    <p class="mt-1 text-[13px] text-muted">We have read these from the source named, but staff have not yet confirmed them. They count towards no score and no legal status.</p>
                    <ul class="mt-3 space-y-3 text-[14px]">
                        @foreach ($pendingLegal as $r)
                            <li><span class="font-semibold">{{ $r->authority }}: on its list as {{ mb_strtolower(str_replace('Verified ', '', $r->statusLabel())) }}, not yet checked by us</span>@if ($r->issued_on) · {{ $r->issued_on->format('j M Y') }}@endif
                                <span class="block text-[12px] text-muted">@if ($link = \App\Services\InvestmentIndex\IndexBrowser::safeUrl($r->source_url))<a href="{{ $link }}" class="link" target="_blank" rel="noopener nofollow">{{ $r->source?->name }}</a>@else{{ $r->source?->name }}@endif</span></li>
                        @endforeach
                        @foreach ($reported as $e)
                            <li><span class="font-semibold">{{ config("investment_index.categories.$category.factors.{$e->factor}.label") ?? \Illuminate\Support\Str::headline($e->factor) }}:</span>
                                {{ $e->value_numeric !== null ? rtrim(rtrim(number_format($e->value_numeric, 2), '0'), '.').'%' : (config('investment_index.context_evidence.operational_status.levels.'.$e->value_level) ?? \Illuminate\Support\Str::headline((string) $e->value_level)) }}
                                <span class="block text-[12px] text-muted">{{ $e->excerpt }}</span>
                                <span class="block text-[12px] text-muted">@if ($link = \App\Services\InvestmentIndex\IndexBrowser::safeUrl($e->source_url))<a href="{{ $link }}" class="link" target="_blank" rel="noopener nofollow">{{ $e->source?->name }}</a>@else{{ $e->source?->name }}@endif · {{ $e->source?->typeLabel() }} · {{ $e->observed_on?->format('j M Y') }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="card min-w-0 p-5" aria-labelledby="market-title">
                <h2 id="market-title" class="text-base font-bold">Price and rental records</h2>
                @php $anyPrices = collect($priceSeries)->contains(fn ($rows) => $rows->isNotEmpty()); @endphp
                @if (! $anyPrices)
                    <p class="mt-2 text-[14px] text-muted">Historical performance data unavailable. No price, rental or valuation record is on file for this project.</p>
                @else
                    <p class="mt-1 text-[13px] text-muted">Each kind of price stays in its own list. An asking price is not a sale, and a tax value is not a market price.</p>
                    @foreach ($priceSeries as $label => $rows)
                        @if ($rows->isNotEmpty())
                            <h3 class="mt-4 text-[14px] font-semibold">{{ $label }}</h3>
                            <ul class="mt-1 divide-y divide-line text-[14px]">
                                @foreach ($rows as $o)
                                    <li class="py-2">{{ $o->currency }} {{ number_format($o->average_amount) }} per {{ $o->size_value == 1 ? $o->size_unit : $o->sizeLabel() }} · {{ \Illuminate\Support\Str::headline($o->property_type) }}
                                        <span class="block text-[12px] text-muted">{{ $o->observed_on?->format('j M Y') }} · @if ($link = \App\Services\InvestmentIndex\IndexBrowser::safeUrl($o->source?->url))<a href="{{ $link }}" class="link" target="_blank" rel="noopener nofollow">{{ $o->source?->name }}</a>@else{{ $o->source?->name }}@endif @if ($o->status !== 'verified') · awaiting our check @endif</span></li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                    <p class="mt-3 text-[13px] text-muted">Historical performance is not calculated from these records.@if ($priceSeries['FBR tax valuations (not market prices)']->isNotEmpty()) Each FBR figure comes from the notification named and may since have been replaced by a newer one.@endif</p>
                @endif
            </section>

            @if ($s)
                <section class="card min-w-0 p-5" aria-labelledby="factors-title">
                    <h2 id="factors-title" class="text-base font-bold">Score breakdown</h2>
                    <p class="mt-1 text-[13px] text-muted">Each factor is scored 0–100 only from verified evidence; a factor without it is left out, never estimated.</p>
                    <div class="relative mt-3 overflow-x-auto">
                        <table class="table table-compact min-w-[560px]">
                            <thead><tr><th scope="col">Factor</th><th scope="col" class="text-right">Weight</th><th scope="col">Verified value</th><th scope="col" class="text-right">Score</th></tr></thead>
                            <tbody>
                                @foreach (collect($s->factors)->reject(fn ($f) => $f['display_only'] ?? false) as $f)
                                    <tr>
                                        <td>{{ $f['label'] }}</td>
                                        <td class="text-right tabular-nums">{{ $f['weight'] }}%</td>
                                        <td class="text-[13px]">{{ $f['value'] ?? 'Insufficient Verified Data' }}@if (! $f['value'] && $f['missing'])<span class="block text-muted">{{ $f['missing'] }}</span>@endif</td>
                                        <td class="text-right tabular-nums">{{ $f['score'] !== null ? number_format($f['score'], 0) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                @if ($s->risks)
                    <section class="card p-5" aria-labelledby="risks-title">
                        <h2 id="risks-title" class="text-base font-bold">Risks found</h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-[14px]">@foreach ($s->risks as $risk)<li>{{ $risk }}</li>@endforeach</ul>
                    </section>
                @endif
            @endif

            <section class="card p-5" aria-labelledby="changes-title">
                <h2 id="changes-title" class="text-base font-bold">Ranking changes</h2>
                @forelse ($changes as $c)
                    <div class="border-b border-line py-3 last:border-0">
                        <p class="text-[13px] text-muted">{{ $c->created_at->localTime()->format('j M Y') }} ·
                            {{ ['entered' => 'Entered the ranking', 'left' => 'Left the ranking', 'moved_up' => 'Moved up', 'moved_down' => 'Moved down', 'rescored' => 'Score changed', 'status' => 'Status changed'][$c->change] ?? $c->change }}
                            @if ($c->from_rank || $c->to_rank) ({{ $c->from_rank ? '#'.$c->from_rank : '—' }} → {{ $c->to_rank ? '#'.$c->to_rank : '—' }})@endif</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-[14px]">@foreach (collect($c->reasons)->reject(fn ($r) => str_starts_with($r, 'Confidence:'))->take(5) as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                    </div>
                @empty
                    <p class="mt-2 text-[14px] text-muted">No ranking changes recorded yet.</p>
                @endforelse
            </section>
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="card p-5" aria-labelledby="sources-title">
                <h2 id="sources-title" class="text-base font-bold">Original data sources</h2>
                @forelse ($sources as $src)
                    <div class="border-b border-line py-2.5 text-[14px] last:border-0">
                        @if ($src['url'])<a href="{{ $src['url'] }}" class="link font-semibold" target="_blank" rel="noopener nofollow">{{ $src['name'] }}</a>@else<span class="font-semibold">{{ $src['name'] }}</span>@endif
                        <span class="block text-[12px] text-muted">{{ $src['type'] }}@if ($src['note']) · {{ $src['note'] }}@endif</span>
                    </div>
                @empty
                    <p class="mt-2 text-[14px] text-muted">No verified source on file yet.</p>
                @endforelse
            </section>

            <section class="card p-5" aria-labelledby="legal-title">
                <h2 id="legal-title" class="text-base font-bold">Official legal records</h2>
                @forelse ($legal as $r)
                    <div class="border-b border-line py-2.5 text-[14px] last:border-0">
                        <span class="font-semibold">{{ $r->authority }}: {{ $r->statusLabel() }}</span>
                        <span class="block text-[12px] text-muted">{{ config('investment_index.legal.record_types.'.$r->record_type) }}@if ($r->reference) · {{ $r->reference }}@endif · {{ $r->effectiveDate()->format('j M Y') }}</span>
                        @if (in_array($r->source?->type, \App\Services\InvestmentIndex\IndexBrowser::PUBLIC_SOURCE_TYPES, true) && ($link = \App\Services\InvestmentIndex\IndexBrowser::safeUrl($r->source_url)))
                            <a href="{{ $link }}" class="link text-[12px]" target="_blank" rel="noopener nofollow">Official source</a>
                        @endif
                    </div>
                @empty
                    <p class="mt-2 text-[14px] text-muted">No verified official record yet. Until one is on file, the legal status is shown as not verified, never as approved.</p>
                @endforelse
            </section>

            @if ($history->count() > 1)
                <section class="card p-5" aria-labelledby="trend-title">
                    <h2 id="trend-title" class="text-base font-bold">Score over time</h2>
                    <ul class="mt-2 divide-y divide-line text-[14px]">
                        @foreach ($history as $h)
                            <li class="flex justify-between py-1.5"><span class="text-muted">{{ $h->run?->published_at?->localTime()->format('j M Y') }}</span><span class="tabular-nums">{{ $h->status === 'ranked' ? number_format($h->score, 1).' (#'.$h->category_rank.')' : config('investment_index.statuses.'.$h->status) }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($canSubmit)
                <section class="card p-5">
                    <h2 class="text-base font-bold">Own or manage this development?</h2>
                    <p class="mt-1 text-[14px] text-muted">Submit documented occupancy, rental, progress or approval evidence. Staff check every document; rankings cannot be bought.</p>
                    <a href="{{ route('investment-index.evidence.create', $project) }}" class="btn btn-secondary btn-sm mt-3">Submit evidence</a>
                </section>
            @endif
            <p class="text-[12px] text-muted">{{ config('meezan.investment.disclaimer') }}</p>
            <p class="text-[12px] text-muted">Legal status shows only what official records on file say. "Unknown / Unverified" means no official record has been found here; it does not mean a project is illegal.</p>
        </aside>
    </div>
</x-layouts.app>
