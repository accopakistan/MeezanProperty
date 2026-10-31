@php use App\Enums\DealerStatus; $status = $dealer->verification_status; @endphp
<x-layouts.dashboard :title="$dealer->agency_name" section="admin" :heading="$dealer->agency_name" :subheading="$dealer->city.' · registered '.$dealer->created_at->format('j M Y')">
    <x-slot:actions>
        <a href="{{ route('admin.dealers.index') }}" class="btn btn-ghost"><x-icon name="arrow-left" class="size-4" /> All dealers</a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <section class="card card-body">
                <h2 class="font-bold">Application details</h2>
                <dl class="mt-4 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Contact person' => $dealer->user->name,
                        'Email' => $dealer->user->email,
                        'Private mobile' => $dealer->user->phone ?? '—',
                        'Account status' => ucfirst($dealer->user->status),
                        'Office address' => $dealer->office_address ?? '—',
                        'Registration / licence' => $dealer->registration_number ?? 'Not provided',
                        'Years in business' => $dealer->years_in_business ?? '—',
                        'Public phone' => $dealer->public_phone ?? '—',
                        'WhatsApp' => $dealer->whatsapp_number ?? '—',
                        'Accepts leads' => $dealer->accepts_leads ? 'Yes' : 'No',
                    ] as $label => $value)
                        <div class="border-b border-line pb-3"><dt class="text-xs text-muted">{{ $label }}</dt><dd class="mt-0.5 font-medium">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                <div class="mt-5">
                    <p class="text-xs text-muted">Operating areas</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($dealer->areas as $area)<span class="badge bg-brand-50 text-brand-700">{{ $area->name }}</span>@endforeach
                    </div>
                </div>
                @if ($dealer->bio)
                    <p class="mt-5 rounded-xl bg-page p-4 text-sm">{{ $dealer->bio }}</p>
                @endif
            </section>

            <section class="card" aria-labelledby="documents-title">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h2 id="documents-title" class="font-bold">Verification documents</h2>
                    <span class="text-xs text-muted">Private · every view is logged</span>
                </div>
                @forelse ($dealer->documents as $document)
                    <div @class(['border-b border-line px-5 py-3 text-sm last:border-0', 'opacity-60' => $document->status === 'superseded'])>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $document->typeLabel() }} <x-status-badge :tone="$document->statusTone()" :label="$document->statusLabel()" /></p>
                                <p class="truncate text-xs text-muted">{{ $document->original_name }} · {{ $document->sizeLabel() }} · uploaded {{ $document->created_at->format('j M Y, g:i a') }}</p>
                                @if ($document->reviewed_at)
                                    <p class="text-xs text-muted">Reviewed {{ $document->reviewed_at->format('j M Y') }}{{ $document->review_note ? ' · '.$document->review_note : '' }}</p>
                                @endif
                            </div>
                            <a href="{{ route('admin.dealers.documents.show', $document) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="eye" class="size-4" /> Open</a>
                        </div>
                        @if ($document->status !== 'superseded')
                            <form method="POST" action="{{ route('admin.dealers.documents.review', $document) }}" class="mt-2 flex flex-wrap items-center gap-2">
                                @csrf @method('PATCH')
                                <input name="note" class="input min-w-48 flex-1 py-1.5 text-[13px]" placeholder="Reason for the dealer (required to reject)" aria-label="Reason for {{ $document->typeLabel() }}">
                                @if ($document->status !== 'approved')<button name="decision" value="approved" class="btn btn-secondary btn-sm text-emerald-700">Approve</button>@endif
                                @if ($document->status !== 'rejected')<button name="decision" value="rejected" class="btn btn-secondary btn-sm text-red-600">Reject</button>@endif
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-muted">No documents uploaded. Ask the dealer to upload them under Verification, or verify by other means and record how in the reason.</p>
                @endforelse
            </section>

            <section class="card" aria-labelledby="listings-title">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <h2 id="listings-title" class="font-bold">Listing activity</h2>
                    <div class="flex flex-wrap gap-2 text-xs">
                        @foreach ($listingCounts as $s => $count)
                            <span class="badge bg-page text-ink/70 ring-1 ring-line">{{ ucfirst($s) }} {{ $count }}</span>
                        @endforeach
                    </div>
                </div>
                @forelse ($listings as $property)
                    <a href="{{ route('admin.properties.show', $property) }}" class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 text-sm last:border-0 hover:bg-page">
                        <span class="min-w-0"><span class="block truncate font-semibold">{{ $property->title }}</span><span class="text-xs text-muted">{{ $property->reference }} · {{ $property->area?->name }}</span></span>
                        <x-status-badge :tone="$property->status->tone()" :label="$property->status->label()" />
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-muted">No listings yet.</p>
                @endforelse
                @if ($listings->isNotEmpty())
                    <a href="{{ route('admin.properties.index', ['dealer' => $dealer->user_id]) }}" class="block border-t border-line px-5 py-3 text-center text-sm font-semibold text-brand-600">All listings by this dealer</a>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card card-body">
                <p class="text-xs font-bold tracking-wide text-muted uppercase">Verification</p>
                <div class="mt-2"><x-status-badge :tone="$status->tone()" :label="$status->label()" /></div>
                @if ($dealer->verified_at)
                    <p class="mt-2 text-[13px] text-muted">Verified {{ $dealer->verified_at->format('j M Y') }} by {{ $dealer->verifier?->name ?? 'staff' }}</p>
                @endif
                @if ($dealer->rejection_reason)
                    <p class="mt-3 rounded-lg bg-red-50 p-3 text-[13px] text-red-800">{{ $dealer->rejection_reason }}</p>
                @endif

                @php $checklist = $dealer->documentChecklist(); $ready = $dealer->requiredDocumentsApproved(); @endphp
                <ul class="mt-4 space-y-1 text-[13px]">
                    @foreach ($checklist as $row)
                        <li class="flex justify-between gap-2"><span>{{ $row['label'] }}</span><span @class(['font-medium', 'text-emerald-700' => $row['state'] === 'approved', 'text-red-600' => $row['state'] === 'rejected', 'text-muted' => in_array($row['state'], ['missing', 'pending'], true)])>{{ ['approved' => 'Approved', 'rejected' => 'Rejected', 'pending' => 'To review', 'missing' => 'Missing'][$row['state']] }}</span></li>
                    @endforeach
                </ul>
                @error('decision')<p class="field-error mt-2">{{ $message }}</p>@enderror

                <div class="mt-5 space-y-3">
                    @if ($status === DealerStatus::Submitted)
                        <form method="POST" action="{{ route('admin.dealers.verification', $dealer) }}">@csrf @method('PATCH')
                            <button name="decision" value="start_review" class="btn btn-secondary w-full">Start review</button>
                        </form>
                    @endif
                    @if ($status === DealerStatus::Suspended)
                        <form method="POST" action="{{ route('admin.dealers.verification', $dealer) }}">@csrf @method('PATCH')
                            <button name="decision" value="reinstate" class="btn btn-primary w-full"><x-icon name="badge-check" class="size-4" /> Lift suspension</button>
                        </form>
                    @elseif ($status !== DealerStatus::Verified)
                        @if ($ready)
                            <form method="POST" action="{{ route('admin.dealers.verification', $dealer) }}">@csrf @method('PATCH')
                                <button name="decision" value="verify" class="btn btn-primary w-full"><x-icon name="badge-check" class="size-4" /> Verify dealer</button>
                            </form>
                        @else
                            <p class="rounded-lg bg-page p-3 text-[13px] text-muted">Approve every required document to verify this dealer.</p>
                        @endif
                    @endif
                    <form method="POST" action="{{ route('admin.dealers.verification', $dealer) }}" class="space-y-2 rounded-xl border border-line p-3">
                        @csrf @method('PATCH')
                        <x-field name="reason" label="Reason (shown to the dealer)" placeholder="e.g. Agency registration could not be confirmed" />
                        <div class="grid grid-cols-2 gap-2">
                            @if ($status->awaitsReview() || $status === DealerStatus::Pending)
                                <button name="decision" value="request_documents" class="btn btn-secondary btn-sm">Request documents</button>
                                <button name="decision" value="reject" class="btn btn-secondary btn-sm text-red-600">Reject</button>
                            @endif
                            @if ($status !== DealerStatus::Suspended)
                                <button name="decision" value="suspend" class="btn btn-secondary btn-sm text-red-600">Suspend</button>
                            @endif
                        </div>
                    </form>
                </div>

                <form method="POST" action="{{ route('admin.dealers.note', $dealer) }}" class="mt-5 space-y-2">
                    @csrf @method('PATCH')
                    <x-field name="internal_note" type="textarea" rows="3" label="Internal note (staff only, never shown to the dealer)" :value="$dealer->internal_note" />
                    <button class="btn btn-secondary btn-sm">Save note</button>
                </form>
            </section>

            @php $k = $performance['kpis']; $l = $performance['leads']; @endphp
            <section class="card card-body">
                <h2 class="font-bold">Performance, last 30 days</h2>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="text-muted">Listing views</dt><dd class="text-right font-semibold">{{ number_format($k['views']) }}</dd>
                    <dt class="text-muted">Inquiries</dt><dd class="text-right font-semibold">{{ $k['inquiries'] }}</dd>
                    <dt class="text-muted">Inquiries answered</dt><dd class="text-right font-semibold">{{ $k['responseRate'] === null ? '—' : $k['responseRate'].'%' }}</dd>
                    <dt class="text-muted">Median response</dt><dd class="text-right font-semibold">{{ $k['medianResponseHours'] === null ? '—' : $k['medianResponseHours'].' h' }}</dd>
                    <dt class="text-muted">Leads received</dt><dd class="text-right font-semibold">{{ $l['received'] }}</dd>
                    <dt class="text-muted">Leads responded</dt><dd class="text-right font-semibold">{{ $l['responded'] }}</dd>
                </dl>
                <p class="mt-3 text-xs text-muted">Useful when setting lead capacity. Response times count from when tracking began.</p>
            </section>

            <form method="POST" action="{{ route('admin.dealers.capacity', $dealer) }}" class="card card-body space-y-3">
                @csrf @method('PATCH')
                <h2 class="font-bold">Lead capacity</h2>
                <p class="text-[13px] text-muted">Maximum active requirement leads at a time. Leave empty for no limit. Currently {{ $dealer->user->leadAssignments()->where('status', 'active')->count() }} active.</p>
                <input type="number" name="lead_capacity" min="1" max="500" value="{{ $dealer->lead_capacity }}" class="input" placeholder="No limit" aria-label="Lead capacity">
                @error('lead_capacity')<p class="field-error">{{ $message }}</p>@enderror
                <button class="btn btn-secondary btn-sm w-max">Save capacity</button>
            </form>

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
                        <li class="text-muted">No history yet.</li>
                    @endforelse
                </ol>
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
