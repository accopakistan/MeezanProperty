@php
    use App\Services\Investment\RankingBrowser;
    $methodology = \App\Models\InvestmentMethodology::active();
@endphp
<x-layouts.app title="Investment Rankings — Smart Real Estate Investments in Pakistan"
               description="Compare housing societies, apartment buildings, shopping malls, commercial plazas and business towers across Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar, using verified market data, rental yields and transparent investment scores."
               :canonical="route('rankings.city', 'pakistan')" :robots="$calc?->includes_demo || ! $indexable ? 'noindex, follow' : null">
    @include('rankings.partials.hero', [
        'title' => 'Smart Real Estate', 'highlight' => 'Investments', 'subtitle' => 'for a Better Tomorrow',
        'intro' => 'Compare housing societies, apartment buildings, shopping malls, commercial plazas and business towers across Pakistan using verified market data, historical performance, rental yields and transparent investment analysis.',
        'photo' => config('meezan.place_photos.home'),
        'stats' => [[number_format($overview['societies']), 'Societies', 'map-pin'], [number_format($overview['commercial_projects']), 'Commercial Projects', 'building'],
            [7, 'Major Cities', 'map-pin'], [number_format($overview['verified_observations'] + $overview['verified_indicators']), 'Verified records', 'shield']],
        'breadcrumbs' => [['Investment Rankings', route('rankings.index')], ['All Pakistan', null]],
        'city' => null, 'segmentKey' => null, 'filters' => $filters,
    ])
    @include('rankings.partials.city-cards', ['cities' => $cities, 'active' => null, 'link' => fn ($slug) => route('rankings.city', $slug),
        'countLabel' => fn ($c) => number_format($c['societies']).' societies · '.$c['commercial_projects'].' commercial'])

    <div class="container-page py-6">
        @include('investment.partials.demo-notice')
        @unless ($calc)
            @include('rankings.partials.insufficient', ['place' => null])
        @endunless
        @include('rankings.partials.index-boards', ['city' => null])

        <section class="mt-6" aria-labelledby="choose-title">
            <h2 id="choose-title" class="text-lg font-bold">Choose an investment category</h2>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                @foreach (RankingBrowser::publicGroups() as $g => $group)
                    <div class="card p-4">
                        <h3 class="text-sm font-bold"><a href="{{ route('rankings.group', ['pakistan', $g]) }}" class="hover:text-brand-700">{{ $group['label'] }}</a></h3>
                        <ul class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[13px]">@foreach (RankingBrowser::publicSegmentsIn($g) as $slug => $s)<li><a href="{{ route('rankings.segment', ['pakistan', $g, $slug]) }}" class="link">{{ $s['label'] }}</a></li>@endforeach</ul>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <div class="grid min-w-0 gap-4 md:grid-cols-2">
                @if ($featured->flatten(1)->isEmpty())
                    <section class="card p-5 md:col-span-2" aria-labelledby="market-rankings-title">
                        <h2 id="market-rankings-title" class="text-base font-bold">Market rankings</h2>
                        <p class="mt-1 text-[13px] text-muted"><span class="font-semibold text-ink">Insufficient Verified Data.</span> Price growth, rental yield and market score lists need verified sales and rent records, and none are published yet. Advertised prices are shown on project pages, labelled as advertised, and do not count here. Nothing is estimated to fill these lists.</p>
                    </section>
                @endif
                @foreach ($featured->flatten(1)->isEmpty() ? [] : $featured as $slug => $rows)
                    @php $seg = RankingBrowser::segment($slug); @endphp
                    <section class="card overflow-hidden" aria-labelledby="top-{{ $slug }}">
                        <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                            <h2 id="top-{{ $slug }}" class="text-base font-bold">Top {{ $seg['label'] }} in Pakistan</h2>
                            <a href="{{ route('rankings.segment', ['pakistan', $seg['group'], $slug]) }}" class="link text-[13px]">View all →</a>
                        </div>
                        @if ($rows->isEmpty())
                            <p class="p-4 text-[13px] text-muted"><span class="font-semibold text-ink">Insufficient Verified Data.</span> No verified market data for {{ strtolower($seg['label']) }} yet.</p>
                        @else
                            <ol class="divide-y divide-line">
                                @foreach ($rows as $n => $i)
                                    <li class="flex items-center gap-3 px-4 py-2.5 text-[13px]">
                                        <span class="grid size-6 place-items-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-700">{{ $n + 1 }}</span>
                                        <span class="min-w-0 flex-1"><a href="{{ route('rankings.segment', ['pakistan', $seg['group'], $slug, 'selected' => $i['key']]) }}#performance" class="font-semibold text-brand-700">{{ $i['name'] }}</a><span class="block text-[12px] text-muted">{{ $i['location'] }}@if ($i['group_label']) · {{ $i['group_label'] }}@endif</span></span>
                                        @include('rankings.partials.badges', ['badge' => 'score', 'item' => $i])
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </section>
                @endforeach
            </div>
            <div class="min-w-0 space-y-6">
                @include('rankings.partials.advisor', ['city' => null, 'segmentKey' => null])
                <section class="card p-4" aria-labelledby="mpis-title">
                    <h2 id="mpis-title" class="text-base font-bold">Meezan Property Investment Score (MPIS)</h2>
                    <p class="mt-1 text-[13px] text-muted">Methodology v{{ $methodology?->version ?? '—' }}, approved by an administrator. Default weights:</p>
                    <ul class="mt-2 space-y-1 text-[13px]">
                        @foreach ($methodology?->weightsFor('overall') ?? [] as $factor => $w)
                            <li class="flex items-center gap-2"><span class="w-44 shrink-0">{{ config('meezan.investment.factors.'.$factor) }}</span><span class="h-2 rounded-full bg-brand-500" style="width: {{ $w * 3 }}px"></span><span class="tabular-nums">{{ $w }}%</span></li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-[12px] text-muted">Scores are shown only with enough verified data, always next to a separate data-confidence level. Each property type uses the weights of a matching ranking category.</p>
                    <a href="{{ route('investment.methodology') }}" class="link mt-2 inline-block text-[13px]">Full methodology</a>
                </section>
            </div>
        </div>

        <div class="mt-6">@include('rankings.partials.overview', ['place' => 'Pakistan'])</div>
        <div class="mt-6">@include('rankings.partials.upcoming', ['title' => 'Upcoming & New Projects in Pakistan'])</div>
        <div class="mt-6">@include('investment.partials.disclaimer')</div>
    </div>
</x-layouts.app>
