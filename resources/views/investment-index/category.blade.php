@php
    $commercial = in_array($key, ['commercial-plazas', 'shopping-malls', 'business-towers'], true);
    $title = $category['leaderboard'].($city ? ' in '.$city : ' in Pakistan');
@endphp
<x-layouts.app :title="$title.' — Meezan Investment Index'"
               :description="$title.', ranked from verified evidence: legal approval, '.collect($category['factors'])->except('legal_approval')->pluck('label')->take(4)->map(fn ($l) => mb_strtolower($l))->implode(', ').' and more.'"
               :canonical="route('rankings.category', array_filter(['category' => $key, 'city' => $city]))" :noindex="$ranked->isEmpty()">
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

        <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
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

        @if ($others->isNotEmpty())
            <section class="card mt-6 p-5" aria-labelledby="others-title">
                <h2 id="others-title" class="text-base font-bold">Assessed, not ranked</h2>
                <p class="mt-1 text-[13px] text-muted">These developments are not in the leaderboard. No score is shown for them.</p>
                <ul class="mt-3 divide-y divide-line">
                    @foreach ($others as $s)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                            <span class="min-w-0"><a href="{{ route('rankings.project', $s->project) }}" class="link font-semibold">{{ $s->project->name }}</a> <span class="text-[12px] text-muted">{{ $s->project->city }}</span>
                                <span class="block text-[12px] text-muted">{{ \Illuminate\Support\Str::limit($s->reasons[0] ?? '', 160) }}</span></span>
                            @include('investment-index.partials.status', ['status' => $s->status])
                        </li>
                    @endforeach
                </ul>
            </section>
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
        <p class="mt-6 text-[13px] text-muted">{{ config('meezan.investment.disclaimer') }}</p>
    </div>
</x-layouts.app>
