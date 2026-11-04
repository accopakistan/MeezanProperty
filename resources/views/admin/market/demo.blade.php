<x-layouts.dashboard title="Demo data" section="admin" heading="Investment demo data" subheading="An illustrative dataset (fictional projects marked “(Demo)”, invented prices, rents and indicators) that fills the Investment Rankings until real market data is collected. It is never counted as verified and is ranked in its own calculation.">
    @include('admin.market.partials.nav')
    @php $s = $stats; @endphp

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Demo mode</h2>
            <p class="mt-1 text-2xl font-bold">{{ $s['demo_mode'] ? 'On' : 'Off' }}</p>
            <p class="mt-1 text-[13px] text-muted">{{ $s['demo_mode'] ? 'Public dashboards show the demo rankings, with the demo notice on every page.' : 'Public dashboards show real verified data only.' }}
                {{ $override === null ? 'Set by INVESTMENT_DEMO_MODE ('.($envDefault ? 'true' : 'false').').' : 'Overridden here.' }}</p>
            <form method="POST" action="{{ route('admin.market.demo.mode') }}" class="mt-3 flex flex-wrap gap-2">@csrf
                <button name="mode" value="{{ $s['demo_mode'] ? 'off' : 'on' }}" class="btn btn-primary btn-sm">Turn {{ $s['demo_mode'] ? 'off' : 'on' }}</button>
                @if ($override !== null)<button name="mode" value="default" class="btn btn-secondary btn-sm">Use .env setting</button>@endif
            </form>
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Demo records</h2>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($s['projects'] + $s['observations'] + $s['indicators']) }}</p>
            <p class="mt-1 text-[13px] text-muted">{{ number_format($s['projects']) }} projects · {{ number_format($s['observations']) }} price & rent records · {{ number_format($s['indicators']) }} indicators · {{ $s['sources'] }} demo sources</p>
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Real data (kept separate)</h2>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($s['real']['verified_observations'] + $s['real']['verified_indicators']) }} <span class="text-sm font-medium text-muted">verified records</span></p>
            <p class="mt-1 text-[13px] text-muted">{{ $s['real']['projects'] }} real projects · {{ $s['real']['sources'] }} real sources</p>
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Published rankings</h2>
            <p class="mt-1 text-[13px]">Demo: {!! $s['demo_calculation'] ? '<a class="link" href="'.route('admin.market.calculations.show', $s['demo_calculation']).'">#'.$s['demo_calculation']->id.'</a> · '.e($s['demo_calculation']->published_at?->localTime()->format('j M Y, H:i')) : 'none' !!}</p>
            <p class="mt-1 text-[13px]">Real: {!! $s['real_calculation'] ? '<a class="link" href="'.route('admin.market.calculations.show', $s['real_calculation']).'">#'.$s['real_calculation']->id.'</a>' : 'none (no verified data published)' !!}</p>
            <p class="mt-1 text-[12px] text-muted">A demo calculation never replaces the real one.</p>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card card-body">
            <h2 class="text-base font-bold">Demo projects by city</h2>
            <table class="mt-2 w-full text-left text-[13px]">
                <thead class="text-muted"><tr><th class="py-1 font-medium">City</th><th class="py-1 font-medium">Projects</th><th class="py-1 font-medium">Price & rent records</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach (\App\Support\Cities::names() as $city)
                        <tr><td class="py-1">{{ $city }}</td><td class="py-1 tabular-nums">{{ $s['projects_by_city'][$city] ?? 0 }}</td><td class="py-1 tabular-nums">{{ number_format($s['records_by_city'][$city] ?? 0) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <section class="card card-body">
            <h2 class="text-base font-bold">Demo projects by category</h2>
            <ul class="mt-2 divide-y divide-line text-[13px]">
                @forelse ($s['projects_by_category'] as $cat => $n)
                    <li class="flex justify-between py-1"><span>{{ config('meezan.investment.project_categories.'.$cat.'.label') ?? $cat }}</span><span class="tabular-nums">{{ $n }}</span></li>
                @empty
                    <li class="py-1 text-muted">No demo projects.</li>
                @endforelse
            </ul>
        </section>
        <section class="card card-body">
            <h2 class="text-base font-bold">Demo ranking rows by property type</h2>
            <ul class="mt-2 divide-y divide-line text-[13px]">
                @forelse ($s['demo_rows_by_segment'] as $seg => $n)
                    <li class="flex justify-between py-1"><a class="link" href="{{ route('rankings.segment', ['pakistan', config('meezan.investment.segments.'.$seg.'.group'), $seg]) }}">{{ config('meezan.investment.segments.'.$seg.'.label') }}</a><span class="tabular-nums">{{ $n }}</span></li>
                @empty
                    <li class="py-1 text-muted">No demo rankings calculated.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card card-body space-y-3">
            <h2 class="text-base font-bold">Load, update and rank</h2>
            <p class="text-[13px] text-muted">The dataset is generated the same way every time (seeded by each project's name), so loading it again creates no duplicates. Real projects with the same name are never changed. This can take a minute or two.</p>
            <form method="POST" action="{{ route('admin.market.demo.run') }}" class="flex flex-wrap gap-2">@csrf
                <button name="action" value="seed" class="btn btn-primary">Load demo data (7 cities)</button>
                <button name="action" value="refresh" class="btn btn-secondary" data-confirm="Regenerate the price, rent and indicator records of every demo project?">Regenerate demo records</button>
                <button name="action" value="recalculate" class="btn btn-secondary">Recalculate demo rankings</button>
            </form>
            <p class="text-[12px] text-muted">Command line: <code>php artisan meezan:investment-demo seed|refresh|recalculate|remove|status</code> or <code>php artisan db:seed --class=InvestmentDemoSeeder</code>.</p>
            <p class="text-[13px]">Filter the records: <a class="link" href="{{ route('admin.market.records.index', ['observations', 'origin' => 'demo']) }}">demo price & rent records</a> · <a class="link" href="{{ route('admin.market.records.index', ['observations', 'origin' => 'real']) }}">real records</a> · <a class="link" href="{{ route('admin.projects.index', ['origin' => 'demo']) }}">demo projects</a> · <a class="link" href="{{ route('admin.projects.index', ['origin' => 'real']) }}">real projects</a></p>
        </section>

        <form method="POST" action="{{ route('admin.market.demo.remove') }}" class="card card-body space-y-3 border-red-200">
            @csrf
            <h2 class="text-base font-bold text-red-700">Remove all demo data</h2>
            <p class="text-[13px] text-muted">Deletes every demo project, demo price, rent and indicator record, demo source and demo calculation. Real projects, verified records and real rankings are not touched. A demo project or source that a real record refers to is kept and reported.</p>
            <x-field name="confirm" label="Type REMOVE DEMO DATA to confirm" required autocomplete="off" />
            <button class="btn btn-danger">Remove demo data</button>
        </form>
    </div>
</x-layouts.dashboard>
