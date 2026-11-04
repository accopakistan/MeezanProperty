@php
    $isObs = $type === 'observations';
    $statusTone = ['draft' => 'bg-page text-muted ring-line', 'submitted' => 'bg-brand-50 text-brand-700 ring-brand-200', 'reviewed' => 'bg-gold-50 text-gold-700 ring-gold-200', 'verified' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'rejected' => 'bg-red-50 text-red-700 ring-red-200'];
@endphp
<x-layouts.dashboard :title="$isObs ? 'Market observations' : 'Market indicators'" section="admin" :heading="$isObs ? 'Price & rent observations' : 'Market indicators'"
                     :subheading="$isObs ? 'Average prices and monthly rents with range, sample size, date, basis (asking, transaction or valuation) and source.' : 'Demand, supply, liquidity, development progress, infrastructure, legal verification, developer status and growth drivers, each with its evidence.'">
    @include('admin.market.partials.nav')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field name="city" type="select" label="City" placeholder="All cities" :value="$filters['city']" :options="array_combine(config('meezan.cities'), config('meezan.cities'))" />
            <x-field name="status" type="select" label="Status" placeholder="All" :value="$filters['status']" :options="\App\Models\MarketObservation::STATUSES" />
            <x-field name="origin" type="select" label="Data" placeholder="Demo and real" :value="$filters['origin'] ?? null" :options="['real' => 'Real data only', 'demo' => 'Demo data only']" />
            @if ($isObs)<x-field name="kind" type="select" label="Kind" placeholder="Sale and rent" :value="$filters['kind']" :options="\App\Models\MarketObservation::KINDS" />@endif
            <button class="btn btn-secondary">Filter</button>
        </form>
        <div class="flex gap-2">
            <a href="{{ route('admin.market.records.import', $type) }}" class="btn btn-outline btn-sm">Import CSV</a>
            <a href="{{ route('admin.market.records.create', $type) }}" class="btn btn-primary btn-sm">Add {{ $isObs ? 'observation' : 'indicator' }}</a>
        </div>
    </div>
    <div class="card relative overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line text-[13px] text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Place</th>
                    @if ($isObs)<th class="px-4 py-3 font-medium">What</th><th class="px-4 py-3 font-medium">Average</th>@else<th class="px-4 py-3 font-medium">Indicator</th><th class="px-4 py-3 font-medium">Value</th>@endif
                    <th class="px-4 py-3 font-medium">Observed</th><th class="px-4 py-3 font-medium">Source</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3"><span class="sr-only">Open</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($records as $r)
                    <tr>
                        <td class="px-4 py-3"><span class="font-semibold">{{ $r->project?->name ?? $r->area?->name ?? $r->city }}</span><span class="block text-xs text-muted">{{ ucfirst($r->entityType()) }} · {{ $r->city }}{{ $r->asset_class ? ' · '.ucfirst($r->asset_class) : '' }}</span></td>
                        @if ($isObs)
                            <td class="px-4 py-3 text-[13px]">{{ \App\Models\MarketObservation::KINDS[$r->kind] }} · {{ $r->basisLabel() }}<span class="block text-muted">{{ trim(($r->sizeLabel() ?? '').' '.(\App\Enums\PropertyType::tryFrom($r->property_type)?->label() ?? $r->property_type)) }}</span></td>
                            <td class="px-4 py-3 tabular-nums">{{ $r->amountLabel() }}<span class="block text-xs text-muted">n = {{ $r->sample_size }}</span></td>
                        @else
                            <td class="px-4 py-3">{{ $r->indicatorLabel() }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $r->valueLabel() }}</td>
                        @endif
                        <td class="px-4 py-3 text-[13px]">{{ $r->observed_on->format('j M Y') }}</td>
                        <td class="px-4 py-3 text-[13px]">{{ $r->source?->name }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $statusTone[$r->status] ?? '' }}">{{ $r->statusLabel() }}</span></td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.market.records.edit', [$type, $r->id]) }}" class="btn btn-secondary btn-sm">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No records. Add one or import a CSV.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.dashboard>
