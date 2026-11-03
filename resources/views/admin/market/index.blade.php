<x-layouts.dashboard title="Market Data Centre" section="admin" heading="Meezan Property Market Intelligence & Data Centre" subheading="Prices, rents and market indicators with their sources, reviewed before use. Rankings are calculated only from verified records and published by an admin.">
    @include('admin.market.partials.nav')

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach (['observations' => ['Price & rent observations', $observations], 'indicators' => ['Market indicators', $indicators]] as $type => [$label, $counts])
            <section class="card card-body">
                <h2 class="text-sm font-semibold text-muted">{{ $label }}</h2>
                <p class="mt-1 text-3xl font-bold">{{ number_format(array_sum($counts)) }}</p>
                <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[13px]">
                    @foreach (\App\Models\MarketObservation::STATUSES as $key => $s)
                        <li><a href="{{ route('admin.market.records.index', [$type, 'status' => $key]) }}" class="link">{{ $s }} {{ $counts[$key] ?? 0 }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endforeach
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Sources</h2>
            <p class="mt-1 text-3xl font-bold">{{ $sources }}</p>
            <a href="{{ route('admin.market.sources.create') }}" class="link mt-2 inline-block text-[13px]">Add a source</a>
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Published rankings</h2>
            @if ($published)
                <p class="mt-1 text-lg font-bold">{{ $published->published_at?->localTime()->format('j M Y') }}</p>
                <p class="text-[13px] text-muted">Methodology v{{ $published->methodology_version }} · {{ $published->summary['ranked'] ?? 0 }} ranked</p>
            @else
                <p class="mt-1 text-lg font-bold">None yet</p>
            @endif
            <p class="mt-1 text-[13px] text-muted">Active methodology: v{{ $methodology?->version ?? '—' }}</p>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card card-body">
            <h2 class="text-base font-bold">Verified records by city</h2>
            <table class="mt-3 w-full text-left text-sm">
                <thead class="text-[13px] text-muted"><tr><th class="py-1.5 font-medium">City</th><th class="py-1.5 font-medium">Observations</th><th class="py-1.5 font-medium">Indicators</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach (config('meezan.cities') as $city)
                        <tr><td class="py-1.5">{{ $city }}</td><td class="py-1.5 tabular-nums">{{ $verifiedByCity['observations'][$city] ?? 0 }}</td><td class="py-1.5 tabular-nums">{{ $verifiedByCity['indicators'][$city] ?? 0 }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <section class="card card-body">
            <h2 class="text-base font-bold">Recent imports</h2>
            <ul class="mt-3 space-y-2 text-[13px]">
                @forelse ($imports as $b)
                    <li class="flex justify-between gap-2"><span>{{ $b->file_name }} <span class="text-muted">· {{ $b->type }} · {{ $b->creator?->name }}</span></span><span class="text-muted">{{ $b->imported }} imported · {{ $b->duplicates }} duplicates · {{ $b->failed }} errors</span></li>
                @empty
                    <li class="text-muted">No imports yet.</li>
                @endforelse
            </ul>
            @if ($latest)
                <p class="mt-4 text-[13px]">Latest calculation: <a href="{{ route('admin.market.calculations.show', $latest) }}" class="link">#{{ $latest->id }} · {{ $latest->statusLabel() }}</a></p>
            @endif
        </section>
    </div>
</x-layouts.dashboard>
