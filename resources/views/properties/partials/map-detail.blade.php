@php
    /** @var \App\Models\Property $p */
    $profile = $p->dealer?->dealerProfile;
    $verified = $p->hasVerifiedDealer();
@endphp
{{-- Selected listing beside the map (reference: Map Search Dashboard, right panel). Public facts only. --}}
<div class="relative" data-gallery>
    <img src="{{ $p->coverUrl() ?? $p->placeholderUrl() }}" alt="{{ $p->title }}" class="aspect-[2/1] w-full object-cover" data-gallery-main>
    <span class="tag absolute top-3 left-3 {{ $p->is_featured ? 'bg-brand-600' : 'bg-brand-500' }}">{{ $p->is_featured ? 'Featured' : $p->purpose->label() }}</span>
    <div class="absolute top-3 right-3 flex gap-1.5">
        <x-save-button :property="$p" :saved="\App\Support\Lookup::isSaved($p->id)" class="rounded-md bg-white/90 [&_button]:text-red-500" style="plain" />
        <button type="button" class="grid size-8 place-items-center rounded-md bg-white/90 text-brand-600" data-share="{{ route('properties.show', $p) }}" aria-label="Share"><x-icon name="share" class="size-4" /><span data-share-label class="sr-only">Share</span></button>
    </div>
    @if ($p->images->count() > 1)
        <button type="button" class="absolute top-1/2 left-3 grid size-8 -translate-y-1/2 place-items-center rounded-full bg-white shadow" data-gallery-prev aria-label="Previous photo"><x-icon name="chevron-left" class="size-4" /></button>
        <button type="button" class="absolute top-1/2 right-3 grid size-8 -translate-y-1/2 place-items-center rounded-full bg-white shadow" data-gallery-next aria-label="Next photo"><x-icon name="chevron-right" class="size-4" /></button>
        <span class="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-ink/70 px-2.5 py-0.5 text-xs text-white" data-gallery-counter>1 / {{ $p->images->count() }}</span>
        <div class="hidden">@foreach ($p->images as $image)<button type="button" data-gallery-thumb="{{ $image->url() }}"></button>@endforeach</div>
    @endif
</div>
<div class="p-4">
    <h2 class="font-serif text-xl leading-snug font-bold">{{ $p->title }}</h2>
    <p class="mt-1 font-serif text-2xl font-bold text-brand-600">{{ $p->priceLabel() }}</p>
    <p class="mt-2 flex items-center justify-between gap-2 text-sm text-ink/80"><span class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-4 text-brand-600" /> {{ $p->locationLabel() }}</span></p>
    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-muted">
        @if ($p->type->hasRooms() && $p->bedrooms)<li class="flex items-center gap-1"><x-icon name="bed" class="size-4" /> {{ $p->bedrooms }} Beds</li>@endif
        @if ($p->type->hasRooms() && $p->bathrooms)<li class="flex items-center gap-1"><x-icon name="bath" class="size-4" /> {{ $p->bathrooms }} Baths</li>@endif
        <li class="flex items-center gap-1"><x-icon name="area" class="size-4" /> {{ $p->areaLabel() }}</li>
        <li class="flex items-center gap-1"><x-icon name="home" class="size-4" /> {{ $p->type->label() }}</li>
    </ul>
    <h3 class="mt-4 font-semibold">Overview</h3>
    <p class="mt-1 line-clamp-3 text-[13px] leading-5 text-muted">{{ $p->description }}</p>
    <div class="mt-4 grid grid-cols-2 gap-2">
        <a href="{{ route('properties.show', $p) }}" class="btn btn-primary btn-sm"><x-icon name="eye" class="size-4" /> View Details</a>
        <a href="{{ route('properties.show', $p) }}#inquiry" class="btn btn-outline btn-sm"><x-icon name="message" class="size-4" /> Send Inquiry</a>
    </div>
    @if ($profile)
        <div class="mt-4 border-t border-line pt-4">
            <p class="text-sm font-semibold">Listed By</p>
            <div class="mt-2 flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-full bg-ink text-sm font-bold text-white">{{ collect(explode(' ', $profile->agency_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ $profile->agency_name }}</p>
                    <p class="text-xs {{ $verified ? 'text-emerald-700' : 'text-muted' }}">{{ $verified ? 'Verified Dealer' : 'Verification pending' }}</p>
                </div>
                <div class="text-center text-xs text-muted"><span class="block text-base font-bold text-ink">{{ $p->dealer->properties()->published()->count() }}</span>Listings</div>
            </div>
        </div>
    @endif
</div>
