{{-- The Meezan Investment Index for one city or all of Pakistan. $indexBoards (category => config + 'key', 'top'), $city (name or null). --}}
<section aria-labelledby="index-boards-title">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <h2 id="index-boards-title" class="text-lg font-bold">Meezan Investment Index{{ $city ? ' in '.$city : '' }}</h2>
        <a href="{{ route('rankings.index') }}" class="link text-[13px]">All leaderboards →</a>
    </div>
    @php
        $totals = collect($indexBoards)->pluck('counts');
        $index = app(\App\Services\InvestmentIndex\IndexBrowser::class);
        $research = $index->researchTotals($city);
    @endphp
    <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach (['Projects researched' => $totals->sum('assessed'), 'Ranked' => $totals->sum('ranked'), 'Official legal status on file' => $research['legal_verified'], 'On an official list, awaiting our check' => $research['awaiting_check'], 'Excluded on legal grounds' => $totals->sum('excluded')] as $label => $value)
            <div class="card p-3"><dt class="text-[12px] text-muted">{{ $label }}</dt><dd class="text-xl font-bold tabular-nums">{{ number_format($value) }}</dd></div>
        @endforeach
    </dl>
    <p class="mt-2 text-[12px] text-muted">
        @if ($research['published_at'])Latest calculation: {{ $research['published_at']->localTime()->format('j M Y') }}. @endif
        @unless ($city)Projects on file: {{ collect($index->cityCards())->map(fn ($c) => $c['name'].' '.number_format($c['projects']))->implode(' · ') }}.@endunless
    </p>
    <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach ($indexBoards as $board)
            <article class="card flex min-w-0 flex-col p-4">
                <h3 class="text-sm font-bold"><a href="{{ $city ? route('rankings.city-category', [\App\Support\Cities::slug($city), $board['key']]) : route('rankings.category', $board['key']) }}" class="hover:text-brand-700">{{ $board['label'] }}</a></h3>
                <p class="text-[12px] text-muted">{{ number_format($board['counts']['assessed']) }} on file · {{ number_format($board['counts']['ranked']) }} ranked</p>
                @if ($board['top']->isEmpty())
                    @php $list = \App\Services\InvestmentIndex\IndexBrowser::provisionalPublic() ? app(\App\Services\InvestmentIndex\IndexBrowser::class)->provisional($board['key'], $city, 3) : collect(); @endphp
                    @if ($list->isNotEmpty())
                        <p class="mt-2 text-[12px] font-semibold text-amber-800">Provisional research ranking</p>
                        <ol class="mt-1 divide-y divide-line text-[13px]">
                            @foreach ($list as $s)
                                <li class="flex items-center gap-2 py-1.5"><span class="min-w-0 flex-1 truncate"><span class="font-semibold text-muted">P{{ $s->provisional_rank }}</span> <a href="{{ route('rankings.project', $s->project) }}" class="link">{{ $s->project?->name }}</a></span><span class="font-bold tabular-nums">{{ number_format($s->provisional_score, 1) }}</span></li>
                            @endforeach
                        </ol>
                    @else
                        @php $documented = app(\App\Services\InvestmentIndex\IndexBrowser::class)->mostDocumented($board['key'], $city, 3); @endphp
                        @if ($documented->isEmpty())
                            <p class="mt-2 text-[13px] text-muted"><span class="font-semibold text-ink">Coverage gap.</span> None researched here yet.</p>
                        @else
                            <p class="mt-2 text-[12px] font-semibold text-muted">Most documented so far, not a ranking</p>
                            <ul class="mt-1 divide-y divide-line text-[13px]">
                                @foreach ($documented as $s)
                                    <li class="py-1.5"><a href="{{ route('rankings.project', $s->project) }}" class="link block truncate">{{ $s->project?->name }}</a><span class="block truncate text-[12px] text-muted">{{ $s->project?->city }} · {{ \App\Services\InvestmentIndex\IndexBrowser::legalLine($s) }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                @else
                    <ol class="mt-2 divide-y divide-line text-[13px]">
                        @foreach ($board['top'] as $s)
                            <li class="flex items-center gap-2 py-1.5">
                                <span class="min-w-0 flex-1 truncate"><span class="font-semibold text-muted">#{{ $s->category_rank }}</span> <a href="{{ route('rankings.project', $s->project) }}" class="link">{{ $s->project->name }}</a></span>
                                <span class="font-bold tabular-nums">{{ number_format($s->score, 1) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </article>
        @endforeach
    </div>
</section>
