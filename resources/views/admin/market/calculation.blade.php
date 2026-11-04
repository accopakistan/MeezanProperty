<x-layouts.dashboard :title="'Calculation #'.$calc->id" section="admin" :heading="'Calculation #'.$calc->id.' · '.$calc->statusLabel().($calc->includes_demo ? ' · DEMO' : '')" :subheading="'Methodology v'.$calc->methodology_version.' · '.($calc->summary['observations'] ?? 0).($calc->includes_demo ? ' observations (including illustrative demo records)' : ' verified observations').' and '.($calc->summary['indicators'] ?? 0).' indicators used · input checksum '.\Illuminate\Support\Str::limit($calc->inputs['checksum'] ?? '—', 12, '')">
    @include('admin.market.partials.nav')
    @if ($calc->status === 'completed')
        <form method="POST" action="{{ route('admin.market.calculations.publish', $calc) }}" class="mb-4" data-confirm="Publish these rankings? The current publication is kept as history.">
            @csrf<button class="btn btn-primary">Publish this calculation</button>
        </form>
    @endif
    @error('publish')<p class="field-error mb-3">{{ $message }}</p>@enderror
    @if ($calc->error)<p class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $calc->error }}</p>@endif

    <nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="Category">
        @foreach (config('meezan.investment.categories') as $key => $c)
            <a href="{{ route('admin.market.calculations.show', [$calc, 'category' => $key]) }}" @class(['rounded-full px-3 py-1.5 font-semibold ring-1', 'bg-ink text-white ring-ink' => $category === $key, 'bg-white ring-line' => $category !== $key])>{{ $c['label'] }}</a>
        @endforeach
    </nav>
    <div class="card relative overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line text-[13px] text-muted"><tr><th class="px-4 py-3 font-medium">Rank</th><th class="px-4 py-3 font-medium">Entry</th><th class="px-4 py-3 font-medium">Score</th><th class="px-4 py-3 font-medium">Confidence</th><th class="px-4 py-3 font-medium">Risk</th><th class="px-4 py-3 font-medium">Coverage / reason</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $r)
                    <tr>
                        <td class="px-4 py-2.5 tabular-nums">{{ $r->rank ? '#'.$r->rank : '—' }}</td>
                        <td class="px-4 py-2.5"><span class="font-semibold">{{ $r->entity_name }}</span><span class="block text-xs text-muted">{{ $r->entityTypeLabel() }} · {{ $r->city }} · {{ ucfirst($r->asset_class) }}</span></td>
                        <td class="px-4 py-2.5 tabular-nums">{{ $r->isRanked() ? number_format($r->score, 1) : 'Insufficient data' }}</td>
                        <td class="px-4 py-2.5"><x-investment-badge kind="confidence" :level="$r->confidence" /></td>
                        <td class="px-4 py-2.5"><x-investment-badge kind="risk" :level="$r->risk" /></td>
                        <td class="px-4 py-2.5 text-[13px] text-muted">{{ round(($r->factors['coverage'] ?? 0) * 100) }}%{{ $r->factors['reason'] ? ' · '.$r->factors['reason'] : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No entries in this category. Verified data is needed to rank anything.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.dashboard>
