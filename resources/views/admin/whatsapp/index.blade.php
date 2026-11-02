@php use App\Models\OutboundMessage; @endphp
<x-layouts.dashboard title="WhatsApp" section="admin" heading="WhatsApp control center" subheading="Integration status, message activity and automation switches. Figures are live from the database; secret values are never shown.">
    <div class="mb-5 flex flex-wrap gap-3">
        <a href="{{ route('admin.whatsapp.messages') }}" class="btn btn-secondary btn-sm">Outgoing message log</a>
        <a href="{{ route('admin.conversations.index', ['channel' => 'whatsapp']) }}" class="btn btn-secondary btn-sm">WhatsApp conversations</a>
    </div>

    @if ($provider->isTest())
        <div class="mb-6 flex gap-3 rounded-xl border-2 border-dashed border-amber-400 bg-amber-50 p-4 text-sm text-amber-900" role="note">
            <x-icon name="alert" class="size-5 shrink-0" />
            <p><strong>TEST / MOCK MODE.</strong> Messages are recorded and marked as tests; nothing is sent to any phone. {{ $switches['test_mode']['on'] ? 'Test mode is switched on below.' : 'MEEZAN_WHATSAPP_DRIVER=mock is set in .env.' }}</p>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section class="card card-body" aria-labelledby="status-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h2 id="status-title" class="font-bold">Integration status</h2>
                <x-status-badge :tone="$isLive ? 'success' : 'warning'" :label="$isLive ? 'Sending to real numbers' : 'Not sending to real numbers'" />
            </div>
            <ul class="mt-4 divide-y divide-line text-sm">
                <li class="flex items-center justify-between gap-3 py-2.5"><span>Provider</span><span class="font-semibold">{{ $provider->label() }} <span class="text-xs font-normal text-muted">(driver: {{ $driver }})</span></span></li>
                @foreach ($checks as $check)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="flex items-center gap-2"><x-icon :name="$check['ok'] ? 'check' : 'x'" @class(['size-4 shrink-0', 'text-emerald-600' => $check['ok'], 'text-red-500' => ! $check['ok']]) />{{ $check['label'] }}</span>
                        <span class="text-muted">{{ $check['detail'] }}</span>
                    </li>
                @endforeach
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <span class="flex items-center gap-2"><x-icon :name="$receiving ? 'check' : 'x'" @class(['size-4', 'text-emerald-600' => $receiving, 'text-red-500' => ! $receiving]) />Incoming webhooks accepted</span>
                    <span class="text-muted">{{ $receiving ? 'Yes (signature checked)' : 'No: needs all four Meta values' }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <span class="flex items-center gap-2"><x-icon :name="$publicUrl ? 'info' : 'x'" @class(['size-4', 'text-sky-600' => $publicUrl, 'text-red-500' => ! $publicUrl]) />Webhook publicly reachable</span>
                    <span class="text-right text-muted">{{ $publicUrl ? 'HTTPS address set (not tested from outside)' : 'No: this is a local address' }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <span class="flex items-center gap-2"><x-icon :name="$verifiedAt ? 'check' : 'x'" @class(['size-4', 'text-emerald-600' => $verifiedAt, 'text-red-500' => ! $verifiedAt]) />Meta verified the webhook</span>
                    <span class="text-muted">{{ $verifiedAt ? \Illuminate\Support\Carbon::parse($verifiedAt)->format('j M Y, g:i a') : 'Never' }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <span class="flex items-center gap-2"><x-icon :name="($lastTest['ok'] ?? false) ? 'check' : 'x'" @class(['size-4', 'text-emerald-600' => $lastTest['ok'] ?? false, 'text-red-500' => ! ($lastTest['ok'] ?? false)]) />Connection test</span>
                    <span class="text-right text-muted">{{ $lastTest ? $lastTest['detail'].' ('.\Illuminate\Support\Carbon::parse($lastTest['at'])->format('j M, g:i a').')' : 'Not run' }}</span>
                </li>
                <li class="flex items-center justify-between gap-3 py-2.5"><span>Last webhook received</span><span class="text-muted">{{ $lastWebhook ? $lastWebhook->created_at->format('j M Y, g:i a').' · '.$lastWebhook->status : 'None' }}</span></li>
                <li class="flex items-center justify-between gap-3 py-2.5"><span>Queue</span><span class="text-right text-muted">{{ $queue['connection'] }}{{ $queue['pending'] !== null ? ' · '.$queue['pending'].' waiting' : '' }} · {{ $queue['failed'] }} failed jobs</span></li>
            </ul>
            <div class="mt-4 rounded-lg bg-page p-3 text-xs">
                <p class="font-semibold">Callback URL to enter in the Meta App dashboard (WhatsApp → Configuration)</p>
                <p class="mt-1 font-mono break-all">{{ $webhookUrl }}</p>
                <p class="mt-1 text-muted">Meta can only reach a public HTTPS address. On localhost this URL cannot receive real WhatsApp messages; use the test console below.</p>
            </div>
            <form method="POST" action="{{ route('admin.whatsapp.test') }}" class="mt-4">@csrf <button class="btn btn-secondary btn-sm">Test connection</button></form>
            @if ($queue['connection'] !== 'sync' && $queue['pending'])
                <p class="mt-3 text-xs text-amber-700">Jobs are waiting. Start a worker: <code>php artisan queue:work --queue=whatsapp,default</code></p>
            @endif
        </section>

        <aside class="space-y-6">
            <section class="card card-body" aria-labelledby="switches-title">
                <h2 id="switches-title" class="font-bold">Automation</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    @foreach ($switches as $key => $switch)
                        <li class="flex items-center justify-between gap-3">
                            <span><span class="font-semibold">{{ $switch['label'] }}</span>
                                <span class="block text-xs text-muted">{{ ['automation' => 'Off: no automatic messages (staff replies still work).', 'auto_reply' => 'Off: messages are kept and held for staff.', 'test_mode' => 'On: everything goes to the mock provider.'][$key] }}</span></span>
                            <form method="POST" action="{{ route('admin.whatsapp.settings') }}" @if ($switch['on'] && $key !== 'test_mode') data-confirm="Turn {{ strtolower($switch['label']) }} off?" @endif>
                                @csrf @method('PATCH')
                                <input type="hidden" name="switch" value="{{ $key }}"><input type="hidden" name="on" value="{{ $switch['on'] ? 0 : 1 }}">
                                <button @class(['btn btn-sm min-w-16', 'btn-primary' => $switch['on'], 'btn-secondary' => ! $switch['on']]) aria-pressed="{{ $switch['on'] ? 'true' : 'false' }}">{{ $switch['on'] ? 'On' : 'Off' }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="card card-body" aria-labelledby="counts-title">
                <h2 id="counts-title" class="font-bold">Activity</h2>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    @foreach ([
                        'Conversations' => $counts['conversations'], 'Active (24 h)' => $counts['active'],
                        'Incoming messages' => $counts['incoming'], 'Outgoing messages' => $counts['outgoing'],
                        'Failed to send' => $counts['failed'], 'Awaiting retry' => $counts['retrying'],
                        'Blocked by policy' => $counts['blocked'], 'Held for staff' => $counts['held'],
                        'Processing failed' => $counts['processingFailed'], 'Qualified WhatsApp leads' => $counts['leads'],
                        'Test messages' => $counts['test'], 'Webhook failures (7 d)' => $counts['webhookFailures'],
                    ] as $label => $value)
                        <div class="rounded-lg bg-page p-3"><dt class="text-xs text-muted">{{ $label }}</dt><dd class="text-xl font-bold tabular-nums">{{ number_format($value) }}</dd></div>
                    @endforeach
                </dl>
            </section>
        </aside>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card overflow-hidden" aria-labelledby="attention-title">
            <h2 id="attention-title" class="card-body pb-0 font-bold">Failed and blocked messages</h2>
            @if ($attention->isEmpty())
                <p class="card-body text-sm text-muted">None.</p>
            @else
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($attention as $message)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <span class="min-w-0">
                                <span class="font-semibold">#{{ $message->id }} · {{ str_replace('_', ' ', $message->purpose) }}</span> <span class="text-muted">to {{ $message->maskedRecipient() }}</span>
                                <span class="block text-xs text-muted">{{ $message->status === 'blocked' ? (OutboundMessage::BLOCK_REASONS[$message->blocked_reason] ?? $message->blocked_reason) : $message->last_error }} · {{ $message->created_at->format('j M, g:i a') }}</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <x-status-badge :tone="$message->statusTone()" :label="ucfirst($message->status)" />
                                @if ($message->isRetryable())
                                    <form method="POST" action="{{ route('admin.whatsapp.retry', $message) }}">@csrf <button class="btn btn-secondary btn-sm">Retry</button></form>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card overflow-hidden" aria-labelledby="held-title">
            <h2 id="held-title" class="card-body pb-0 font-bold">Incoming messages not answered automatically</h2>
            @if ($heldMessages->isEmpty())
                <p class="card-body text-sm text-muted">None.</p>
            @else
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($heldMessages as $message)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <span class="min-w-0">
                                <a href="{{ route('admin.conversations.show', $message->conversation_id) }}" class="font-semibold hover:text-brand-700">{{ $message->conversation?->maskedContact() }}</a>
                                <span class="block truncate text-xs text-muted">{{ \Illuminate\Support\Str::limit($message->body ?? '['.$message->type.']', 70) }}</span>
                            </span>
                            <x-status-badge :tone="$message->processing_status === 'failed' ? 'danger' : 'warning'" :label="['held' => 'Held (auto-reply off)', 'rate_limited' => 'Too many messages', 'failed' => 'Processing failed'][$message->processing_status] ?? $message->processing_status" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    @if ($simulator)
        <section class="mt-6 rounded-xl border-2 border-dashed border-amber-400 bg-amber-50/60 p-5" aria-labelledby="sim-title">
            <h2 id="sim-title" class="font-bold text-amber-900">TEST / MOCK console</h2>
            <p class="mt-1 text-sm text-amber-900">Simulates WhatsApp locally: messages go through the real webhook processor, assistant, lead routing and policy. Nothing is sent to any phone, and outgoing messages are marked as tests. Available only on local or testing environments with the mock provider.</p>
            @error('from')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                <form method="POST" action="{{ route('admin.whatsapp.simulate') }}" class="space-y-3">
                    @csrf <input type="hidden" name="action" value="message">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="from" label="Test buyer number" :value="old('from', $lastSimulated['from'] ?? '920000000001')" maxlength="12" />
                        <x-field name="name" label="Name" :value="old('name', 'Test buyer')" maxlength="60" />
                    </div>
                    <x-field name="text" type="textarea" label="Incoming message" rows="2" maxlength="1000" placeholder="Mujhe DHA Lahore mein 10 marla ghar chahiye" />
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="process_now" value="1" class="checkbox" checked> Process now (without waiting for the queue worker)</label>
                    <button class="btn btn-primary btn-sm">Simulate incoming message</button>
                </form>
                <div class="space-y-3 text-sm">
                    <form method="POST" action="{{ route('admin.whatsapp.simulate') }}">@csrf <input type="hidden" name="action" value="duplicate"><input type="hidden" name="process_now" value="1">
                        <button class="btn btn-secondary btn-sm" @disabled(! $lastSimulated)>Re-deliver the last message (duplicate webhook)</button></form>
                    <form method="POST" action="{{ route('admin.whatsapp.simulate') }}" class="flex flex-wrap gap-2">@csrf
                        <button name="action" value="fail_next" class="btn btn-secondary btn-sm">Make the next send fail</button>
                        <button name="action" value="fail_next_temporary" class="btn btn-secondary btn-sm">Next send: temporary error</button></form>
                    <form method="POST" action="{{ route('admin.whatsapp.simulate') }}" class="flex flex-wrap items-end gap-2">@csrf <input type="hidden" name="action" value="status">
                        <x-field name="outbound_id" type="number" label="Test message #" min="1" class="w-32" />
                        <x-field name="delivery" type="select" label="Status update" :options="['delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed']" class="w-36" />
                        <button class="btn btn-secondary btn-sm">Simulate status</button></form>
                </div>
            </div>
        </section>
    @endif
</x-layouts.dashboard>
