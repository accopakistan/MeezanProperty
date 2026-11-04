<x-layouts.dashboard title="Owner documents" section="admin" heading="Owner documents" subheading="Evidence submitted by owners and managers. Check each document once; verified evidence counts in the Index with the submitter or the named issuer as its source.">
    @include('admin.investment-index.partials.nav')

    <div class="mb-4 flex gap-2 text-sm">
        @foreach (\App\Models\InvestmentEvidenceSubmission::STATUSES as $key => $label)
            <a href="{{ route('admin.investment-index.submissions', ['status' => $key]) }}" @class(['rounded-full px-3 py-1 ring-1', 'bg-brand-600 text-white ring-brand-600' => $status === $key, 'bg-white ring-line' => $status !== $key])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-left text-[13px]">
            <thead class="bg-surface text-muted"><tr><th class="px-4 py-2 font-medium">Submitted</th><th class="px-4 py-2 font-medium">Project</th><th class="px-4 py-2 font-medium">Claim</th><th class="px-4 py-2 font-medium">From</th><th class="px-4 py-2 font-medium"></th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($submissions as $s)
                    <tr>
                        <td class="px-4 py-2.5 text-muted">{{ $s->created_at->localTime()->format('j M Y, H:i') }}</td>
                        <td class="px-4 py-2.5">{{ $s->project?->name }}</td>
                        <td class="px-4 py-2.5 font-semibold">{{ $s->claim() }}</td>
                        <td class="px-4 py-2.5">{{ $s->organisation }} <span class="block text-muted">{{ $s->user?->name }} · {{ \App\Models\InvestmentEvidenceSubmission::DOCUMENT_TYPES[$s->document_type] ?? $s->document_type }}</span></td>
                        <td class="px-4 py-2.5"><a class="btn btn-secondary btn-sm" href="{{ route('admin.investment-index.submissions.show', $s) }}">{{ $s->status === 'pending' ? 'Check' : 'View' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-muted">No submissions here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $submissions->links() }}</div>
</x-layouts.dashboard>
