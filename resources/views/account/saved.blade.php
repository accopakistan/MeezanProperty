{{-- Layout: public/Refrence/Meezan Property Buyer Dashboard Showcase.png — 6B. Saved Properties --}}
<x-layouts.dashboard title="Saved properties" section="account" heading="Saved Properties" subheading="Properties you have saved for later.">
    <div class="mb-5 flex gap-1 overflow-x-auto rounded-md border border-line bg-white p-1 scrollbar-none">
        @foreach (['' => 'All', 'homes' => 'Homes', 'plots' => 'Plots', 'commercial' => 'Commercial'] as $value => $label)
            <a href="{{ route('account.saved', array_filter(['group' => $value])) }}"
               @class(['shrink-0 rounded px-4 py-2 text-sm font-medium', 'bg-brand-600 text-white' => (string) $group === $value, 'text-muted hover:bg-page hover:text-ink' => (string) $group !== $value])>
                {{ $label }} ({{ $value === '' ? $counts->sum() : ($counts[$value] ?? 0) }})
            </a>
        @endforeach
    </div>

    @if ($properties->isEmpty())
        <x-empty-state icon="heart" title="No saved properties here yet">
            Tap the heart on any listing to keep it here for later.
            <x-slot:actions><a href="{{ route('properties.index') }}" class="btn btn-primary">Browse properties</a></x-slot:actions>
        </x-empty-state>
    @else
        <div class="space-y-3">
            @foreach ($properties as $property)
                <article class="card flex flex-col gap-4 p-3 sm:flex-row sm:items-center">
                    <a href="{{ route('properties.show', $property) }}" class="relative block shrink-0 overflow-hidden rounded-md sm:w-48">
                        <img src="{{ $property->coverThumbUrl() ?? $property->placeholderUrl() }}" alt="{{ $property->title }}" loading="lazy" class="aspect-[16/10] w-full object-cover">
                        <span class="tag absolute top-2 left-2 {{ $property->purpose->value === 'rent' ? 'bg-gold-500' : 'bg-brand-500' }}">{{ $property->purpose->label() }}</span>
                    </a>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('properties.show', $property) }}" class="font-semibold hover:text-brand-700">{{ $property->title }}</a>
                            <x-save-button :property="$property" saved style="plain" />
                        </div>
                        <p class="text-[13px] text-muted">{{ $property->locationLabel() }}</p>
                        <p class="mt-1 text-lg font-bold text-brand-600">{{ $property->priceLabel() }}</p>
                        <p class="mt-1 flex flex-wrap gap-x-3 text-[12.5px] text-muted">
                            @if ($property->type->hasRooms() && $property->bedrooms)<span>{{ $property->bedrooms }} Beds</span>@endif
                            @if ($property->type->hasRooms() && $property->bathrooms)<span>{{ $property->bathrooms }} Baths</span>@endif
                            <span>{{ $property->areaLabel() }}</span>
                            <span>{{ $property->type->label() }}</span>
                        </p>
                        @unless ($property->isPublic())
                            <p class="mt-1 text-xs font-semibold text-amber-700">This listing is no longer available.</p>
                        @endunless
                    </div>
                    <div class="flex shrink-0 gap-2 sm:flex-col">
                        <a href="{{ route('properties.show', $property) }}" class="btn btn-outline btn-sm">View Details</a>
                        <form method="POST" action="{{ route('properties.save', $property) }}">@csrf
                            <input type="hidden" name="saved" value="0">
                            <button class="btn btn-secondary btn-sm w-full border-red-200 text-red-600 hover:bg-red-50">Remove</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-8">{{ $properties->links() }}</div>
    @endif
</x-layouts.dashboard>
