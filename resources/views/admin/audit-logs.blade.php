<x-layouts.dashboard title="Audit log" section="admin" heading="Audit log" subheading="Append-only record of verification, moderation, lead and account actions.">
    <form method="GET" class="mb-5 flex flex-wrap gap-2">
        <select name="action" class="input w-auto" data-autosubmit aria-label="Filter by action">
            <option value="">All actions</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ ucfirst($action) }}</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-secondary">Filter</button></noscript>
    </form>

    @if ($logs->isEmpty())
        <x-empty-state icon="shield" title="No entries" />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Subject</th><th>Details</th><th>IP</th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap text-muted">{{ $log->created_at->format('j M Y, H:i') }}</td>
                                <td class="whitespace-nowrap">{{ $log->user?->name ?? 'System' }}</td>
                                <td><code class="rounded bg-page px-1.5 py-0.5 text-xs">{{ $log->action }}</code></td>
                                <td class="whitespace-nowrap text-[13px] text-muted">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                                <td class="max-w-80 text-xs text-muted">{{ $log->properties ? collect($log->properties)->map(fn ($v, $k) => $k.': '.(is_scalar($v) || $v === null ? ($v ?? '—') : json_encode($v)))->implode(' · ') : '' }}</td>
                                <td class="text-xs text-muted">{{ $log->ip_address }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $logs->links() }}</div>
    @endif
</x-layouts.dashboard>
