@php
    /** @var \App\Models\AreaBoundary $boundary */
    $mapConfig = config('meezan.map');
    $editable = ! $boundary->exists || $boundary->isEditable();
    $geometry = old('geometry', $boundary->exists ? json_encode($boundary->geometry, JSON_UNESCAPED_SLASHES) : '');
    $editor = [
        'style' => $mapConfig['style_url'] ?: null,
        'raster' => ['tiles' => $mapConfig['tile_url'], 'attribution' => $mapConfig['attribution']],
        'center' => $centre ? [(float) $centre[1], (float) $centre[0]] : null,
        'areaCentre' => $area->hasCoordinates() ? [(float) $area->longitude, (float) $area->latitude] : null,
        'pakistan' => [[$mapConfig['pakistan_bounds']['west'], $mapConfig['pakistan_bounds']['south']], [$mapConfig['pakistan_bounds']['east'], $mapConfig['pakistan_bounds']['north']]],
        'editable' => $editable,
        'neighbours' => ['type' => 'FeatureCollection', 'features' => $neighbours->map(fn ($n) => ['type' => 'Feature', 'properties' => ['name' => $n->area->name], 'geometry' => $n->geometry])->values()],
    ];
    $title = ($boundary->exists ? 'Boundary #'.$boundary->id : 'New boundary').' — '.$area->name;
