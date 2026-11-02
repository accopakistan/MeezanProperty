@php use App\Enums\LeadStatus; $req = $lead->requirement; $inq = $lead->inquiry; @endphp
<x-layouts.dashboard :title="'Lead #'.$lead->id" section="admin" :heading="'Lead #'.$lead->id" :subheading="($inq ? 'Listing inquiry' : 'Shared requirement').' · created '.$lead->created_at->format('j M Y, g:i a')">
    <x-slot:actions>
        <a href="{{ route('admin.leads.index') }}" class="btn btn-ghost"><x-icon name="arrow-left" class="size-4" /> All leads</a>
    </x-slot:actions>

    @if ($lead->hold_reason)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
            <p class="font-bold">Held for staff: {{ \App\Models\Lead::HOLD_REASONS[$lead->hold_reason] ?? $lead->hold_reason }}.</p>
            <p class="mt-1">It is retried daily (allowances reset each month), or assign it to a dealer here.</p>
        </div>
    @endif
    @if ($lead->isInvalid())
        <div class="mb-6 rounded-xl border border-line bg-page p-4 text-sm" role="status">
            Marked as <strong>{{ strtolower(\App\Models\Lead::INVALID_REASONS[$lead->invalid_reason] ?? $lead->invalid_reason) }}</strong> on {{ $lead->invalidated_at->format('j M Y') }}. It does not count against any dealer’s lead allowance.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <section class="card card-body">
                <h2 class="font-bold">{{ $inq ? 'Inquiry' : 'Requirement' }}</h2>
                @if ($inq)
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs text-muted">Name</dt><dd class="font-medium">{{ $inq->name }}</dd></div>
                        <div><dt class="text-xs text-muted">Phone</dt><dd class="font-medium">{{ $inq->phone }}</dd></div>
                        <div><dt class="text-xs text-muted">Email</dt><dd class="font-medium">{{ $inq->email }}</dd></div>
                    </dl>
                    <p class="mt-4 rounded-xl bg-page p-4 text-sm whitespace-pre-line">{{ $inq->message }}</p>
                @elseif ($req)
                    <p class="mt-2 text-sm">{{ $req->summary() }}</p>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs text-muted">Buyer</dt><dd class="font-medium">{{ $req->user?->name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted">Contact</dt><dd class="font-medium">{{ $req->user?->email }}</dd></div>
                        <div><dt class="text-xs text-muted">Consent to share</dt><dd class="font-medium">{{ $lead->consent_to_share ? 'Yes' : 'No' }}</dd></div>
                    </dl>
                    @if ($req->notes)<p class="mt-4 rounded-xl bg-page p-4 text-sm">{{ $req->notes }}</p>@endif
                @endif
                @if ($lead->property)
                    <p class="mt-4 text-sm">Listing: <a href="{{ route('admin.properties.show', $lead->property) }}" class="link">{{ $lead->property->reference }} – {{ $lead->property->title }}</a></p>
                @endif
            </section>

            <section class="card" aria-labelledby="assignments-title">
                <h2 id="assignments-title" class="border-b border-line px-5 py-4 font-bold">Assignment history</h2>
                @forelse ($lead->assignments as $a)
                    <div class="border-b border-line px-5 py-3.5 text-sm last:border-0">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ $a->dealer?->dealerProfile?->agency_name ?? $a->dealer?->name }}</p>
                                <p class="text-xs text-muted">{{ $a->reason }} · by {{ $a->assigner?->name ?? 'automatic routing' }} · {{ $a->assigned_at->format('j M Y, g:i a') }}@if ($a->score !== null) · score {{ $a->score }}@endif</p>
                            </div>
                            <x-status-badge :tone="$a->status === 'active' ? 'success' : 'muted'" :label="ucfirst($a->status)" />
                        </div>
                        {{-- This dealer's own CRM state. Note text is private to the dealer; only counts are shown. --}}
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                            <x-status-badge :tone="$a->pipeline_stage->tone()" :label="'Dealer stage: '.$a->pipeline_stage->label()" />
                            <span>Priority {{ strtolower($a->priorityLabel()) }}</span>
                            <span>· last activity {{ ($a->last_activity_at ?? $a->assigned_at)->diffForHumans() }}</span>
                            <span>· {{ $a->pending_follow_ups }} follow-up(s) pending{{ $a->overdue_follow_ups ? ', '.$a->overdue_follow_ups.' overdue' : '' }}</span>
                            <span>· {{ $a->scheduled_appointments }} appointment(s)</span>
                            <span>· {{ $a->recommendations_count }} recommendation(s)</span>
                            <span>· {{ $a->notes_count }} private note(s)</span>
                            @if ($a->stale_flagged_at)<span class="badge bg-amber-50 text-amber-700">Needs attention</span>@endif
                            @if ($a->outcome_reason)<span>· outcome: {{ config('meezan.crm.lost_reasons.'.$a->outcome_reason, $a->outcome_reason) }}</span>@endif
                            @if ($a->end_reason)<span>· ended: {{ $a->end_reason }}</span>@endif
                        </div>
                        @if ($a->status === 'active')
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs font-semibold text-red-700">Remove this dealer</summary>
                                <form method="POST" action="{{ route('admin.leads.assignments.destroy', $a) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                    @csrf @method('DELETE')
                                    <label class="text-xs">Reason (kept in the audit log) <input name="reason" class="input mt-1 py-1.5" maxlength="255" required></label>
                                    <button class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-muted">Not assigned to any dealer.</p>
                @endforelse
            </section>
        </div>

        <aside class="space-y-6">
            <form method="POST" action="{{ route('admin.leads.status', $lead) }}" class="card card-body space-y-3">
                @csrf @method('PATCH')
                <p class="text-xs font-bold tracking-wide text-muted uppercase">Status</p>
                <x-status-badge :tone="$lead->status->tone()" :label="$lead->status->label()" />
                <select name="status" class="input" aria-label="New status">
                    @foreach (LeadStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected($lead->status === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary w-full">Update status</button>
            </form>

            <form method="POST" action="{{ route('admin.leads.reassign', $lead) }}" class="card card-body space-y-3">
                @csrf
                <p class="text-xs font-bold tracking-wide text-muted uppercase">Reassign</p>
                @if ($suggested->isNotEmpty())
                    <p class="text-[13px] text-muted">Suggested by area match: {{ $suggested->map(fn ($s) => $s['dealer']->dealerProfile->agency_name.' ('.$s['score'].($s['lead_limit'] !== null ? ', '.$s['leads_used'].'/'.$s['lead_limit'].' leads this month' : '').($s['at_capacity'] ? ', at limit' : '').')')->implode(', ') }}</p>
                @endif
                @if ($dealers->isEmpty())
                    <p class="rounded-lg bg-amber-50 p-3 text-[13px] text-amber-900">No verified dealers are available. Verify a dealer before assigning leads.</p>
                @else
                    <x-field name="dealer_id" type="select" label="Verified dealer" required placeholder="Choose a dealer" :options="$dealers->mapWithKeys(fn ($d) => [$d->id => $d->dealerProfile->agency_name.' — '.$d->name])->all()" />
                    <x-field name="reason" label="Reason" required placeholder="e.g. Original dealer unresponsive" />
                    <button class="btn btn-primary w-full">Assign lead</button>
                    <p class="text-xs text-muted">Current active assignments are ended and the change is recorded in the audit log. Staff assignments count towards the dealer’s monthly allowance.</p>
                @endif
            </form>

            <form method="POST" action="{{ route('admin.leads.assignments.store', $lead) }}" class="card card-body space-y-3">
                @csrf
                <p class="text-xs font-bold tracking-wide text-muted uppercase">Add a dealer</p>
                @error('dealer_id')<p class="rounded-lg bg-red-50 p-2 text-[13px] text-red-700">{{ $message }}</p>@enderror
                <x-field name="dealer_id" type="select" label="Verified dealer" required placeholder="Choose a dealer" :options="$dealers->mapWithKeys(fn ($d) => [$d->id => $d->dealerProfile->agency_name.' — '.$d->name])->all()" />
                <x-field name="reason" label="Reason" required maxlength="255" />
                <label class="flex items-start gap-2 text-[13px]"><input type="checkbox" name="override_allowance" value="1" class="checkbox mt-0.5"> Assign even if the dealer has used their monthly lead allowance (recorded)</label>
                <button class="btn btn-secondary w-full">Add dealer</button>
                <p class="text-xs text-muted">Keeps the current dealers. Verification, city and area coverage and the {{ config('meezan.leads.max_dealers_per_requirement', 3) }}-dealer maximum always apply.</p>
            </form>

            <form method="POST" action="{{ route('admin.leads.validity', $lead) }}" class="card card-body space-y-3">
                @csrf @method('PATCH')
                <p class="text-xs font-bold tracking-wide text-muted uppercase">Validity</p>
                <x-field name="invalid_reason" type="select" label="Mark as" :value="$lead->invalid_reason" placeholder="Valid lead" :options="\App\Models\Lead::INVALID_REASONS" />
                <button class="btn btn-secondary w-full">Save</button>
                <p class="text-xs text-muted">Spam, duplicate and invalid leads do not use any dealer’s lead allowance.</p>
            </form>
        </aside>
    </div>
</x-layouts.dashboard>
