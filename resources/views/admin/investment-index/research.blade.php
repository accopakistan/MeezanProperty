<x-layouts.dashboard title="Research queue" section="admin" heading="Research queue" subheading="Every real project: what is on file, what is missing and what waits for a person. The AI and the import never approve legal evidence.">
    @include('admin.investment-index.partials.nav')

    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach (['Projects' => $totals['projects'], 'Ranked' => $totals['ranked'], 'Provisional' => $totals['provisional'], 'Under research' => $totals['research'], 'Legal records waiting' => $totals['awaiting_check'], 'Excluded' => $totals['excluded']] as $label => $value)
            <div class="card p-3"><dt class="text-[12px] text-muted">{{ $label }}</dt><dd class="text-xl font-bold tabular-nums">{{ number_format($value) }}</dd></div>
        @endforeach
    </dl>
    <p class="mt-2 text-xs text-muted">Latest calculation: {{ $run?->published_at?->localTime()->format('j M Y, g:i a') ?? 'None' }}. Active sources: {{ number_format($sourceCount) }}.</p>
    <p class="mt-1 text-xs text-muted">By city: {{ $byCity->map(fn ($n, $c) => $c.' '.number_format($n))->implode(' · ') }}.</p>
    <p class="mt-1 text-xs text-muted">By category: {{ $byCategory->map(fn ($n, $c) => (config('meezan.investment.project_categories.'.$c.'.label') ?? $c).' '.number_format($n))->implode(' · ') }}.</p>

    <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="card card-body min-w-0">
            <h2 class="text-base font-bold">What is on file</h2>
            <dl class="mt-2 divide-y divide-line text-[13px]">
                @foreach ($gaps as $label => $value)
                    <div class="flex justify-between gap-3 py-1.5"><dt class="text-muted">{{ $label }}</dt><dd class="font-semibold tabular-nums">{{ number_format($value) }}</dd></div>
                @endforeach
            </dl>
        </div>
        <div class="card card-body min-w-0">
            <h2 class="text-base font-bold">Latest calculations</h2>
            <ul class="mt-2 divide-y divide-line text-[13px]">
                @forelse ($latestRuns as $r)
                    <li class="flex justify-between gap-3 py-1.5"><span class="text-muted">#{{ $r->id }} · {{ $r->trigger }}</span><span>{{ $r->status }} · {{ $r->created_at?->localTime()->format('j M, g:i a') }}</span></li>
                @empty
                    <li class="py-1.5 text-muted">None yet.</li>
                @endforelse
            </ul>
        </div>
        <div class="card card-body min-w-0">
            <h2 class="text-base font-bold">Latest collection jobs</h2>
            <ul class="mt-2 divide-y divide-line text-[13px]">
                @forelse ($latestCollections as $c)
                    <li class="py-1.5"><span class="block truncate">{{ $c->source?->name ?? 'Source' }}</span><span class="text-[12px] text-muted">{{ $c->status }} · {{ $c->created_at?->localTime()->format('j M, g:i a') }}</span></li>
                @empty
                    <li class="py-1.5 text-muted">None yet.</li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="card card-body mt-6" aria-labelledby="provisional-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h2 id="provisional-title" class="text-base font-bold">Provisional Research Ranking</h2>
                <p class="mt-1 text-[13px] text-muted">The same scoring, also counting records staff have not yet checked. It needs a non-adverse legal status on an authority's list, at least {{ config('investment_index.provisional.min_factors') }} scored factors and {{ config('investment_index.provisional.min_coverage') * 100 }}% of the weight. It is never a verified rank. Visitors see it only while this is on.</p>
            </div>
            <x-status-badge :tone="$provisionalPublic ? 'warning' : 'neutral'" :label="$provisionalPublic ? 'Shown to visitors' : 'Hidden from visitors'" />
        </div>
        <form method="POST" action="{{ route('admin.investment-index.research.provisional') }}" class="mt-3 flex flex-wrap items-center gap-3">
            @csrf @method('PATCH')
            <input type="hidden" name="on" value="{{ $provisionalPublic ? 0 : 1 }}">
            @unless ($provisionalPublic)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirmed" value="1" class="checkbox" required> I approve publishing provisional positions, labelled as provisional</label>
            @endunless
            <button class="btn btn-secondary btn-sm">{{ $provisionalPublic ? 'Hide from visitors' : 'Show to visitors' }}</button>
        </form>
        <div class="mt-4 space-y-3">
            @foreach ($provisional as $category => $rows)
                <div>
                    <h3 class="text-sm font-semibold">{{ config("investment_index.categories.$category.label") }}: {{ $rows->count() }}</h3>
                    @if ($rows->isNotEmpty())
                        <ol class="mt-1 text-[13px] text-muted">
                            @foreach ($rows->take(10) as $s)
                                <li>P{{ $s->provisional_rank }} · <a href="{{ route('rankings.project', $s->project) }}" class="link">{{ $s->project->name }}</a> · score {{ number_format($s->provisional_score, 1) }} · evidence {{ number_format($s->provisional_coverage * 100, 0) }}% · {{ (int) $s->provisional_unchecked }} unchecked</li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <form method="GET" class="mt-6 flex flex-wrap items-end gap-3" role="search">
        <x-field name="q" label="Project" :value="$filters['q'] ?? null" placeholder="Name" />
        <x-field name="category" type="select" label="Category" placeholder="All" :options="collect(config('investment_index.categories'))->mapWithKeys(fn ($c) => [$c['project_categories'][0] => $c['label']])->all()" :value="$filters['category'] ?? null" />
        <x-field name="city" type="select" label="City" placeholder="All" :options="array_combine(\App\Support\Cities::names(), \App\Support\Cities::names())" :value="$filters['city'] ?? null" />
        <x-field name="gap" type="select" label="Show" placeholder="All projects" :options="['awaiting' => 'Waiting for a person', 'legal' => 'No legal record', 'evidence' => 'No other evidence', 'market' => 'No market data', 'developer' => 'No developer']" :value="$filters['gap'] ?? null" />
        <button class="btn btn-secondary">Filter</button>
    </form>

    <section class="card mt-4 min-w-0 overflow-hidden" aria-label="Projects">
        <div class="overflow-x-auto">
            <table class="table table-compact min-w-[760px]">
                <thead><tr><th scope="col">Project</th><th scope="col" class="text-right">Legal</th><th scope="col" class="text-right">Evidence</th><th scope="col">Missing</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                    @foreach ($projects as $p)
                        <tr>
                            <td><a href="{{ route('rankings.project', $p) }}" class="link font-semibold">{{ $p->name }}</a><span class="block text-[12px] text-muted">{{ $p->city }} · {{ $p->categoryLabel() }}@if ($p->developer) · {{ $p->developer }}@endif</span>@if ($p->discovery_url)<a href="{{ $p->discovery_url }}" class="block text-[12px] text-muted hover:underline" target="_blank" rel="noopener nofollow">Source</a>@endif</td>
                            <td class="text-right text-[13px] tabular-nums">{{ $p->legal_verified }} verified @if ($p->legal_waiting)<span class="block text-amber-700">{{ $p->legal_waiting }} waiting</span>@endif</td>
                            <td class="text-right text-[13px] tabular-nums">{{ $p->evidence_verified }} verified @if ($p->evidence_waiting)<span class="block text-amber-700">{{ $p->evidence_waiting }} waiting</span>@endif</td>
                            <td class="text-[12px] text-muted">{{ collect([$p->legal_verified + $p->legal_waiting ? null : 'legal record', $p->developer ? null : 'developer', $p->evidence_verified + $p->evidence_waiting ? null : 'evidence', $p->latitude ? null : 'coordinates'])->filter()->implode(', ') ?: 'Nothing listed' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.investment-index.research.request', $p->id) }}" class="flex gap-2">@csrf
                                    <input name="note" class="input w-44 py-1.5 text-xs" maxlength="500" required placeholder="What should be researched?" aria-label="Research request for {{ $p->name }}">
                                    <button class="btn btn-secondary btn-sm">Request</button>
                                </form>
                                <span class="mt-1 flex flex-wrap gap-x-3 text-[12px]">
                                    <a href="{{ route('admin.investment-index.verification', ['project' => $p->id]) }}" class="text-brand-700 hover:underline">Check, verify or reject</a>
                                    <a href="{{ route('investment-index.evidence.create', $p) }}" class="text-brand-700 hover:underline">Add evidence</a>
                                    <a href="{{ route('admin.projects.edit', $p) }}" class="text-brand-700 hover:underline">Publication</a>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-4">{{ $projects->links() }}</div>
</x-layouts.dashboard>
