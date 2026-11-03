<x-layouts.dashboard title="AI Memory & Knowledge" section="admin" heading="Memory & Knowledge" subheading="Agents answer only from approved knowledge. Long-term memory proposed by an agent waits here for approval; approving a new version archives the old one.">
    @include('admin.ai.partials.nav')

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <section class="card card-body">
                <h2 class="text-base font-bold">Proposed memory ({{ $proposed->count() }})</h2>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @forelse ($proposed as $m)
                        <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                            <div class="min-w-0"><span class="font-semibold">{{ $m->key }}</span> <span class="text-[13px] text-muted">· {{ $m->scope }} · {{ $m->agent?->name ?? 'shared' }} · v{{ $m->version }}</span><p class="mt-1 text-[13px]">{{ $m->content }}</p><p class="text-[12px] text-muted">Source: {{ $m->source }}</p></div>
                            <form method="POST" action="{{ route('admin.ai.memory.decide', $m) }}" class="flex gap-2">@csrf @method('PATCH')
                                <button name="decision" value="approve" class="btn btn-primary btn-sm">Approve</button><button name="decision" value="reject" class="btn btn-secondary btn-sm">Reject</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-2 text-muted">Nothing waiting.</li>
                    @endforelse
                </ul>
            </section>

            <section class="card card-body">
                <h2 class="text-base font-bold">Knowledge documents</h2>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @foreach ($documents as $d)
                        <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2"><span class="font-semibold">{{ $d->title }}</span> @include('admin.ai.partials.status', ['status' => $d->status])</p>
                                <p class="text-[13px] text-muted">{{ \App\Models\AiKnowledgeDocument::CATEGORIES[$d->category] ?? $d->category }} · v{{ $d->version }} · {{ $d->agents ? 'agents: '.implode(', ', $d->agents) : 'all agents' }}</p>
                                <details class="mt-1 text-[13px]"><summary class="cursor-pointer text-muted">Show text</summary><p class="mt-2 whitespace-pre-line">{{ $d->body }}</p><p class="mt-1 text-[12px] text-muted">Source: {{ $d->source }}</p></details>
                            </div>
                            <form method="POST" action="{{ route('admin.ai.knowledge.status', $d) }}">@csrf @method('PATCH')
                                @if ($d->status === 'approved')<button name="status" value="archived" class="btn btn-secondary btn-sm">Archive</button>@else<button name="status" value="approved" class="btn btn-outline btn-sm">Approve</button>@endif
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <div class="min-w-0 space-y-6">
            <form method="POST" action="{{ route('admin.ai.knowledge.store') }}" class="card card-body space-y-3">
                @csrf
                <h2 class="text-base font-bold">Add knowledge</h2>
                <x-field name="category" type="select" label="Category" required :options="\App\Models\AiKnowledgeDocument::CATEGORIES" />
                <x-field name="title" label="Title" required />
                <x-field name="body" type="textarea" rows="6" label="Text" required hint="Facts only, from a source you can name. Agents quote this to users." />
                <x-field name="source" label="Source" required placeholder="e.g. Company policy document, March 2026" />
                <fieldset><legend class="label">Visible to (none = all agents)</legend>
                    <div class="grid grid-cols-2 gap-1 text-[13px]">@foreach ($agents as $slug => $name)<label class="flex items-center gap-1.5"><input type="checkbox" name="agents[]" value="{{ $slug }}"> {{ $name }}</label>@endforeach</div>
                </fieldset>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="approve" value="1"> Approve now</label>
                <button class="btn btn-primary">Save</button>
            </form>

            <form method="POST" action="{{ route('admin.ai.memory.store') }}" class="card card-body space-y-3">
                @csrf
                <h2 class="text-base font-bold">Add a memory note</h2>
                <x-field name="scope" type="select" label="Who may read it" required :options="['shared' => 'All agents that allow shared memory', 'agent' => 'One agent only']" />
                <x-field name="agent" type="select" label="Agent (for one agent only)" :options="['' => '—'] + $agents->all()" />
                <x-field name="key" label="Short name" required hint="Saving the same name again makes a new version and archives the old one." />
                <x-field name="content" type="textarea" rows="3" label="Note" required />
                <x-field name="source" label="Source" required placeholder="e.g. Management decision, November 2026" />
                <x-field name="expires_at" type="date" label="Expires (optional)" />
                <button class="btn btn-primary">Save note</button>
            </form>

            <section class="card card-body">
                <h2 class="text-base font-bold">Approved long-term memory</h2>
                <ul class="mt-2 space-y-2 text-[13px]">
                    @forelse ($memories as $m)
                        <li class="flex items-start justify-between gap-2"><span class="min-w-0"><span class="font-semibold">{{ $m->key }}</span> <span class="text-muted">({{ $m->agent?->name ?? 'shared' }})</span><br>{{ \Illuminate\Support\Str::limit($m->content, 120) }}</span>
                            <form method="POST" action="{{ route('admin.ai.memory.decide', $m) }}">@csrf @method('PATCH')<button name="decision" value="archive" class="link text-[12px]">Archive</button></form></li>
                    @empty
                        <li class="text-muted">None yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-layouts.dashboard>
