@php use App\Enums\LeadStatus; $req = $lead->requirement; $inq = $lead->inquiry; @endphp
<x-layouts.dashboard :title="'Lead #'.$lead->id" section="admin" :heading="'Lead #'.$lead->id" :subheading="($inq ? 'Listing inquiry' : 'Shared requirement').' · created '.$lead->created_at->format('j M Y, g:i a')">
    <x-slot:actions>
        <a href="{{ route('admin.leads.index') }}" class="btn btn-ghost"><x-icon name="arrow-left" class="size-4" /> All leads</a>
    </x-slot:actions>

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
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3.5 text-sm last:border-0">
                        <div>
                            <p class="font-semibold">{{ $a->dealer?->dealerProfile?->agency_name ?? $a->dealer?->name }}</p>
                            <p class="text-xs text-muted">{{ $a->reason }} · by {{ $a->assigner?->name ?? 'automatic routing' }} · {{ $a->assigned_at->format('j M Y, g:i a') }}@if ($a->score !== null) · score {{ $a->score }}@endif</p>
                        </div>
                        <x-status-badge :tone="$a->status === 'active' ? 'success' : 'muted'" :label="ucfirst($a->status)" />
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
                    <p class="text-[13px] text-muted">Suggested by area match: {{ $suggested->map(fn ($s) => $s['dealer']->dealerProfile->agency_name.' ('.$s['score'].')')->implode(', ') }}</p>
                @endif
                @if ($dealers->isEmpty())
                    <p class="rounded-lg bg-amber-50 p-3 text-[13px] text-amber-900">No verified dealers are available. Verify a dealer before assigning leads.</p>
                @else
                    <x-field name="dealer_id" type="select" label="Verified dealer" required placeholder="Choose a dealer" :options="$dealers->mapWithKeys(fn ($d) => [$d->id => $d->dealerProfile->agency_name.' — '.$d->name])->all()" />
                    <x-field name="reason" label="Reason" required placeholder="e.g. Original dealer unresponsive" />
                    <button class="btn btn-primary w-full">Assign lead</button>
                    <p class="text-xs text-muted">Current active assignments are ended and the change is recorded in the audit log.</p>
                @endif
            </form>
        </aside>
    </div>
</x-layouts.dashboard>
