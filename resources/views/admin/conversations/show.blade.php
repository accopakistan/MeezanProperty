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
                                @if ($m->intent) · {{ str_replace('_', ' ', $m->intent) }} @endif
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
