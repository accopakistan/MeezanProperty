{{-- Kanban card: one of the dealer's own lead assignments. Draggable on desktop; the "Move to" menu works everywhere. --}}
@php $stage = $assignment->pipeline_stage; @endphp
<article class="rounded-xl border border-line bg-white p-3.5 shadow-card transition hover:border-brand-200 hover:shadow-lift"
         draggable="true" data-crm-card data-stage-url="{{ route('dealer.crm.leads.stage', $assignment) }}" data-stage="{{ $stage->value }}"
         data-allowed="{{ collect($stage->allowedNext())->map->value->implode(',') }}" aria-label="{{ $assignment->buyerName() }}">
    <div class="flex items-start justify-between gap-2">
        <a href="{{ route('dealer.crm.leads.show', $assignment) }}" class="min-w-0 font-semibold leading-snug hover:text-brand-700">{{ $assignment->buyerName() }}</a>
        @if ($assignment->priority !== 'normal')
            <x-status-badge :tone="$assignment->priorityTone()" :label="$assignment->priorityLabel()" />
        @endif
    </div>
    <p class="mt-1 line-clamp-2 text-xs text-muted">{{ $assignment->headline() }}</p>
    <div class="mt-2.5 flex flex-wrap items-center gap-1.5 text-[11px]">
        @if ($stage->column() !== $stage->value && ! in_array($stage->value, ['viewing_scheduled'], true))
            <x-status-badge :tone="$stage->tone()" :label="$stage->label()" />
        @endif
        @if ($assignment->overdue_count)
            <span class="badge bg-red-50 text-red-700 ring-1 ring-red-200 ring-inset">{{ $assignment->overdue_count }} overdue</span>
        @endif
        @if ($assignment->stale_flagged_at)
            <span class="badge bg-amber-50 text-amber-700 ring-1 ring-amber-200 ring-inset">Needs attention</span>
        @endif
        @if ($assignment->lead?->requirement?->source === 'whatsapp')
            <span class="badge bg-emerald-50 text-emerald-700">WhatsApp</span>
        @endif
        <span class="ml-auto text-muted" title="Last activity">{{ ($assignment->last_activity_at ?? $assignment->assigned_at)->diffForHumans(short: true) }}</span>
    </div>
    @if ($stage->allowedNext())
        <form method="POST" action="{{ route('dealer.crm.leads.stage', $assignment) }}" class="mt-2.5">
            @csrf @method('PATCH')
            <label class="sr-only" for="move-{{ $assignment->id }}">Move {{ $assignment->buyerName() }} to</label>
            <select id="move-{{ $assignment->id }}" name="stage" class="input w-full py-1.5 text-xs" data-autosubmit>
                <option value="">Move to…</option>
                @foreach ($stage->allowedNext() as $next)
                    @unless ($next->needsReason())<option value="{{ $next->value }}">{{ $next->label() }}</option>@endunless
                @endforeach
            </select>
        </form>
    @endif
</article>
