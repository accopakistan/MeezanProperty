@php
    use App\Services\Investment\RankingBrowser;
    $title = 'Investment Rankings in '.$city;
@endphp
<x-layouts.app :title="$title" :description="'Residential societies, plots, houses, apartments, shopping malls, plazas and business towers in '.$city.', compared by verified price history, rental yield and the Meezan Property Investment Score.'"
               :canonical="route('rankings.city', $citySlug)" :robots="$indexable && $tab === 'all' ? null : 'noindex, follow'">
    @include('rankings.partials.hero', [
        'title' => 'Smart Real Estate Investments in', 'highlight' => $city,
        'intro' => 'Compare residential societies, commercial projects and investment opportunities in '.$city.' using '.($calc?->includes_demo ? 'illustrative data (not verified market data)' : 'verified market data').', historical performance, rental yields and transparent investment analysis.',
        'photo' => config('meezan.place_photos.'.$city),
        'stats' => [[number_format($overview['societies']), 'Societies', 'map-pin'], [number_format($overview['commercial_projects']), 'Commercial Projects', 'building'],
            [number_format($overview['listings']), 'Live listings', 'home'], [number_format($overview['ranked']), 'Ranked (MPIS)', 'chart']],
        'breadcrumbs' => [['Investment Rankings', route('rankings.index')], [$city, null]],
        'segmentKey' => null, 'filters' => $filters,
    ])

    @include('rankings.partials.city-cards', ['active' => $city, 'link' => fn ($slug) => route('rankings.city', $slug),
        'countLabel' => fn ($c) => number_format($c['societies']).' societies · '.$c['commercial_projects'].' commercial'])
    @include('rankings.partials.category-nav', ['mode' => 'city', 'active' => $tab])

    <div class="container-page py-6" data-rankings-page>
        @include('investment.partials.demo-notice')
        @unless ($calc)
            <p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                <strong>No rankings are published yet for {{ $city }}.</strong> Counts below are live; scores appear only after verified market records are reviewed and a calculation is published.
            </p>
        @endunless

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <div class="min-w-0" data-rankings-target>@include('rankings.partials.city-results')</div>
            <div class="min-w-0 space-y-6">
                @include('rankings.partials.map', ['title' => 'Investment Map — '.$city])
                @include('rankings.partials.advisor', ['segmentKey' => $segmentKey])
            </div>
        </div>

        <div class="mt-6">@include('rankings.partials.overview', ['place' => $city])</div>

        <section class="mt-6" aria-labelledby="explore-title">
            <h2 id="explore-title" class="text-lg font-bold">Explore {{ $city }} by property type</h2>
            <div class="mt-3 grid gap-4 md:grid-cols-3">
                @foreach (config('meezan.investment.groups') as $g => $group)
                    <div class="card p-4">
                        <h3 class="text-sm font-bold"><a href="{{ route('rankings.group', [$citySlug, $g]) }}" class="hover:text-brand-700">{{ $group['label'] }}</a></h3>
                        <ul class="mt-2 space-y-1 text-[13px]">@foreach (RankingBrowser::segmentsIn($g) as $slug => $s)<li><a href="{{ route('rankings.segment', [$citySlug, $g, $slug]) }}" class="link">{{ $s['label'] }}</a></li>@endforeach</ul>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="mt-6">@include('rankings.partials.upcoming', ['title' => 'Upcoming & New Projects in '.$city])</div>
        <div class="mt-6">@include('investment.partials.disclaimer')</div>
    </div>
</x-layouts.app>
