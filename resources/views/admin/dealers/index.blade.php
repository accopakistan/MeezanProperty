@php use App\Enums\DealerStatus; @endphp
<x-layouts.dashboard title="Dealers" section="admin" heading="Dealers" subheading="Review applications and manage dealer verification.">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="flex gap-1 overflow-x-auto rounded-xl border border-line bg-white p-1 scrollbar-none">
            <a href="{{ route('admin.dealers.index') }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => empty($filters['status']), 'text-muted hover:text-ink' => ! empty($filters['status'])])>All <span class="text-xs opacity-75">{{ $counts->sum() }}</span></a>
            @foreach (DealerStatus::cases() as $status)
                <a href="{{ route('admin.dealers.index', ['status' => $status->value]) }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => ($filters['status'] ?? null) === $status->value, 'text-muted hover:text-ink' => ($filters['status'] ?? null) !== $status->value])>
                    {{ $status === DealerStatus::Pending ? 'Pending' : $status->label() }} <span class="text-xs opacity-75">{{ $counts[$status->value] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
        <form method="GET" class="ml-auto flex gap-2">
            @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="input w-56 py-2" placeholder="Agency or email" aria-label="Search dealers">
            <button class="btn btn-secondary btn-sm">Search</button>
        </form>
    </div>

    @if ($dealers->isEmpty())
        <x-empty-state icon="briefcase" title="No dealers found" />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Agency</th><th>Contact</th><th>Areas</th><th class="text-right">Listings</th><th>Status</th><th>Registered</th><th class="sr-only">Open</th></tr></thead>
                    <tbody>
                        @foreach ($dealers as $dealer)
                            <tr>
                                <td><a href="{{ route('admin.dealers.show', $dealer) }}" class="font-semibold hover:text-brand-700">{{ $dealer->agency_name }}</a><p class="text-xs text-muted">{{ $dealer->city }}</p></td>
                                <td class="text-[13px]"><p>{{ $dealer->user->name }}</p><p class="text-muted">{{ $dealer->user->email }}</p></td>
                                <td class="max-w-56 text-[13px] text-muted">{{ $dealer->areas->pluck('name')->take(3)->implode(', ') }}@if ($dealer->areas->count() > 3) +{{ $dealer->areas->count() - 3 }}@endif</td>
                                <td class="text-right tabular-nums">{{ $dealer->listings_count }}</td>
                                <td>
                                    <x-status-badge :tone="$dealer->verification_status->tone()" :label="$dealer->verification_status->label()" />
                                    <p class="mt-1 text-xs whitespace-nowrap text-muted">{{ $dealer->documents_count ? $dealer->documents_count.' '.\Illuminate\Support\Str::plural('document', $dealer->documents_count) : 'No documents' }}</p>
                                </td>
                                <td class="whitespace-nowrap text-muted">{{ $dealer->created_at->format('j M Y') }}</td>
                                <td class="text-right"><a href="{{ route('admin.dealers.show', $dealer) }}" class="btn btn-secondary btn-sm">Review</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $dealers->links() }}</div>
    @endif
</x-layouts.dashboard>
