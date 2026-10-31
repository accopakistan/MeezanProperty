<x-layouts.dashboard title="My requirements" section="account" heading="My requirements" subheading="Saved searches we use to recommend listings — and, if you choose, to connect you with verified dealers.">
    <x-slot:actions>
        <a href="{{ route('account.requirements.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> New requirement</a>
    </x-slot:actions>

    @if ($requirements->isEmpty())
        <x-empty-state icon="target" title="No requirements yet">
            Describe the property you need and we’ll show the best-fitting published listings.
            <x-slot:actions><a href="{{ route('account.requirements.create') }}" class="btn btn-primary">Create a requirement</a></x-slot:actions>
        </x-empty-state>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($requirements as $requirement)
                @php $shared = $requirement->leads->flatMap->activeAssignments->count(); @endphp
                <article class="card card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-bold">{{ $requirement->title ?: ($requirement->type?->label() ?? 'Property').' '.strtolower($requirement->purpose->label()) }}</h2>
                            <p class="mt-1 text-sm text-muted">{{ $requirement->summary() }}</p>
                        </div>
                        <x-status-badge :tone="$requirement->status === 'active' ? 'success' : 'muted'" :label="ucfirst($requirement->status)" />
                    </div>
                    <p class="mt-4 flex items-center gap-2 text-[13px] text-muted">
                        <x-icon :name="$shared ? 'users' : 'lock'" class="size-4" />
                        {{ $shared ? 'Shared with '.$shared.' verified '.\Illuminate\Support\Str::plural('dealer', $shared) : 'Private — not shared with dealers' }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('account.requirements.matches', $requirement) }}" class="btn btn-primary btn-sm"><x-icon name="sparkles" class="size-4" /> View matches</a>
                        <a href="{{ route('account.requirements.edit', $requirement) }}" class="btn btn-secondary btn-sm">Edit</a>
                        @if ($requirement->status === 'active')
                            <form method="POST" action="{{ route('account.requirements.destroy', $requirement) }}">@csrf @method('DELETE')
                                <button class="btn btn-ghost btn-sm text-muted">Close</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