@endphp
<x-layouts.dashboard :title="$title" section="admin" :heading="$area->name.', '.$area->city" :subheading="$boundary->exists ? 'Boundary #'.$boundary->id.' · '.$boundary->statusLabel() : 'New outline (saved as a draft)'">
    <p class="mb-4 text-sm"><a href="{{ route('admin.boundaries.index', ['city' => $area->city]) }}" class="link">← Map boundaries</a> · <a href="{{ route('admin.areas.edit', $area) }}" class="link">Location record</a> · <a href="{{ route('areas.show', $area) }}" class="link">Public guide</a></p>

    @if ($centreOutside)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">
            The location’s recorded centre point is outside this outline. Check the outline (or the centre on the location record) before verifying.
        </div>
    @endif
    @unless ($editable)
        <div class="mb-4 rounded-lg border border-line bg-page p-3 text-sm" role="status">
            {{ $boundary->statusLabel() }} outlines are not edited in place. To change it, create a new draft from it; publishing the new draft replaces this one.
        </div>
    @endunless

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <form method="POST" action="{{ $boundary->exists ? route('admin.boundaries.update', $boundary) : route('admin.boundaries.store') }}" class="card card-body space-y-4" data-boundary-form>
            @csrf
            @if ($boundary->exists) @method('PATCH') @else <input type="hidden" name="area_id" value="{{ $area->id }}"> @endif

            @if ($editable)
                <div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="Outline tools">
                    <button type="button" class="btn btn-primary btn-sm" data-be="draw" aria-pressed="false"><x-icon name="pencil" class="size-4" /> <span data-be-draw-label>Add points</span></button>
                    <button type="button" class="btn btn-secondary btn-sm" data-be="undo"><x-icon name="refresh" class="size-4" /> Undo</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-be="delete" disabled>Delete point</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-be="clear">Clear</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-be="fit"><x-icon name="map" class="size-4" /> Zoom to outline</button>
                </div>
                <p class="text-[13px] text-muted">Press <strong>Add points</strong>, then click around the edge of {{ $area->name }}. Drag a point to move it; drag a small white dot to add a point between two others; select a point and press Delete to remove it.</p>
            @endif

            <div class="relative h-[60vh] min-h-[380px] overflow-hidden rounded-xl border border-line bg-brand-50">
                <div class="size-full" data-boundary-editor="{{ json_encode($editor, JSON_UNESCAPED_SLASHES) }}" role="application" aria-label="Boundary map editor"></div>
                <p class="absolute inset-x-3 bottom-3 z-10 rounded-md bg-white/95 px-3 py-1.5 text-xs text-muted shadow" data-be-status aria-live="polite">Loading map…</p>
            </div>

            <details class="rounded-xl border border-line p-3" @if ($errors->has('geometry')) open @endif>
                <summary class="cursor-pointer text-sm font-semibold">GeoJSON (paste or import)</summary>
                <p class="mt-2 text-[13px] text-muted">A Polygon or MultiPolygon in [longitude, latitude] order, a Feature, or a FeatureCollection with one feature. Shapes with holes or several parts can be imported here; the map tools edit single outlines only.</p>
                <textarea name="geometry" rows="6" class="input mt-2 font-mono text-xs" data-be-geojson @readonly(! $editable) spellcheck="false">{{ $geometry }}</textarea>
                @if ($editable)
                    <label class="mt-2 block text-[13px]">Import a .geojson file: <input type="file" accept=".geojson,.json,application/geo+json,application/json" class="text-[13px]" data-be-file></label>
                @endif
            </details>
            @error('geometry')<p class="field-error" role="alert">{{ $message }}</p>@enderror

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="source" type="select" label="Source" required :value="$boundary->source ?? 'admin_drawn'" :options="\App\Models\AreaBoundary::SOURCES" :disabled="! $editable" />
                <x-field name="source_reference" label="Source reference and licence" :value="$boundary->source_reference" placeholder="e.g. OSM relation 1234567 (ODbL), authority map ref." :disabled="! $editable" />
            </div>
            <x-field name="notes" type="textarea" label="Notes" rows="2" :value="$boundary->notes" :disabled="! $editable" />
            <p class="text-[13px] text-muted">Only use outlines Meezan Property has drawn or checked itself, or open data whose licence allows it (record it above). Never copy another portal’s maps or outlines.</p>

            @if ($editable)
                <button class="btn btn-primary">{{ $boundary->exists ? 'Save draft' : 'Save as draft' }}</button>
            @endif
        </form>

        <div class="space-y-6">
            @if ($boundary->exists)
                <section class="card card-body">
                    <h2 class="text-base font-bold">Status: {{ $boundary->statusLabel() }}</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-[13px]">
                        <dt class="text-muted">Points</dt><dd>{{ number_format($boundary->vertex_count) }}</dd>
                        <dt class="text-muted">Size</dt><dd>{{ $boundary->area_sq_km !== null ? number_format($boundary->area_sq_km, 2).' km²' : '—' }}</dd>
                        <dt class="text-muted">Verified</dt><dd>{{ $boundary->verified_at?->localTime()->format('j M Y') ?? '—' }}</dd>
                        <dt class="text-muted">Published</dt><dd>{{ $boundary->published_at?->localTime()->format('j M Y') ?? '—' }}</dd>
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($boundary->nextActions() as $action => $label)
                            <form method="POST" action="{{ route('admin.boundaries.transition', $boundary) }}"
                                  @if ($action === 'publish') data-confirm="Publish this outline on the public map? It replaces any outline already published for {{ $area->name }}." @endif>
                                @csrf @method('PATCH')
                                <input type="hidden" name="action" value="{{ $action }}">
                                <button @class(['btn btn-sm', 'btn-primary' => in_array($action, ['verify', 'publish'], true), 'btn-secondary' => ! in_array($action, ['verify', 'publish'], true)])>{{ $label }}</button>
                            </form>
                        @endforeach
                        @unless ($editable)
                            <form method="POST" action="{{ route('admin.boundaries.duplicate', $boundary) }}">
                                @csrf
                                <button class="btn btn-outline btn-sm">New draft from this</button>
                            </form>
                        @endunless
                    </div>
                    <p class="mt-3 text-[12.5px] text-muted">Draft → Verified (checked against the ground or the authority’s map) → Published (on the public map). Unpublishing returns it to Verified.</p>
                </section>
            @endif

            <section class="card card-body">
                <h2 class="text-base font-bold">Versions for {{ $area->name }}</h2>
                <ul class="mt-3 space-y-2 text-[13px]">
                    @forelse ($versions as $v)
                        <li class="flex justify-between gap-2">
                            <a href="{{ route('admin.boundaries.edit', $v) }}" @class(['link', 'font-bold' => $v->is($boundary)])>#{{ $v->id }} · {{ $v->statusLabel() }}</a>
                            <span class="text-muted">{{ $v->updated_at?->localTime()->format('j M Y') }}</span>
                        </li>
                    @empty
                        <li class="text-muted">No outline yet.</li>
                    @endforelse
                </ul>
            </section>

            @if ($history->isNotEmpty())
                <section class="card card-body">
                    <h2 class="text-base font-bold">History</h2>
                    <ul class="mt-3 space-y-2 text-[13px]">
                        @foreach ($history as $log)
                            <li><span class="font-medium">{{ $log->actionLabel() }}</span> <span class="text-muted">· {{ $log->user?->name ?? 'System' }} · {{ $log->created_at->localTime()->format('j M Y, g:i a') }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
