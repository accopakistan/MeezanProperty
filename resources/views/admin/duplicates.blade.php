<x-layouts.dashboard title="Duplicate review" section="admin" heading="Duplicate Review" subheading="Pending listings that match another live or pending listing on purpose, type, area, size (±2%) and price (±10%).">
    @if ($flagged->isEmpty())
        <x-empty-state icon="check" title="No likely duplicates in the queue" />
    @else
        <div class="space-y-4">
            @foreach ($flagged as $row)
                @php $p = $row['property']; @endphp
                <article class="card p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <a href="{{ route('admin.properties.show', $p) }}" class="font-semibold hover:text-brand-700">{{ $p->reference }} – {{ $p->title }}</a>
                            <p class="text-[13px] text-muted">{{ $p->dealer?->dealerProfile?->agency_name }} · {{ $p->area?->name }} · {{ $p->areaLabel() }} · {{ $p->priceLabel() }}</p>
                        </div>
                        <a href="{{ route('admin.properties.show', $p) }}" class="btn btn-secondary btn-sm">Review</a>
                    </div>
                    <p class="mt-3 text-xs font-semibold tracking-wide text-muted uppercase">Looks similar to</p>
                    <ul class="mt-1 divide-y divide-line text-sm">
                        @foreach ($row['candidates'] as $c)
                            <li class="flex flex-wrap justify-between gap-2 py-2">
                                <a href="{{ route('admin.properties.show', $c) }}" class="link">{{ $c->reference }} – {{ $c->title }}</a>
                                <span class="text-muted">{{ $c->dealer?->dealerProfile?->agency_name }}{{ $c->user_id === $p->user_id ? ' (same dealer)' : '' }} · {{ $c->priceLabel() }} · {{ strtolower($c->status->label()) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
