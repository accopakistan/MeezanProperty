<x-layouts.dashboard title="AI Command Center" section="admin" heading="AI Command Center" subheading="The Central AI Brain and its 16 agents. Agents read platform data through permitted tools, report what is missing, and ask for approval before any change.">
    @include('admin.ai.partials.nav')

    @unless ($llm)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            <strong>Rules mode.</strong> No language model is configured, so agents write reports from data only (no model wording or classification). Reports, tools, approvals and limits all work.
            <a href="{{ route('admin.ai.providers') }}" class="link">Add an API key in AI Providers</a>
        </div>
    @endunless

    @if (\App\Services\Agents\AiProvider::controls()['emergency_stop'])
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="status"><strong>Emergency stop is on.</strong> No paid AI requests are being made. <a href="{{ route('admin.ai.usage') }}" class="link">Usage &amp; Cost</a></div>
    @endif
    @php
        $states = $readiness->countBy('state');
    @endphp
    <section class="card card-body mb-6" aria-label="Central AI status">
        <h2 class="text-base font-bold">Central AI status</h2>
        <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-[13px] text-muted">Language model</dt><dd class="font-semibold">{{ $llm ? 'Configured' : 'Not configured (rules mode)' }}</dd></div>
            <div><dt class="text-[13px] text-muted">Google Gemini</dt><dd class="font-semibold">{{ str_replace('_', ' ', ucfirst($connectors['gemini']['status'])) }} · <a href="{{ route('admin.ai.providers') }}" class="link font-normal">Gemini Settings</a></dd></div>
            <div><dt class="text-[13px] text-muted">Agent jobs waiting</dt><dd class="font-semibold">{{ $agentJobsWaiting }} on queue "{{ config('meezan.agents.queue') }}"</dd></div>
            <div><dt class="text-[13px] text-muted">Running now</dt><dd class="font-semibold">{{ $tasksByStatus['running'] ?? 0 }} task(s)</dd></div>
        </dl>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Agents</h2>
            <p class="mt-1 text-3xl font-bold">{{ $agents->where('status', 'active')->count() }} <span class="text-base font-medium text-muted">/ {{ $agents->count() }} active</span></p>
            <p class="mt-1 text-[13px] text-muted">{{ collect(\App\Services\Agents\AgentReadiness::LABELS)->filter(fn ($l, $k) => $states[$k] ?? 0)->map(fn ($l, $k) => $l.' '.$states[$k])->implode(' · ') }}</p>
            <p class="text-[13px] text-muted">Disabled {{ $agents->whereIn('status', ['disabled', 'maintenance'])->count() }}</p>
            <a href="{{ route('admin.ai.agents.index') }}" class="link mt-1 inline-block text-[13px]">Agent Directory</a>
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Tasks (7 days)</h2>
            <p class="mt-1 text-3xl font-bold">{{ number_format($tasksByStatus->sum()) }}</p>
            <p class="mt-1 text-[13px] text-muted">{{ collect(\App\Models\AiTask::STATUSES)->filter(fn ($l, $k) => $tasksByStatus[$k] ?? 0)->map(fn ($l, $k) => $l.' '.$tasksByStatus[$k])->implode(' · ') ?: 'None yet' }}</p>
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Pending approvals</h2>
            <p class="mt-1 text-3xl font-bold">{{ $pendingApprovals }}</p>
            <a href="{{ route('admin.ai.approvals.index') }}" class="link mt-1 inline-block text-[13px]">Approval Center</a>
            <p class="mt-1 text-[13px] text-muted">Passed to staff: {{ $openTickets }} ticket(s), {{ $openStaffTasks }} task(s). <a href="{{ route('admin.ai.work') }}" class="link">Staff Work</a></p>
        </section>
        <section class="card card-body">
            <h2 class="text-sm font-semibold text-muted">Model usage today</h2>
            <p class="mt-1 text-3xl font-bold">{{ number_format($tokensToday) }} <span class="text-base font-medium text-muted">tokens</span></p>
            <p class="mt-1 text-[13px] text-muted">{{ $pricingSet ? 'Cost USD '.number_format($costToday, 4) : 'Cost not calculated: model pricing is not configured.' }}</p>
        </section>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <section class="card card-body min-w-0 xl:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-bold">Ask the AI Brain</h2>
                <a href="{{ route('admin.ai.tasks.index') }}" class="link text-[13px]">All tasks</a>
            </div>
            <form method="POST" action="{{ route('admin.ai.tasks.store') }}" class="mt-3 space-y-3">
                @csrf
                <x-field name="request" type="textarea" rows="3" label="Request" required placeholder="e.g. Prepare a business performance report for this month" />
                <div class="flex flex-wrap items-end gap-3">
                    <x-field name="agent" type="select" label="Agent" class="min-w-56" :options="['' => 'Route automatically'] + $agents->where('status', 'active')->pluck('name', 'slug')->all()" />
                    <button class="btn btn-primary">Run task</button>
                </div>
            </form>
            <h3 class="mt-6 text-sm font-bold">Recent tasks</h3>
            <ul class="mt-2 divide-y divide-line text-sm">
                @forelse ($recentTasks as $t)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('admin.ai.tasks.show', $t) }}" class="link max-w-full min-w-0 truncate">{{ $t->title }}</a>
                        <span class="flex items-center gap-2 text-[13px] text-muted">{{ $t->agent?->name }} @include('admin.ai.partials.status', ['status' => $t->status, 'label' => $t->statusLabel()])</span>
                    </li>
                @empty
                    <li class="py-2 text-muted">No tasks yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Connectors</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($connectors as $c)
                    <li class="flex items-center justify-between gap-2"><span class="min-w-0">{{ $c['label'] }}</span>@include('admin.ai.partials.status', ['status' => $c['status']])</li>
                @endforeach
            </ul>
            @if ($failed->isNotEmpty())
                <h3 class="mt-6 text-sm font-bold">Failed tasks (7 days)</h3>
                <ul class="mt-2 space-y-1 text-[13px]">
                    @foreach ($failed as $t)
                        <li><a href="{{ route('admin.ai.tasks.show', $t) }}" class="link">{{ $t->agent?->name }}</a>: {{ \Illuminate\Support\Str::limit($t->error, 90) }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.dashboard>
