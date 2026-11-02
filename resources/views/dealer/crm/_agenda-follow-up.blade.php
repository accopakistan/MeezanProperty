<li class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
    <span class="min-w-0 flex-1">
        <a href="{{ route('dealer.crm.leads.show', $followUp->lead_assignment_id) }}" class="font-semibold hover:text-brand-700">{{ $followUp->typeLabel() }}</a>
        <x-status-badge :tone="$followUp->priority === 'urgent' ? 'danger' : ($followUp->priority === 'high' ? 'warning' : 'neutral')" :label="config('meezan.crm.priorities.'.$followUp->priority)" class="ml-1" />
        <span class="block truncate text-xs text-muted">{{ $followUp->notes ?: \Illuminate\Support\Str::limit($followUp->assignment->lead?->property?->title ?? collect([$followUp->assignment->lead?->area?->name, $followUp->assignment->lead?->requirement?->city])->filter()->implode(', '), 60) }}</span>
    </span>
    <span @class(['text-xs font-semibold whitespace-nowrap', 'text-red-700' => $followUp->isOverdue(), 'text-muted' => ! $followUp->isOverdue()])>{{ $followUp->due_at->localTime()->format('j M, g:i a') }}</span>
    <form method="POST" action="{{ route('dealer.crm.follow-ups.update', [$followUp->lead_assignment_id, $followUp->id]) }}">
        @csrf @method('PATCH') <input type="hidden" name="outcome" value="completed">
        <button class="btn btn-secondary btn-sm"><x-icon name="check" class="size-3.5" /> Done</button>
    </form>
</li>
