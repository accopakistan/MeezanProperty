@php
    use App\Enums\PropertyStatus;
    $tabs = ['' => 'All'] + collect(PropertyStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
@endphp
<x-layouts.dashboard title="My listings" section="dealer" heading="My listings" subheading="Create, edit and track the review status of your properties.">
    <x-slot:actions>
        <a href="{{ route('dealer.properties.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Add a listing</a>
    </x-slot:actions>

    <div class="mb-5 flex gap-1 overflow-x-auto rounded-xl border border-line bg-white p-1 scrollbar-none">
        @foreach ($tabs as $value => $label)
            @php $count = $value === '' ? $counts->sum() : ($counts[$value] ?? 0); @endphp
            <a href="{{ route('dealer.properties.index', array_filter(['status' => $value])) }}"
               @class(['flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => (string) $status === $value, 'text-muted hover:bg-page hover:text-ink' => (string) $status !== $value])>
                {{ $label }} <span @class(['rounded-full px-1.5 text-xs', 'bg-white/20' => (string) $status === $value, 'bg-page' => (string) $status !== $value])>{{ $count }}</span>
            </a>
        @endforeach
    </div>

    @if ($properties->isEmpty())
        <x-empty-state icon="layers" :title="$status ? 'No '.strtolower(PropertyStatus::from($status)->label()).' listings' : 'You have no listings yet'">
            Listings you add are saved as drafts until you submit them for review.
            <x-slot:actions><a href="{{ route('dealer.properties.create') }}" class="btn btn-primary">Add a listing</a></x-slot:actions>
        </x-empty-state>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr><th>Listing</th><th>Price</th><th>Status</th><th class="text-right">Views</th><th class="text-right">Inquiries</th><th>Updated</th><th class="sr-only">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($properties as $property)
                            <tr>
                                <td class="min-w-72">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $property->coverThumbUrl() ?? $property->placeholderUrl() }}" alt="" class="h-12 w-16 shrink-0 rounded-lg object-cover">
                                        <div class="min-w-0">
                                            <a href="{{ route('dealer.properties.edit', $property) }}" class="block max-w-80 truncate font-semibold hover:text-brand-700">{{ $property->title }}</a>
                                            <p class="text-xs text-muted">{{ $property->reference }} · {{ $property->type->label() }} · {{ $property->area?->name }}</p>
                                            @if (in_array($property->status, [PropertyStatus::Rejected, PropertyStatus::ChangesRequested, PropertyStatus::Suspended], true) && $property->rejection_reason)
                                                <p class="mt-1 text-xs font-medium text-red-600">Reviewer: {{ $property->rejection_reason }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap font-semibold">{{ $property->priceLabel() }}</td>
                                <td>
                                    <x-status-badge :tone="$property->status->tone()" :label="$property->status->label()" />
                                    @if ($property->expires_at && in_array($property->status, [PropertyStatus::Published, PropertyStatus::Expired], true))
                                        @if ($property->hasLapsed())
                                            <p class="mt-1 text-xs font-medium whitespace-nowrap text-red-600">Expired {{ $property->expires_at->format('j M Y') }} · hidden from buyers</p>
                                        @else
                                            <p @class(['mt-1 text-xs whitespace-nowrap', 'font-medium text-amber-700' => $property->canBeRenewed(), 'text-muted' => ! $property->canBeRenewed()])>Live until {{ $property->expires_at->format('j M Y') }}</p>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ number_format($property->views) }}</td>
                                <td class="text-right tabular-nums">{{ $property->inquiries_count }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $property->updated_at->format('j M Y') }}</td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        @if ($property->canBeRenewed())
                                            <form method="POST" action="{{ route('dealer.properties.renew', $property) }}" data-confirm="Renew {{ $property->reference }} for another {{ config('meezan.listing.lifetime_days') }} days? Only renew if it is still available at {{ $property->priceLabel() }}.">@csrf
                                                <input type="hidden" name="still_available" value="1">
                                                <button class="btn btn-primary btn-sm"><x-icon name="refresh" class="size-3.5" /> Renew</button>
                                            </form>
                                        @elseif (in_array($property->status, [PropertyStatus::Draft, PropertyStatus::Rejected], true) || $property->needsReviewToRelist())
                                            <form method="POST" action="{{ route('dealer.properties.submit', $property) }}">@csrf
                                                <button class="btn btn-primary btn-sm">Submit</button>
                                            </form>
                                        @endif
                                        <a href="{{ route('properties.show', $property) }}" class="btn btn-ghost btn-sm px-2" title="Preview" aria-label="Preview {{ $property->title }}"><x-icon name="eye" class="size-4" /></a>
                                        <a href="{{ route('dealer.properties.edit', $property) }}" class="btn btn-ghost btn-sm px-2" title="Edit" aria-label="Edit {{ $property->title }}"><x-icon name="pencil" class="size-4" /></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $properties->links() }}</div>
    @endif
</x-layouts.dashboard>
