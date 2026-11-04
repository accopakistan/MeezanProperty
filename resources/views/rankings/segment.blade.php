@php
    use App\Services\Investment\RankingBrowser;
    // Layout: Investment Rankings reference dashboards (shopping malls / societies).
    $place = $city ?? 'Pakistan';
    $title = 'Top '.$seg['label'].' in '.$place;
    $scored = $featured->filter(fn ($i) => $i['score'] !== null);
    $subtitle = $seg['group'] === 'residential' ? 'Price History, Rental Yields and Transparent Scores' : 'Rental Yields, Occupancy and Transparent Investment Scores';
    $itemUrl = fn ($i) => request()->fullUrlWithQuery(['selected' => $i['key'], 'page' => null]).'#performance';
    $breadcrumbs = [['Investment Rankings', route('rankings.index')], [$place, route('rankings.city', $citySlug)]];
    if (count(RankingBrowser::segmentsIn($group)) > 1) {
        $breadcrumbs[] = [config('meezan.investment.groups.'.$group.'.label'), route('rankings.group', [$citySlug, $group])];
    }
    $breadcrumbs[] = [$seg['label'], null];
@endphp
<x-layouts.app :title="$title.' — Investment Rankings'" :description="'Compare '.strtolower($seg['label']).' in '.$place.' by verified price history, rental yield, risk and the Meezan Property Investment Score, with sources and data confidence for every entry.'"
               :canonical="route('rankings.segment', [$citySlug, $group, $segmentKey])" :robots="$indexable ? null : 'noindex, follow'">
    @if ($scored->isNotEmpty() && ! $calc?->includes_demo)
        <x-slot:head>
            <script type="application/ld+json">{!! \App\Support\Seo::json(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $title,
                'itemListElement' => $scored->values()->map(fn ($i, $n) => ['@type' => 'ListItem', 'position' => $n + 1, 'name' => $i['name'].' ('.$i['location'].')', 'url' => $itemUrl($i)])->all()]) !!}</script>
        </x-slot:head>
    @endif

    @include('rankings.partials.hero', [
        'title' => 'Top', 'highlight' => $seg['label'], 'subtitle' => 'in '.$place.' — '.$subtitle,
        'intro' => $calc?->includes_demo
            ? 'Explore '.strtolower($seg['label']).' by price history, rental yield, location, development and risk. This preview uses illustrative data, not verified market records.'
            : 'Explore '.strtolower($seg['label']).' ranked only from reviewed and verified market records: price history, rental yield, location, development and risk, each with its data confidence and sources.',
        'photo' => config('meezan.place_photos.'.($city ?? 'home')),
        'stats' => [[$total, $seg['label'].' tracked', 'layers'], [$scoredCount, 'Scored (MPIS)', 'chart'], [7, 'Major Cities', 'map-pin'], ['v'.($calc?->methodology_version ?? \App\Models\InvestmentMethodology::active()?->version ?? '—'), 'Methodology', 'shield']],
        'segmentKey' => $segmentKey, 'filters' => $filters, 'sizes' => $sizes,
    ])

    @include('rankings.partials.city-cards', ['active' => $city, 'link' => fn ($slug) => route('rankings.segment', [$slug, $group, $segmentKey]),
        'countLabel' => fn ($c) => $c['matching'].' '.\Illuminate\Support\Str::plural($seg['singular'], $c['matching']).' tracked'])
    @include('rankings.partials.category-nav', ['mode' => 'segment', 'active' => $segmentKey])

    <div class="container-page py-6" data-rankings-page>
        @include('investment.partials.demo-notice')
        @unless ($calc)
            <p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                <strong>No rankings are published yet.</strong> Entries below are listed without scores until verified market records are reviewed and an administrator publishes a calculation.
                <a href="{{ route('investment.methodology') }}" class="link">How rankings work</a>
            </p>
        @endunless

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] 2xl:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)_minmax(0,0.8fr)]">
            <section class="card min-w-0 overflow-hidden" aria-labelledby="table-title">
                <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <h2 id="table-title" class="text-base font-bold">{{ $title }}</h2>
                    <a href="{{ route('investment.methodology') }}" class="link text-[13px]">How MPIS works</a>
                </div>
                @include('rankings.partials.segment-results')
            </section>
            <div class="min-w-0">@include('rankings.partials.map', ['title' => 'Investment Map — '.$seg['label'].' in '.$place])</div>
            <div class="min-w-0 xl:col-span-2 2xl:col-span-1">
                @include('rankings.partials.advisor', ['examples' => [
                    'Which '.strtolower($seg['singular']).' is best for investment in '.$place.'?', 'Which '.strtolower($seg['label']).' have verified rental income in '.$place.'?',
                    'Compare the top '.strtolower($seg['label']).' in '.$place.'.', 'What are the risks of investing in '.strtolower($seg['label']).' in '.$place.'?']])
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            <div class="min-w-0">@include('rankings.partials.performance')</div>
            <div class="min-w-0 space-y-6">
                <section class="card p-4">@include('rankings.partials.forecast', ['scenarios' => ($selected['row'] ?? null)?->metric('scenarios'), 'name' => $selected['name'] ?? null, 'r' => $selected['row'] ?? null])</section>
                <section class="card overflow-hidden" aria-labelledby="compare-title" data-compare-tray>
                    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                        <h2 id="compare-title" class="text-base font-bold">Compare {{ $seg['label'] }}</h2>
                    </div>
                    <div class="p-4 text-[13px]">
                        <p class="text-muted" data-compare-empty>Tick up to four entries in the table to compare them side by side. Only the same property type{{ $seg['group_by'] ? ' and '.($seg['group_by'] === 'bedrooms' ? 'bedroom count' : 'size') : '' }} is compared like for like.</p>
                        <ul class="mt-3 flex flex-wrap gap-2" data-compare-list></ul>
                        <a href="{{ route('rankings.compare', [$citySlug, $group, $segmentKey]) }}" class="btn btn-primary mt-3" data-compare-now>Compare Now <x-icon name="arrow-right" class="size-4" /></a>
                    </div>
                </section>
            </div>
        </div>

        <div class="mt-6">@include('rankings.partials.featured', ['title' => 'Featured '.$seg['label'].' in '.$place, 'url' => $itemUrl])</div>
        <div class="mt-6">@include('rankings.partials.upcoming', ['title' => 'Upcoming & New '.$seg['label']])</div>
        <div class="mt-6">@include('investment.partials.disclaimer')</div>
    </div>
</x-layouts.app>
