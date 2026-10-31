<x-layouts.dashboard title="Locations" section="admin" heading="Locations" subheading="The national directory of areas, societies, phases and blocks used for listings, search and lead routing. Latitude/longitude is the approximate centre used to place listings without an exact pin.">
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <x-stat-card label="Active locations" :value="$totals['active']" icon="map-pin" />
        <a href="{{ route('admin.areas.index', ['review' => 1]) }}"><x-stat-card label="Flagged for review" :value="$totals['review']" icon="alert" tone="gold" /></a>
        <a href="{{ route('admin.areas.index', ['no_coords' => 1]) }}"><x-stat-card label="Without a map centre" :value="$totals['noCoords']" icon="map" tone="info" /></a>
    </div>

    <form method="POST" action="{{ route('admin.areas.store') }}" class="card mb-4 grid gap-3 p-4 sm:grid-cols-[1.5fr_1fr_120px_auto_auto] sm:items-end">
        @csrf
        <x-field name="name" label="New area / society" required placeholder="e.g. DHA Phase 9 Prism" />
        <x-field name="city" type="select" label="City" required :options="\App\Support\Cities::options()" />
        <x-field name="sort_order" type="number" label="Order" min="0" max="1000" placeholder="100" />
        <label class="flex h-11 items-center gap-2 text-sm"><input type="checkbox" name="is_popular" value="1" class="checkbox"> Popular</label>
        <button class="btn btn-primary h-11">Add area</button>
    </form>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto]" role="search">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Name or alias" aria-label="Search locations">
        <select name="city" class="input" aria-label="City"><option value="">All cities</option>@foreach (config('meezan.cities') as $c)<option @selected(($filters['city'] ?? null) === $c)>{{ $c }}</option>@endforeach</select>
        <select name="type" class="input" aria-label="Type"><option value="">All types</option>@foreach (\App\Models\Area::TYPES as $k => $l)<option value="{{ $k }}" @selected(($filters['type'] ?? null) === $k)>{{ $l }}</option>@endforeach</select>
        <select name="status" class="input" aria-label="Approval status"><option value="">Any status</option>@foreach (\App\Models\Area::APPROVAL_STATUSES as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? null) === $k)>{{ $l }}</option>@endforeach</select>
        <button class="btn btn-secondary">Filter</button>
        <div class="flex flex-wrap gap-4 text-sm sm:col-span-2 lg:col-span-5">
            <label class="flex items-center gap-2"><input type="checkbox" name="review" value="1" class="checkbox" @checked($filters['review'] ?? false)> Flagged for review</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="no_coords" value="1" class="checkbox" @checked($filters['no_coords'] ?? false)> No map centre</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="inactive" value="1" class="checkbox" @checked($filters['inactive'] ?? false)> Inactive / merged</label>
        </div>
    </form>

    <p class="mb-2 text-sm text-muted">{{ number_format($areas->total()) }} {{ \Illuminate\Support\Str::plural('location', $areas->total()) }}</p>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th colspan="9" class="p-0">
                        <div class="grid min-w-[980px] grid-cols-[1.6fr_0.8fr_1fr_100px_100px_60px_60px_70px_auto] gap-3 px-4 py-3"><span>Location</span><span>City</span><span>Type · status</span><span>Latitude</span><span>Longitude</span><span class="text-right">Listings</span><span class="text-center">Popular</span><span>Order</span><span class="w-28"></span></div>
                    </th></tr>
                </thead>
                <tbody>
                    @forelse ($areas as $area)
                        <tr>
                            <td colspan="9" class="p-0">
                                <form method="POST" action="{{ route('admin.areas.update', $area) }}" class="grid min-w-[980px] grid-cols-[1.6fr_0.8fr_1fr_100px_100px_60px_60px_70px_auto] items-center gap-3 px-4 py-2.5">
                                    @csrf @method('PATCH')
                                    <div class="min-w-0">
                                        <input name="name" value="{{ $area->name }}" class="input py-1.5 text-sm" aria-label="Name" required>
                                        @if ($area->parent)<p class="mt-0.5 truncate text-[11px] text-muted">in {{ $area->parent->name }}</p>@endif
                                    </div>
                                    <select name="city" class="input py-1.5 text-sm" aria-label="City">@foreach (config('meezan.cities') as $city)<option @selected($area->city === $city)>{{ $city }}</option>@endforeach</select>
                                    <span class="text-[12px] leading-tight">
                                        {{ $area->typeLabel() }}<br>
                                        <span class="text-muted">{{ $area->approvalLabel() }}</span>
                                        @if ($area->needs_review)<span class="badge mt-0.5 bg-amber-50 text-amber-800 ring-1 ring-amber-200">Review</span>@endif
                                    </span>
                                    <input name="latitude" value="{{ $area->latitude }}" inputmode="decimal" class="input py-1.5 text-sm" aria-label="Latitude" placeholder="—" title="{{ $area->coordinates_source ? 'Source: '.$area->coordinates_source : 'Approximate centre, used for listings without an exact pin' }}">
                                    <input name="longitude" value="{{ $area->longitude }}" inputmode="decimal" class="input py-1.5 text-sm" aria-label="Longitude" placeholder="—">
                                    <span class="text-right text-sm tabular-nums">{{ $area->properties_count }}</span>
                                    <label class="flex justify-center"><input type="checkbox" name="is_popular" value="1" class="checkbox" @checked($area->is_popular) aria-label="Popular"></label>
                                    <input type="number" name="sort_order" value="{{ $area->sort_order }}" class="input py-1.5 text-sm" aria-label="Order">
                                    <span class="flex gap-1.5">
                                        <button class="btn btn-secondary btn-sm">Save</button>
                                        <a href="{{ route('admin.areas.edit', $area) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    </span>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-sm text-muted">No locations match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $areas->links() }}</div>
</x-layouts.dashboard>
