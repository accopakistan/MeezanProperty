<x-layouts.dashboard title="Map boundaries" section="admin" heading="Map boundaries" subheading="Outlines of societies and areas for map search. Draw or import an outline for an existing location, check it, then publish it. Only published outlines appear on the public map.">
    @php
        $badge = fn (string $status) => match ($status) {
            'published' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'verified' => 'bg-brand-50 text-brand-700 ring-brand-200',
            'archived' => 'bg-page text-muted ring-line',
            default => 'bg-amber-50 text-amber-800 ring-amber-200',
        };
    @endphp

    <div class="mb-5 grid gap-3 sm:grid-cols-4">
        @foreach (\App\Models\AreaBoundary::STATUSES as $key => $label)
            <a href="{{ route('admin.boundaries.index', array_filter(['status' => $key, 'city' => $filters['city']])) }}" class="card card-body py-3 hover:shadow-lift" @if ($filters['status'] === $key) aria-current="true" @endif>
                <p class="text-[13px] text-muted">{{ $label }}</p>
                <p class="text-2xl font-bold">{{ number_format($counts[$key] ?? 0) }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="card card-body mb-5 grid gap-3 sm:grid-cols-[1fr_200px_200px_auto]" role="search">
        <div>
            <label for="q" class="label">Location name</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" class="input" placeholder="e.g. DHA Phase 6, Bahria Town">
        </div>
        <x-field name="city" type="select" label="City" placeholder="All cities" :value="$filters['city']" :options="\App\Support\Cities::options()" />
        <x-field name="status" type="select" label="Status" placeholder="All but archived" :value="$filters['status']" :options="\App\Models\AreaBoundary::STATUSES" />
        <button class="btn btn-primary self-end">Filter</button>
    </form>

    @if ($filters['q'] !== '')
        <section class="card card-body mb-5">
            <h2 class="text-base font-bold">Draw a new outline</h2>
            <p class="mt-1 text-[13px] text-muted">Locations matching “{{ $filters['q'] }}”. Outlines are always attached to an existing location.</p>
            @if ($candidates->isEmpty())
                <p class="mt-3 text-sm text-muted">No active location matches. Add it under <a href="{{ route('admin.areas.index') }}" class="link">Locations</a> first.</p>
            @else
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($candidates as $candidate)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span>{{ $candidate->name }} <span class="text-muted">· {{ $candidate->city }} · {{ \App\Models\Area::TYPES[$candidate->type] ?? $candidate->type }}</span>
                                @if ($candidate->published_count)<span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-xs text-emerald-700">has a published outline</span>@endif
                            </span>
                            <a href="{{ route('admin.boundaries.create', ['area' => $candidate->id]) }}" class="btn btn-outline btn-sm"><x-icon name="pencil" class="size-4" /> Draw outline</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <div class="card relative overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line text-[13px] text-muted">
                <tr><th class="px-4 py-3 font-medium">Location</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3 font-medium">Points</th><th class="px-4 py-3 font-medium">Size</th><th class="px-4 py-3 font-medium">Source</th><th class="px-4 py-3 font-medium">Updated</th><th class="px-4 py-3"><span class="sr-only">Open</span></th></tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($boundaries as $b)
                    <tr>
                        <td class="px-4 py-3"><span class="font-semibold">{{ $b->area->name }}</span><span class="block text-xs text-muted">{{ $b->area->city }} · #{{ $b->id }}</span></td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $badge($b->status) }}">{{ $b->statusLabel() }}</span></td>
                        <td class="px-4 py-3 tabular-nums">{{ number_format($b->vertex_count) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ $b->area_sq_km !== null ? number_format($b->area_sq_km, 2).' km²' : '—' }}</td>
                        <td class="px-4 py-3 text-[13px]">{{ \App\Models\AreaBoundary::SOURCES[$b->source] ?? $b->source }}</td>
                        <td class="px-4 py-3 text-[13px] text-muted">{{ $b->updated_at?->localTime()->format('j M Y, g:i a') }}<span class="block">{{ $b->creator?->name }}</span></td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.boundaries.edit', $b) }}" class="btn btn-secondary btn-sm">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No boundaries yet. Search for a location above to draw its outline.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $boundaries->links() }}</div>
</x-layouts.dashboard>
