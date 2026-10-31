<x-layouts.dashboard title="Saved searches" section="account" heading="Saved Searches" subheading="Re-run a search in one click and see listings published since you last looked.">
    @if ($searches->isEmpty())
        <x-empty-state icon="search" title="No saved searches yet">
            Run a search, then choose “Save search” at the top of the results.
            <x-slot:actions><a href="{{ route('properties.index') }}" class="btn btn-primary">Start searching</a></x-slot:actions>
        </x-empty-state>
    @else
        <div class="space-y-3">
            @foreach ($searches as $search)
                @php $chips = $search->search()->chips(); @endphp
                <article class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="font-semibold">{{ $search->name }}</h2>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @forelse ($chips as $chip)
                                    <span class="badge bg-brand-50 text-brand-700">{{ $chip['label'] }}</span>
                                @empty
                                    <span class="text-[13px] text-muted">All properties</span>
                                @endforelse
                            </div>
                            <p class="mt-2 text-xs text-muted">Saved {{ $search->created_at->format('j M Y') }} · last opened {{ $search->last_checked_at?->diffForHumans() ?? 'never' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($search->new_count)
                                <span class="rounded-full bg-gold-500 px-2.5 py-1 text-xs font-bold text-brand-900">{{ $search->new_count }} new</span>
                            @endif
                            <a href="{{ route('account.saved-searches.open', $search) }}" class="btn btn-primary btn-sm">View results</a>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-end gap-3 border-t border-line pt-4">
                        <form method="POST" action="{{ route('account.saved-searches.update', $search) }}" class="flex flex-wrap items-end gap-2">
                            @csrf @method('PATCH')
                            <label class="text-xs text-muted">Name<input name="name" value="{{ $search->name }}" class="input mt-1 w-64 py-1.5 text-sm" maxlength="120" required></label>
                            <label class="text-xs text-muted">Email alerts<select name="alert_frequency" class="input mt-1 w-32 py-1.5 text-sm">
                                @foreach (\App\Models\SavedSearch::FREQUENCIES as $value => $label)<option value="{{ $value }}" @selected($search->alert_frequency === $value)>{{ $label }}</option>@endforeach
                            </select></label>
                            <button class="btn btn-secondary btn-sm">Save</button>
                        </form>
                        <form method="POST" action="{{ route('account.saved-searches.destroy', $search) }}" class="ml-auto" data-confirm="Delete this saved search?">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /> Delete</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
        @unless (auth()->user()->notify_email)
            <p class="mt-4 text-[13px] text-muted">Email alerts are off for your account. Turn them on in <a href="{{ route('account.settings') }}" class="link">Settings</a>.</p>
        @endunless
    @endif
</x-layouts.dashboard>
