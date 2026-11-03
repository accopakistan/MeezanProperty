<x-layouts.dashboard title="AI Tasks" section="admin" heading="Task Center" subheading="Every request handled by the AI Brain, with its agent, source, status and tool use. Delegated subtasks are listed under their parent task.">
    @include('admin.ai.partials.nav')

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <x-field name="status" type="select" label="Status" :options="['' => 'All'] + \App\Models\AiTask::STATUSES" :value="request('status')" />
        <x-field name="agent" type="select" label="Agent" :options="['' => 'All agents'] + $agents->pluck('name', 'slug')->all()" :value="request('agent')" />
        <label class="mb-2.5 flex items-center gap-2 text-sm"><input type="checkbox" name="subtasks" value="1" @checked(request()->boolean('subtasks'))> Include subtasks</label>
        <button class="btn btn-secondary">Filter</button>
    </form>

    <div class="card relative overflow-x-auto">
        <table class="table table-compact min-w-[820px]">
            <thead><tr><th scope="col">Task</th><th scope="col">Agent</th><th scope="col">Source</th><th scope="col">Tools</th><th scope="col">Mode</th><th scope="col">Created</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse ($tasks as $t)
                    <tr>
                        <td class="max-w-80"><a href="{{ route('admin.ai.tasks.show', $t) }}" class="link">{{ $t->title }}</a>@if ($t->parent_id)<span class="ml-1 text-[12px] text-muted">(subtask)</span>@endif</td>
                        <td class="text-[13px]">{{ $t->agent?->name ?? '—' }}</td>
                        <td class="text-[13px]">{{ $t->source }}</td>
                        <td class="tabular-nums">{{ $t->tool_calls }}</td>
                        <td class="text-[13px]">{{ $t->mode ?? '—' }}</td>
                        <td class="text-[13px] whitespace-nowrap">{{ $t->created_at->localTime()->format('j M, H:i') }}</td>
                        <td>@include('admin.ai.partials.status', ['status' => $t->status, 'label' => $t->statusLabel()])</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No tasks match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tasks->links() }}</div>
</x-layouts.dashboard>
