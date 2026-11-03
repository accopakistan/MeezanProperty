<x-layouts.dashboard title="AI Staff Work" section="admin" heading="Staff Work" subheading="What the agents have passed to people: support tickets, internal tasks and draft content. Agents never close these themselves.">
    @include('admin.ai.partials.nav')

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Support tickets ({{ $tickets->count() }} open)</h2>
            <ul class="mt-2 divide-y divide-line text-sm">
                @forelse ($tickets as $t)
                    <li class="py-3">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-semibold">{{ $t->reference }}</span> @include('admin.ai.partials.status', ['status' => $t->status]) <span class="badge bg-page text-muted ring-1 ring-line">{{ ucfirst($t->priority) }}</span></p>
                        <p class="mt-1 font-medium">{{ $t->subject }}</p>
                        <p class="mt-1 whitespace-pre-line break-words text-[13px] text-muted">{{ $t->summary }}</p>
                        <p class="mt-1 text-[12px] text-muted">{{ ucfirst($t->category) }} · {{ $t->channel }} · {{ $t->created_at?->localTime()->format('j M, H:i') }}@if ($t->user) · {{ $t->user->name }}@endif</p>
                        <form method="POST" action="{{ route('admin.ai.tickets.update', $t) }}" class="mt-2 flex flex-wrap gap-2">@csrf @method('PATCH')
                            @if ($t->status === 'open')<button name="status" value="in_progress" class="btn btn-outline btn-sm">Start</button>@endif
                            <button name="status" value="resolved" class="btn btn-secondary btn-sm">Resolve</button>
                        </form>
                    </li>
                @empty
                    <li class="py-2 text-muted">No open tickets.</li>
                @endforelse
            </ul>
        </section>

        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Internal tasks ({{ $staffTasks->count() }} open)</h2>
            <ul class="mt-2 divide-y divide-line text-sm">
                @forelse ($staffTasks as $t)
                    <li class="py-3">
                        <p class="font-medium">{{ $t->title }}</p>
                        <p class="mt-1 whitespace-pre-line break-words text-[13px] text-muted">{{ $t->description }}</p>
                        <p class="mt-1 text-[12px] text-muted">{{ str_replace('_', ' ', ucfirst($t->category)) }}@if ($t->agent) · from {{ $t->agent->name }}@endif @if ($t->approval_id) · approved request #{{ $t->approval_id }}@endif @if ($t->due_on) · due {{ $t->due_on->format('j M Y') }}@endif · {{ $t->assignee?->name ?? 'Unassigned' }}</p>
                        <form method="POST" action="{{ route('admin.ai.staff-tasks.update', $t) }}" class="mt-2 flex flex-wrap gap-2">@csrf @method('PATCH')
                            @unless ($t->assigned_to === auth()->id())<button name="action" value="assign" class="btn btn-outline btn-sm">Assign to me</button>@endunless
                            <button name="action" value="done" class="btn btn-secondary btn-sm">Done</button>
                            <button name="action" value="cancelled" class="btn btn-secondary btn-sm">Cancel</button>
                        </form>
                    </li>
                @empty
                    <li class="py-2 text-muted">No open tasks.</li>
                @endforelse
            </ul>
        </section>

        <section class="card card-body min-w-0 xl:col-span-2">
            <h2 class="text-base font-bold">Draft content ({{ $drafts->count() }} recent)</h2>
            <p class="mt-1 text-[13px] text-muted">Nothing here has been published or sent. "Approved" drafts are ready for staff to post by hand.</p>
            <ul class="mt-2 divide-y divide-line text-sm">
                @forelse ($drafts as $d)
                    <li class="py-3">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-medium">{{ $d->title }}</span> @include('admin.ai.partials.status', ['status' => $d->status])</p>
                        <p class="mt-1 text-[12px] text-muted">{{ ucfirst($d->type) }}@if ($d->channel) · {{ $d->channel }}@endif @if ($d->agent) · from {{ $d->agent->name }}@endif · {{ $d->created_at?->localTime()->format('j M, H:i') }}</p>
                        <details class="mt-1 text-[13px]"><summary class="cursor-pointer text-muted">Show text</summary><p class="mt-2 whitespace-pre-line break-words">{{ $d->body }}</p></details>
                        @if ($d->status === 'draft')
                            <form method="POST" action="{{ route('admin.ai.drafts.update', $d) }}" class="mt-2">@csrf @method('PATCH')<button name="status" value="rejected" class="btn btn-secondary btn-sm">Discard</button></form>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-muted">No drafts yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.dashboard>
