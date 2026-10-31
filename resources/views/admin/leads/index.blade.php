@php use App\Enums\LeadStatus; @endphp
<x-layouts.dashboard title="Leads" section="admin" heading="Leads" subheading="Inquiries and shared requirements, with their dealer assignments.">
    <div class="mb-5 flex gap-1 overflow-x-auto rounded-xl border border-line bg-white p-1 scrollbar-none">
        <a href="{{ route('admin.leads.index') }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => empty($filters['status']), 'text-muted hover:text-ink' => ! empty($filters['status'])])>All <span class="text-xs opacity-75">{{ $counts->sum() }}</span></a>
        @foreach (LeadStatus::cases() as $s)
            <a href="{{ route('admin.leads.index', ['status' => $s->value]) }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => ($filters['status'] ?? null) === $s->value, 'text-muted hover:text-ink' => ($filters['status'] ?? null) !== $s->value])>{{ $s->label() }} <span class="text-xs opacity-75">{{ $counts[$s->value] ?? 0 }}</span></a>
        @endforeach
    </div>

    @if ($leads->isEmpty())
        <x-empty-state icon="inbox" title="No leads yet">Leads are created when buyers send an inquiry or share a requirement with dealers.</x-empty-state>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Lead</th><th>Source</th><th>Subject</th><th>Assigned to</th><th>Status</th><th>Created</th><th class="sr-only">Open</th></tr></thead>
                    <tbody>
                        @foreach ($leads as $lead)
                            <tr>
                                <td class="font-semibold">#{{ $lead->id }} <span class="block text-xs font-normal text-muted">{{ $lead->contactName() }}</span></td>
                                <td><span class="badge bg-page text-ink/70 ring-1 ring-line">{{ $lead->inquiry_id ? 'Inquiry' : 'Requirement' }}</span><span class="block text-xs text-muted">{{ $lead->source }}</span></td>
                                <td class="max-w-72 text-[13px]">
                                    @if ($lead->property)
                                        {{ $lead->property->reference }} – {{ \Illuminate\Support\Str::limit($lead->property->title, 40) }}
                                    @else
                                        {{ $lead->type?->label() ?? 'Any type' }} · {{ $lead->purpose?->label() }} · {{ $lead->area?->name ?? 'Any area' }}
                                    @endif
                                </td>
                                <td class="text-[13px]">
                                    @forelse ($lead->activeAssignments as $a)
                                        <span class="block">{{ $a->dealer?->dealerProfile?->agency_name ?? $a->dealer?->name }}</span>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                </td>
                                <td><x-status-badge :tone="$lead->status->tone()" :label="$lead->status->label()" /></td>
                                <td class="whitespace-nowrap text-muted">{{ $lead->created_at->format('j M Y') }}</td>
                                <td class="text-right"><a href="{{ route('admin.leads.show', $lead) }}" class="btn btn-secondary btn-sm">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $leads->links() }}</div>
    @endif
</x-layouts.dashboard>
