<x-layouts.dashboard title="Listing reports" section="admin" heading="Listing Reports" subheading="Reports from visitors about duplicate, misleading or fraudulent listings.">
    <div class="mb-5 inline-flex rounded-md border border-line bg-white p-1">
        @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $value => $label)
            <a href="{{ route('admin.reports.index', ['status' => $value]) }}" @class(['rounded px-4 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => $status === $value, 'text-muted hover:text-ink' => $status !== $value])>{{ $label }} <span class="text-xs opacity-75">{{ $counts[$value] ?? 0 }}</span></a>
        @endforeach
    </div>

    @if ($reports->isEmpty())
        <x-empty-state icon="shield" :title="'No '.$status.' reports'" />
    @else
        <div class="space-y-3">
            @foreach ($reports as $report)
                <article class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $report->reasonLabel() }}</p>
                            <p class="mt-0.5 text-[13px] text-muted">
                                @if ($report->property)
                                    <a href="{{ route('admin.properties.show', $report->property) }}" class="link">{{ $report->property->reference }} – {{ $report->property->title }}</a>
                                    · {{ $report->property->dealer?->dealerProfile?->agency_name }} · listing {{ strtolower($report->property->status->label()) }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-muted">Reported {{ $report->created_at->diffForHumans() }} by {{ $report->user?->name ?? ($report->reporter_email ?: 'a visitor') }}</p>
                        </div>
                        @if ($report->status !== 'open')
                            <p class="text-xs text-muted">{{ ucfirst($report->status) }} by {{ $report->resolver?->name }} · {{ $report->resolved_at?->format('j M Y') }}</p>
                        @endif
                    </div>
                    @if ($report->details)<p class="mt-3 rounded-md bg-page p-3 text-sm">{{ $report->details }}</p>@endif
                    @if ($report->resolution_note)<p class="mt-2 text-xs text-muted">Note: {{ $report->resolution_note }}</p>@endif
                    @if ($report->status === 'open')
                        <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" class="mt-4 flex flex-wrap items-end gap-2 border-t border-line pt-4">
                            @csrf @method('PATCH')
                            <label class="flex-1 text-xs text-muted">Note (required to unpublish)<input name="note" class="input mt-1 py-1.5 text-sm" maxlength="255"></label>
                            <button name="decision" value="dismiss" class="btn btn-secondary btn-sm">Dismiss</button>
                            <button name="decision" value="resolve" class="btn btn-secondary btn-sm">Mark resolved</button>
                            <button name="decision" value="unpublish" class="btn btn-danger btn-sm">Unpublish listing</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="mt-6">{{ $reports->links() }}</div>
    @endif
</x-layouts.dashboard>
