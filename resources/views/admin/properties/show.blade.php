@php use App\Enums\PropertyStatus; $status = $property->status; @endphp
<x-layouts.dashboard :title="'Review '.$property->reference" section="admin" :heading="$property->title" :subheading="$property->reference.' · '.$property->type->label().' · '.$property->locationLabel()">
    <x-slot:actions>
        <a href="{{ route('admin.properties.index', ['status' => $status->value]) }}" class="btn btn-ghost"><x-icon name="arrow-left" class="size-4" /> Back</a>
        @unless ($property->trashed())
            <a href="{{ route('properties.show', $property) }}" target="_blank" class="btn btn-secondary"><x-icon name="eye" class="size-4" /> {{ $property->isPublic() ? 'View live' : 'Preview' }}</a>
        @endunless
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <section class="card card-body">
                <h2 class="font-bold">Photos <span class="text-sm font-normal text-muted">({{ $property->images->count() }})</span></h2>
                @if ($property->images->isEmpty())
                    <p class="mt-3 rounded-xl bg-page p-4 text-sm text-muted">No photos uploaded.</p>
                @else
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($property->images as $image)
                            <div @class(['overflow-hidden rounded-xl ring-1', 'ring-line' => $image->status === 'active', 'ring-red-300 opacity-70' => $image->status !== 'active'])>
                                <a href="{{ $image->url() }}" target="_blank" class="relative block">
                                    <img src="{{ $image->thumbUrl() }}" alt="Photo {{ $loop->iteration }}" loading="lazy" class="aspect-[4/3] w-full object-cover">
                                    @if ($loop->first && $image->status === 'active')<span class="tag absolute top-1.5 left-1.5 bg-brand-600 text-[10px]">Cover</span>@endif
                                    @if ($image->status !== 'active')<span class="tag absolute top-1.5 left-1.5 bg-red-600 text-[10px]">{{ ucfirst($image->status) }}</span>@endif
                                </a>
                                <p class="px-2 pt-1 text-[11px] text-muted">{{ $image->width ? $image->width.'×'.$image->height : 'size not recorded' }}{{ $image->width && $image->width < \App\Services\ListingCompleteness::LOW_RES_WIDTH ? ' · low resolution' : '' }}</p>
                                <form method="POST" action="{{ route('admin.properties.images.moderate', $image) }}" class="flex flex-wrap gap-1 p-2">
                                    @csrf @method('PATCH')
                                    <input name="note" class="input w-full py-1 text-[11px]" placeholder="Reason (to hide)" aria-label="Reason for photo {{ $loop->iteration }}" value="{{ $image->moderation_note }}">
                                    @if ($image->status === 'active')
                                        <button name="status" value="flagged" class="btn btn-ghost btn-sm px-2 text-[11px]">Flag</button>
                                        <button name="status" value="removed" class="btn btn-ghost btn-sm px-2 text-[11px] text-red-600">Hide</button>
                                    @else
                                        <button name="status" value="active" class="btn btn-ghost btn-sm px-2 text-[11px]">Restore</button>
                                    @endif
                                </form>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-muted">Hidden photos stay on file and disappear from the public listing. No automatic image analysis is used.</p>
                @endif
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Details</h2>
                <dl class="mt-4 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-3">
                    @foreach ([
                        'Purpose' => $property->purpose->label(),
                        'Price' => $property->priceLabel(),
                        'Price (raw PKR)' => number_format($property->price),
                        'Area' => $property->areaLabel(),
                        'Normalised' => number_format($property->area_sqft).' sq ft',
                        'Bedrooms / baths' => ($property->bedrooms ?? '—').' / '.($property->bathrooms ?? '—'),
                        'Address' => $property->address ?? '—',
                        'Furnishing' => $property->furnishing ? ucfirst($property->furnishing) : '—',
                    ] as $label => $value)
                        <div class="border-b border-line pb-3"><dt class="text-xs text-muted">{{ $label }}</dt><dd class="mt-0.5 font-medium">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                <h3 class="mt-6 text-sm font-bold">Description</h3>
                <p class="mt-2 rounded-xl bg-page p-4 text-sm leading-6 whitespace-pre-line">{{ $property->description }}</p>
                @if ($amenities = $property->amenityLabels())
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($amenities as $amenity)<span class="badge bg-brand-50 text-brand-700">{{ $amenity }}</span>@endforeach
                    </div>
                @endif
            </section>

            <section class="card card-body" aria-labelledby="admin-location-title">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="admin-location-title" class="font-bold">Location</h2>
                    @include('properties.partials.location-status', ['property' => $property])
                </div>
                <p class="mt-2 text-sm">
                    {{ $property->locationLabel() }}
                    @if ($property->hasCoordinates())
                        <span class="text-muted">· pin {{ number_format($property->latitude, 7) }}, {{ number_format($property->longitude, 7) }}</span>
                    @endif
                </p>
                <dl class="mt-2 grid gap-1 text-[13px] sm:grid-cols-2">
                    <div><dt class="inline text-muted">Location quality:</dt> <dd class="inline font-medium">{{ $property->locationStatusLabel() }}</dd></div>
                    <div><dt class="inline text-muted">Public map shows:</dt> <dd class="inline font-medium">{{ \App\Models\Property::LOCATION_PRIVACY[$property->location_privacy] ?? $property->location_privacy }}</dd></div>
                </dl>
                @if ($property->location_flag_reason)
                    <p class="mt-2 rounded-lg bg-amber-50 p-2.5 text-[13px] text-amber-900">{{ $property->location_flag_reason }}</p>
                @endif
                @if ($property->hasCoordinates())
                    <form method="POST" action="{{ route('admin.properties.review', $property) }}" class="mt-3 flex flex-wrap items-center gap-2">
                        @csrf @method('PATCH')
                        <input name="reason" class="input min-w-48 flex-1 py-1.5 text-[13px]" placeholder="Why the pin needs checking (to flag)" aria-label="Location flag reason">
                        @if ($property->location_status !== 'verified')<button name="action" value="verify_location" class="btn btn-secondary btn-sm text-emerald-700">Mark location verified</button>@endif
                        @if ($property->location_status !== 'flagged')<button name="action" value="flag_location" class="btn btn-secondary btn-sm text-amber-700">Flag location</button>@endif
                    </form>
                @endif
                @if ($markers)
                    <div class="mt-3 h-64 overflow-hidden rounded-lg border border-line">
                        @include('properties.partials.map-panel', ['markers' => $markers, 'zoom' => 15, 'approximateText' => 'No exact pin: shown at the centre of '.$property->area?->name.'.'])
                    </div>
                    @if ($property->hasCoordinates())
                        <p class="mt-2 text-xs text-muted">Check that the pin sits inside {{ $property->area?->name ?? 'the stated area' }} before approving.</p>
                    @endif
                @else
                    <p class="mt-2 text-[13px] text-muted">No exact pin, and the area has no centre point, so this listing is not shown on the map.</p>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card card-body">
                <p class="text-xs font-bold tracking-wide text-muted uppercase">Moderation</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-status-badge :tone="$status->tone()" :label="$status->label()" />
                    @if ($property->is_demo)<span class="badge bg-ink text-white">Demo listing</span>@endif
                </div>
                @if ($property->expires_at && $status === PropertyStatus::Published)
                    <p class="mt-2 text-[13px] text-muted">Expires {{ $property->expires_at->format('j M Y') }}</p>
                @endif

                @if ($property->trashed())
                    <p class="mt-4 text-sm text-muted">The dealer removed this listing on {{ $property->deleted_at->format('j M Y') }}.</p>
                @else
                    <div class="mt-5 space-y-3">
                        @if (in_array($status, [PropertyStatus::Pending, PropertyStatus::Rejected, PropertyStatus::Draft, PropertyStatus::ChangesRequested], true))
                            <form method="POST" action="{{ route('admin.properties.moderate', $property) }}">@csrf @method('PATCH')
                                <button name="decision" value="approve" class="btn btn-primary w-full"><x-icon name="check" class="size-4" /> Approve & publish</button>
                            </form>
                        @endif
                        @if ($status === PropertyStatus::Suspended)
                            <form method="POST" action="{{ route('admin.properties.moderate', $property) }}">@csrf @method('PATCH')
                                <button name="decision" value="reinstate" class="btn btn-primary w-full">Lift suspension</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.properties.moderate', $property) }}" class="space-y-2 rounded-xl border border-line p-3">
                            @csrf @method('PATCH')
                            <x-field name="reason" type="textarea" rows="2" label="Note to dealer" placeholder="Required to reject, request changes or suspend, e.g. Photos do not match the listed property" />
                            <div class="grid grid-cols-2 gap-2">
                                @if (in_array($status, [PropertyStatus::Pending, PropertyStatus::Published], true))
                                    <button name="decision" value="request_changes" class="btn btn-secondary btn-sm">Request changes</button>
                                @endif
                                @if ($status !== PropertyStatus::Rejected)
                                    <button name="decision" value="reject" class="btn btn-secondary btn-sm text-red-600">Reject</button>
                                @endif
                                @if (in_array($status, [PropertyStatus::Published, PropertyStatus::Pending], true))
                                    <button name="decision" value="suspend" class="btn btn-secondary btn-sm text-red-600">Suspend</button>
                                @endif
                                @if ($status === PropertyStatus::Published)
                                    <button name="decision" value="unpublish" class="btn btn-secondary btn-sm">Unpublish</button>
                                @endif
                                @if ($status !== PropertyStatus::Archived)
                                    <button name="decision" value="archive" class="btn btn-secondary btn-sm">Archive</button>
                                @endif
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.properties.review', $property) }}" class="flex flex-wrap items-center gap-2">
                            @csrf @method('PATCH')
                            @if ($property->flagged_at)
                                <span class="text-[13px] text-amber-800">Requires review since {{ $property->flagged_at->format('j M') }}</span>
                                <button name="action" value="clear_flag" class="btn btn-ghost btn-sm">Clear</button>
                            @else
                                <input name="reason" class="input min-w-40 flex-1 py-1.5 text-[13px]" placeholder="What needs checking" aria-label="Review flag reason">
                                <button name="action" value="flag" class="btn btn-ghost btn-sm text-amber-700">Mark as requiring review</button>
                            @endif
                        </form>

                        @if ($status === PropertyStatus::Published)
                            <form method="POST" action="{{ route('admin.properties.feature', $property) }}">@csrf @method('PATCH')
                                <button class="btn btn-gold w-full"><x-icon name="star" class="size-4" /> {{ $property->is_featured ? 'Remove from featured' : 'Feature on homepage' }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </section>

            <section class="card card-body">
                <p class="text-xs font-bold tracking-wide text-muted uppercase">Dealer</p>
                @if ($profile = $property->dealer?->dealerProfile)
                    <a href="{{ route('admin.dealers.show', $profile) }}" class="mt-2 block font-semibold hover:text-brand-700">{{ $profile->agency_name }}</a>
                    <div class="mt-1"><x-status-badge :tone="$profile->verification_status->tone()" :label="$profile->verification_status->label()" /></div>
                    <p class="mt-2 text-[13px] text-muted">{{ $property->dealer->name }} · {{ $property->dealer->email }}</p>
                @else
                    <p class="mt-2 text-sm">{{ $property->dealer?->name }}</p>
                @endif
                @if ($property->authorization_type)
                    <p class="mt-3 text-[13px]"><span class="text-muted">Lister declared:</span> {{ \App\Models\Property::AUTHORIZATION[$property->authorization_type] ?? $property->authorization_type }} <span class="text-muted">({{ $property->authorization_confirmed_at?->format('j M Y') }})</span></p>
                @else
                    <p class="mt-3 text-[13px] text-amber-700">No ownership / authorisation declaration recorded.</p>
                @endif
            </section>

            {{-- Review checklist: internal signals in neutral wording --}}
            <section class="card card-body">
                <h2 class="font-bold">Review checklist</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($signals as $signal)
                        <li class="flex gap-2">
                            <x-icon :name="$signal['tone'] === 'success' ? 'check' : ($signal['tone'] === 'warning' ? 'alert' : 'info')" @class(['mt-0.5 size-4 shrink-0', 'text-emerald-600' => $signal['tone'] === 'success', 'text-amber-600' => $signal['tone'] === 'warning', 'text-muted' => ! in_array($signal['tone'], ['success', 'warning'], true)]) />
                            <span><span class="font-medium">{{ $signal['label'] }}</span>@if ($signal['detail'])<span class="block text-xs text-muted">{{ $signal['detail'] }}</span>@endif</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-muted">Completeness measures the information provided, not the property. Signals suggest what to check; none is a finding.</p>
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Possible duplicates</h2>
                @forelse ($duplicates as $d)
                    <a href="{{ route('admin.properties.show', $d) }}" class="mt-2 block text-sm"><span class="link">{{ $d->reference }}</span> <span class="text-muted">· {{ $d->dealer?->dealerProfile?->agency_name }} · {{ $d->priceLabel() }} · {{ strtolower($d->status->label()) }}</span>
                        <span class="block text-xs text-amber-800">{{ implode(', ', $d->duplicate_reasons ?? []) }}</span></a>
                @empty
                    <p class="mt-2 text-sm text-muted">No similar listing found.</p>
                @endforelse
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Reports <span class="text-sm font-normal text-muted">({{ $reports->count() }})</span></h2>
                @forelse ($reports as $r)
                    <p class="mt-2 text-sm"><span class="font-medium">{{ $r->reasonLabel() }}</span> <span class="text-muted">· {{ $r->status }} · {{ $r->created_at->format('j M') }}</span></p>
                @empty
                    <p class="mt-2 text-sm text-muted">No reports.</p>
                @endforelse
                @if ($reports->where('status', 'open')->isNotEmpty())
                    <a href="{{ route('admin.reports.index') }}" class="link mt-2 inline-block text-sm">Review open reports</a>
                @endif
            </section>

            <section class="card card-body">
                <h2 class="font-bold">History</h2>
                <ol class="mt-4 space-y-3 border-l border-line pl-4 text-sm">
                    @forelse ($history as $log)
                        <li>
                            <p class="font-semibold">{{ $log->actionLabel() }}</p>
                            <p class="text-xs text-muted">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at->format('j M Y, g:i a') }}</p>
                            @if ($log->properties['reason'] ?? null)<p class="mt-1 text-xs">“{{ $log->properties['reason'] }}”</p>@endif
                        </li>
                    @empty
                        <li class="text-muted">No history recorded.</li>
                    @endforelse
                </ol>
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
