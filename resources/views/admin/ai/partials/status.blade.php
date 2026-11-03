{{-- Status pill for agents, tasks, approvals and connectors. --}}
@php
    $tone = match ($status) {
        'active', 'completed', 'approved', 'verified', 'ok', 'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'running', 'pending', 'awaiting_approval', 'configured', 'proposed', 'draft', 'untested', 'limit', 'open', 'in_progress' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'failed', 'error', 'rejected', 'prohibited', 'denied', 'invalid' => 'bg-red-50 text-red-700 ring-red-200',
        'configuration_required', 'requires_setup', 'maintenance', 'waiting_credentials', 'in_development' => 'bg-orange-50 text-orange-800 ring-orange-200',
        'disabled' => 'bg-page text-muted ring-line',
        default => 'bg-page text-muted ring-line',
    };
@endphp
<span class="badge ring-1 {{ $tone }}">{{ $label ?? ucfirst(str_replace('_', ' ', $status)) }}</span>
