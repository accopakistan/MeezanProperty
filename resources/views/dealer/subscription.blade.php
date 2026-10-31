<x-layouts.dashboard title="Subscription" section="dealer" heading="Subscription" subheading="Your plan and usage.">
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section class="card overflow-hidden">
            <div class="bg-brand-800 p-6 text-white">
                <p class="eyebrow text-gold-300">Current plan</p>
                <p class="mt-2 text-2xl font-extrabold">Launch access</p>
                <p class="mt-1 text-sm text-white/70">Listing and lead features are available without a paid plan during the launch period.</p>
            </div>
            <dl class="grid grid-cols-3 divide-x divide-line">
                <div class="p-5"><dt class="text-xs text-muted">Active & pending listings</dt><dd class="mt-1 text-2xl font-extrabold">{{ $activeListings }}</dd></div>
                <div class="p-5"><dt class="text-xs text-muted">Photos uploaded</dt><dd class="mt-1 text-2xl font-extrabold">{{ $photoCount }}</dd></div>
                <div class="p-5"><dt class="text-xs text-muted">Leads received</dt><dd class="mt-1 text-2xl font-extrabold">{{ $leadCount }}</dd></div>
            </dl>
        </section>

        <x-empty-state icon="credit-card" title="Paid plans are not available yet">
            Billing has not been set up. When subscription plans launch, you’ll be able to choose and manage a plan here. You will not be charged without choosing a plan.
        </x-empty-state>
    </div>
</x-layouts.dashboard>
