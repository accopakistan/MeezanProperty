<x-layouts.dashboard title="Ranking calculations" section="admin" heading="Ranking calculations" subheading="Each run uses only verified records and the active methodology, and records its inputs. Nothing is published automatically: review a completed run, then publish it.">
    @include('admin.market.partials.nav')
    <form method="POST" action="{{ route('admin.market.calculations.store') }}" class="mb-4 flex flex-wrap items-center gap-3">@csrf
        <button class="btn btn-primary btn-sm">Run a calculation now</button>
        <span class="text-[13px] text-muted">Active methodology: v{{ $methodology?->version ?? '—' }}. A scheduled run also happens weekly (never published automatically).</span>
    </form>
    <div class="card relative overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line text-[13px] text-muted"><tr><th class="px-4 py-3 font-medium">Run</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3 font-medium">Methodology</th><th class="px-4 py-3 font-medium">Inputs</th><th class="px-4 py-3 font-medium">Result</th><th class="px-4 py-3"><span class="sr-only">Open</span></th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($calculations as $c)
                    <tr>
                        <td class="px-4 py-3">#{{ $c->id }}<span class="block text-xs text-muted">{{ $c->started_at?->localTime()->format('j M Y, g:i a') }} · {{ $c->trigger }}{{ $c->triggeredBy ? ' · '.$c->triggeredBy->name : '' }}</span></td>
                        <td class="px-4 py-3">{{ $c->statusLabel() }}@if ($c->includes_demo) <span class="badge bg-gold-500 px-1.5 py-0 text-[10px] font-extrabold text-brand-900">DEMO</span>@endif</td>
                        <td class="px-4 py-3">v{{ $c->methodology_version }}</td>
                        <td class="px-4 py-3 text-[13px]">{{ $c->summary['observations'] ?? 0 }} observations · {{ $c->summary['indicators'] ?? 0 }} indicators</td>
                        <td class="px-4 py-3 text-[13px]">{{ $c->summary['ranked'] ?? 0 }} ranked · {{ $c->summary['insufficient'] ?? 0 }} insufficient{{ $c->error ? ' · '.\Illuminate\Support\Str::limit($c->error, 60) : '' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.market.calculations.show', $c) }}" class="btn btn-secondary btn-sm">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No calculations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $calculations->links() }}</div>
</x-layouts.dashboard>
