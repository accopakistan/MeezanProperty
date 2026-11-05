@php
    $commercial = in_array($key, ['commercial-plazas', 'shopping-malls', 'business-towers'], true);
    $title = $category['leaderboard'].($city ? ' in '.$city : ' in Pakistan');
@endphp
<x-layouts.app :title="$title.' — Meezan Investment Index'"
               :description="$title.', ranked from verified evidence: legal approval, '.collect($category['factors'])->except('legal_approval')->pluck('label')->take(4)->map(fn ($l) => mb_strtolower($l))->implode(', ').' and more.'"
               :canonical="$city ? route('rankings.city-category', [\App\Support\Cities::slug($city), $key]) : route('rankings.category', $key)" :noindex="$ranked->isEmpty() || $filters !== []">
    @include('investment-index.partials.hero', [
        'title' => $title,
        'intro' => 'Ranked by the '.mb_strtolower($category['label']).' scoring engine: '.collect($weights)->map(fn ($w, $f) => mb_strtolower($category['factors'][$f]['label'] ?? $f).' '.$w.'%')->implode(', ').'.',
        'breadcrumbs' => [['Investment Rankings', route('rankings.index')], [$category['label'], $city ? route('rankings.category', $key) : null]] + ($city ? [2 => [$city, null]] : []),
        'run' => $run, 'searchGroup' => $commercial ? 'commercial' : null,
    ])

    <div class="container-page py-8">
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Categories">
            @foreach (config('investment_index.categories') as $k => $c)
                <a href="{{ route('rankings.category', array_filter(['category' => $k, 'city' => $city])) }}" @class(['rounded-full px-3.5 py-1.5 font-semibold ring-1', 'bg-brand-600 text-white ring-brand-600' => $k === $key, 'bg-white ring-line hover:ring-brand-300' => $k !== $key]) @if ($k === $key) aria-current="page" @endif>{{ $c['label'] }}</a>
            @endforeach
            <a href="{{ route('rankings.overall') }}" class="rounded-full bg-white px-3.5 py-1.5 font-semibold ring-1 ring-line hover:ring-brand-300">Overall Index</a>
        </nav>

        <form method="GET" action="{{ route('rankings.category', $key) }}" class="mt-4 flex flex-wrap items-end gap-3">
            <x-field name="q" label="Search" :value="$filters['q'] ?? null" placeholder="Project or developer" />
            <x-field name="legal" type="select" label="Legal status" placeholder="Any" :options="\App\Services\InvestmentIndex\IndexBrowser::legalFilters()" :value="$filters['legal'] ?? null" />
            <x-field name="status" type="select" label="Index status" placeholder="Any" :options="config('investment_index.statuses')" :value="$filters['status'] ?? null" />
            <x-field name="development" type="select" label="Development" placeholder="Any" :options="\App\Models\Project::STATUSES" :value="$filters['development'] ?? null" />
            <x-field name="confidence" type="select" label="Data confidence" placeholder="Any" :options="['high' => 'High Confidence', 'medium' => 'Moderate Confidence', 'limited' => 'Low Confidence', 'insufficient' => 'Insufficient Evidence']" :value="$filters['confidence'] ?? null" />
            <x-field name="risk" type="select" label="Risk" placeholder="Any" :options="['low' => 'Low', 'moderate' => 'Moderate', 'elevated' => 'Elevated', 'high' => 'High']" :value="$filters['risk'] ?? null" />
            <x-field name="city" type="select" label="City" :options="array_combine(\App\Support\Cities::names(), \App\Support\Cities::names())" :value="$city" placeholder="All of Pakistan" class="min-w-48" />
            <button class="btn btn-secondary">Show</button>
        </form>

        <section class="card mt-5 min-w-0 p-0" aria-labelledby="ranked-title">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-line px-5 py-4">
                <h2 id="ranked-title" class="text-base font-bold">Ranked {{ mb_strtolower($category['label']) }}</h2>
                <p class="text-[13px] text-muted">{{ $counts['ranked'] }} ranked · {{ $counts['not_eligible'] }} not eligible · {{ $counts['insufficient'] }} insufficient verified data · {{ $counts['excluded'] }} excluded (Pakistan)</p>
            </div>
            <div class="p-5">
                @if ($ranked->isEmpty())
                    @include('investment-index.partials.insufficient', ['counts' => $counts])
                @else
                    @include('investment-index.partials.leaderboard', ['rows' => $ranked])
                    <p class="mt-3 text-[12px] text-muted">Ranks are Pakistan-wide{{ $city ? '; filtered to '.$city : '' }}.@if ($ranked->count() >= \App\Http\Controllers\InvestmentIndexController::PAGE_LIMIT) Showing the top {{ \App\Http\Controllers\InvestmentIndexController::PAGE_LIMIT }}; every project's rank is on its profile.@endif Scores are 0–100 from verified evidence only; the Data Confidence Score shows how complete, recent and independent that evidence is.</p>
                @endif
            </div>
        </section>

        @if ($provisional->isNotEmpty())
            <section class="card mt-6 min-w-0 p-0" aria-labelledby="provisional-title">
                <div class="border-b border-line px-5 py-4">
                    <h2 id="provisional-title" class="text-base font-bold">Provisional research ranking</h2>
                    <p class="mt-1 text-[13px] text-muted"><strong class="text-ink">Not fully verified, and not an investment recommendation.</strong> Calculated by the same scoring from actual evidence, including records staff have not yet checked. It covers less of the methodology than a verified ranking needs, so the order can change as evidence arrives.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-compact min-w-[640px]">
                        <thead><tr><th scope="col">Position</th><th scope="col">Project</th><th scope="col" class="text-right">Provisional score</th><th scope="col" class="text-right">Confidence</th><th scope="col" class="text-right">Evidence</th><th scope="col">Legal status</th><th scope="col">Updated</th></tr></thead>
                        <tbody>
                            @foreach ($provisional as $s)
                                <tr>
                                    <td class="font-semibold tabular-nums">P{{ $s->provisional_rank }}</td>
                                    <td><a href="{{ route('rankings.project', $s->project) }}" class="link font-semibold">{{ $s->project->name }}</a><span class="block text-[12px] text-muted">{{ collect([$s->project->area?->name, $s->project->city])->filter()->unique()->implode(', ') }}@if ($s->project->developer) · {{ $s->project->developer }}@endif</span></td>
                                    <td class="text-right font-bold tabular-nums">{{ number_format($s->provisional_score, 1) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($s->provisional_confidence, 0) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($s->provisional_coverage * 100, 0) }}% of weight</td>
                                    <td class="text-[13px]">{{ \App\Services\InvestmentIndex\IndexBrowser::legalLabel($s->legal_status) }}@if ($s->provisional_unchecked) <span class="block text-[12px] text-muted">{{ $s->provisional_unchecked }} {{ \Illuminate\Support\Str::plural('record', $s->provisional_unchecked) }} not yet checked</span>@endif</td>
                                    <td class="text-[13px] whitespace-nowrap text-muted">{{ $s->created_at?->localTime()->format('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($others->isNotEmpty())
            <form method="GET" action="{{ route('rankings.category-compare', $key) }}" class="card mt-6 min-w-0 p-5" aria-labelledby="others-title">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 id="others-title" class="text-base font-bold">Assessed, not ranked</h2>
                        <p class="mt-1 text-[13px] text-muted">These developments are not in the leaderboard, and no score is shown for them. Unranked means the evidence is not yet enough to compare; it does not mean a low score. Being listed here is not a recommendation.</p>
                    </div>
                    <button class="btn btn-outline btn-sm">Compare selected</button>
                </div>
                <ul class="mt-3 divide-y divide-line">
                    @foreach ($others as $s)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                            <span class="flex min-w-0 flex-1 gap-2.5">
                                <input type="checkbox" name="projects[]" value="{{ $s->project->slug }}" class="checkbox mt-1" aria-label="Compare {{ $s->project->name }}">
                                <span class="min-w-0"><a href="{{ route('rankings.project', $s->project) }}" class="link font-semibold">{{ $s->project->name }}</a>@if ($s->research_depth > 0) <span class="text-[12px] text-muted">({{ $s->research_depth }} {{ \Illuminate\Support\Str::plural('item', $s->research_depth) }} on file)</span>@endif <span class="text-[12px] text-muted">{{ $s->project->city }}@if ($s->project->developer) · {{ $s->project->developer }}@endif</span>
                                    <span class="block text-[12px] text-muted">Legal status: <span class="font-semibold text-ink">{{ \App\Services\InvestmentIndex\IndexBrowser::legalLine($s) }}</span> · Development: {{ $s->project->statusLabel() }} · {{ \App\Services\InvestmentIndex\IndexBrowser::confidenceLabel($s->confidence_level) }} ({{ number_format($s->confidence_score, 0) }})</span>
                                    <span class="block text-[12px] text-muted">Evidence covers {{ number_format(($s->coverage ?? 0) * 100, 0) }}% of the scoring weight · {{ count($s->source_ids ?? []) }} verified {{ \Illuminate\Support\Str::plural('source', count($s->source_ids ?? [])) }} · Last updated {{ ($s->last_verified_at ?? $s->created_at)?->localTime()->format('j M Y') }}</span>
                                    @if ($strengths = collect($s->factors)->reject(fn ($f) => $f['display_only'] ?? false)->filter(fn ($f) => ($f['score'] ?? 0) >= 70)->pluck('label')->take(3)->implode(', '))<span class="block text-[12px] text-muted">Verified strength: {{ $strengths }}</span>@endif
                                    @if ($s->risks)<span class="block text-[12px] text-muted">Risk: {{ \Illuminate\Support\Str::limit($s->risks[0], 120) }}</span>@endif
                                    <span class="block text-[12px] text-muted">{{ $s->legal_status === 'not_verified' && $s->waiting_authority ? 'Not ranked yet: staff must check this record against the authority\'s list, and more evidence is needed.' : \Illuminate\Support\Str::limit($s->reasons[0] ?? '', 160) }}</span></span>
                            </span>
                            @include('investment-index.partials.status', ['status' => $s->status])
                        </li>
                    @endforeach
                </ul>
                @if ($others instanceof \Illuminate\Contracts\Pagination\Paginator && $others->hasPages())<div class="mt-3">{{ $others->links() }}</div>@endif
            </form>
        @elseif ($filters !== [])
            <p class="card mt-6 p-5 text-[14px] text-muted">Nothing here matches these filters.</p>
        @elseif ($ranked->isEmpty())
            <p class="card mt-6 p-5 text-[14px] text-muted"><span class="font-semibold text-ink">Coverage gap:</span> no {{ mb_strtolower($category['label']) }} are on file{{ $city ? ' for '.$city : '' }} yet. This reflects what has been researched so far, not what exists.</p>
        @endif

        @if ($markers !== [])
            <div class="mt-6">@include('rankings.partials.map', ['markers' => $markers, 'city' => $city, 'title' => $category['label'].' on the map'.($city ? ' — '.$city : '')])</div>
        @endif

        <section class="card mt-6 p-5" aria-labelledby="weights-title">
            <h2 id="weights-title" class="text-base font-bold">How {{ mb_strtolower($category['label']) }} are scored</h2>
            <div class="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($weights as $factor => $weight)
                    <div class="flex items-center justify-between gap-3 text-[14px]"><span>{{ $category['factors'][$factor]['label'] ?? $factor }}</span><span class="font-semibold tabular-nums">{{ $weight }}%</span></div>
                @endforeach
            </div>
            <p class="mt-3 text-[13px] text-muted">Methodology version {{ $methodologyVersion }}. <a href="{{ route('rankings.methodology') }}" class="link">Full methodology</a></p>
        </section>
        <p class="mt-6 text-[13px] text-muted">{{ config('meezan.investment.disclaimer') }} Legal status shows only what official records on file say. "Unknown / Unverified" means no official record has been found here; it does not mean a project is illegal.</p>
    </div>
</x-layouts.app>
