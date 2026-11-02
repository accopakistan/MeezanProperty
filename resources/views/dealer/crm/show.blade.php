@php
    use App\Enums\PipelineStage;
    use App\Models\LeadAppointment;
    use App\Models\LeadRecommendation;
    $req = $lead->requirement;
    $stage = $assignment->pipeline_stage;
    $listingOptions = $assignment->recommendations->filter(fn ($r) => $r->property)->mapWithKeys(fn ($r) => [$r->property_id => $r->property->reference.' · '.\Illuminate\Support\Str::limit($r->property->title, 40)])
        ->union($lead->property ? [$lead->property_id => $lead->property->reference.' · '.\Illuminate\Support\Str::limit($lead->property->title, 40)] : [])->all();
@endphp
<x-layouts.dashboard :title="$assignment->buyerName().' · Lead'" section="dealer" :heading="$assignment->buyerName()" :subheading="$assignment->headline()">
    @include('dealer.crm._nav')

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
    @endif

    {{-- Summary bar --}}
    <section class="card mb-6 flex flex-wrap items-center gap-x-6 gap-y-3 p-5" aria-label="Lead summary">
        <div><p class="text-xs text-muted">Stage</p><x-status-badge :tone="$stage->tone()" :label="$stage->label()" class="mt-1" /></div>
        <form method="POST" action="{{ route('dealer.crm.leads.priority', $assignment) }}">
            @csrf @method('PATCH')
            <label for="priority" class="text-xs text-muted">Your priority</label>
            <select id="priority" name="priority" class="input mt-1 w-32 py-1.5 text-sm" data-autosubmit>
                @foreach (config('meezan.crm.priorities') as $value => $label)<option value="{{ $value }}" @selected($assignment->priority === $value)>{{ $label }}</option>@endforeach
            </select>
        </form>
        <div><p class="text-xs text-muted">Assigned</p><p class="mt-1 text-sm font-semibold">{{ $assignment->assigned_at->localTime()->format('j M Y, g:i a') }}</p></div>
        <div><p class="text-xs text-muted">Last activity</p><p class="mt-1 text-sm font-semibold">{{ ($assignment->last_activity_at ?? $assignment->assigned_at)->diffForHumans() }}</p></div>
        <div><p class="text-xs text-muted">Source</p><p class="mt-1 text-sm font-semibold">{{ $lead->property ? 'Listing enquiry' : ($req?->source === 'whatsapp' ? 'WhatsApp assistant' : 'Buyer requirement') }}</p></div>
        <div class="ml-auto text-sm">
            <p class="text-xs text-muted">Buyer contact (shared with consent)</p>
            <div class="mt-1 flex flex-wrap gap-2">
                @php
                    $phone = $req?->user?->phone ?? $req?->contact_phone ?? $lead->inquiry?->phone;
                    $email = $req?->user?->email ?? $lead->inquiry?->email;
                    $wa = $phone ? (preg_replace('/^0/', '92', preg_replace('/\D+/', '', $phone))) : null;
                @endphp
                @if ($phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="btn btn-secondary btn-sm"><x-icon name="phone" class="size-4" /> {{ $phone }}</a>@endif
                @if ($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>@endif
                @if ($email)<a href="mailto:{{ $email }}" class="btn btn-secondary btn-sm"><x-icon name="mail" class="size-4" /> Email</a>@endif
                @unless ($phone || $email)<span class="text-muted">Not shared</span>@endunless
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            {{-- Stage --}}
            <section class="card card-body" aria-labelledby="stage-title">
                <h2 id="stage-title" class="font-bold">Move this lead</h2>
                @if ($stages)
                    <form method="POST" action="{{ route('dealer.crm.leads.stage', $assignment) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                        @csrf @method('PATCH')
                        <x-field name="stage" type="select" label="New stage" required placeholder="Choose…" :options="collect($stages)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
                        <x-field name="reason" type="select" label="Reason (required for Lost)" placeholder="—" :options="config('meezan.crm.lost_reasons')" />
                        <x-field name="note" type="textarea" label="Note (required for Invalid or Duplicate)" rows="2" maxlength="500" class="sm:col-span-2" />
                        @if (in_array(PipelineStage::Won, $stages, true))
                            <details class="rounded-lg border border-line p-3 sm:col-span-2">
                                <summary class="cursor-pointer text-sm font-semibold">Won details (optional)</summary>
                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <x-field name="transaction_type" type="select" label="Transaction" placeholder="—" :options="['sale' => 'Sale', 'rent' => 'Rental']" />
                                    <x-field name="property_id" type="select" label="Property" placeholder="Not recorded" :options="$listingOptions" />
                                    <x-field name="value" type="number" label="Value (PKR)" min="0" step="1" />
                                </div>
                                <p class="mt-2 text-xs text-muted">Recorded as you report it. It is not verified and is never counted as Meezan Property revenue.</p>
                            </details>
                        @endif
                        <div class="sm:col-span-2"><button class="btn btn-primary btn-sm">Update stage</button></div>
                    </form>
                @endif
                @if ($assignment->outcome_reason || $assignment->outcome_note)
                    <p class="mt-3 text-sm text-muted">Outcome: {{ config('meezan.crm.lost_reasons.'.$assignment->outcome_reason, ucfirst((string) $assignment->outcome_reason)) }}{{ $assignment->outcome_note ? ' — '.$assignment->outcome_note : '' }}</p>
                @endif
                @if ($stage === PipelineStage::Won && ($assignment->won_transaction_type || $assignment->wonProperty || $assignment->won_value))
                    <p class="mt-2 text-sm text-muted">Won: {{ collect([$assignment->won_transaction_type ? ucfirst($assignment->won_transaction_type) : null, $assignment->wonProperty?->reference, $assignment->won_value ? 'PKR '.number_format((float) $assignment->won_value).' (dealer-reported)' : null])->filter()->implode(' · ') }}</p>
                @endif
            </section>

            {{-- Requirement --}}
            <section class="card card-body" aria-labelledby="req-title">
                <h2 id="req-title" class="font-bold">{{ $lead->property && ! $req ? 'Enquiry' : 'Buyer requirement' }}</h2>
                @if ($lead->property && ! $req)
                    <p class="mt-3 text-sm">About <a href="{{ route('properties.show', $lead->property) }}" class="link" target="_blank">{{ $lead->property->reference }} · {{ $lead->property->title }}</a> ({{ $lead->property->priceLabel() }})</p>
                    @if ($lead->inquiry?->message)<p class="mt-3 rounded-xl bg-page p-4 text-sm whitespace-pre-line">{{ $lead->inquiry->message }}</p>@endif
                @elseif ($req)
                    <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        @foreach ([
                            'Intent' => $req->purpose?->label(),
                            'City' => $req->city,
                            'Areas' => $req->areas()->pluck('name')->implode(', ') ?: 'Any',
                            'Property type' => $req->type?->label() ?? 'Any',
                            'Size' => $req->min_area_sqft ? 'From '.round($req->min_area_sqft / config('meezan.marla_sqft')).' Marla' : null,
                            'Budget' => $req->max_price ? 'Up to '.\App\Support\Format::price($req->max_price).(($req->preferences['budget_approx'] ?? false) ? ' (approx.)' : '') : null,
                            'Bedrooms' => $req->bedrooms ? $req->bedrooms.'+' : null,
                            'Bathrooms' => $req->preferences['bathrooms'] ?? null ? $req->preferences['bathrooms'].'+' : null,
                        ] as $label => $value)
                            @if ($value)<div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>@endif
                        @endforeach
                    </dl>
                    @if ($req->notes)<p class="mt-4 rounded-xl bg-page p-4 text-sm">{{ $req->notes }}</p>@endif
                @endif
            </section>

            {{-- Matches and recommendations --}}
            <section class="card card-body" aria-labelledby="match-title">
                <h2 id="match-title" class="font-bold">Matching listings</h2>
                <p class="mt-1 text-xs text-muted">Live listings on Meezan Property that fit the requirement, from the same matching the buyer’s assistant uses.</p>
                @forelse ($matches as $match)
                    <div class="mt-3 flex flex-wrap items-center gap-3 rounded-xl border border-line p-3 text-sm">
                        <span class="min-w-0 flex-1">
                            <a href="{{ route('properties.show', $match->property) }}" target="_blank" class="font-semibold hover:text-brand-700">{{ $match->property->title }}</a>
                            <span class="block text-xs text-muted">{{ $match->property->reference }} · {{ $match->property->priceLabel() }} · {{ $match->label() }}</span>
                        </span>
                        <form method="POST" action="{{ route('dealer.crm.recommendations.store', $assignment) }}">@csrf <input type="hidden" name="property_id" value="{{ $match->property->id }}"><button class="btn btn-secondary btn-sm">Recommend</button></form>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-muted">No live listings match right now.</p>
                @endforelse
                <form method="POST" action="{{ route('dealer.crm.recommendations.store', $assignment) }}" class="mt-4 flex flex-wrap items-end gap-2">
                    @csrf
                    <x-field name="reference" label="Recommend by listing reference" placeholder="e.g. MP-000123" maxlength="30" class="w-56" />
                    <button class="btn btn-secondary btn-sm">Recommend</button>
                </form>

                @if ($assignment->recommendations->isNotEmpty())
                    <h3 class="mt-6 text-sm font-bold">Recommended to this buyer</h3>
                    <ul class="mt-2 divide-y divide-line text-sm">
                        @foreach ($assignment->recommendations as $rec)
                            <li class="flex flex-wrap items-center gap-3 py-2.5">
                                <span class="min-w-0 flex-1">
                                    @if ($rec->property)<a href="{{ route('properties.show', $rec->property) }}" target="_blank" class="font-semibold hover:text-brand-700">{{ $rec->property->title }}</a>
                                    <span class="block text-xs text-muted">{{ $rec->property->reference }} · {{ $rec->property->priceLabel() }} · recommended {{ $rec->created_at->localTime()->format('j M') }}</span>@else<span class="text-muted">Listing removed</span>@endif
                                </span>
                                <form method="POST" action="{{ route('dealer.crm.recommendations.update', [$assignment, $rec->id]) }}">
                                    @csrf @method('PATCH')
                                    <label class="sr-only" for="rec-{{ $rec->id }}">Buyer response</label>
                                    <select id="rec-{{ $rec->id }}" name="status" class="input w-40 py-1.5 text-xs" data-autosubmit>
                                        @foreach (LeadRecommendation::STATUSES as $value => $label)<option value="{{ $value }}" @selected($rec->status === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-muted">Record the buyer’s response only when they have told you.</p>
                @endif
            </section>

            {{-- Timeline --}}
            <section class="card card-body" aria-labelledby="tl-title">
                <h2 id="tl-title" class="font-bold">Activity</h2>
                <ol class="mt-4 space-y-3 border-l-2 border-line pl-5 text-sm">
                    @foreach ($timeline as $entry)
                        <li class="relative">
                            <span class="absolute top-1.5 -left-[1.6rem] size-2.5 rounded-full {{ $entry->type === 'stage_changed' ? 'bg-brand-600' : 'bg-gold-500' }}"></span>
                            <p class="font-semibold">{{ $entry->label() }}
                                @if ($entry->type === 'stage_changed')<span class="font-normal text-muted">: {{ PipelineStage::tryFrom($entry->properties['from'] ?? '')?->label() }} → {{ PipelineStage::tryFrom($entry->properties['to'] ?? '')?->label() }}{{ isset($entry->properties['reason']) ? ' ('.config('meezan.crm.lost_reasons.'.$entry->properties['reason'], $entry->properties['reason']).')' : '' }}</span>@endif
                                @if ($entry->type === 'priority_changed')<span class="font-normal text-muted">: {{ config('meezan.crm.priorities.'.($entry->properties['to'] ?? '')) }}</span>@endif
                                @if ($entry->type === 'property_recommended')<span class="font-normal text-muted">: {{ $entry->properties['reference'] ?? '' }}</span>@endif
                            </p>
                            <p class="text-xs text-muted">{{ $entry->created_at->localTime()->format('j M Y, g:i a') }} · {{ $entry->actor?->id === auth()->id() ? 'You' : ($entry->actor ? 'Meezan Property staff' : 'Automatic') }}</p>
                        </li>
                    @endforeach
                    <li class="relative">
                        <span class="absolute top-1.5 -left-[1.6rem] size-2.5 rounded-full bg-zinc-400"></span>
                        <p class="font-semibold">Lead assigned to you</p>
                        <p class="text-xs text-muted">{{ $assignment->assigned_at->localTime()->format('j M Y, g:i a') }}{{ $assignment->reason ? ' · '.$assignment->reason : '' }}</p>
                    </li>
                </ol>
            </section>
        </div>

        <aside class="min-w-0 space-y-6">
            {{-- Notes --}}
            <section class="card card-body" aria-labelledby="notes-title">
                <h2 id="notes-title" class="font-bold">Private notes</h2>
                <p class="mt-1 text-xs text-muted">Only you can see these. Never shown to the buyer or other dealers.</p>
                <form method="POST" action="{{ route('dealer.crm.notes.store', $assignment) }}" class="mt-3 space-y-2">
                    @csrf
                    <label for="note-body" class="sr-only">New note</label>
                    <textarea id="note-body" name="body" rows="2" maxlength="2000" class="input" required placeholder="e.g. Budget flexible; best to call in the evening"></textarea>
                    <button class="btn btn-primary btn-sm">Add note</button>
                </form>
                <ul class="mt-4 space-y-3">
                    @foreach ($assignment->notes as $note)
                        <li class="rounded-xl bg-page p-3 text-sm">
                            <p class="whitespace-pre-line">{{ $note->body }}</p>
                            <p class="mt-1 text-xs text-muted">{{ $note->created_at->localTime()->format('j M Y, g:i a') }}{{ $note->edited_at ? ' · edited '.$note->edited_at->diffForHumans() : '' }}</p>
                            @if ($note->author_id === auth()->id())
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs font-semibold text-brand-700">Edit</summary>
                                    <form method="POST" action="{{ route('dealer.crm.notes.update', [$assignment, $note->id]) }}" class="mt-2 space-y-2">
                                        @csrf @method('PATCH')
                                        <textarea name="body" rows="2" maxlength="2000" class="input" required aria-label="Edit note">{{ $note->body }}</textarea>
                                        <button class="btn btn-secondary btn-sm">Save</button>
                                    </form>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Follow-ups --}}
            <section class="card card-body" aria-labelledby="fu-title">
                <h2 id="fu-title" class="font-bold">Follow-ups</h2>
                <form method="POST" action="{{ route('dealer.crm.follow-ups.store', $assignment) }}" class="mt-3 grid grid-cols-2 gap-2">
                    @csrf
                    <x-field name="type" type="select" label="Type" :options="config('meezan.crm.follow_up_types')" required />
                    <x-field name="priority" type="select" label="Priority" :options="config('meezan.crm.priorities')" value="normal" />
                    <x-field name="due_at" type="datetime-local" label="Due" :value="now(config('meezan.timezone'))->addDay()->setTime(11, 0)->format('Y-m-d\TH:i')" required class="col-span-2" />
                    <x-field name="notes" label="What to do" maxlength="1000" class="col-span-2" placeholder="e.g. Share two DHA Phase 6 options" />
                    <div class="col-span-2"><button class="btn btn-primary btn-sm">Schedule follow-up</button></div>
                </form>
                <ul class="mt-4 divide-y divide-line text-sm">
                    @foreach ($assignment->followUps as $followUp)
                        <li class="flex flex-wrap items-center gap-2 py-2.5">
                            <span class="min-w-0 flex-1"><span class="font-semibold">{{ $followUp->typeLabel() }}</span> <span class="text-muted">· {{ $followUp->due_at->localTime()->format('j M, g:i a') }}</span>
                                @if ($followUp->notes)<span class="block text-xs text-muted">{{ $followUp->notes }}</span>@endif</span>
                            <x-status-badge :tone="$followUp->statusTone()" :label="ucfirst($followUp->displayStatus())" />
                            @if ($followUp->status === 'pending')
                                <form method="POST" action="{{ route('dealer.crm.follow-ups.update', [$assignment, $followUp->id]) }}" class="flex gap-1">
                                    @csrf @method('PATCH')
                                    <button name="outcome" value="completed" class="btn btn-secondary btn-sm px-2" title="Mark done" aria-label="Mark follow-up done"><x-icon name="check" class="size-4" /></button>
                                    <button name="outcome" value="cancelled" class="btn btn-ghost btn-sm px-2" title="Cancel" aria-label="Cancel follow-up"><x-icon name="x" class="size-4" /></button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Appointments --}}
            <section class="card card-body" aria-labelledby="appt-title">
                <h2 id="appt-title" class="font-bold">Viewings and appointments</h2>
                <details class="mt-3" @if ($errors->has('starts_at')) open @endif>
                    <summary class="btn btn-secondary btn-sm cursor-pointer list-none">Schedule</summary>
                    <form method="POST" action="{{ route('dealer.crm.appointments.store', $assignment) }}" class="mt-3 grid grid-cols-2 gap-2">
                        @csrf
                        <x-field name="type" type="select" label="Type" :options="config('meezan.crm.appointment_types')" required />
                        <x-field name="duration_minutes" type="number" label="Minutes" value="30" min="5" max="480" />
                        <x-field name="starts_at" type="datetime-local" label="Date and time" :value="now(config('meezan.timezone'))->addDay()->setTime(16, 0)->format('Y-m-d\TH:i')" required class="col-span-2" />
                        <x-field name="property_id" type="select" label="Property (optional)" placeholder="None" :options="$listingOptions" class="col-span-2" />
                        <x-field name="location" label="Location" maxlength="255" class="col-span-2" placeholder="e.g. Site office, DHA Phase 6" />
                        <x-field name="notes" label="Notes" maxlength="1000" class="col-span-2" />
                        <div class="col-span-2"><button class="btn btn-primary btn-sm">Save appointment</button></div>
                    </form>
                    <p class="mt-2 text-xs text-muted">Recommend a listing first to choose it here. Scheduling a viewing moves a contacted or qualified lead to “Viewing scheduled”.</p>
                </details>
                <ul class="mt-4 divide-y divide-line text-sm">
                    @foreach ($assignment->appointments as $appointment)
                        <li class="py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="min-w-0 flex-1"><span class="font-semibold">{{ $appointment->typeLabel() }}</span> <span class="text-muted">· {{ $appointment->starts_at->localTime()->format('D j M, g:i a') }} · {{ $appointment->duration_minutes }} min</span>
                                    <span class="block text-xs text-muted">{{ collect([$appointment->property?->reference, $appointment->location, $appointment->notes])->filter()->implode(' · ') }}</span></span>
                                <x-status-badge :tone="$appointment->statusTone()" :label="LeadAppointment::STATUSES[$appointment->status]" />
                            </div>
                            @if ($appointment->status === 'scheduled')
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach (['completed' => 'Completed', 'no_show' => 'No show', 'cancelled' => 'Cancel'] as $value => $label)
                                        <form method="POST" action="{{ route('dealer.crm.appointments.update', [$assignment, $appointment->id]) }}">@csrf @method('PATCH') <input type="hidden" name="status" value="{{ $value }}"><button class="btn {{ $value === 'cancelled' ? 'btn-ghost' : 'btn-secondary' }} btn-sm">{{ $label }}</button></form>
                                    @endforeach
                                    <details class="w-full">
                                        <summary class="cursor-pointer text-xs font-semibold text-brand-700">Reschedule</summary>
                                        <form method="POST" action="{{ route('dealer.crm.appointments.update', [$assignment, $appointment->id]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="type" value="{{ $appointment->type }}">
                                            <label class="text-xs">New time <input type="datetime-local" name="starts_at" class="input mt-1 py-1.5" value="{{ \App\Support\LocalTime::toInput($appointment->starts_at) }}" required></label>
                                            <button class="btn btn-secondary btn-sm">Save</button>
                                        </form>
                                    </details>
                                </div>
                            @elseif ($appointment->status_reason)
                                <p class="mt-1 text-xs text-muted">{{ $appointment->status_reason }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
