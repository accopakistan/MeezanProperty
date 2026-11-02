<x-layouts.dashboard title="Pipeline" section="dealer" heading="Sales pipeline" subheading="Drag a card to move it, or use “Move to…” on any card. Only you see your board.">
    @include('dealer.crm._nav')
    @include('dealer.crm._filters', ['showStage' => false])

    <p class="mb-3 hidden text-sm text-red-600" role="alert" data-crm-error></p>

    {{-- Desktop: columns side by side. Phones: the same columns stacked, each collapsible. --}}
    <div class="grid gap-4 lg:auto-cols-[minmax(15rem,1fr)] lg:grid-flow-col lg:overflow-x-auto lg:pb-3" data-crm-board>
        @foreach ($columns as $key => $column)
            <section id="col-{{ $key }}" class="flex min-w-0 flex-col rounded-2xl border border-line bg-page/70" aria-labelledby="col-title-{{ $key }}"
                     data-crm-column data-drop-stage="{{ $column['stage']->value }}" data-needs-reason="{{ $column['stage']->needsReason() ? '1' : '0' }}">
                <details class="group" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 lg:cursor-default">
                        <h2 id="col-title-{{ $key }}" class="flex items-center gap-2 text-sm font-bold">
                            <span @class(['size-2 rounded-full', 'bg-gold-500' => $key === 'won', 'bg-zinc-400' => $key === 'lost', 'bg-brand-600' => ! in_array($key, ['won', 'lost'], true)])></span>
                            {{ $column['label'] }}
                        </h2>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold tabular-nums text-muted ring-1 ring-line">{{ $column['count'] }}</span>
                    </summary>
                    <div class="space-y-3 px-3 pb-3" data-crm-dropzone>
                        @forelse ($column['cards'] as $assignment)
                            @include('dealer.crm._card')
                        @empty
                            <p class="rounded-xl border border-dashed border-line px-3 py-6 text-center text-xs text-muted">No leads</p>
                        @endforelse
                        @if ($column['count'] > $column['cards']->count())
                            <a href="{{ route('dealer.crm.leads', array_filter($filters) + ['outcome' => $key === 'won' ? 'won' : null]) }}" class="block text-center text-xs font-semibold text-brand-700 hover:underline">
                                {{ $column['count'] - $column['cards']->count() }} more in the list
                            </a>
                        @endif
                    </div>
                </details>
            </section>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-muted">Moving a lead to Lost, Invalid or Duplicate asks for a reason on the lead page. Each column shows up to {{ $perColumn }} leads, most urgent and most recently active first.</p>
</x-layouts.dashboard>
