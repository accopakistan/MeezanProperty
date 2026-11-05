@php
    $who = $conversation->channel === 'whatsapp' ? ($conversation->contact_name ?? 'WhatsApp contact') : ($conversation->user?->name ?? 'Website visitor');
@endphp
<x-layouts.dashboard :title="'Conversation #'.$conversation->id" section="admin" :heading="$who" :subheading="ucfirst($conversation->channel).' · '.(\App\Models\Conversation::STATUSES[$conversation->status] ?? $conversation->status).' · '.($revealed ?? $conversation->maskedContact())">
    <x-slot:actions>
        <div class="flex flex-wrap gap-2">
            @if ($conversation->escalated_at)
                <form method="POST" action="{{ route('admin.conversations.resolve', $conversation) }}">@csrf<button class="btn btn-primary btn-sm">Mark handled</button></form>
            @endif
            @if ($conversation->channel === 'whatsapp' && ! $revealed)
                <form method="POST" action="{{ route('admin.conversations.reveal', $conversation) }}">@csrf<button class="btn btn-secondary btn-sm" title="Recorded in the audit log">Show number</button></form>
            @endif
            <form method="POST" action="{{ route('admin.conversations.mode', $conversation) }}" class="flex gap-2">
                @csrf @method('PATCH')
                @foreach (['bot' => 'Return to bot', 'human' => 'Take over (human)', 'closed' => 'Close'] as $value => $label)
                    @if ($conversation->mode !== $value && ($conversation->channel === 'whatsapp' || $value === 'closed'))
                        <button name="mode" value="{{ $value }}" class="btn btn-secondary btn-sm">{{ $label }}</button>
                    @endif
                @endforeach
            </form>
        </div>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="card">
            <div class="space-y-3 bg-page/60 p-5">
                @forelse ($messages as $m)
                    @php $assistant = $m->speaker() === 'assistant'; @endphp
                    <div @class(['flex', 'justify-end' => $assistant])>
                        <div @class(['max-w-[80%] rounded-2xl px-4 py-3 text-sm', 'rounded-br-md bg-brand-600 text-white' => $assistant, 'rounded-bl-md border border-line bg-white' => ! $assistant])>
                            <p class="whitespace-pre-line" dir="auto">{{ $m->body ?? '['.$m->type.' message]' }}</p>
                            <p @class(['mt-1 text-[11px]', 'text-white/70' => $assistant, 'text-muted' => ! $assistant])>
                                {{ $m->created_at->format('j M, g:i a') }}
                                @if ($m->author() === 'staff') · Staff{{ $m->staff ? ' ('.$m->staff->name.')' : '' }} @elseif ($m->author() === 'ai' && $conversation->channel === 'whatsapp') · AI{{ $m->agent_slug ? ' ('.\App\Services\Agents\AgentCatalog::name($m->agent_slug).')' : '' }} @endif
                                @if ($m->intent) · {{ str_replace('_', ' ', $m->intent) }} @endif
                                @if ($assistant && $conversation->channel === 'whatsapp' && isset($outbound[$m->id])) · {{ $outbound[$m->id]->deliveryLabel() }} @endif
                                @if ($assistant && $conversation->channel === 'whatsapp') · {{ $m->delivery_status === 'not_sent' ? 'not sent (blocked by policy or sending disabled)' : ($m->delivery_status ?? 'not sent') }} @endif
                                @if (! $assistant && $m->processing_status && $m->processing_status !== 'processed') · {{ ['held' => 'held (auto-reply off)', 'rate_limited' => 'not answered (too many messages)', 'duplicate' => 'not answered (repeat)', 'skipped' => 'for staff', 'failed' => 'processing failed', 'queued' => 'waiting for queue', 'processing' => 'processing'][$m->processing_status] ?? $m->processing_status }} @endif
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-muted">No messages.</p>
                @endforelse
            </div>
            <p class="border-t border-line px-5 py-4 text-[13px] text-muted">
                @if ($conversation->channel === 'whatsapp')
                    <form method="POST" action="{{ route('admin.conversations.reply', $conversation) }}" class="space-y-2">
                        @csrf
                        <label for="reply-body" class="label">Reply as Meezan Property</label>
                        <textarea id="reply-body" name="body" rows="3" maxlength="1000" class="input" required placeholder="Type a reply…">{{ old('body') }}</textarea>
                        @error('body')<span class="block text-red-600">{{ $message }}</span>@enderror
                        <span class="flex flex-wrap items-center justify-between gap-2">
                            <span>{{ $canSend ? 'Sent through '.app(\App\Services\WhatsApp\WhatsAppClient::class)->provider()->label().'. Only within 24 hours of the buyer’s last message; never after STOP.' : 'Sending is not enabled: the reply is saved but not sent.' }}</span>
                            <button class="btn btn-primary btn-sm">Send reply</button>
                        </span>
                    </form>
                @else
                    Website conversation. Showing the latest {{ $messages->count() }} messages.
                @endif
            </p>
        </div>

        <aside class="space-y-4">
            @if ($conversation->escalated_at)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    <p class="font-semibold">Escalated {{ $conversation->escalated_at->diffForHumans() }}</p>
                    <p class="text-[13px]">{{ \App\Notifications\AssistantNeedsAttention::REASONS[$conversation->escalation_reason] ?? $conversation->escalation_reason }}</p>
                </div>
            @endif
            @if ($conversation->channel === 'whatsapp')
                <section class="card card-body text-sm" aria-labelledby="handoff-title">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="handoff-title" class="text-base font-bold">Human handoff</h2>
                        <x-status-badge :tone="$conversation->hasOpenHandoff() ? 'warning' : 'neutral'" :label="\App\Models\Conversation::HANDOFF_STATUSES[$conversation->handoff_status] ?? 'None'" />
                    </div>
                    <dl class="mt-3 space-y-1.5">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Who answers now</dt><dd class="text-right font-medium">{{ $conversation->mode === 'bot' ? 'AI' : ($conversation->mode === 'human' ? 'Staff' : 'Nobody (closed)') }}</dd></div>
                        @if ($conversation->assigned_agent)<div class="flex justify-between gap-3"><dt class="text-muted">Last AI agent</dt><dd class="text-right font-medium">{{ \App\Services\Agents\AgentCatalog::name($conversation->assigned_agent) }}</dd></div>@endif
                        @if ($conversation->assignee)<div class="flex justify-between gap-3"><dt class="text-muted">Assigned to</dt><dd class="text-right font-medium">{{ $conversation->assignee->name }}</dd></div>@endif
                        @if ($conversation->handoff_reason)<div class="flex justify-between gap-3"><dt class="text-muted">Reason</dt><dd class="text-right font-medium">{{ \App\Notifications\AssistantNeedsAttention::REASONS[$conversation->handoff_reason] ?? str_replace('_', ' ', $conversation->handoff_reason) }}</dd></div>@endif
                        @if ($conversation->handoff_requested_at)<div class="flex justify-between gap-3"><dt class="text-muted">Requested</dt><dd class="text-right">{{ $conversation->handoff_requested_at->format('j M, g:i a') }}</dd></div>@endif
                        @if ($conversation->handoff_resolved_at)<div class="flex justify-between gap-3"><dt class="text-muted">Resolved</dt><dd class="text-right">{{ $conversation->handoff_resolved_at->format('j M, g:i a') }}</dd></div>@endif
                    </dl>
                    <form method="POST" action="{{ route('admin.conversations.handoff', $conversation) }}" class="mt-4 flex items-end gap-2">
                        @csrf <input type="hidden" name="action" value="assign">
                        <span class="flex-1">
                            <label for="handoff-staff" class="label">Assign to</label>
                            <select id="handoff-staff" name="staff_id" class="input py-2 text-sm">
                                @foreach ($staff as $person)<option value="{{ $person->id }}" @selected($conversation->assigned_to === $person->id)>{{ $person->name }}</option>@endforeach
                            </select>
                        </span>
                        <button class="btn btn-secondary btn-sm">Assign</button>
                    </form>
                    @if ($conversation->hasOpenHandoff())
                        <form method="POST" action="{{ route('admin.conversations.handoff', $conversation) }}" class="mt-3 flex flex-wrap gap-2">
                            @csrf
                            <button name="action" value="resolve" class="btn btn-secondary btn-sm">Resolve</button>
                            <button name="action" value="resume" class="btn btn-primary btn-sm">Resolve and resume AI</button>
                        </form>
                    @endif
                </section>
                <section class="card card-body text-sm" aria-labelledby="note-title">
                    <h2 id="note-title" class="text-base font-bold">Internal note</h2>
                    <p class="mt-1 text-xs text-muted">Staff only. Never sent to the customer.</p>
                    <form method="POST" action="{{ route('admin.conversations.note', $conversation) }}" class="mt-2 space-y-2">
                        @csrf @method('PATCH')
                        <textarea name="internal_note" rows="3" maxlength="2000" class="input" aria-label="Internal note">{{ old('internal_note', $conversation->internal_note) }}</textarea>
                        <button class="btn btn-secondary btn-sm">Save note</button>
                    </form>
                </section>
            @endif
            <section class="card card-body">
                <h2 class="text-base font-bold">What the assistant understood</h2>
                @php $summary = (new \App\Services\Assistant\RequirementState($conversation->state))->summary(); @endphp
                @if ($summary)
                    <dl class="mt-3 space-y-1.5 text-sm">
                        @foreach ($summary as $label => $value)
                            <div class="flex justify-between gap-3"><dt class="text-muted">{{ $label }}</dt><dd class="text-right font-medium">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-2 text-sm text-muted">Nothing yet.</p>
                @endif
            </section>
            <section class="card card-body text-sm">
                <h2 class="text-base font-bold">Requirement &amp; lead</h2>
                @if ($conversation->requirement)
                    <p class="mt-2">{{ $conversation->requirement->summary() }}</p>
                    <p class="mt-1 text-[13px] text-muted">Last match: {{ $conversation->requirement->match_status ?? 'not run' }}{{ $conversation->requirement->last_match_count !== null ? ' ('.$conversation->requirement->last_match_count.')' : '' }}</p>
                @else
                    <p class="mt-2 text-muted">No requirement saved.</p>
                @endif
                @if ($conversation->lead)
                    <p class="mt-3"><a href="{{ route('admin.leads.show', $conversation->lead) }}" class="link">Lead #{{ $conversation->lead->id }}</a> · {{ $conversation->lead->status->label() }}</p>
                    <ul class="mt-1 text-[13px] text-muted">
                        @foreach ($conversation->lead->assignments as $a)
                            <li>{{ $a->dealer?->dealerProfile?->agency_name ?? $a->dealer?->name }} · {{ $a->status }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
