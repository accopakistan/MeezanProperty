<x-layouts.dashboard title="Official registry" section="admin" heading="Official registry" subheading="Every entry read from the official lists, and the project it belongs to. A single near-exact name in the same city is matched automatically; anything less certain waits for a decision here. Only matched entries become legal records.">
    @include('admin.investment-index.partials.nav')

    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-4">
        <x-field name="q" label="Scheme name" :value="$filters['q'] ?? null" />
        <x-field name="match" type="select" label="Match" :options="\App\Models\InvestmentRegistryEntry::MATCH_STATUSES" :value="$filters['match'] ?? null" placeholder="All" />
        <x-field name="list" type="select" label="List" :options="\App\Models\InvestmentRegistryEntry::LIST_TYPES" :value="$filters['list'] ?? null" placeholder="All" />
        <div class="flex items-end"><button class="btn btn-secondary">Filter</button></div>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-left text-[13px]">
            <thead class="bg-surface text-muted"><tr><th class="px-4 py-2 font-medium">Official entry</th><th class="px-4 py-2 font-medium">List and status</th><th class="px-4 py-2 font-medium">Seen</th><th class="px-4 py-2 font-medium">Project</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($entries as $e)
                    <tr class="align-top">
                        <td class="px-4 py-2.5"><span class="font-semibold">{{ $e->scheme_name }}</span><span class="block text-muted">{{ $e->location }}</span>
                            <a class="link text-[12px]" href="{{ $e->source_url }}" target="_blank" rel="noopener noreferrer">{{ $e->source?->name }}</a></td>
                        <td class="px-4 py-2.5">{{ \App\Models\InvestmentRegistryEntry::LIST_TYPES[$e->list_type] }}
                            <span class="block">{{ $e->status ? config('investment_index.legal.statuses.'.$e->status) : 'Not recognised' }}@if ($e->raw_status) <span class="text-muted">“{{ \Illuminate\Support\Str::limit($e->raw_status, 60) }}”</span>@endif</span>
                            @if ($e->issued_on)<span class="block text-muted">{{ $e->issued_on->format('j M Y') }}</span>@endif</td>
                        <td class="px-4 py-2.5 text-muted">{{ $e->first_seen_at->localTime()->format('j M Y') }} – {{ $e->last_seen_at->localTime()->format('j M Y') }}@if ($e->removed_at)<span class="block font-semibold text-amber-700">No longer listed</span>@endif</td>
                        <td class="px-4 py-2.5">
                            <span class="font-semibold">{{ \App\Models\InvestmentRegistryEntry::MATCH_STATUSES[$e->match_status] }}</span>
                            @if ($e->project)<a class="link block" href="{{ route('projects.show', $e->project) }}">{{ $e->project->name }}</a>@endif
                            <form method="POST" action="{{ route('admin.investment-index.registry.match', $e) }}" class="mt-1.5 flex flex-wrap gap-1.5">@csrf
                                <select name="project_id" class="input py-1 text-[13px]" aria-label="Project for {{ $e->scheme_name }}">
                                    <option value="">Not a project here</option>
                                    @foreach ($projects->where('city', $e->city) as $p)<option value="{{ $p->id }}" @selected($e->project_id === $p->id)>{{ $p->name }}</option>@endforeach
                                </select>
                                <button class="btn btn-secondary btn-sm">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-muted">No entries. Authorize an official source and collect it.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.dashboard>
