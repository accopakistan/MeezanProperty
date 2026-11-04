<x-layouts.app title="Overall Meezan Investment Index — Investment Rankings"
               description="The Overall Meezan Investment Index compares housing societies, commercial plazas, apartment buildings, shopping malls and business towers on one scale: each project's standing within its own category, weighted by data confidence."
               :canonical="route('rankings.overall')" :noindex="$overall->isEmpty()">
    @include('investment-index.partials.hero', [
        'title' => 'Overall Meezan Investment Index',
        'intro' => 'Unlike assets are not compared by raw prices or yields. Each project\'s normalized score is its standing within its own category (a mid-rank percentile, 0–100); the Index is that score scaled by its Data Confidence Score, from ×0.5 at no confidence to ×1.0 at full confidence.',
        'breadcrumbs' => [['Investment Rankings', route('rankings.index')], ['Overall Index', null]],
        'run' => $run,
    ])
    <div class="container-page py-8">
        <section class="card min-w-0 p-5">
            @if ($overall->isEmpty())
                <div class="rounded-xl border border-dashed border-brand-200 px-5 py-6 text-center" role="status">
                    <p class="text-base font-bold">Insufficient Verified Data</p>
                    <p class="mx-auto mt-1 max-w-xl text-[14px] text-muted">A category enters the Overall Index once at least {{ $minPeers }} of its projects are ranked, so that standings can be compared fairly. None has reached that yet.</p>
                </div>
            @else
                <div class="relative overflow-x-auto">
                    <table class="table table-compact min-w-[760px]">
                        <thead><tr><th scope="col" class="w-12">Rank</th><th scope="col">Project</th><th scope="col">Category</th><th scope="col" class="text-right">Category rank</th><th scope="col" class="text-right">Normalized</th><th scope="col" class="text-right">Confidence</th><th scope="col" class="text-right">Index</th></tr></thead>
                        <tbody>
                            @foreach ($overall as $s)
                                <tr>
                                    <td class="text-lg font-bold tabular-nums text-brand-700">{{ $s->overall_rank }}</td>
                                    <td><a href="{{ route('rankings.project', $s->project) }}" class="link font-semibold">{{ $s->project->name }}</a><span class="block text-[12px] text-muted">{{ $s->project->city }}</span></td>
                                    <td class="text-[13px]"><a href="{{ route('rankings.category', $s->category) }}" class="link">{{ $s->categoryLabel() }}</a></td>
                                    <td class="text-right tabular-nums">{{ $s->category_rank }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($s->normalized_score, 1) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($s->confidence_score, 0) }}</td>
                                    <td class="text-right text-base font-bold tabular-nums">{{ number_format($s->overall_index, 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-[12px] text-muted">Only categories with at least {{ $minPeers }} ranked projects take part.@if ($overall->count() >= \App\Http\Controllers\InvestmentIndexController::PAGE_LIMIT) Showing the top {{ \App\Http\Controllers\InvestmentIndexController::PAGE_LIMIT }}; every project's position is on its profile.@endif</p>
            @endif
        </section>
        <p class="mt-6 text-[13px] text-muted">{{ config('meezan.investment.disclaimer') }} <a href="{{ route('rankings.methodology') }}" class="link">Methodology</a></p>
    </div>
</x-layouts.app>
