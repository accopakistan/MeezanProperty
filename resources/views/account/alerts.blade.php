<x-layouts.dashboard title="Alerts" section="account" heading="Property Alerts" subheading="New listings published since you last opened each saved search.">
    @if ($groups->isNotEmpty())
        <x-slot:actions>
            <form method="POST" action="{{ route('account.alerts.seen') }}">@csrf <button class="btn btn-secondary">Mark all as seen</button></form>
        </x-slot:actions>
    @endif

    @if (! $hasSearches)
        <x-empty-state icon="bell" title="No alerts yet">
            Save a search and new matching listings will appear here.
            <x-slot:actions><a href="{{ route('properties.index') }}" class="btn btn-primary">Start searching</a></x-slot:actions>
        </x-empty-state>
    @elseif ($groups->isEmpty())
        <x-empty-state icon="check" title="You’re all caught up">No new listings for your saved searches since you last checked.</x-empty-state>
    @else
        <div class="space-y-8">
            @foreach ($groups as $group)
                <section>
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <h2 class="font-serif text-xl font-bold">{{ $group['search']->name }} <span class="ml-1 rounded-full bg-gold-500 px-2 py-0.5 align-middle font-sans text-xs text-brand-900">{{ $group['count'] }} new</span></h2>
                        <a href="{{ route('account.saved-searches.open', $group['search']) }}" class="text-sm font-semibold text-brand-600">See all results →</a>
                    </div>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($group['matches'] as $property)
                            <x-property-card :property="$property" badge="New" />
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
