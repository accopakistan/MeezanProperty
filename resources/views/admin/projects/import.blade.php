@php $result = session('import'); @endphp
<x-layouts.dashboard title="Import projects" section="admin" heading="Import projects" subheading="Societies, malls, plazas, towers and other developments for Investment Rankings. Each row is checked on its own; existing projects are never overwritten and imported projects stay hidden until you publish them.">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <form method="POST" action="{{ route('admin.projects.import.store') }}" enctype="multipart/form-data" class="card card-body space-y-4">
            @csrf
            <div>
                <label for="file" class="label">CSV file (UTF-8, up to 5 MB, {{ number_format(\App\Services\Investment\ProjectImport::MAX_ROWS) }} rows)</label>
                <input id="file" name="file" type="file" accept=".csv,text/csv" required class="input">
                @error('file')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary">Import (hidden until published)</button>
            <p class="text-[13px] text-muted">Only import projects whose details were checked with the developer or an official source. Market prices, rents and indicators go through the Market Data Centre instead, where they are reviewed before use.</p>
        </form>
        <section class="card card-body text-[13px]">
            <h2 class="text-base font-bold">Columns</h2>
            <p class="mt-1 text-muted">First row must be the header. Required: name, city, status, description. <em>category</em> is one of: {{ implode(', ', array_keys(config('meezan.investment.project_categories'))) }}. <em>status</em>: {{ implode(', ', array_keys(\App\Models\Project::STATUSES)) }}. Dates as YYYY-MM-DD; area_slug from the location directory.</p>
            <code class="mt-2 block rounded-lg bg-page p-3 text-xs break-words">{{ implode(',', $columns) }}</code>
            <a href="{{ route('admin.projects.template') }}" class="link mt-3 inline-block">Download the template</a>
        </section>
    </div>

    @if ($result)
        <section class="card card-body mt-6" aria-live="polite">
            <h2 class="text-base font-bold">Result</h2>
            <p class="mt-1 text-sm">{{ $result['rows'] }} rows read · <strong>{{ $result['imported'] }}</strong> imported · {{ $result['duplicates'] }} already existed (not changed) · {{ count($result['errors']) }} rejected</p>
            @if ($result['errors'])
                <ul class="mt-3 space-y-1 text-[13px]">
                    @foreach ($result['errors'] as $e)<li><span class="font-semibold">Row {{ $e['row'] }}:</span> {{ implode(' ', $e['errors']) }}</li>@endforeach
                </ul>
            @endif
            <a href="{{ route('admin.projects.index') }}" class="link mt-3 inline-block text-sm">Review and publish the imported projects</a>
        </section>
    @endif
</x-layouts.dashboard>
