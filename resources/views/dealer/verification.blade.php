@php
    use App\Enums\DealerStatus;
    use App\Models\DealerDocument;
    $status = $profile->verification_status;
    $isOwner = $profile->business_type === 'owner';
    $stateIcon = ['approved' => ['check', 'text-emerald-600'], 'pending' => ['clock', 'text-amber-600'], 'rejected' => ['alert', 'text-red-600'], 'missing' => ['info', 'text-muted']];
    $stateText = ['approved' => 'Approved', 'pending' => 'Uploaded, awaiting review', 'rejected' => 'Needs replacing', 'missing' => 'Not uploaded'];
@endphp
{{-- Reference: Dealer Platform UI Mockup, "Document upload" and "Verification submitted" --}}
<x-layouts.dashboard title="Verification" section="dealer" heading="Verification documents"
                     subheading="Documents that help our team confirm your identity and {{ $isOwner ? 'ownership' : 'agency' }}.">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-6">
            {{-- Status and the next step --}}
            <section class="card card-body" aria-labelledby="status-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold tracking-wide text-muted uppercase">Verification status</p>
                        <h2 id="status-title" class="mt-1 text-lg font-bold">{{ $headline['title'] }}</h2>
                        <p class="mt-1 text-sm text-muted">{{ $headline['body'] }}</p>
                    </div>
                    <x-status-badge :tone="$headline['tone']" :label="$headline['title']" />
                </div>
                @if ($profile->rejection_reason && in_array($status, [DealerStatus::Rejected, DealerStatus::Suspended, DealerStatus::Pending], true))
                    <p class="mt-3 rounded-lg bg-red-50 p-3 text-[13px] text-red-800"><strong>Reason from our team:</strong> {{ $profile->rejection_reason }}</p>
                @endif
                @if ($status->dealerCanSubmit())
                    <form method="POST" action="{{ route('dealer.verification.submit') }}" class="mt-4">
                        @csrf
                        <button class="btn btn-primary" @disabled(! $profile->requiredDocumentsUploaded())><x-icon name="send" class="size-4" /> Submit for review</button>
                        @unless ($profile->requiredDocumentsUploaded())
                            <span class="ml-2 text-[13px] text-muted">Upload every required document first.</span>
                        @endunless
                    </form>
                @endif
            </section>

            @if ($status !== DealerStatus::Suspended)
                <form method="POST" action="{{ route('dealer.documents.store') }}" enctype="multipart/form-data" class="card card-body space-y-5" aria-labelledby="upload-title">
                    @csrf
                    <div>
                        <h2 id="upload-title" class="text-base font-bold">Upload a document</h2>
                        <p class="mt-1 text-[13px] text-muted">Photo (JPG, PNG, WebP) or PDF · up to {{ config('meezan.verification.max_document_kb') / 1024 }} MB each · up to 4 files at a time, {{ DealerDocument::MAX_PER_PROFILE }} in total.</p>
                    </div>
                    <x-field name="type" type="select" label="Document type" required placeholder="Choose a type" :options="DealerDocument::TYPES" />
                    <div>
                        <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-brand-200 bg-brand-50/50 px-6 py-8 text-center transition hover:border-brand-400">
                            <x-icon name="upload" class="size-7 text-brand-500" />
                            <span class="mt-2 text-sm font-semibold">Choose files</span>
                            <span class="text-xs text-muted" data-file-names>Make sure all four corners and the text are readable.</span>
                            <input type="file" name="files[]" accept="image/jpeg,image/png,image/webp,application/pdf" multiple class="sr-only" required
                                   data-file-list>
                        </label>
                        @error('files')<p class="field-error">{{ $message }}</p>@enderror
                        @foreach ($errors->get('files.*') as $messages)<p class="field-error">{{ $messages[0] }}</p>@endforeach
                    </div>
                    <button class="btn btn-primary w-max"><x-icon name="upload" class="size-4" /> Upload</button>
                </form>
            @endif

            <section class="card" aria-labelledby="docs-title">
                <h2 id="docs-title" class="border-b border-line px-5 py-4 font-bold">Your documents</h2>
                @forelse ($documents as $document)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-0">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$document->isImage() ? 'image' : 'file-text'" class="size-5" /></span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">{{ $document->typeLabel() }}</p>
                                <p class="truncate text-xs text-muted">{{ $document->original_name }} · {{ $document->sizeLabel() }} · {{ $document->created_at->format('j M Y') }}</p>
                                @if ($document->status === 'rejected' && $document->review_note)
                                    <p class="mt-1 text-xs text-red-700">{{ $document->review_note }} Upload a replacement of the same type.</p>
                                @endif
                            </div>
                            <x-status-badge :tone="$document->statusTone()" :label="$document->statusLabel()" />
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('dealer.documents.show', $document) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="eye" class="size-4" /> View</a>
                            @if ($canRemove && $document->status === 'pending')
                                <form method="POST" action="{{ route('dealer.documents.destroy', $document) }}" data-confirm="Remove this document?">@csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50" aria-label="Remove {{ $document->typeLabel() }}"><x-icon name="trash" class="size-4" /></button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-empty-state compact class="m-5" icon="file-text" title="No documents yet">Upload the documents listed on the right to speed up verification.</x-empty-state>
                @endforelse
            </section>
        </div>

        <aside class="space-y-5">
            <section class="card card-body">
                <h2 class="text-sm font-bold">Required documents</h2>
                <ul class="mt-3 space-y-2.5 text-sm">
                    @foreach ($checklist as $row)
                        @php [$icon, $tone] = $stateIcon[$row['state']]; @endphp
                        <li class="flex items-start gap-2">
                            <x-icon :name="$icon" class="mt-0.5 size-4 shrink-0 {{ $tone }}" />
                            <span><span class="block">{{ $row['label'] }}</span><span class="text-xs text-muted">{{ $stateText[$row['state']] }}</span></span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-muted">Optional: {{ $isOwner ? 'proof of ownership' : 'proof of office address' }} or other supporting documents.</p>
            </section>

            <section class="card card-body text-[13px] text-muted">
                <p class="flex items-center gap-2 font-semibold text-ink"><x-icon name="lock" class="size-4 text-brand-500" /> How we handle documents</p>
                <p class="mt-2">Documents are stored privately and are visible only to you and Meezan Property staff. They are used only to verify your account, are never shown to buyers, and every staff view is logged.</p>
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
