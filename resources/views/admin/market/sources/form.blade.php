<x-layouts.dashboard :title="$source->exists ? 'Edit source' : 'Add source'" section="admin" :heading="$source->exists ? $source->name : 'Add a data source'" subheading="Record what the source is, whether you may use it, and how reliable it is. Do not add sources that require scraping or bypassing access controls.">
    @include('admin.market.partials.nav')
    <form method="POST" action="{{ $source->exists ? route('admin.market.sources.update', $source) : route('admin.market.sources.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf @if ($source->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Name" required :value="$source->name" />
            <x-field name="type" type="select" label="Source type" required :value="$source->type" placeholder="Choose" :options="config('meezan.investment.source_types')" />
            <x-field name="url" type="url" label="URL or reference" :value="$source->url" />
            <x-field name="publisher" label="Publisher / organisation" :value="$source->publisher" hint="Sources from one publisher count once for independence." />
            <x-field name="license" label="Licence or permission" :value="$source->license" placeholder="e.g. CC BY 4.0, written permission dated …" />
            <x-field name="reliability" type="select" label="Reliability" required :value="$source->reliability" :options="\App\Models\MarketSource::RELIABILITY" />
            <x-field name="coverage" label="Coverage" :value="$source->coverage" placeholder="Cities, property types, period" />
            <x-field name="last_checked_on" type="date" label="Last checked" :value="$source->last_checked_on?->toDateString()" />
        </div>
        <x-field name="notes" type="textarea" label="Notes" rows="3" :value="$source->notes" />
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $source->is_active))> Active (can be cited by new records)</label>
        <button class="btn btn-primary">Save source</button>
    </form>
</x-layouts.dashboard>
