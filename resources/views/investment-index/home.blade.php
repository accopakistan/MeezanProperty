<x-layouts.app title="Investment Rankings — Meezan Investment Index for Pakistan"
               description="The Meezan Investment Index ranks housing societies, commercial plazas, apartment buildings, shopping malls and business towers across Pakistan from verified, traceable evidence, with official legal checks and no paid placement."
               :canonical="route('rankings.index')">
    @include('investment-index.partials.hero', [
        'title' => 'The strongest real estate developments in Pakistan, from verified evidence',
        'intro' => 'Five Pakistan-wide leaderboards and the Overall Meezan Investment Index. Every score is calculated by Meezan Property\'s engine from official legal records and verified evidence, each with its source and date. Where the evidence is not there, we say so.',
        'breadcrumbs' => [['Investment Rankings', null]],
        'run' => $run,
    ])

    <div class="container-page py-8">
        <section aria-labelledby="leaderboards-title">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 id="leaderboards-title" class="text-xl font-bold">Leaderboards</h2>
                <p class="text-[13px] text-muted">{{ number_format($counts['assessed']) }} developments assessed · {{ number_format($counts['ranked']) }} ranked</p>
            </div>
            <div class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($categories as $c)
                    <article class="card flex min-w-0 flex-col p-5">
                        <h3 class="text-base font-bold"><a href="{{ route('rankings.category', $c['key']) }}" class="hover:text-brand-700">{{ $c['leaderboard'] }}</a></h3>
                        <p class="mt-0.5 text-[12px] text-muted">{{ $c['counts']['ranked'] }} ranked · {{ $c['counts']['insufficient'] }} insufficient data · {{ $c['counts']['excluded'] }} excluded</p>
                        @if ($c['top']->isEmpty())
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
