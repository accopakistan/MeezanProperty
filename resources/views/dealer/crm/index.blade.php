<x-layouts.dashboard title="All leads" section="dealer" heading="All leads" subheading="Every lead assigned to you, with filters.">
    @include('dealer.crm._nav')
    @include('dealer.crm._filters')

    @if ($assignments->isEmpty())
        <x-empty-state icon="target" title="{{ array_filter($filters) ? 'No leads match these filters' : 'No leads yet' }}">
            {{ array_filter($filters) ? 'Try clearing a filter.' : 'Buyer requirements routed to your areas and enquiries on your listings appear here.' }}
        </x-empty-state>
    @else
        <div class="card overflow-hidden"><div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Buyer</th><th>Looking for</th><th>Stage</th><th>Priority</th><th>Next follow-up</th><th>Assigned</th><th class="sr-only">Open</th></tr></thead>
                <tbody>
                    @foreach ($assignments as $assignment)
                        @php $next = $assignment->followUps->first(); @endphp
                        <tr>
                            <td class="font-semibold"><a href="{{ route('dealer.crm.leads.show', $assignment) }}" class="hover:text-brand-700">{{ $assignment->buyerName() }}</a>
                                @if ($assignment->lead?->requirement?->source === 'whatsapp')<span class="badge ml-1 bg-emerald-50 text-emerald-700">WhatsApp</span>@endif
                                @if ($assignment->stale_flagged_at)<span class="badge ml-1 bg-amber-50 text-amber-700">Needs attention</span>@endif</td>
                            <td class="min-w-56 text-[13px]">{{ $assignment->headline() }}</td>
                            <td><x-status-badge :tone="$assignment->pipeline_stage->tone()" :label="$assignment->pipeline_stage->label()" /></td>
                            <td><x-status-badge :tone="$assignment->priorityTone()" :label="$assignment->priorityLabel()" /></td>
                            <td class="text-[13px] whitespace-nowrap">
                                @if ($next)<span @class(['font-semibold text-red-600' => $next->isOverdue()])>{{ $next->typeLabel() }} · {{ $next->due_at->localTime()->format('j M, g:i a') }}</span>@else<span class="text-muted">—</span>@endif
                            </td>
                            <td class="whitespace-nowrap text-muted">{{ $assignment->assigned_at->localTime()->format('j M Y') }}</td>
                            <td class="text-right"><a href="{{ route('dealer.crm.leads.show', $assignment) }}" class="btn btn-secondary btn-sm">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
        <div class="mt-6">{{ $assignments->links() }}</div>
    @endif
</x-layouts.dashboard>
