<x-layouts.dashboard title="Compare packages" section="dealer" heading="Compare packages" subheading="Limits and features of every available package.">
    <x-breadcrumbs :items="[['Packages & Payments', route('dealer.subscription')], ['Compare packages', null]]" class="mb-6" />

    <div class="card mb-6 grid gap-6 p-5 sm:grid-cols-3">
        <x-usage-meter label="Your active listings" :used="$usage['listings']" :limit="$plan->limit('listings')" />
        <x-usage-meter label="Your leads this month" :used="$usage['leads']" :limit="$plan->limit('leads')" />
        <div class="text-sm"><p class="text-[13px] font-semibold">Current package</p><p class="mt-1 text-lg font-extrabold">{{ $plan->name }}</p></div>
    </div>

    @if ($packages->isEmpty())
        <x-empty-state icon="credit-card" title="No packages are available yet">You can keep listing on your current access.</x-empty-state>
    @else
        @if ($packages->contains('is_demo', true))
            <p class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="note">Packages marked “Draft” are for testing. Their prices and limits are not final.</p>
        @endif
        <div @class(['grid gap-6 pt-3 sm:grid-cols-2', 'xl:grid-cols-3' => $packages->count() === 3, 'xl:grid-cols-4' => $packages->count() >= 4])>
            @foreach ($packages as $package)
                <x-package-card :package="$package" :current="$current?->package_id === $package->id && ! $current->scheduled_package_id" :action="route('dealer.billing.checkout', $package)" />
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
