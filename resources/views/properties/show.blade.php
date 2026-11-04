@php
    /** @var \App\Models\Property $property */
    use App\Enums\AreaUnit;
    use App\Enums\PropertyStatus;
    use App\Enums\Purpose;
    use App\Support\Format;
    use Illuminate\Support\Str;

    // Layout: public/Refrence/Meezan Property Listing Dashboard.png
    $images = $property->images;
    $profile = $property->dealer?->dealerProfile;
    $verified = $property->hasVerifiedDealer();
    $user = auth()->user();
    $url = route('properties.show', $property);
    $isRent = $property->purpose === Purpose::Rent;
    $waNumber = $verified && $profile?->whatsapp_number ? preg_replace('/\D+/', '', $profile->whatsapp_number) : null;
    $waText = rawurlencode("Assalam o Alaikum, I'm interested in {$property->reference} – {$property->title} on Meezan Property: {$url}");
    $marlas = $property->area_sqft / (int) config('meezan.marla_sqft', 225);
    $perMarla = ! $isRent && in_array($property->area_unit, [AreaUnit::Marla, AreaUnit::Kanal], true) && $marlas > 0 ? (int) round($property->price / $marlas) : null;
    $rooms = $property->type->hasRooms();
    $specs = array_filter([
        $rooms && $property->bedrooms ? ['bed', $property->bedrooms.' Beds'] : null,
        $rooms && $property->bathrooms ? ['bath', $property->bathrooms.' Baths'] : null,
        ['area', $property->areaLabel()],
        ['home', $property->type->label()],
        $property->furnishing ? ['sofa', ucfirst($property->furnishing)] : null,
    ]);
    $details = array_filter([
        'Property Type' => $property->type->label(),
        'Purpose' => $property->purpose->label(),
        'Location' => $property->locationLabel(),
        'Area' => $property->areaLabel().($property->area_unit !== AreaUnit::SquareFeet ? ' ('.number_format($property->area_sqft).' Sq.ft.)' : ''),
        'Bedrooms' => $rooms ? $property->bedrooms : null,
        'Bathrooms' => $property->bathrooms,
        'Covered Area' => $property->coveredAreaLabel(),
        'Status' => $property->completionLabel(),
        'Furnished' => $property->furnishing ? ucfirst($property->furnishing) : null,
        'Listed By' => $property->listerIsOwner() ? 'Private owner' : 'Dealer / agency',
        'Price' => $property->priceLabel(),
        'Price per Marla' => $perMarla ? Format::price($perMarla) : null,
        'Posted On' => $property->published_at?->format('j M Y'),
        'Days on Meezan Property' => $property->daysListed() !== null ? ($property->daysListed() === 0 ? 'Listed today' : $property->daysListed().' '.\Illuminate\Support\Str::plural('day', $property->daysListed())) : null,
        'Property ID' => $property->reference,
    ], fn ($v) => $v !== null && $v !== '');
    $tag = $property->purpose === Purpose::Rent ? ['For Rent', 'bg-gold-500'] : ['For Sale', 'bg-brand-500'];
    $visit = request()->boolean('visit');
@endphp
@php
    // Search title and description from the listing's own fields (Seo); demo listings are kept out of search.
    $seoCategory = \App\Support\Seo::categoryFor($property);
    $coordinates = $property->showsExactLocation() ? $property->publicCoordinates() : null;
