@props(['property', 'layout' => 'grid', 'saved' => false, 'dealer' => false, 'badge' => null])
{{--
    Listing card from the reference pages (Homepage, Search Results, Collage).
    layout: grid (image on top) | list (image left, compact — map and saved pages)
    dealer: show the dealer row with call / WhatsApp, as on the search results reference.
--}}
@php
    /** @var \App\Models\Property $property */
    use App\Enums\Purpose;

    $url = route('properties.show', $property);
    $cover = $property->coverThumbUrl();
    $saved = $saved || \App\Support\Lookup::isSaved($property->id);
    $profile = $property->relationLoaded('dealer') ? $property->dealer?->dealerProfile : null;
    $verified = $property->hasVerifiedDealer();
    [$tagLabel, $tagClass] = $property->is_featured
        ? ['Featured', 'bg-brand-600']
        : ($property->purpose === Purpose::Rent ? ['For Rent', 'bg-gold-500'] : ['For Sale', 'bg-brand-500']);
    $tagLabel = $badge ?? $tagLabel;
    $rooms = $property->type->hasRooms();
    $reduction = $property->recentReduction();
@endphp
<article {{ $attributes->merge(['class' => 'group relative flex overflow-hidden rounded-[var(--radius-card)] border border-line bg-white shadow-card transition hover:shadow-lift '.($layout === 'list' ? 'flex-row' : 'flex-col')]) }} data-result-id="{{ $property->id }}">
    <div @class(['relative shrink-0 overflow-hidden bg-brand-50', 'aspect-[16/9]' => $layout === 'grid', 'w-36 sm:w-40' => $layout === 'list'])>
        <img src="{{ $cover ?? $property->placeholderUrl() }}" alt="{{ $cover ? $property->title : 'No photo yet — '.$property->type->label() }}" width="640" height="360" loading="lazy" decoding="async"
             class="size-full object-cover transition duration-500 group-hover:scale-[1.03] {{ $layout === 'list' ? 'absolute inset-0' : '' }}">
        <div class="absolute top-2.5 left-2.5 flex flex-col items-start gap-1">
            <span class="tag {{ $tagClass }}">{{ $tagLabel }}</span>
            @if ($reduction)
                <span class="tag bg-emerald-600 text-[10px]">Price reduced</span>
            @elseif ($property->isNewListing())
                <span class="tag bg-gold-500 text-[10px] text-brand-900">New</span>
            @endif
            @if ($property->is_demo)
                <span class="tag bg-ink/75 text-[10px]">Demo listing</span>
            @endif
        </div>
        @if ($layout === 'grid')
            <x-save-button :property="$property" :saved="$saved" class="absolute top-2 right-2 z-10" />
        @endif
    </div>

    <div @class(['flex min-w-0 flex-1 flex-col', 'p-4' => $layout === 'grid', 'px-3.5 py-3' => $layout === 'list'])>
        <div class="flex items-start justify-between gap-2">
            <h3 @class(['font-semibold leading-snug text-ink', 'line-clamp-1 text-[15px]' => $layout === 'grid', 'line-clamp-2 font-serif text-base' => $layout === 'list'])>
                <a href="{{ $url }}" class="after:absolute after:inset-0 focus:outline-none">{{ $property->title }}</a>
            </h3>
            @if ($layout === 'list')
                <x-save-button :property="$property" :saved="$saved" style="plain" class="relative z-10 -mt-0.5" />
            @endif
        </div>
        <p class="mt-1 flex items-center gap-1 text-[13px] text-muted"><x-icon name="map-pin" class="size-3.5" /> {{ $property->locationLabel() }}</p>
        <p class="mt-1.5 flex flex-wrap items-baseline gap-x-2 text-[17px] font-bold text-brand-600">
            {{ $property->priceLabel() }}
            @if ($reduction)
                <span class="text-xs font-semibold text-emerald-700">↓ from {{ \App\Support\Format::compactPrice($reduction->old_price) }}</span>
            @endif
        </p>

        <ul class="mt-2.5 flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-[12.5px] text-muted">
            @if ($rooms && $property->bedrooms)
                <li class="flex items-center gap-1"><x-icon name="bed" class="size-4" /> {{ $property->bedrooms }} Beds</li>
            @endif
            @if ($rooms && $property->bathrooms)
                <li class="flex items-center gap-1"><x-icon name="bath" class="size-4" /> {{ $property->bathrooms }} Baths</li>
            @endif
            <li class="flex items-center gap-1"><x-icon name="area" class="size-4" /> {{ $property->areaLabel() }}</li>
            @unless ($rooms)
                <li class="flex items-center gap-1"><x-icon name="tag" class="size-4" /> {{ $property->type->label() }}</li>
            @endunless
        </ul>

        @if ($dealer && $profile)
            <div class="relative z-10 mt-3.5 flex items-center gap-2.5 border-t border-line pt-3.5">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">{{ collect(explode(' ', $profile->agency_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-[13px] font-semibold">{{ $profile->agency_name }}</p>
                    <p class="text-xs {{ $verified ? 'text-emerald-700' : 'text-muted' }}">{{ $verified ? 'Verified Dealer' : ($property->listerIsOwner() ? 'Listed by owner' : 'Verification pending') }}</p>
                </div>
                @if ($verified && $profile->public_phone)
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $profile->public_phone) }}" class="grid size-9 place-items-center rounded-md border border-line text-brand-600 hover:border-brand-300" aria-label="Call {{ $profile->agency_name }}"><x-icon name="phone" class="size-4" /></a>
                @endif
                @if ($verified && $profile->whatsapp_number)
                    <a href="https://wa.me/{{ preg_replace('/\D+/', '', $profile->whatsapp_number) }}?text={{ rawurlencode('Assalam o Alaikum, I am interested in '.$property->reference.' on Meezan Property: '.$url) }}" target="_blank" rel="noopener"
                       class="grid size-9 place-items-center rounded-md bg-[#16a34a] text-white hover:bg-[#15803d]" aria-label="WhatsApp {{ $profile->agency_name }}"><x-icon name="whatsapp" class="size-4" /></a>
                @endif
                @unless ($verified && ($profile->public_phone || $profile->whatsapp_number))
                    <a href="{{ $url }}#inquiry" class="btn btn-outline btn-sm px-2.5">Inquire</a>
                @endunless
            </div>
        @endif
    </div>
</article>
