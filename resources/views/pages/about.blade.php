<x-layouts.app title="About Meezan Property" description="Meezan Property is a Pakistani real estate platform built on balance and trust: reviewed listings, verified dealers and honest AI matching.">
    <section class="bg-brand-800">
        <div class="container-page py-14 sm:py-20">
            <x-breadcrumbs :items="[['About', null]]" class="[&_a]:text-white/60 [&_span]:text-white/90 [&_svg]:text-white/40" />
            <h1 class="mt-8 max-w-3xl text-3xl font-extrabold text-white sm:text-5xl">Property decisions deserve <span class="text-gold-500">balance</span>.</h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-white/75">“Meezan” means balance — the scale that weighs fairly. We are building a property platform for Pakistan that weighs the interests of buyers, tenants and dealers fairly, starting with honest information.</p>
        </div>
    </section>
    <div class="container-page grid gap-10 py-14 lg:grid-cols-3">
        @foreach ([
            ['Reviewed listings', 'Every listing is checked by our team before it is published. Listings that are incomplete or misleading are sent back to the dealer with a reason.'],
            ['Verified dealers', 'Dealers register with their agency details and operating areas. The verified badge appears only after our team checks them.'],
            ['Relevance-first leads', 'Buyer requirements are shared only with consent, and only with verified dealers who operate in the areas the buyer asked for.'],
        ] as [$title, $body])
            <div>
                <span class="block h-1 w-12 rounded-full bg-gold-500"></span>
                <h2 class="mt-5 text-xl font-extrabold">{{ $title }}</h2>
                <p class="mt-2 text-[15px] leading-7 text-muted">{{ $body }}</p>
            </div>
        @endforeach
    </div>
    <div class="container-page">
        <div class="card card-body flex flex-wrap items-center justify-between gap-4">
            <p class="text-lg font-bold">We started in Lahore and now cover Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar, adding cities one at a time.</p>
            <a href="{{ route('pages.contact') }}" class="btn btn-primary">Get in touch</a>
        </div>
    </div>
</x-layouts.app>
