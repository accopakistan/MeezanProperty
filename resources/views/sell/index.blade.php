@php $isLister = $user?->isDealer(); @endphp
<x-layouts.app title="Sell or list your property" description="List your house, plot or commercial property on Meezan Property as a private owner or as a registered dealer. Every listing is reviewed before it goes live.">
    <section class="relative isolate overflow-hidden bg-brand-900">
        <img src="{{ asset('images/ref/hero-home.jpg') }}" alt="" class="absolute inset-y-0 right-0 -z-20 h-full w-full object-cover md:w-[60%]">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/85 to-brand-900/20"></div>
        <div class="container-page py-14">
            <x-breadcrumbs :items="[['Sell', null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-6 max-w-2xl text-4xl leading-tight text-white sm:text-5xl">Sell or rent out your property</h1>
            <p class="mt-3 max-w-xl text-lg text-white/85">Reach serious buyers and tenants in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar. Every listing is reviewed by our team before it is published.</p>
            @if ($isLister)
                <a href="{{ route('dealer.properties.create') }}" class="btn btn-gold btn-lg mt-6"><x-icon name="plus" class="size-5" /> Add a listing</a>
            @endif
        </div>
    </section>

    <div class="container-page mt-10 grid gap-6 lg:grid-cols-2">
        <section class="card flex flex-col p-7">
            <span class="grid size-14 place-items-center rounded-full bg-gold-100 text-gold-600"><x-icon name="home" class="size-7" /></span>
            <h2 class="mt-4 font-serif text-2xl font-bold">I’m the owner</h2>
            <p class="mt-2 text-[15px] text-muted">List your own house, flat, plot or shop directly. Your listing is labelled “Listed by owner”, and inquiries come straight to you.</p>
            <ul class="mt-4 space-y-2 text-sm">
                <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> Free during the launch period</li>
                <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> Up to {{ config('meezan.listing.max_images') }} photos per listing</li>
                <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> Your phone number is never shown publicly</li>
            </ul>
            <div class="mt-auto pt-6">
                @guest
                    <a href="{{ route('sell.owner') }}" class="btn btn-primary">List as an owner</a>
                @else
                    @if ($isLister)
                        <a href="{{ route('dealer.properties.create') }}" class="btn btn-primary">Add a listing</a>
                    @else
                        <p class="text-sm text-muted">You’re signed in as a {{ strtolower($user->role->label()) }}. Sign out and create an owner account to list a property.</p>
                    @endif
                @endguest
            </div>
        </section>

        <section class="card flex flex-col p-7">
            <span class="grid size-14 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon name="briefcase" class="size-7" /></span>
            <h2 class="mt-4 font-serif text-2xl font-bold">I’m a dealer or agency</h2>
            <p class="mt-2 text-[15px] text-muted">Manage many listings, get verified, appear in our dealer directory and receive buyer requirements for the areas you cover.</p>
            <ul class="mt-4 space-y-2 text-sm">
                <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> Verified badge after our agency checks</li>
                <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> Area-matched buyer leads (with buyer consent)</li>
                <li class="flex gap-2"><x-icon name="check" class="size-4 text-emerald-600" /> Listing and lead performance dashboard</li>
            </ul>
            <div class="mt-auto pt-6">
                @guest
                    <a href="{{ route('dealer.register') }}" class="btn btn-outline">Register your agency</a>
                @endguest
            </div>
        </section>
    </div>

    <section class="container-page mt-12" aria-labelledby="rules-title">
        <div class="rounded-xl bg-brand-50 p-6 sm:p-9">
            <h2 id="rules-title" class="section-title">How listing works</h2>
            <ol class="mt-6 grid gap-4 md:grid-cols-4">
                @foreach ([
                    ['Create your account', 'Owners and dealers have separate sign-ups.'],
                    ['Add property details', 'Price in PKR, size in Marla, Kanal or sq ft, photos and features.'],
                    ['Confirm your authority', 'Declare that you own the property or are authorised to market it.'],
                    ['We review, then publish', 'Our team checks every listing. Duplicate or misleading listings are rejected.'],
                ] as $i => [$title, $body])
                    <li class="rounded-lg bg-white p-5 shadow-card">
                        <span class="grid size-8 place-items-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ $i + 1 }}</span>
                        <p class="mt-3 font-semibold">{{ $title }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $body }}</p>
                    </li>
                @endforeach
            </ol>
            <p class="mt-5 text-[13px] text-muted">Meezan Property does not verify legal ownership. Buyers should always check title documents with the relevant society or land authority.</p>
        </div>
    </section>
</x-layouts.app>
