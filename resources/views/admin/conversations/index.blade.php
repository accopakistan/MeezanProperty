<x-layouts.dashboard title="AI assistant" section="admin" heading="AI assistant" subheading="Website and WhatsApp conversations, saved requirements, AI-created leads and escalations. Phone numbers are masked.">
    <div @class(['mb-5 flex flex-col gap-2 rounded-2xl border p-4 text-sm sm:flex-row sm:items-center', 'border-emerald-200 bg-emerald-50' => $configured, 'border-amber-200 bg-amber-50' => ! $configured])>
        <x-icon :name="$configured ? 'check' : 'alert'" class="{{ $configured ? 'text-emerald-600' : 'text-amber-600' }}" />
        <p class="flex-1">
            <strong>WhatsApp:</strong>
            @if (! $configured)
                integration pending. Cloud API credentials are not configured, so no WhatsApp messages are received or sent. The website assistant works without them. See <a href="{{ route('admin.settings') }}" class="link">Settings</a>.
            @elseif (! $canSend)
                receiving only. Webhooks are verified and stored; replies are prepared but not sent until <code>WHATSAPP_SEND_ENABLED=true</code>.
            @else
                live. Bot replies are sent while a conversation is in bot mode.
            @endif
            <span class="text-muted">· Parser: {{ config('meezan.ai.driver') === 'llm' && filled(config('services.openai.key')) ? 'LLM with rules fallback' : 'rules engine' }}</span>
        </p>
    </div>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <a href="{{ route('admin.conversations.index', ['status' => 'escalated']) }}"><x-stat-card label="Needs a person" :value="$counts['escalated']" icon="alert" tone="gold" /></a>
        <a href="{{ route('admin.conversations.index', ['status' => 'failed']) }}"><x-stat-card label="Not understood" :value="$counts['failed']" icon="message" tone="info" /></a>
        <x-stat-card label="AI requirements" :value="$counts['requirements']" icon="target" />
        <a href="{{ route('admin.leads.index') }}"><x-stat-card label="AI leads" :value="$counts['leads']" icon="users" tone="success" /></a>
        <x-stat-card label="Failed webhooks (7 days)" :value="$counts['webhookFailures']" icon="ban" tone="info" />
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-2" role="search">
        <select name="channel" class="input w-auto py-2 text-sm" aria-label="Channel" data-autosubmit>
            <option value="">All channels</option>
            @foreach (['website' => 'Website', 'whatsapp' => 'WhatsApp'] as $value => $label)<option value="{{ $value }}" @selected(($filters['channel'] ?? null) === $value)>{{ $label }}</option>@endforeach
        </select>
        <select name="status" class="input w-auto py-2 text-sm" aria-label="Status" data-autosubmit>
            <option value="">Any status</option>
            @foreach (\App\Models\Conversation::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>@endforeach
        </select>
        <select name="mode" class="input w-auto py-2 text-sm" aria-label="Mode" data-autosubmit>
            <option value="">Bot or human</option>
            @foreach (['bot' => 'Bot', 'human' => 'Human', 'closed' => 'Closed'] as $value => $label)<option value="{{ $value }}" @selected($mode === $value)>{{ $label }}</option>@endforeach
        </select>
        <noscript><button class="btn btn-secondary btn-sm">Filter</button></noscript>
    </form>

    @if ($conversations->isEmpty())
        <x-empty-state icon="bot" title="No conversations yet">Conversations appear here when buyers use the AI Property Finder or (once configured) WhatsApp.</x-empty-state>
    @else
        <div class="card divide-y divide-line">
            @foreach ($conversations as $c)
                <a href="{{ route('admin.conversations.show', $c) }}" class="flex flex-wrap items-center gap-4 px-5 py-4 hover:bg-page">
                    <span @class(['grid size-10 place-items-center rounded-full', 'bg-emerald-50 text-emerald-700' => $c->channel === 'whatsapp', 'bg-brand-50 text-brand-700' => $c->channel !== 'whatsapp'])><x-icon :name="$c->channel === 'whatsapp' ? 'whatsapp' : 'bot'" /></span>
                    <span class="min-w-0 flex-1 text-sm">
                        <span class="block font-semibold">{{ $c->channel === 'whatsapp' ? ($c->contact_name ?? 'WhatsApp contact') : ($c->user?->name ?? 'Website visitor') }} <span class="font-normal text-muted">· {{ $c->maskedContact() }}</span></span>
                        <span class="block truncate text-xs text-muted">
                            {{ $c->requirement?->summary() ?? 'No saved requirement' }} · {{ $c->messages_count }} messages
                        </span>
                    </span>
                    @if ($c->lead)<x-status-badge tone="success" label="Lead · {{ $c->lead->status->label() }}" />@endif
                    <x-status-badge :tone="['escalated' => 'warning', 'failed' => 'danger', 'lead' => 'success', 'qualified' => 'brand', 'closed' => 'muted'][$c->status] ?? 'neutral'" :label="\App\Models\Conversation::STATUSES[$c->status] ?? $c->status" />
                    <span class="hidden text-xs text-muted sm:block">{{ $c->last_message_at?->diffForHumans() }}</span>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $conversations->links() }}</div>
    @endif
</x-layouts.dashboard>
