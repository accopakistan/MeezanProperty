<x-layouts.dashboard :title="'Edit '.$area->name" section="admin" :heading="$area->name.', '.$area->city" subheading="Hierarchy, authority status, aliases and map centre. Record approval status only as published by the authority, with its reference and source.">
    <p class="mb-4 text-sm"><a href="{{ route('admin.areas.index', ['city' => $area->city]) }}" class="link">← All locations in {{ $area->city }}</a> · <a href="{{ route('areas.show', $area) }}" class="link">Public guide</a></p>

    @unless ($area->is_active)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            This location is inactive{{ $area->merged_into_id ? ' (merged)' : '' }}: it is hidden from search, forms and the directory.
        </div>
    @endunless

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <form method="POST" action="{{ route('admin.areas.update', $area) }}" class="card card-body space-y-5">
            @csrf @method('PATCH')
            <input type="hidden" name="full_edit" value="1">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Display name" required :value="$area->name" />
                <x-field name="city" type="select" label="City" required :value="$area->city" :options="\App\Support\Cities::options()" />
                <x-field name="official_name" label="Official name (as listed by the authority)" :value="$area->official_name" />
                <x-field name="type" type="select" label="Type" required :value="$area->type" :options="\App\Models\Area::TYPES" />
                <div class="sm:col-span-2">
                    <label for="parent_id" class="label">Part of</label>
                    <select id="parent_id" name="parent_id" class="input">
                        <option value="">— Top level in {{ $area->city }} —</option>
                        @foreach ($parents as $p)
                            <option value="{{ $p->id }}" @selected(old('parent_id', $area->parent_id) === $p->id)>{{ str_repeat('— ', $p->depth) }}{{ $p->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <fieldset class="space-y-4 rounded-xl border border-line p-4">
                <legend class="px-1 text-sm font-semibold">Authority &amp; approval</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="authority" label="Authority" :value="$area->authority" placeholder="e.g. LDA, CDA, DHA Karachi" />
                    <x-field name="approval_status" type="select" label="Approval status" required :value="$area->approval_status" :options="\App\Models\Area::APPROVAL_STATUSES" />
                    <x-field name="approval_reference" label="Reference" :value="$area->approval_reference" placeholder="List, letter no., file no., date" class="sm:col-span-2" />
                    <x-field name="verification_source_url" type="url" label="Source URL" :value="$area->verification_source_url" />
                    <x-field name="verified_on" type="date" label="Checked on" :value="$area->verified_on?->toDateString()" />
                    <x-field name="confidence" type="select" label="Confidence" :value="$area->confidence" placeholder="—" :options="['high' => 'High', 'medium' => 'Medium', 'low' => 'Low']" />
                </div>
                <p class="text-[13px] text-muted">A society missing from an authority’s list is “Not verified”, never “illegal”. Use “Listed as illegal” only with the authority’s own list as the source.</p>
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-4">
                <x-field name="latitude" label="Centre latitude" :value="$area->latitude" inputmode="decimal" />
                <x-field name="longitude" label="Centre longitude" :value="$area->longitude" inputmode="decimal" />
                <x-field name="sort_order" type="number" label="Order" min="0" max="1000" :value="$area->sort_order" />
                <div class="text-[13px] text-muted sm:pt-7">Source: {{ $area->coordinates_source ?? 'none' }}{{ $area->coordinate_precision ? ' ('.$area->coordinate_precision.')' : '' }}</div>
            </div>

            <div>
                <label for="aliases" class="label">Aliases <span class="font-normal text-muted">(one per line: spellings, abbreviations, old names)</span></label>
                <textarea id="aliases" name="aliases" rows="4" class="input">{{ old('aliases', $area->aliases->pluck('alias')->implode("\n")) }}</textarea>
            </div>
            <x-field name="notes" type="textarea" label="Research notes" :value="$area->notes" rows="3" />

            <div class="flex flex-wrap gap-5 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="is_popular" value="1" class="checkbox" @checked($area->is_popular)> Popular</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="needs_review" value="1" class="checkbox" @checked($area->needs_review)> Flag for review</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked($area->is_active)> Active (shown in search and forms)</label>
            </div>
            <button class="btn btn-primary">Save location</button>
        </form>

        <div class="space-y-6">
            @if ($area->is_active)
                <form method="POST" action="{{ route('admin.areas.merge', $area) }}" class="card card-body space-y-3">
                    @csrf
                    <h2 class="text-base font-bold">Merge a duplicate</h2>
                    <p class="text-[13px] text-muted">Moves this location’s listings, leads, projects, dealer areas, requirements and phases to another {{ $area->city }} location, adds “{{ $area->name }}” as its alias and deactivates this record. Old links redirect.</p>
                    <x-field name="into_id" type="select" label="Merge into" required placeholder="Choose the location to keep" :options="$parents->where('id', '!=', $area->parent_id)->pluck('name', 'id')->all()" />
                    <label class="flex items-start gap-2 text-[13px]"><input type="checkbox" name="confirm" value="1" class="checkbox mt-0.5"> I have checked these are the same place.</label>
                    @error('confirm')<p class="field-error">{{ $message }}</p>@enderror
                    <button class="btn btn-secondary">Merge</button>
                </form>
            @endif

            @php $outline = $area->boundaries()->whereIn('status', ['published', 'verified', 'draft'])->orderByRaw("case status when 'published' then 0 when 'verified' then 1 else 2 end")->latest('id')->first(); @endphp
            <section class="card card-body">
                <h2 class="text-base font-bold">Map boundary</h2>
                @if ($outline)
                    <p class="mt-1 text-[13px] text-muted">Outline #{{ $outline->id }} · {{ $outline->statusLabel() }} · {{ number_format($outline->vertex_count) }} points</p>
                    <a href="{{ route('admin.boundaries.edit', $outline) }}" class="btn btn-secondary btn-sm mt-3">Open outline</a>
                @else
                    <p class="mt-1 text-[13px] text-muted">No outline yet. Outlines appear on map search once verified and published.</p>
                    @if ($area->is_active)<a href="{{ route('admin.boundaries.create', ['area' => $area->id]) }}" class="btn btn-outline btn-sm mt-3"><x-icon name="pencil" class="size-4" /> Draw outline</a>@endif
                @endif
            </section>

            <section class="card card-body">
                <h2 class="text-base font-bold">History</h2>
                <ul class="mt-3 space-y-2 text-[13px]">
                    @forelse ($history as $log)
                        <li><span class="font-medium">{{ $log->action }}</span> · {{ $log->user?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}</li>
                    @empty
                        <li class="text-muted">No changes recorded yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-layouts.dashboard>
