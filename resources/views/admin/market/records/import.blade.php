<x-layouts.dashboard :title="'Import '.$type" section="admin" :heading="'Import '.($type === 'observations' ? 'price & rent observations' : 'market indicators')" subheading="Rows are validated with the same rules as manual entry, saved as drafts and still go through review. Exact duplicates are skipped.">
    @include('admin.market.partials.nav')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <form method="POST" action="{{ route('admin.market.records.import.store', $type) }}" enctype="multipart/form-data" class="card card-body space-y-4">
            @csrf
            <div>
                <label for="file" class="label">CSV file (UTF-8, up to 2 MB, {{ number_format(\App\Services\Investment\MarketRecords::MAX_IMPORT_ROWS) }} rows)</label>
                <input id="file" name="file" type="file" accept=".csv,text/csv" required class="input">
                @error('file')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary">Import as drafts</button>
            <p class="text-[13px] text-muted">Only import data you are allowed to use, from a registered source (source_id). Do not import scraped data.</p>
        </form>
        <section class="card card-body text-[13px]">
            <h2 class="text-base font-bold">Columns</h2>
            <p class="mt-1 text-muted">First row must be the header. Use area_slug / project_slug for locations (blank = whole city), dates as YYYY-MM-DD.</p>
            <code class="mt-2 block rounded-lg bg-page p-3 text-xs break-words">{{ implode(',', $columns) }}</code>
            <a href="{{ route('admin.market.records.template', $type) }}" class="link mt-3 inline-block">Download the header template</a>
        </section>
    </div>

    @isset($batch)
        <section class="card card-body mt-6" aria-live="polite">
            <h2 class="text-base font-bold">Result: {{ $batch->file_name }}</h2>
            <p class="mt-1 text-sm">{{ $batch->rows }} rows read · <strong>{{ $batch->imported }}</strong> imported as drafts · {{ $batch->duplicates }} duplicates skipped · {{ $batch->failed }} with errors</p>
            @if ($batch->errors)
                <ul class="mt-3 space-y-1 text-[13px]">
                    @foreach ($batch->errors as $e)<li><span class="font-semibold">Row {{ $e['row'] }}:</span> {{ implode(' ', $e['errors']) }}</li>@endforeach
                </ul>
            @endif
            <a href="{{ route('admin.market.records.index', [$type, 'status' => 'draft']) }}" class="link mt-3 inline-block text-sm">Review the imported drafts</a>
        </section>
    @endisset
</x-layouts.dashboard>
