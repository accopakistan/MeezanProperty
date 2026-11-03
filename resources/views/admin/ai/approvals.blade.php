<x-layouts.dashboard title="AI Approvals" section="admin" heading="Approval Center" subheading="Actions agents proposed that change data or reach people. Nothing happens until an administrator approves; approved changes become staff tasks or drafts, and channels that are not verified stay blocked.">
    @include('admin.ai.partials.nav')

    <nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="Approval status">
        @foreach (['pending' => 'Pending'] + collect(\App\Models\AiApproval::STATUSES)->except('pending')->all() + ['all' => 'All'] as $key => $label)
            <a href="{{ route('admin.ai.approvals.index', ['status' => $key]) }}" @class(['chip', 'chip-active' => $status === $key])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="space-y-4">
        @forelse ($approvals as $a)
            <article class="card card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2">@include('admin.ai.partials.status', ['status' => $a->status]) <span class="font-semibold">{{ $a->action }}</span> <span class="text-[13px] text-muted">· {{ $a->agent?->name }} · {{ $a->created_at->localTime()->format('j M Y, H:i') }}</span></p>
                        <p class="mt-2 text-sm">{{ $a->description }}</p>
                        <details class="mt-2 text-[13px]">
                            <summary class="cursor-pointer text-muted">Details and evidence</summary>
                            <pre class="mt-2 overflow-x-auto rounded-lg bg-page p-3 text-[12px] whitespace-pre-wrap">{{ json_encode(['payload' => $a->payload, 'evidence' => $a->evidence], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                        @if ($a->task)<p class="mt-1 text-[13px]"><a href="{{ route('admin.ai.tasks.show', $a->task) }}" class="link">Open task</a></p>@endif
                        @if ($a->decided_at)
                            <p class="mt-2 text-[13px] text-muted">{{ ucfirst($a->status) }} {{ $a->decider ? 'by '.$a->decider->name : '' }} on {{ $a->decided_at->localTime()->format('j M Y, H:i') }}@if ($a->decision_note) — “{{ $a->decision_note }}”@endif</p>
                        @endif
                        @if ($a->execution)<p class="mt-1 text-[13px]"><span class="font-semibold">Result:</span> {{ $a->execution['message'] ?? '' }}</p>@endif
                    </div>
                    @if ($a->isPending())
                        <form method="POST" action="{{ route('admin.ai.approvals.decide', $a) }}" class="w-full space-y-2 sm:w-72">
                            @csrf
                            <label class="sr-only" for="note-{{ $a->id }}">Decision note</label>
                            <input id="note-{{ $a->id }}" name="note" class="input" maxlength="500" placeholder="Note (optional)">
                            <div class="flex gap-2">
                                <button name="decision" value="approve" class="btn btn-primary btn-sm flex-1">Approve</button>
                                <button name="decision" value="reject" class="btn btn-secondary btn-sm flex-1">Reject</button>
                            </div>
                            <p class="text-[12px] text-muted">Expires {{ $a->expires_at?->localTime()->format('j M Y') ?? 'never' }}</p>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="card card-body text-sm text-muted">No approvals here.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $approvals->links() }}</div>
</x-layouts.dashboard>
