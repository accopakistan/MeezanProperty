@php
    $s = $score;
    $categoryConfig = $category ? config("investment_index.categories.$category") : null;
    $legalLabel = $s ? match ($s->legal_status) { 'not_verified' => 'Not verified', 'excluded' => 'Excluded — legal', default => config('investment_index.legal.statuses.'.$s->legal_status, $s->legal_status) } : 'Not verified';
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
                    <p class="mt-3 rounded-lg bg-surface px-4 py-3 text-[14px]"><span class="font-semibold">Insufficient Verified Data.</span> <span class="text-muted">This development has not been assessed in a published run yet.</span></p>
                @endif
                <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div><dt class="text-[12px] text-muted">Investment score</dt><dd class="text-2xl font-bold tabular-nums">{{ $s && $s->score !== null && $s->status === 'ranked' ? number_format($s->score, 1) : '—' }}</dd>
                        @if ($s && $s->status === 'not_eligible' && $s->score !== null)<dd class="text-[12px] text-muted">Scored {{ number_format($s->score, 1) }}; not ranked</dd>@endif</div>
                    <div><dt class="text-[12px] text-muted">Ranking position</dt><dd class="text-2xl font-bold tabular-nums">{{ $s?->category_rank ? '#'.$s->category_rank : '—' }}</dd>
                        @if ($s?->overall_rank)<dd class="text-[12px] text-muted">Overall Index #{{ $s->overall_rank }}</dd>@endif</div>
                    <div><dt class="text-[12px] text-muted">Data confidence</dt><dd class="text-2xl font-bold tabular-nums">{{ $s ? number_format($s->confidence_score, 0) : '—' }}</dd><dd class="text-[12px] text-muted">{{ $s ? ucfirst($s->confidence_level) : 'No evidence' }}</dd></div>
                    <div><dt class="text-[12px] text-muted">Risk</dt><dd class="text-base font-bold">{{ $s?->riskLabel() ?? '—' }}</dd></div>
                </dl>
                @if ($s && $s->status !== 'ranked')
                    <ul class="mt-4 space-y-1 rounded-lg bg-surface px-4 py-3 text-[14px]">
                        @foreach (collect($s->reasons)->reject(fn ($r) => str_starts_with($r, 'Confidence:'))->take(4) as $reason)<li>{{ $reason }}</li>@endforeach
                    </ul>
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
                    <dt class="text-muted">Historical performance</dt><dd>{{ $marketHistory && $marketHistory['score'] !== null ? $marketHistory['value'].' (verified transactions and valuations)' : 'Insufficient Verified Data' }}</dd>
                    <dt class="text-muted">Last verification</dt><dd>{{ $s?->last_verified_at?->localTime()->format('j M Y') ?? 'Never' }}</dd>
                </dl>
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
        </aside>
    </div>
</x-layouts.app>
