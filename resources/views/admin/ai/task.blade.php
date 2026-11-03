<x-layouts.dashboard :title="'AI task '.\Illuminate\Support\Str::limit($task->title, 40)" section="admin" :heading="$task->result['title'] ?? $task->title" :subheading="'Request: '.$task->request">
    @include('admin.ai.partials.nav')

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <section class="card card-body">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        @include('admin.ai.partials.status', ['status' => $task->status, 'label' => $task->statusLabel()])
                        <span class="text-[13px] text-muted">{{ $task->agent?->name ?? 'No agent' }} · {{ $task->mode === 'llm' ? 'data + model wording' : 'data only (rules)' }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($task->result)<a href="{{ route('admin.ai.tasks.export', $task) }}" class="btn btn-secondary btn-sm">Export report (.md)</a>@endif
                        @if ($task->status === 'failed' && $task->attempts < config('meezan.agents.limits.max_attempts'))
                            <form method="POST" action="{{ route('admin.ai.tasks.retry', $task) }}">@csrf<button class="btn btn-outline btn-sm">Retry</button></form>
                        @endif
                        @if (in_array($task->status, ['pending', 'awaiting_approval'], true))
                            <form method="POST" action="{{ route('admin.ai.tasks.cancel', $task) }}" data-confirm="Cancel this task? Pending approvals will be rejected.">@csrf<button class="btn btn-secondary btn-sm">Cancel</button></form>
                        @endif
                    </div>
                </div>
                @if ($task->error)
                    <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $task->error }}</p>
                @endif
                @if ($task->result)
                    <div class="mt-5">@include('admin.ai.partials.report', ['report' => $task->result])</div>
                @elseif (! $task->error)
                    <p class="mt-4 text-sm text-muted">No result yet. Queued tasks run when the queue worker processes the "{{ config('meezan.agents.queue') }}" queue.</p>
                @endif
            </section>

            @if ($task->children->isNotEmpty())
                <section class="card card-body">
                    <h2 class="text-base font-bold">Delegated subtasks</h2>
                    <ul class="mt-2 divide-y divide-line text-sm">
                        @foreach ($task->children as $c)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2"><a href="{{ route('admin.ai.tasks.show', $c) }}" class="link">{{ $c->agent?->name }}: {{ $c->title }}</a>@include('admin.ai.partials.status', ['status' => $c->status, 'label' => $c->statusLabel()])</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="card card-body">
                <h2 class="text-base font-bold">Tool calls ({{ $toolCalls->count() }})</h2>
                <div class="relative mt-2 overflow-x-auto">
                    <table class="table table-compact min-w-[640px]">
                        <thead><tr><th scope="col">Tool</th><th scope="col">Risk</th><th scope="col">Input</th><th scope="col">Result</th><th scope="col">ms</th></tr></thead>
                        <tbody>
                            @forelse ($toolCalls as $c)
                                <tr>
                                    <td class="text-[13px] font-semibold">{{ $c->tool }}</td>
                                    <td class="text-[13px]">{{ $c->risk }}</td>
                                    <td class="max-w-56 text-[12px] break-all text-muted">{{ $c->input ? json_encode($c->input, JSON_UNESCAPED_UNICODE) : '—' }}</td>
                                    <td class="text-[13px]">@include('admin.ai.partials.status', ['status' => $c->status === 'ok' ? 'completed' : ($c->status === 'approval_requested' ? 'pending' : 'failed'), 'label' => $c->status])@if ($c->error)<br><span class="text-[12px] text-muted">{{ $c->error }}</span>@endif</td>
                                    <td class="tabular-nums text-[13px]">{{ $c->duration_ms }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No tools were used.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="min-w-0 space-y-6">
            <section class="card card-body text-sm">
                <h2 class="text-base font-bold">Details</h2>
                <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-[13px]">
                    <dt class="text-muted">Task ID</dt><dd class="break-all">{{ $task->uuid }}</dd>
                    <dt class="text-muted">Source</dt><dd>{{ $task->source }}</dd>
                    <dt class="text-muted">Requested by</dt><dd>{{ $task->requester?->name ?? 'System' }}</dd>
                    <dt class="text-muted">Intent</dt><dd>{{ $task->intent ?? '—' }}</dd>
                    <dt class="text-muted">Depth</dt><dd>{{ $task->depth }}@if ($task->parent) · <a href="{{ route('admin.ai.tasks.show', $task->parent) }}" class="link">parent task</a>@endif</dd>
                    <dt class="text-muted">Attempts</dt><dd>{{ $task->attempts }} / {{ config('meezan.agents.limits.max_attempts') }}</dd>
                    <dt class="text-muted">Started</dt><dd>{{ $task->started_at?->localTime()->format('j M Y, H:i:s') ?? '—' }}</dd>
                    <dt class="text-muted">Finished</dt><dd>{{ $task->completed_at?->localTime()->format('j M Y, H:i:s') ?? '—' }}</dd>
                    <dt class="text-muted">Tokens</dt><dd>{{ number_format($task->tokens_in + $task->tokens_out) }}</dd>
                    <dt class="text-muted">Cost</dt><dd>{{ $task->cost_usd !== null ? 'USD '.number_format($task->cost_usd, 6) : ($usage->isEmpty() ? 'No model calls' : 'Not calculated (no pricing)') }}</dd>
                </dl>
            </section>

            @if ($task->approvals->isNotEmpty())
                <section class="card card-body">
                    <h2 class="text-base font-bold">Approvals</h2>
                    <ul class="mt-2 space-y-3 text-[13px]">
                        @foreach ($task->approvals as $a)
                            <li>@include('admin.ai.partials.status', ['status' => $a->status]) <span class="font-semibold">{{ $a->action }}</span><br>{{ $a->description }}
                                @if ($a->execution)<br><span class="text-muted">{{ $a->execution['message'] ?? '' }}</span>@endif
                                @if ($a->status === 'pending')<br><a href="{{ route('admin.ai.approvals.index') }}" class="link">Decide in the Approval Center</a>@endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($usage->isNotEmpty())
                <section class="card card-body">
                    <h2 class="text-base font-bold">Model calls</h2>
                    <ul class="mt-2 space-y-1 text-[13px]">
                        @foreach ($usage as $u)
                            <li>{{ $u->purpose }} · {{ $u->provider }} · {{ $u->model }} · {{ number_format($u->tokens_in + $u->tokens_out) }} tokens · {{ $u->cost_usd !== null ? 'USD '.number_format($u->cost_usd, 6).' · ' : '' }}{{ $u->ok ? 'ok' : 'error: '.$u->error }}@if ($u->fallback_from) · <span class="font-semibold text-amber-800">fallback from {{ $u->fallback_from }}</span>@endif</li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
