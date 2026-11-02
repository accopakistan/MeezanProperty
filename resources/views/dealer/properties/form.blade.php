@php
    use App\Enums\AreaUnit;
    use App\Enums\PropertyStatus;
    use App\Support\Lookup;

    /** @var \App\Models\Property $property */
    $editing = $property->exists;
    $status = $property->status;
    $canSubmit = ! $editing || in_array($status, [PropertyStatus::Draft, PropertyStatus::Rejected, PropertyStatus::ChangesRequested, PropertyStatus::Expired], true) || $property->hasLapsed();
    $completeness = $editing ? \App\Services\ListingCompleteness::for($property) : null;
    $selectedAmenities = old('amenities', $property->amenities ?? []);
    $purpose = old('purpose', $property->purpose?->value ?? 'sale');
@endphp
<x-layouts.dashboard :title="$editing ? 'Edit listing' : 'Add a listing'" section="dealer" :heading="$editing ? 'Edit listing' : 'Add a listing'"
                     :subheading="$editing ? $property->reference.' · '.$property->title : 'Fill in the details buyers look for. Listings are reviewed before publishing.'">
    @if ($editing)
        <x-slot:actions>
            <a href="{{ route('properties.show', $property) }}" class="btn btn-secondary" target="_blank"><x-icon name="eye" class="size-4" /> Preview</a>
        </x-slot:actions>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">Please fix {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('issue', $errors->count()) }} below.</p>
        </div>
    @endif

    @unless ($editing && \App\Services\Billing\Entitlements::countsListing($property->status))
        <x-listing-allowance class="mb-6" />
    @endunless

    <form method="POST" action="{{ $editing ? route('dealer.properties.update', $property) : route('dealer.properties.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6">
            <section class="card card-body space-y-5" aria-labelledby="basics">
                <h2 id="basics" class="text-base font-bold">Basics</h2>
                <x-field name="title" label="Listing title" :value="$property->title" required maxlength="160" placeholder="e.g. 10 Marla House with Basement in DHA Phase 5" hint="Include size, type and area. 10–160 characters." />
                <div class="grid gap-5 sm:grid-cols-2">
                    <fieldset>
                        <legend class="label">Purpose <span class="text-red-500">*</span></legend>
                        <div class="grid grid-cols-2 gap-1 rounded-xl bg-page p-1">
                            @foreach (['sale' => 'For sale', 'rent' => 'For rent'] as $value => $label)
                                <label class="cursor-pointer">
                                    <input type="radio" name="purpose" value="{{ $value }}" class="peer sr-only" @checked($purpose === $value)>
                                    <span class="block rounded-lg py-2 text-center text-sm font-semibold text-muted peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('purpose')<p class="field-error">{{ $message }}</p>@enderror
                    </fieldset>
                    <x-field name="type" type="select" label="Property type" required data-type-select>
                        <option value="">Choose a type</option>
                        @foreach (Lookup::typesByGroup() as $label => $types)
                            <optgroup label="{{ $label }}">
                                @foreach ($types as $t)
                                    <option value="{{ $t->value }}" data-rooms="{{ $t->hasRooms() ? 1 : 0 }}" @selected(old('type', $property->type?->value) === $t->value)>{{ $t->label() }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </x-field>
                </div>
            </section>

            <section class="card card-body space-y-5" aria-labelledby="location">
                <h2 id="location" class="text-base font-bold">Location</h2>
                @include('dealer.properties.partials.location-select')
                <x-field name="address" label="Street / block (optional)" :value="$property->address" placeholder="e.g. Block K, Street 12" hint="Shown publicly. Leave out house numbers if you prefer." />
                @include('dealer.properties.partials.location-picker')
            </section>

            <section class="card card-body space-y-5" aria-labelledby="price">
                <h2 id="price" class="text-base font-bold">Price & size</h2>
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-field name="price" type="number" label="Price (PKR)" required min="1000" step="1" :value="$property->price" placeholder="25000000" hint="Full amount in rupees. For rent, the monthly rent." class="sm:col-span-1" />
                    <x-field name="area_size" type="number" label="Area size" required min="0.25" step="0.25" :value="$property->area_size" placeholder="10" />
                    <x-field name="area_unit" type="select" label="Unit" required :value="$property->area_unit?->value" :options="collect(AreaUnit::cases())->mapWithKeys(fn ($u) => [$u->value => $u->label()])->all()" />
                </div>
                <div class="grid gap-5 sm:grid-cols-3" data-rooms-field>
                    <x-field name="bedrooms" type="number" label="Bedrooms" min="0" max="30" :value="$property->bedrooms" />
                    <x-field name="bathrooms" type="number" label="Bathrooms" min="0" max="30" :value="$property->bathrooms" />
                    <x-field name="furnishing" type="select" label="Furnishing" :value="$property->furnishing" placeholder="Not specified" :options="['unfurnished' => 'Unfurnished', 'semi-furnished' => 'Semi-furnished', 'furnished' => 'Furnished']" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="covered_area_sqft" type="number" min="50" step="1" label="Covered area (sq ft, optional)" :value="$property->covered_area_sqft" hint="Built-up area. Leave blank for open plots." />
                    <x-field name="completion_status" type="select" label="Status (optional)" :value="$property->completion_status" placeholder="Not specified" :options="\App\Models\Property::COMPLETION" />
                </div>
            </section>

            <section class="card card-body space-y-5" aria-labelledby="details">
                <h2 id="details" class="text-base font-bold">Description & features</h2>
                <x-field name="description" type="textarea" label="Description" rows="7" required :value="$property->description" hint="At least 50 characters. Describe layout, condition, possession and nearby facilities." />
                <fieldset>
                    <legend class="label">Features & amenities</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach (config('meezan.amenities') as $key => $label)
                            <label class="flex items-center gap-2.5 rounded-lg border border-line px-3 py-2.5 text-sm has-[:checked]:border-brand-300 has-[:checked]:bg-brand-50">
                                <input type="checkbox" name="amenities[]" value="{{ $key }}" class="checkbox" @checked(in_array($key, $selectedAmenities, true))> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </section>

            <section class="card card-body space-y-4" aria-labelledby="authority">
                <div>
                    <h2 id="authority" class="text-base font-bold">Your authority to list</h2>
                    <p class="mt-1 text-[13px] text-muted">Meezan Property records this declaration but does not legally verify ownership. Buyers should always check title documents.</p>
                </div>
                <x-field name="authorization_type" type="select" label="Your relationship to this property" required :value="$property->authorization_type ?? (auth()->user()->dealerProfile?->business_type === 'owner' ? 'owner' : 'authorized_agent')" :options="\App\Models\Property::AUTHORIZATION" />
                <div>
                    <label class="flex items-start gap-2.5 text-sm">
                        <input type="checkbox" name="authorization_confirmed" value="1" class="checkbox mt-0.5" @checked(old('authorization_confirmed', $property->authorization_confirmed_at ? 1 : 0))>
                        <span>I confirm that I own this property or have the owner’s permission to advertise it, and that the details are accurate.</span>
                    </label>
                    @error('authorization_confirmed')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="card card-body space-y-5" aria-labelledby="photos">
                <div>
                    <h2 id="photos" class="text-base font-bold">Photos</h2>
                    <p class="mt-1 text-[13px] text-muted">Up to {{ config('meezan.listing.max_images') }} photos · JPG, PNG or WebP · at least 400 × 300 px · max 5 MB each. Photos are resized and saved without location data.</p>
                </div>

                @if ($editing && $property->images->isNotEmpty())
                    @php $coverId = (int) old('cover_image', $property->images->first()->id); @endphp
                    <p class="text-[13px] text-muted">Choose the cover photo and use the arrows to change the order. Changing the order does not send a live listing back to review.</p>
                    <ol class="grid grid-cols-2 gap-3 sm:grid-cols-3 2xl:grid-cols-4" data-photo-order aria-label="Listing photos in display order">
                        @foreach ($property->images as $image)
                            <li class="group relative overflow-hidden rounded-xl ring-1 ring-line has-[[name='remove_images[]']:checked]:ring-2 has-[[name='remove_images[]']:checked]:ring-red-500 has-[[name=cover_image]:checked]:ring-2 has-[[name=cover_image]:checked]:ring-gold-500" data-photo>
                                <input type="hidden" name="image_order[]" value="{{ $image->id }}">
                                <img src="{{ $image->thumbUrl() }}" alt="Photo {{ $loop->iteration }}" class="aspect-[4/3] w-full bg-page object-cover group-has-[[name='remove_images[]']:checked]:opacity-40">
                                <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1.5 bg-white p-2 text-xs font-semibold">
                                    <label class="flex cursor-pointer items-center gap-1.5 text-brand-700">
                                        <input type="radio" name="cover_image" value="{{ $image->id }}" class="size-3.5 accent-brand-600" @checked($coverId === $image->id)> Cover
                                    </label>
                                    <span class="flex gap-1" data-photo-controls hidden>
                                        <button type="button" class="grid size-7 place-items-center rounded-full ring-1 ring-line hover:bg-brand-50 hover:text-brand-700" data-photo-move="-1" aria-label="Move photo earlier"><x-icon name="chevron-left" class="size-4" /></button>
                                        <button type="button" class="grid size-7 place-items-center rounded-full ring-1 ring-line hover:bg-brand-50 hover:text-brand-700" data-photo-move="1" aria-label="Move photo later"><x-icon name="chevron-right" class="size-4" /></button>
                                    </span>
                                    <label class="flex w-full cursor-pointer items-center gap-1.5 text-red-600">
                                        <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="checkbox size-3.5"> Remove photo
                                    </label>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-brand-200 bg-brand-50/50 px-6 py-8 text-center transition hover:border-brand-400">
                    <x-icon name="upload" class="size-7 text-brand-500" />
                    <span class="mt-2 text-sm font-semibold">Choose photos to upload</span>
                    <span class="text-xs text-muted">You can select several at once</span>
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-image-input="new-image-preview">
                </label>
                <div id="new-image-preview" class="grid grid-cols-3 gap-3 sm:grid-cols-5"></div>
                @error('images')<p class="field-error">{{ $message }}</p>@enderror
                @foreach ($errors->get('images.*') as $messages)
                    <p class="field-error">{{ $messages[0] }}</p>
                @endforeach
            </section>
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <div class="card card-body">
                <p class="text-xs font-bold tracking-wide text-muted uppercase">Status</p>
                <div class="mt-2">
                    @if ($editing)
                        <x-status-badge :tone="$status->tone()" :label="$status->label()" />
                    @else
                        <x-status-badge tone="neutral" label="New draft" />
                    @endif
                </div>
                @if (in_array($status, [PropertyStatus::Rejected, PropertyStatus::ChangesRequested, PropertyStatus::Suspended], true) && $property->rejection_reason)
                    <p class="mt-3 rounded-lg bg-red-50 p-3 text-[13px] text-red-800"><strong>Reviewer note:</strong> {{ $property->rejection_reason }}</p>
                @endif
                @if ($editing && $property->hasLapsed())
                    <p class="mt-3 rounded-lg bg-red-50 p-3 text-[13px] text-red-800">
                        Expired on {{ $property->expires_at->format('j M Y') }} and hidden from buyers.
                        {{ $property->canBeRenewed() ? 'If nothing has changed, renew it from My Listings. To change details, edit and submit for review.' : 'Check the details and submit it for review to relist it.' }}
                    </p>
                @elseif ($status === PropertyStatus::Published)
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-[13px] text-amber-900">Saving changes sends this listing back for review. It will be hidden until approved again.</p>
                    @if ($property->expires_at)
                        <p class="mt-2 text-[13px] text-muted">Live until {{ $property->expires_at->format('j M Y') }}.</p>
                    @endif
                @endif

                <div class="mt-5 grid gap-2">
                    @if ($canSubmit)
                        <button type="submit" name="action" value="submit" class="btn btn-primary w-full"><x-icon name="send" class="size-4" /> Submit for review</button>
                        <button type="submit" name="action" value="draft" class="btn btn-secondary w-full">Save as draft</button>
                    @else
                        <button type="submit" name="action" value="draft" class="btn btn-primary w-full">Save changes</button>
                    @endif
                    <a href="{{ route('dealer.properties.index') }}" class="btn btn-ghost w-full">Cancel</a>
                </div>
            </div>

            @if ($completeness)
                {{-- Completeness only: how much information the listing gives, not a rating of the property --}}
                <div class="card card-body">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold">Listing completeness</p>
                        <span class="text-sm font-bold {{ $completeness['ready'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $completeness['score'] }}%</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-page" role="progressbar" aria-valuenow="{{ $completeness['score'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Listing completeness">
                        <div class="h-full rounded-full {{ $completeness['ready'] ? 'bg-emerald-500' : 'bg-gold-500' }}" style="width: {{ $completeness['score'] }}%"></div>
                    </div>
                    @if ($completeness['missing'])
                        <ul class="mt-3 space-y-1 text-[13px] text-muted">
                            @foreach ($completeness['missing'] as $hint)<li class="flex gap-1.5"><x-icon name="info" class="mt-0.5 size-3.5 shrink-0" /> {{ $hint }}</li>@endforeach
                        </ul>
                    @endif
                    <p class="mt-2 text-xs text-muted">Shows how much information buyers get. It is not a rating of the property.</p>
                </div>
            @endif

            <div class="card card-body text-[13px] text-muted">
                <p class="font-semibold text-ink">Review checklist</p>
                <ul class="mt-2 space-y-1.5">
                    <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> Price and size are accurate</li>
                    <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> Photos are of this property</li>
                    <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> You are authorised to market it</li>
                </ul>
            </div>
        </aside>
    </form>

    @if ($editing)
        <form method="POST" action="{{ route('dealer.properties.destroy', $property) }}" class="mt-6" data-confirm="Remove this listing? It will be hidden from the website.">
            @csrf @method('DELETE')
            <button class="btn btn-ghost text-red-600 hover:bg-red-50"><x-icon name="trash" class="size-4" /> Remove listing</button>
        </form>
    @endif
</x-layouts.dashboard>
