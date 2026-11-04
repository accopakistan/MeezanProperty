<x-layouts.dashboard title="Index exceptions" section="admin" heading="Exceptions" subheading="What the automatic pipeline will not decide on its own: sources it cannot read, lists that changed, uncertain matches, schemes removed from official lists, conflicting official records and approval stages it cannot read.">
    @include('admin.investment-index.partials.nav')

    <div class="mb-4 flex gap-2 text-sm">
        @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $key => $label)
            <a href="{{ route('admin.investment-index.exceptions', ['status' => $key]) }}" @class(['rounded-full px-3 py-1 ring-1', 'bg-brand-600 text-white ring-brand-600' => $status === $key, 'bg-white ring-line' => $status !== $key])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($exceptions as $x)
            <section class="card card-body">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-[12px] font-semibold uppercase tracking-wide text-muted">{{ $x->typeLabel() }}@if ($x->occurrences > 1) · seen {{ $x->occurrences }} times @endif · {{ $x->updated_at->localTime()->format('j M Y, H:i') }}</p>
                        <h2 class="mt-0.5 text-[15px] font-bold">{{ $x->title }}</h2>
                        @if ($x->details)<p class="mt-1 text-[14px] text-muted">{{ $x->details }}</p>@endif
                        <p class="mt-1 text-[13px]">
                            @if ($x->source){{ $x->source->name }}@endif
                            @if ($x->project) · <a class="link" href="{{ route('projects.show', $x->project) }}">{{ $x->project->name }}</a>@endif
                            @if ($x->registryEntry) · <a class="link" href="{{ route('admin.investment-index.registry', ['q' => $x->registryEntry->scheme_name]) }}">Registry entry</a>@endif
                        </p>
                        @if ($x->resolution)<p class="mt-1 text-[13px]"><span class="font-semibold">Decision:</span> {{ $x->resolution }}</p>@endif
                    </div>
                </div>
                @if ($x->status === 'open')
                    <form method="POST" action="{{ route('admin.investment-index.exceptions.resolve', $x) }}" class="mt-3 grid gap-3 border-t border-line pt-3 md:grid-cols-[1fr_auto]">@csrf
                        <x-field name="resolution" label="Decision and reason" placeholder="What you checked and decided" required />
                        <div class="flex flex-wrap items-end gap-2">
                            @if ($x->type === 'removed_from_list' && $x->registry_entry_id)
                                <label class="flex items-center gap-1.5 text-[13px]"><input type="checkbox" name="withdraw_record" value="1"> Withdraw its legal record</label>
                            @endif
                            <button name="outcome" value="resolved" class="btn btn-primary btn-sm">Resolve</button>
                            <button name="outcome" value="dismissed" class="btn btn-secondary btn-sm">Dismiss</button>
                        </div>
                    </form>
                @endif
            </section>
        @empty
            <x-empty-state icon="badge-check" title="Nothing waiting" compact>No {{ $status }} exceptions.</x-empty-state>
        @endforelse
    </div>
    <div class="mt-4">{{ $exceptions->links() }}</div>
</x-layouts.dashboard>
