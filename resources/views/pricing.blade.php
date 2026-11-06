<x-layouts.app title="Dealer packages" description="Compare Meezan Property dealer packages: listing limits, monthly buyer leads and featured listings." :robots="$packages->isEmpty() ? 'noindex, follow' : null">
    <section class="bg-brand-800">
        <div class="container-page py-14 sm:py-16">
            <x-breadcrumbs :items="[['Dealer packages', null]]" class="[&_a]:text-white/60 [&_span]:text-white/90 [&_svg]:text-white/40" />
            <p class="eyebrow mt-8 text-gold-300">For dealers and agencies</p>
            <h1 class="mt-2 max-w-3xl text-3xl font-extrabold text-white sm:text-5xl">Packages for every <span class="text-gold-500">agency</span></h1>
            <p class="mt-4 max-w-2xl text-lg leading-8 text-white/75">Each package sets how many listings you can have live, how many buyer requirements we share with you each month, and which extras are included.</p>
        </div>
    </section>

    <div class="container-page py-12">
        @if ($packages->isEmpty())
            <x-empty-state icon="credit-card" title="Dealer packages are being finalised">
                Dealers and agencies can register and list properties now without choosing a package. Package prices and limits will be published here once they are final. Questions? <a href="{{ route('pages.contact') }}" class="link">Contact us</a>.
            </x-empty-state>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('dealer.register') }}" class="btn btn-primary">Register as a dealer</a>
                <a href="{{ route('sell') }}" class="btn btn-secondary">How listing works</a>
            </div>
        @else
            @if ($packages->contains('is_demo', true))
                <p class="mb-8 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="note">
                    Packages marked “Draft” are for testing. Their prices and limits are not final.
                </p>
            @endif
            <div @class(['grid gap-6 pt-3 sm:grid-cols-2', 'xl:grid-cols-3' => $packages->count() === 3, 'xl:grid-cols-4' => $packages->count() >= 4])>
                @foreach ($packages as $package)
                    <x-package-card :package="$package" :current="$current?->package_id === $package->id"
                        :action="auth()->user()?->isDealer() ? route('dealer.billing.checkout', $package) : (auth()->guest() ? route('dealer.register') : null)"
                        :action-label="auth()->user()?->isDealer() ? 'Choose package' : 'Register as a dealer'" />
                @endforeach
            </div>

            <div class="mt-12 grid gap-6 text-sm leading-6 text-muted md:grid-cols-3">
                <div><p class="font-bold text-ink">What counts as a listing</p><p class="mt-1">Listings that are live or waiting for review. Drafts, expired, rejected and archived listings do not count.</p></div>
                <div><p class="font-bold text-ink">What counts as a lead</p><p class="mt-1">Buyer requirements we share with you, per calendar month. Enquiries on your own listings always reach you and are not counted. Spam and duplicates are not counted.</p></div>
                <div><p class="font-bold text-ink">Changing package</p><p class="mt-1">Upgrades start once paid. Moving to a smaller package takes effect at the end of the period you paid for, and your existing listings are never deleted.</p></div>
            </div>
            <p class="mt-8 text-xs text-muted">Prices are in Pakistani rupees unless shown otherwise. Paid packages start only after payment is confirmed.</p>
        @endif
    </div>
</x-layouts.app>
