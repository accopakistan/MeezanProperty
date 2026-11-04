<x-layouts.dashboard title="Investment Index" section="admin" heading="Meezan Investment Index" subheading="Five categories ranked from verified, traceable evidence only. Collection, matching, scoring and publishing run automatically; staff authorize sources once, check owner documents and decide exceptions.">
    @include('admin.investment-index.partials.nav')

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Published index</h2>
            @if ($run)
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $run->summary['ranked'] ?? 0 }} <span class="text-sm font-medium text-muted">ranked of {{ $run->summary['projects'] ?? 0 }}</span></p>
                <p class="mt-1 text-[13px] text-muted">Run #{{ $run->id }}, {{ $run->published_at->localTime()->format('j M Y, H:i') }} · methodology v{{ $run->methodology_version }} · {{ $run->summary['insufficient'] ?? 0 }} with insufficient verified data · {{ $run->summary['excluded'] ?? 0 }} excluded</p>
            @else
                <p class="mt-1 text-[13px] text-muted">No run published yet.</p>
            @endif
            @if ($lastRun && $lastRun->status === 'failed')<p class="mt-2 text-[13px] text-red-600">Last run #{{ $lastRun->id }} failed: {{ $lastRun->error }}</p>@endif
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Verified evidence</h2>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($counts['evidence'] + $counts['legal']) }}</p>
            <p class="mt-1 text-[13px] text-muted">{{ number_format($counts['legal']) }} legal records · {{ number_format($counts['evidence']) }} evidence records · {{ $counts['real_projects'] }} real projects</p>
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Official registry</h2>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($counts['registry']) }} <span class="text-sm font-medium text-muted">entries</span></p>
            <p class="mt-1 text-[13px] text-muted">{{ number_format($counts['matched']) }} matched to projects · {{ $counts['collectable'] }} of {{ $counts['sources'] }} sources authorized</p>
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Waiting for staff</h2>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $counts['exceptions'] + $counts['submissions'] }}</p>
            <p class="mt-1 text-[13px]"><a class="link" href="{{ route('admin.investment-index.exceptions') }}">{{ $counts['exceptions'] }} exceptions</a> · <a class="link" href="{{ route('admin.investment-index.submissions') }}">{{ $counts['submissions'] }} owner documents</a></p>
        </section>
    </div>

    <section class="card card-body mt-6">
        <h2 class="text-base font-bold">Recent collections</h2>
        @include('admin.investment-index.partials.runs', ['runs' => $collections])
    </section>
</x-layouts.dashboard>
