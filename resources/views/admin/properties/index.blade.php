@php use App\Enums\PropertyStatus; @endphp
<x-layouts.dashboard title="Listings" section="admin" heading="Listings" subheading="Moderate, publish and manage property listings.">
    <div class="mb-4 flex gap-1 overflow-x-auto rounded-xl border border-line bg-white p-1 scrollbar-none">
        <a href="{{ route('admin.properties.index', ['status' => '']) }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => ! $status, 'text-muted hover:text-ink' => $status])>All <span class="text-xs opacity-75">{{ $counts->sum() }}</span></a>
        @foreach (PropertyStatus::cases() as $s)
            <a href="{{ route('admin.properties.index', ['status' => $s->value]) }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => $status === $s->value, 'text-muted hover:text-ink' => $status !== $s->value])>
                {{ $s->label() }} <span class="text-xs opacity-75">{{ $counts[$s->value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto]">
        <input type="hidden" name="status" value="{{ $status }}">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Title or reference (MP-000123)" aria-label="Search listings">
        <select name="group" class="input" aria-label="Category">
            <option value="">All categories</option>
            @foreach (['homes' => 'Homes', 'plots' => 'Plots', 'commercial' => 'Commercial'] as $v => $l)
                <option value="{{ $v }}" @selected(($filters['group'] ?? '') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="area" class="input" aria-label="Area">
            <option value="">All areas</option>
            @foreach ($areas as $area)
                <option value="{{ $area->id }}" @selected((int) ($filters['area'] ?? 0) === $area->id)>{{ $area->name }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary">Filter</button>
        @if (! empty($filters['dealer']))
            <input type="hidden" name="dealer" value="{{ $filters['dealer'] }}">
            <p class="text-[13px] text-muted sm:col-span-2 lg:col-span-4">Filtered to one dealer · <a href="{{ route('admin.properties.index', ['status' => $status]) }}" class="link">clear</a></p>
        @endif
    </form>

    @if ($properties->isEmpty())
        <x-empty-state icon="layers" :title="$status === 'pending' ? 'The moderation queue is empty' : 'No listings match these filters'" />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Listing</th><th>Dealer</th><th>Price</th><th>Status</th><th>Submitted</th><th class="sr-only">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($properties as $property)
                            <tr @class(['opacity-60' => $property->trashed()])>
                                <td class="min-w-72">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $property->coverThumbUrl() ?? $property->placeholderUrl() }}" alt="" class="h-12 w-16 shrink-0 rounded-lg object-cover">
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.properties.show', $property) }}" class="block max-w-80 truncate font-semibold hover:text-brand-700">{{ $property->title }}</a>
                                            <p class="text-xs text-muted">{{ $property->reference }} · {{ $property->type->label() }} · {{ $property->area?->name }}
                                                @if ($property->is_demo) · <span class="font-semibold">Demo</span>@endif
                                                @if ($property->is_featured) · <span class="font-semibold text-gold-600">Featured</span>@endif
                                                @if ($property->trashed()) · Removed by dealer @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-[13px]">
                                    {{ $property->dealer?->dealerProfile?->agency_name ?? $property->dealer?->name }}
                                    @if ($property->hasVerifiedDealer())<x-verified-badge label="Verified" class="mt-0.5 flex" />@else<p class="text-xs text-amber-700">Unverified</p>@endif
                                </td>
                                <td class="whitespace-nowrap font-semibold">{{ $property->priceLabel() }}</td>
                                <td><x-status-badge :tone="$property->status->tone()" :label="$property->status->label()" /></td>
                                <td class="whitespace-nowrap text-muted">{{ $property->submitted_at?->format('j M Y') ?? '—' }}</td>
                                <td class="text-right"><a href="{{ route('admin.properties.show', $property) }}" class="btn btn-secondary btn-sm">Review</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $properties->links() }}</div>
    @endif
</x-layouts.dashboard>