@endphp
<x-layouts.app :title="\App\Support\Seo::propertyTitle($property).' — '.$property->priceLabel()" :description="\App\Support\Seo::propertyDescription($property)"
               :canonical="$url" :image="$property->coverUrl()" :image-alt="$property->title" :noindex="$isPreview" :robots="$property->is_demo ? 'noindex, follow' : null">
    @if (! $isPreview && ! $property->is_demo)
        <x-slot:head>
            <script type="application/ld+json">
                {!! \App\Support\Seo::json(array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'RealEstateListing',
                    'name' => $property->title,
                    'description' => \App\Support\Seo::propertyDescription($property),
                    'url' => $url,
                    'datePosted' => $property->published_at?->toDateString(),
                    'dateModified' => $property->updated_at?->toDateString(),
                    'image' => $images->map->url()->all() ?: null,
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => $property->price,
                        'priceCurrency' => 'PKR',
                        'businessFunction' => $property->purpose === Purpose::Rent ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
                    ],
                    // Only what is public: the area and city; map coordinates only when the dealer shows the exact location.
                    'contentLocation' => array_filter([
                        '@type' => 'Place',
                        'name' => $property->locationLabel(),
                        'address' => array_filter(['@type' => 'PostalAddress', 'addressLocality' => $property->city, 'addressRegion' => \App\Support\Cities::province($property->city), 'addressCountry' => 'PK']),
                        'geo' => $coordinates ? ['@type' => 'GeoCoordinates', 'latitude' => $coordinates[0], 'longitude' => $coordinates[1]] : null,
                    ]),
                ])) !!}
            </script>
        </x-slot:head>
    @endif

    <div class="container-page pt-4">
        @if ($isPreview)
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <x-icon name="eye" class="size-4" />
                <span><strong>Preview.</strong> This listing is <strong>{{ strtolower($property->status->label()) }}</strong> and is not visible to the public.</span>
                @if ($property->status === PropertyStatus::Rejected && $property->rejection_reason)
                    <span class="w-full text-amber-800">Reviewer note: {{ $property->rejection_reason }}</span>
                @endif
            </div>
        @endif
        @if ($property->is_demo)
            <div class="mb-4 rounded-lg border border-line bg-white px-4 py-2.5 text-[13px] text-muted"><strong class="text-ink">Sample listing</strong> — an example for platform preview. This is not real inventory and the photos are illustrative.</div>
        @endif

        {{-- Real hierarchy: city → area guide → category in that area → listing. --}}
        <x-breadcrumbs :items="array_values(array_filter([
            in_array($property->city, config('meezan.cities'), true) ? [$property->city, route('cities.show', \App\Support\Cities::slug($property->city))] : null,
            $property->area ? [$property->area->name, route('areas.show', $property->area->slug)] : null,
            [config('meezan.seo.categories.'.$seoCategory.'.label'), $property->area ? route('areas.category', [$property->area->slug, $seoCategory]) : (in_array($property->city, config('meezan.cities'), true) ? route('cities.category', [\App\Support\Cities::slug($property->city), $seoCategory]) : null)],
            [Str::limit($property->title, 48), null],
        ]))" />

        {{-- Gallery + summary --}}
        <div class="mt-4 grid gap-5 xl:grid-cols-[minmax(0,1.12fr)_minmax(0,0.42fr)_minmax(0,1.05fr)]">
            <div class="relative self-start overflow-hidden rounded-[var(--radius-card)] bg-brand-50" data-gallery>
                <img src="{{ $images->first()?->url() ?? $property->placeholderUrl() }}" alt="{{ $property->title }}" width="{{ $images->first()?->width ?: 1600 }}" height="{{ $images->first()?->height ?: 1000 }}" class="aspect-[16/10] size-full object-cover" data-gallery-main fetchpriority="high">
                <div class="absolute top-3 left-3 flex gap-1.5">
                    <span class="tag {{ $tag[1] }}">{{ $tag[0] }}</span>
                    @if ($property->is_featured)<span class="tag bg-gold-500">Featured</span>@endif
                </div>
                @if ($images->count() > 1)
                    <button type="button" class="absolute top-1/2 left-4 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white shadow" data-gallery-prev aria-label="Previous photo"><x-icon name="chevron-left" /></button>
                    <button type="button" class="absolute top-1/2 right-4 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white shadow" data-gallery-next aria-label="Next photo"><x-icon name="chevron-right" /></button>
                    <div class="hidden">
                        @foreach ($images as $image)<button type="button" data-gallery-thumb="{{ $image->url() }}" aria-label="Photo {{ $loop->iteration }}"></button>@endforeach
                    </div>
                @endif
                <div class="absolute bottom-3 left-3 flex gap-2">
                    @if ($images->isNotEmpty())
                        <button type="button" class="btn btn-on-dark btn-sm border-white/60 bg-black/40" data-dialog-open="photos-dialog"><x-icon name="image" class="size-4" /> <span data-gallery-counter>1 / {{ $images->count() }}</span> Photos</button>
                    @else
                        <span class="btn btn-on-dark btn-sm bg-black/40"><x-icon name="image" class="size-4" /> No photos yet</span>
                    @endif
                </div>
            </div>

            <div class="hidden aspect-[0.42/1.08] grid-rows-3 gap-3 self-start xl:grid">
                @for ($i = 1; $i <= 3; $i++)
                    @php $image = $images->get($i); @endphp
                    <button type="button" class="relative block overflow-hidden rounded-[var(--radius-card)] bg-brand-50" @if ($images->isNotEmpty()) data-dialog-open="photos-dialog" @endif aria-label="View photos">
                        <img src="{{ $image?->thumbUrl() ?? $property->placeholderUrl() }}" alt="" loading="lazy" class="size-full object-cover {{ $image ? '' : 'opacity-60' }}">
                        @if ($i === 3 && $images->count() > 4)
                            <span class="absolute inset-0 grid place-items-center bg-black/45 text-[15px] font-semibold text-white">+{{ $images->count() - 4 }} Photos</span>
                        @endif
                    </button>
                @endfor
            </div>

            <div class="min-w-0">
                <div class="flex items-start justify-between gap-3">
                    <h1 class="text-2xl leading-tight sm:text-[26px]">{{ $property->title }}</h1>
                    <div class="flex shrink-0 gap-2">
                        @unless ($isPreview)
                            <x-save-button :property="$property" :saved="\App\Support\Lookup::isSaved($property->id)" style="button" />
                        @endunless
                        <button type="button" class="btn btn-secondary" data-share="{{ $url }}"><x-icon name="share" class="size-4" /> <span data-share-label>Share</span></button>
                    </div>
                </div>
                @unless ($isPreview)
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-[13px]">
                        <form method="POST" action="{{ route('compare.toggle', $property) }}">@csrf
                            <button class="inline-flex items-center gap-1.5 font-semibold text-brand-600 hover:underline"><x-icon name="layers" class="size-4" /> {{ in_array($property->id, session('compare', []), true) ? 'Remove from compare' : 'Add to compare' }}</button>
                        </form>
                        @if (count(session('compare', [])))
                            <a href="{{ route('compare') }}" class="text-muted hover:text-brand-700">View comparison ({{ count(session('compare', [])) }})</a>
                        @endif
                        <button type="button" class="ml-auto inline-flex items-center gap-1 text-muted hover:text-red-600" data-dialog-open="report-dialog"><x-icon name="alert" class="size-4" /> Report listing</button>
                    </div>
                @endunless
                <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[15px] text-ink/80">
                    <span class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-4" /> {{ $property->locationLabel() }}</span>
                    <a href="#location" class="flex items-center gap-1 text-sm font-semibold text-brand-600"><x-icon name="map" class="size-4" /> View on Map</a>
                </p>
                <p class="mt-3 font-serif text-[30px] leading-none font-bold text-brand-600">{{ $property->priceLabel() }}</p>
                @if ($perMarla)
                    <p class="mt-1.5 text-sm text-muted">Approx. {{ Format::price($perMarla) }} per Marla</p>
                @endif
                <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-ink/80">
                    @foreach ($specs as [$icon, $label])
                        <li class="flex items-center gap-1.5"><x-icon :name="$icon" class="size-[18px] text-muted" /> {{ $label }}</li>
                    @endforeach
                </ul>

                <div class="card mt-5 p-5">
                    <h2 class="font-serif text-xl font-bold">Contact Dealer</h2>
                    <div class="mt-3 flex items-center gap-4">
                        <span class="grid size-16 shrink-0 place-items-center rounded-full bg-ink font-serif text-2xl text-white">{{ collect(explode(' ', $profile?->agency_name ?? 'M P'))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-semibold">{{ $profile?->agency_name ?? 'Meezan Property dealer' }}</p>
                            @if ($verified)
                                <span class="mt-1 inline-flex items-center gap-1 rounded bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-700"><x-icon name="badge-check" class="size-3.5" /> Verified Dealer</span>
                            @elseif ($property->listerIsOwner())
                                <span class="mt-1 inline-flex items-center gap-1 rounded bg-gold-100 px-2 py-0.5 text-xs font-semibold text-gold-700"><x-icon name="home" class="size-3.5" /> Listed by owner</span>
                            @else
                                <span class="mt-1 inline-flex items-center gap-1 rounded bg-page px-2 py-0.5 text-xs font-medium text-muted">Verification pending</span>
                            @endif
                        </div>
                        @if ($property->dealer)
                            <p class="hidden text-right text-[13px] text-muted sm:block">Member since {{ $property->dealer->created_at->format('Y') }}</p>
                        @endif
                    </div>
                    <div class="mt-4 grid gap-2.5 sm:grid-cols-2">
                        @if ($verified && $profile->public_phone)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $profile->public_phone) }}" class="btn btn-primary h-11"><x-icon name="phone" class="size-4" /> Call Now</a>
                        @endif
                        @if ($waNumber)
                            <a href="https://wa.me/{{ $waNumber }}?text={{ $waText }}" target="_blank" rel="noopener" class="btn btn-whatsapp h-11"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>
                        @endif
                        <a href="#inquiry" class="btn btn-outline h-11"><x-icon name="mail" class="size-4" /> Send Message</a>
                        <a href="{{ $url }}?visit=1#inquiry" class="btn btn-primary h-11"><x-icon name="calendar" class="size-4" /> Request a Visit</a>
                    </div>
                    @unless ($verified)
                        <p class="mt-3 text-xs text-muted">Phone and WhatsApp appear once the dealer is verified. Your message reaches them through Meezan Property.</p>
                    @endunless
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <nav class="mt-6 flex overflow-x-auto rounded-t-[var(--radius-card)] border border-line bg-white scrollbar-none" aria-label="Sections">
            @foreach (array_filter(['overview' => 'Overview', 'details' => 'Details', 'amenities' => 'Amenities', 'location' => 'Location', 'nearby' => $property->hasCoordinates() && config('meezan.nearby.enabled') ? 'Nearby' : null, 'similar' => 'Similar Properties', 'inquiry' => 'Send Message']) as $id => $label)
                <a href="#{{ $id }}" @class(['shrink-0 border-r border-line px-6 py-3 text-sm font-medium transition', 'bg-brand-600 text-white' => $loop->first, 'text-ink hover:bg-brand-50' => ! $loop->first])>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid gap-5 rounded-b-[var(--radius-card)] border border-t-0 border-line bg-white p-5 lg:grid-cols-3">
                <section id="overview" class="scroll-mt-28" aria-labelledby="description-title">
                    <h2 id="description-title" class="font-serif text-xl font-bold">Property Description</h2>
                    <div class="prose-listing mt-3 text-sm text-muted [&_p]:leading-6 [&_p]:text-muted">
                        @foreach (preg_split('/\R{2,}/', trim($property->description)) as $paragraph)
                            <p>{!! nl2br(e($paragraph)) !!}</p>
                        @endforeach
                    </div>
                    <div id="amenities" class="mt-4 scroll-mt-28 rounded-lg border border-line p-4">
                        <h3 class="font-serif text-lg font-bold">Key Features</h3>
                        @if ($amenities = $property->amenityLabels())
                            <ul class="mt-3 space-y-2.5">
                                @foreach ($amenities as $amenity)
                                    <li class="flex items-center gap-2.5 text-sm"><span class="grid size-5 place-items-center rounded-full bg-brand-600 text-white"><x-icon name="check" class="size-3" /></span> {{ $amenity }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-muted">The dealer has not listed features yet.</p>
                        @endif
                    </div>
                </section>

                <section id="details" class="scroll-mt-28" aria-labelledby="details-title">
                    <h2 id="details-title" class="font-serif text-xl font-bold">Property Details</h2>
                    <dl class="mt-3 text-sm">
                        @foreach ($details as $label => $value)
                            <div class="grid grid-cols-[1fr_1.2fr] gap-3 border-b border-line py-2"><dt class="text-ink/80">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                        @endforeach
                    </dl>

                    @if ($property->priceChanges->isNotEmpty())
                        <div class="mt-5">
                            <h3 class="font-serif text-lg font-bold">Asking Price History</h3>
                            <p class="text-xs text-muted">Changes to the advertised price recorded on Meezan Property. These are asking prices, not sale prices.</p>
                            <table class="mt-2 w-full text-sm">
                                <tbody>
                                    @foreach ($property->priceChanges as $change)
                                        <tr class="border-b border-line">
                                            <td class="py-2 text-muted">{{ $change->changed_at->format('j M Y') }}</td>
                                            <td class="py-2 text-right">{{ Format::price($change->old_price) }} → <strong>{{ Format::price($change->new_price) }}</strong></td>
                                            <td class="py-2 text-right text-xs {{ $change->new_price < $change->old_price ? 'text-emerald-700' : 'text-red-600' }}">{{ $change->new_price < $change->old_price ? '↓' : '↑' }} {{ round(abs($change->new_price - $change->old_price) / max(1, $change->old_price) * 100, 1) }}%</td>
                                        </tr>
                                    @endforeach
                                    @if ($property->published_at)
                                        <tr><td class="py-2 text-muted">{{ $property->published_at->format('j M Y') }}</td><td class="py-2 text-right" colspan="2">Listed</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section id="location" class="scroll-mt-28" aria-labelledby="location-title">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="location-title" class="font-serif text-xl font-bold">Location</h2>
                        @include('properties.partials.location-status', ['property' => $property])
                    </div>
                    <div class="relative mt-3 h-72 overflow-hidden rounded-lg border border-line">
                        {{-- Only the public point is ever rendered; the exact pin stays private unless the advertiser allows it. --}}
                        @if ($property->showsExactLocation())
                            @include('properties.partials.map-panel', ['markers' => $markers, 'zoom' => 15])
                            <a href="https://www.google.com/maps/search/?api=1&query={{ $property->public_latitude }},{{ $property->public_longitude }}" target="_blank" rel="noopener" class="btn btn-outline absolute bottom-3 left-1/2 z-[500] -translate-x-1/2 bg-white"><x-icon name="external" class="size-4" /> View on Google Maps</a>
                        @elseif ($property->showsApproximateLocation())
                            @include('properties.partials.map-panel', ['markers' => $markers, 'zoom' => 14, 'approximateText' => 'Approximate location: the advertiser shows it to within about 500 m. Ask them for the exact address.'])
                        @elseif ($property->area?->hasCoordinates())
                            @include('properties.partials.map-panel', ['markers' => $markers, 'approximateText' => 'Approximate: shown at the centre of '.$property->area->name.'. Ask the advertiser for the exact location.'])
                        @else
                            <div class="grid h-full place-items-center bg-page p-6 text-center">
                                <div>
                                    <x-icon name="map-pin" class="mx-auto size-8 text-brand-400" />
                                    <p class="mt-2 text-sm font-semibold">{{ $property->locationLabel() }}</p>
                                    <p class="mt-1 text-[13px] text-muted">The exact map location has not been provided.</p>
                                    @if ($property->area)
                                        <a href="{{ route('properties.map', ['area' => $property->area->slug]) }}" class="btn btn-outline btn-sm mt-3">Explore {{ $property->area->name }}</a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                    @if ($property->address)
                        <p class="mt-2 text-[13px] text-muted">{{ $property->address }}</p>
                    @endif
                    @if ($property->showsExactLocation())
                        <p class="mt-1 text-xs text-muted">The pin was placed by the advertiser. Confirm the exact location when you visit.</p>
                    @endif
                </section>

                @if ($property->publicCoordinates() && config('meezan.nearby.enabled'))
                    {{-- Reference: Listing Dashboard, "Nearby Places". Loaded after the page so it never slows the listing. --}}
                    <section id="nearby" class="scroll-mt-28 border-t border-line pt-5 lg:col-span-3" aria-labelledby="nearby-title" data-nearby="{{ route('properties.nearby', $property) }}">
                        <h2 id="nearby-title" class="font-serif text-xl font-bold">Nearby Places</h2>
                        <p class="mt-1 text-xs text-muted">Named places within {{ number_format(config('meezan.nearby.radius_m') / 1000, 1) }} km of {{ $property->showsExactLocation() ? 'the advertiser’s pin' : 'the approximate location' }}, in a straight line. From OpenStreetMap, which may be incomplete.</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-nearby-list aria-live="polite">
                            @foreach (\App\Services\NearbyPlaces::CATEGORIES as $category)
                                <div class="animate-pulse rounded-lg bg-page p-3"><p class="text-sm font-semibold text-muted">{{ $category['label'] }}</p><div class="mt-2 h-3 w-3/4 rounded bg-line"></div><div class="mt-1.5 h-3 w-1/2 rounded bg-line"></div></div>
                            @endforeach
                        </div>
                        <p class="mt-2 text-[11px] text-muted">Map data &copy; OpenStreetMap contributors.</p>
                    </section>
                @endif
            </div>

            <aside class="space-y-5">
                @unless ($isRent)
                    <section class="card p-5" aria-labelledby="loan-title" data-loan-calc>
                        <h2 id="loan-title" class="font-serif text-lg font-bold">Home Loan Calculator</h2>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-[13px]">
                            <label class="text-muted">Property Price<input type="number" min="0" value="{{ $property->price }}" class="input mt-1 py-2" data-loan="price"></label>
                            <label class="text-muted">Down Payment (%)<input type="number" min="0" max="100" value="20" class="input mt-1 py-2" data-loan="down"></label>
                            <label class="text-muted">Loan Tenure<select class="input mt-1 py-2" data-loan="years">@foreach ([5, 10, 15, 20] as $y)<option value="{{ $y }}" @selected($y === 10)>{{ $y }} Years</option>@endforeach</select></label>
                            <label class="text-muted">Annual rate (%)<input type="number" min="0" step="0.1" value="15" class="input mt-1 py-2" data-loan="rate"></label>
                        </div>
                        <p class="mt-4 text-[13px] text-muted">Estimated Monthly Installment</p>
                        <p class="text-2xl font-bold text-brand-600" data-loan="result" aria-live="polite">—</p>
                        <p class="mt-1 text-xs text-muted">An estimate only. Use your bank’s actual rate and terms.</p>
                    </section>
                @endunless

                <section id="inquiry" class="card scroll-mt-28 p-5" aria-labelledby="inquiry-title">
                    <h2 id="inquiry-title" class="font-serif text-lg font-bold">{{ $visit ? 'Request a Visit' : 'Send a Message' }}</h2>
                    <p class="mt-1 text-[13px] text-muted">Your details go only to this listing’s dealer.</p>
                    @if ($isPreview)
                        <p class="mt-4 rounded-lg bg-page p-4 text-sm text-muted">Inquiries open once the listing is published.</p>
                    @else
                        <form method="POST" action="{{ route('inquiries.store', $property) }}#inquiry" class="mt-4 space-y-3.5" novalidate>
                            @csrf
                            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                            <x-field name="name" label="Full name" :value="$user?->name" required autocomplete="name" />
                            <x-field name="phone" type="tel" label="Phone" :value="$user?->phone" placeholder="0300 1234567" required autocomplete="tel" />
                            <x-field name="email" type="email" label="Email" :value="$user?->email" required autocomplete="email" />
                            <fieldset>
                                <legend class="label">Preferred contact</legend>
                                <div class="grid grid-cols-3 gap-1 rounded-md bg-page p-1">
                                    @foreach (['call' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'] as $value => $label)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="preferred_contact" value="{{ $value }}" class="peer sr-only" @checked(old('preferred_contact', 'call') === $value)>
                                            <span class="block rounded py-1.5 text-center text-[13px] font-medium text-muted peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-sm">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <x-field name="message" type="textarea" label="Message" rows="4" required
                                     :value="$visit ? 'Assalam o Alaikum, I would like to visit this property ('.$property->reference.'). Please let me know a suitable time.' : 'Assalam o Alaikum, I am interested in this property ('.$property->reference.'). Please share more details.'" />
                            <div>
                                <label class="flex items-start gap-2.5 text-[13px] text-muted">
                                    <input type="checkbox" name="consent" value="1" class="checkbox mt-0.5" @checked(old('consent'))>
                                    <span>I agree that Meezan Property may share my details with this listing’s dealer, as described in the <a href="{{ route('pages.privacy') }}" class="link">privacy policy</a>.</span>
                                </label>
                                @error('consent')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary w-full"><x-icon name="send" class="size-4" /> {{ $visit ? 'Request a Visit' : 'Send Message' }}</button>
                        </form>
                    @endif
                </section>
            </aside>
        </div>

        @php
            $launchCity = in_array($property->city, config('meezan.cities'), true);
            $categoryLabel = config('meezan.seo.categories.'.$seoCategory.'.label');
            $categoryUrl = $property->area ? route('areas.category', [$property->area->slug, $seoCategory]) : ($launchCity ? route('cities.category', [\App\Support\Cities::slug($property->city), $seoCategory]) : null);
        @endphp
        @if ($similar->isNotEmpty())
            <section id="similar" class="mt-10 scroll-mt-28" aria-labelledby="similar-title">
                <div class="flex items-end justify-between gap-3">
                    <h2 id="similar-title" class="section-title text-2xl">Similar Properties in {{ $property->city }}</h2>
                    @if ($categoryUrl)
                        <a href="{{ $categoryUrl }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">{{ $categoryLabel }}{{ $property->area ? ' in '.$property->area->name : '' }} <x-icon name="arrow-right" class="size-4" /></a>
                    @endif
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($similar as $item)
                        <x-property-card :property="$item" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Where to go next: the location's own pages, not keyword lists. --}}
        <nav class="mt-10 rounded-xl border border-line bg-white p-5" aria-label="Explore this location">
            <h2 class="text-base font-bold">Explore {{ $property->area?->name ?? $property->city }}</h2>
            <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                @if ($property->area)<li><a href="{{ route('areas.show', $property->area->slug) }}" class="btn btn-secondary btn-sm">{{ $property->area->name }} area guide</a></li>@endif
                @if ($categoryUrl)<li><a href="{{ $categoryUrl }}" class="btn btn-secondary btn-sm">{{ $categoryLabel }}{{ $property->area ? ' in '.$property->area->name : ' in '.$property->city }}</a></li>@endif
                @if ($launchCity)
                    <li><a href="{{ route('cities.category', [\App\Support\Cities::slug($property->city), $seoCategory]) }}" class="btn btn-secondary btn-sm">{{ $categoryLabel }} in {{ $property->city }}</a></li>
                    <li><a href="{{ route('cities.show', \App\Support\Cities::slug($property->city)) }}" class="btn btn-secondary btn-sm">Property in {{ $property->city }}</a></li>
                @endif
            </ul>
        </nav>
    </div>

    @unless ($isPreview)
        <dialog id="report-dialog" class="m-auto w-full max-w-md rounded-xl bg-white p-0 backdrop:bg-ink/60" aria-label="Report this listing">
            <form method="POST" action="{{ route('properties.report', $property) }}" class="space-y-4 p-6">
                @csrf
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-serif text-xl font-bold">Report this listing</h2>
                        <p class="mt-1 text-[13px] text-muted">Our moderation team reviews every report. The dealer is not told who reported it.</p>
                    </div>
                    <button type="button" class="btn btn-ghost px-2" data-dialog-close aria-label="Close"><x-icon name="x" /></button>
                </div>
                <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <x-field name="reason" type="select" label="What is wrong?" required placeholder="Choose a reason" :options="\App\Models\ListingReport::REASONS" />
                <x-field name="details" type="textarea" rows="3" label="Details (optional)" placeholder="e.g. The same house is listed as MP-000123" />
                @guest
                    <x-field name="reporter_email" type="email" label="Your email (optional)" hint="Only used if we need more information." />
                @endguest
                <button class="btn btn-primary w-full">Send report</button>
            </form>
        </dialog>
    @endunless

    @if ($images->isNotEmpty())
        <dialog id="photos-dialog" class="m-auto max-h-[90vh] w-full max-w-5xl rounded-xl bg-white p-0 backdrop:bg-ink/70" aria-label="All photos">
            <div class="sticky top-0 flex items-center justify-between border-b border-line bg-white px-5 py-3">
                <p class="font-semibold">{{ $images->count() }} Photos</p>
                <button type="button" class="btn btn-ghost px-2" data-dialog-close aria-label="Close"><x-icon name="x" /></button>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                @foreach ($images as $image)
                    <img src="{{ $image->url() }}" alt="{{ $property->title }} — photo {{ $loop->iteration }}" @if ($image->width) width="{{ $image->width }}" height="{{ $image->height }}" @endif loading="lazy" decoding="async" class="h-auto w-full rounded-lg object-cover">
                @endforeach
            </div>
        </dialog>
    @endif
</x-layouts.app>
