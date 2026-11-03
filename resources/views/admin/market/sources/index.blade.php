<x-layouts.dashboard title="Market data sources" section="admin" heading="Data sources" subheading="Every observation and indicator cites one of these. A public website is not verified just because it is public: set reliability by how the data is produced.">
    @include('admin.market.partials.nav')
    <div class="mb-4"><a href="{{ route('admin.market.sources.create') }}" class="btn btn-primary btn-sm">Add source</a></div>
    <div class="card relative overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line text-[13px] text-muted"><tr><th class="px-4 py-3 font-medium">Source</th><th class="px-4 py-3 font-medium">Type</th><th class="px-4 py-3 font-medium">Reliability</th><th class="px-4 py-3 font-medium">Licence / permission</th><th class="px-4 py-3 font-medium">Records</th><th class="px-4 py-3 font-medium">Checked</th><th class="px-4 py-3"><span class="sr-only">Edit</span></th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($sources as $s)
                    <tr>
                        <td class="px-4 py-3"><span class="font-semibold">{{ $s->name }}</span>@unless ($s->is_active)<span class="ml-1 text-xs text-muted">(inactive)</span>@endunless
                            @if ($s->url)<a href="{{ $s->url }}" class="link block text-xs" rel="noopener nofollow" target="_blank">{{ \Illuminate\Support\Str::limit($s->url, 50) }}</a>@endif</td>
                        <td class="px-4 py-3">{{ $s->typeLabel() }}</td>
                        <td class="px-4 py-3">{{ ucfirst($s->reliability) }}</td>
                        <td class="px-4 py-3 text-[13px]">{{ $s->license ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ $s->observations_count + $s->indicators_count }}</td>
                        <td class="px-4 py-3 text-[13px] text-muted">{{ $s->last_checked_on?->format('j M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.market.sources.edit', $s) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No sources yet. Add the reports, records or research you are allowed to use.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $sources->links() }}</div>
</x-layouts.dashboard>
