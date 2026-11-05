<x-layouts.dashboard title="Data validation" section="admin" heading="Ranking data validation" subheading="Checks on real records only. Errors should be zero; the information lines describe work still to do. The same checks run with php artisan meezan:investment-validate.">
    @include('admin.investment-index.partials.nav')

    <section class="card overflow-hidden" aria-labelledby="checks-title">
        <h2 id="checks-title" class="sr-only">Checks</h2>
        <ul class="divide-y divide-line text-sm">
            @foreach ($report as $check)
                <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-3">
                    <span class="min-w-0 flex-1">
                        <span class="font-semibold">{{ $check['label'] }}</span>
                        @if ($check['examples'])<span class="block truncate text-xs text-muted">{{ implode(', ', array_slice($check['examples'], 0, 5)) }}</span>@endif
                    </span>
                    <span class="flex items-center gap-3">
                        <span class="text-lg font-bold tabular-nums">{{ number_format($check['count']) }}</span>
                        <x-status-badge :tone="$check['count'] === 0 ? 'success' : ['error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$check['severity']]" :label="$check['count'] === 0 ? 'Clear' : ucfirst($check['severity'])" />
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    <form method="POST" action="{{ route('admin.investment-index.alias.store') }}" class="card card-body mt-6 max-w-2xl space-y-3" aria-labelledby="alias-title">
        @csrf
        <h2 id="alias-title" class="text-base font-bold">Add another name for a project</h2>
        <p class="text-[13px] text-muted">A list that uses this name will then match the project instead of creating a duplicate. Possible duplicates are listed under Coverage.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-field name="project_id" type="number" label="Project ID" min="1" required />
            <x-field name="alias" label="Other name" maxlength="200" required />
        </div>
        <button class="btn btn-secondary btn-sm">Add name</button>
    </form>
</x-layouts.dashboard>
