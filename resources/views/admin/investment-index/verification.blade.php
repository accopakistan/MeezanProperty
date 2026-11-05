<x-layouts.dashboard title="Verification Centre" section="admin" heading="Verification Centre" subheading="Records added by staff or imported wait here. Check each against its original source: only verified records can affect a score.">
    @include('admin.investment-index.partials.nav')
    @if ($onlyProject)
        <p class="mb-4 rounded-lg border border-line bg-surface px-4 py-3 text-sm">Showing records for <a href="{{ route('rankings.project', $onlyProject) }}" class="link font-semibold">{{ $onlyProject->name }}</a>, {{ $onlyProject->city }}. <a href="{{ route('admin.investment-index.verification') }}" class="link">Show all</a></p>
    @endif

    @if ($bySource->isNotEmpty())
        <section class="card card-body mb-6" aria-labelledby="by-source-title">
            <h2 id="by-source-title" class="text-base font-bold">Whole lists waiting for a check</h2>
            <p class="mt-1 text-[13px] text-muted">These were read by eye from scanned official lists. Compare them against the original, then verify the list. Until then they count towards nothing.</p>
            <ul class="mt-3 divide-y divide-line text-sm">
                @foreach ($bySource as $list)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <span class="min-w-0"><a href="{{ $list->url }}" class="link font-semibold" target="_blank" rel="noopener nofollow">{{ $list->name }}</a> <span class="block text-xs text-muted">{{ $list->pending_legal }} legal {{ str('record')->plural($list->pending_legal) }} waiting</span></span>
                        <form method="POST" action="{{ route('admin.investment-index.review-source', $list->id) }}" class="flex flex-wrap items-center gap-3">
                            @csrf
                            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="checked" value="1" class="checkbox" required> I checked this list against the source</label>
                            <button class="btn btn-secondary btn-sm">Verify list</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($importResult && $importResult['errors'])
        <section class="card card-body mb-6" role="status">
            <h2 class="text-base font-bold">Rejected rows</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-[13px]">@foreach ($importResult['errors'] as $e)<li>Row {{ $e['row'] }}: {{ implode(' ', $e['errors']) }}</li>@endforeach</ul>
        </section>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.investment-index.legal.store') }}" class="card card-body min-w-0 space-y-3">
            @csrf
            <h2 class="text-base font-bold">Add a legal record</h2>
            <x-field name="project_slug" label="Project (address name, as in its page address)" required hint="For example: lucky-one-mall" />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="authority" label="Responsible authority" required placeholder="SBCA" />
                <x-field name="reference" label="Approval or NOC reference" />
                <x-field name="record_type" type="select" label="Record type" required :options="$recordTypes" />
                <x-field name="status" type="select" label="Status" required :options="$statuses" />
                <x-field name="severity" type="select" label="Severity" :options="$severities" />
                <x-field name="issued_on" type="date" label="Date on the record" />
            </div>
            <x-field name="source_url" type="url" label="Official source address" required />
            <x-field name="excerpt" type="textarea" rows="2" label="Evidence notes (restrictions, conditions)" />
            <button class="btn btn-primary btn-sm">Add as unverified</button>
        </form>

        <form method="POST" action="{{ route('admin.investment-index.import') }}" enctype="multipart/form-data" class="card card-body min-w-0 space-y-3">
            @csrf
            <h2 class="text-base font-bold">Import from CSV</h2>
            <p class="text-[13px] text-muted">Rows are checked one by one. A row already on file is skipped, nothing verified is overwritten, and rejected rows are listed with reasons. Accepted rows wait here for verification.</p>
            <x-field name="kind" type="select" label="What the file holds" required :options="['legal' => 'Legal records', 'evidence' => 'Evidence (development, occupancy, rent, operations...)']" />
            <div><label class="label" for="import-file">CSV file</label><input id="import-file" type="file" name="file" accept=".csv,text/csv" class="input" required></div>
            <p class="text-[12px] text-muted"><strong>Legal columns:</strong> project_slug, authority, record_type, status, severity, reference, issued_on, source_url, excerpt.<br>
                <strong>Evidence columns:</strong> project_slug, factor, subject, value_numeric, value_level, unit, observed_on, source_name, source_type, source_url. Asking prices can't be imported as evidence; they belong in Market Data.</p>
            <button class="btn btn-secondary btn-sm">Import</button>
        </form>
    </div>

    @foreach ([['Legal records to verify', $legal, 'legal'], ['Evidence to verify', $evidence, 'evidence']] as [$title, $records, $kind])
        <section class="card mt-6 min-w-0 p-5">
            <h2 class="text-base font-bold">{{ $title }} <span class="font-normal text-muted">({{ $records->total() }})</span></h2>
            <ul class="mt-2 divide-y divide-line">
                @forelse ($records as $r)
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3 text-[14px]">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('rankings.project', $r->project) }}" class="link font-semibold">{{ $r->project->name }}</a> <span class="text-[12px] text-muted">{{ $r->project->city }}</span>
                            <span class="block text-[13px]">@if ($kind === 'legal'){{ $r->authority }}: {{ $r->statusLabel() }} · {{ $recordTypes[$r->record_type] ?? $r->record_type }}@if ($r->reference) · {{ $r->reference }}@endif @else{{ $r->factor }}: {{ $r->value_numeric ?? $r->value_level }} {{ $r->unit }} · observed {{ $r->observed_on?->format('j M Y') }}@endif</span>
                            <a href="{{ $r->source_url }}" class="link block break-all text-[12px]" target="_blank" rel="noopener nofollow">{{ $r->source?->name }}: {{ $r->source_url }}</a>
                        </div>
                        <form method="POST" action="{{ route('admin.investment-index.review', [$kind, $r->id]) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <label class="sr-only" for="reason-{{ $kind }}-{{ $r->id }}">Reason, if rejecting</label>
                            <input id="reason-{{ $kind }}-{{ $r->id }}" name="reason" class="input h-9 w-44 py-1 text-[13px]" placeholder="Reason, if rejecting" maxlength="500">
                            <button name="decision" value="verify" class="btn btn-primary btn-sm">Verify</button>
                            <button name="decision" value="reject" class="btn btn-outline btn-sm">Reject</button>
                        </form>
                    </li>
                @empty
                    <li class="py-3 text-[14px] text-muted">Nothing waiting.</li>
                @endforelse
            </ul>
            @if ($records->hasPages())<div class="mt-3">{{ $records->links() }}</div>@endif
        </section>
    @endforeach
</x-layouts.dashboard>
