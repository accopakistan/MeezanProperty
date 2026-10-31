{{-- Layout: public/Refrence/Meezan Property Buyer Dashboard Showcase.png — 6A. Buyer Dashboard (Overview) --}}
<x-layouts.dashboard title="My account" section="account" :heading="'Welcome Back, '.\Illuminate\Support\Str::before($user->name, ' ').'!'" subheading="Find, save and connect with the best properties across Pakistan.">
    <form method="GET" action="{{ route('properties.index') }}" class="flex max-w-3xl overflow-hidden rounded-md border border-line bg-white shadow-card" role="search">
        <label for="buyer-search" class="sr-only">Search properties</label>
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted" />
            <input id="buyer-search" name="location" class="h-11 w-full border-0 pl-10 text-sm focus:ring-0 focus:outline-none" placeholder="Search properties, locations, or keywords…">
        </div>
        <button class="btn btn-primary h-11 rounded-none px-10">Search</button>
    </form>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Saved Properties" :value="$savedCount" icon="heart" tone="gold" :href="route('account.saved')" />
        <x-stat-card label="Active Requirements" :value="$requirementCount" icon="file-text" tone="gold" :href="route('account.requirements.index')" />
        <x-stat-card label="Inquiries Sent" :value="$inquiryCount" icon="message" tone="gold" :href="route('account.inquiries')" />
        <x-stat-card label="Recommendations" :value="$recommendations?->matches->count() ?? 0" icon="sparkles" tone="gold" :href="$requirement ? route('account.requirements.matches', $requirement) : route('account.requirements.create')" />
    </div>

    <a href="{{ route('ai-finder') }}" class="group relative isolate mt-5 flex min-h-36 items-center overflow-hidden rounded-xl bg-brand-800 p-6 text-white sm:p-8">
        <img src="{{ asset('images/ref/hero-home.jpg') }}" alt="" class="absolute inset-y-0 left-0 -z-20 h-full w-1/2 object-cover">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900/20 via-brand-800/90 to-brand-800"></div>
        <div class="ml-auto max-w-md">
            <p class="font-serif text-2xl font-bold sm:text-[28px]">Find Your Dream Property</p>
            <p class="mt-1 text-sm text-white/85">Get AI-powered recommendations based on your preferences.</p>
            <span class="btn btn-gold mt-4">Explore Properties <x-icon name="arrow-right" class="size-4" /></span>
        </div>
    </a>

    <section class="mt-7" aria-labelledby="recommended">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h2 id="recommended" class="font-serif text-2xl font-bold">Recommended for You</h2>
                @if ($requirement)
                    <p class="text-[13px] text-muted">Based on: {{ $requirement->title ?: $requirement->summary() }}</p>
                @endif
            </div>
            @if ($requirement)
                <a href="{{ route('account.requirements.matches', $requirement) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600">View All <x-icon name="arrow-right" class="size-4" /></a>
            @endif
        </div>
        <div class="mt-4">
            @if (! $requirement)
                <x-empty-state compact icon="target" title="Tell us what you’re looking for">
                    Save a requirement and we’ll recommend the best-fitting published listings.
                    <x-slot:actions>
                        <a href="{{ route('account.requirements.create') }}" class="btn btn-primary btn-sm">Create a requirement</a>
                        <a href="{{ route('ai-finder') }}" class="btn btn-secondary btn-sm"><x-icon name="sparkles" class="size-4" /> Try the AI finder</a>
                    </x-slot:actions>
                </x-empty-state>
            @elseif ($recommendations->isEmpty())
                <x-empty-state compact icon="search" title="No matching listings yet">
                    @foreach ($recommendations->hints as $hint)<span class="block">{{ $hint }}</span>@endforeach
                </x-empty-state>
            @else
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ($recommendations->matches as $match)
                        <x-property-card :property="$match->property" :badge="$match->score.'% Match'" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="mt-7 grid gap-6 xl:grid-cols-2">
        <section class="card" aria-labelledby="recent-inquiries">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 id="recent-inquiries" class="font-bold">Recent Inquiries</h2>
                <a href="{{ route('account.inquiries') }}" class="text-sm font-semibold text-brand-600">View all</a>
            </div>
            @forelse ($recentInquiries as $inquiry)
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 text-sm last:border-0">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $inquiry->property?->title ?? 'Removed listing' }}</p>
                        <p class="text-xs text-muted">{{ $inquiry->created_at->format('j M Y') }}</p>
                    </div>
                    <x-status-badge :tone="$inquiry->statusTone()" :label="ucfirst($inquiry->status)" />
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-muted">You haven’t contacted any dealers yet.</p>
            @endforelse
        </section>

        <section class="card" aria-labelledby="recent-saved">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 id="recent-saved" class="font-bold">Recently Saved</h2>
                <a href="{{ route('account.saved') }}" class="text-sm font-semibold text-brand-600">View all</a>
            </div>
            @forelse ($recentSaved as $property)
                <a href="{{ route('properties.show', $property) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0 hover:bg-page">
                    <img src="{{ $property->coverThumbUrl() ?? $property->placeholderUrl() }}" alt="" class="h-11 w-14 rounded-md object-cover">
                    <span class="min-w-0 text-sm"><span class="block truncate font-semibold">{{ $property->title }}</span><span class="text-xs font-semibold text-brand-600">{{ $property->priceLabel() }}</span></span>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-muted">Tap the heart on any listing to save it.</p>
            @endforelse
        </section>
    </div>
</x-layouts.dashboard>
