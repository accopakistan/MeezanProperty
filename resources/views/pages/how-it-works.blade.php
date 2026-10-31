<x-layouts.app title="How Meezan Property works" description="How listing review, dealer verification, AI matching and lead sharing work on Meezan Property.">
    <div class="container-page py-10">
        <x-breadcrumbs :items="[['How it works', null]]" />
        <h1 class="mt-6 text-3xl font-extrabold sm:text-4xl">How Meezan Property works</h1>
        <p class="mt-3 max-w-2xl text-[15px] leading-7 text-muted">A clear, consistent process for listings, dealers and leads.</p>

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            @foreach ([
                ['badge-check', 'Dealer verification', [
                    'A dealer registers with their name, phone, agency and operating areas.',
                    'Their account starts as “Verification pending”. They can prepare listings straight away.',
                    'Our team reviews the agency details. Approved dealers receive the verified badge; others are told why.',
                    'Verification can be suspended if a dealer breaks our listing rules.',
                ]],
                ['shield', 'Listing review', [
                    'Dealers save a listing as a draft, then submit it for review.',
                    'We check price, size, location, description and photos.',
                    'Approved listings are published for 90 days; rejected listings come back with a reason.',
                    'Edits to a published listing are reviewed again before they go live.',
                ]],
                ['sparkles', 'AI matching', [
                    'You describe what you need, in English or Roman Urdu.',
                    'We search only published listings, and treat property type, city and budget as must-haves.',
                    'Options are ranked by area, budget fit, size, bedrooms and dealer verification.',
                    'Each result explains what it meets — and what it misses.',
                ]],
                ['lock', 'Leads and your privacy', [
                    'An inquiry on a listing goes only to that listing’s dealer.',
                    'A saved requirement stays private unless you tick “share with dealers”.',
                    'If you do, up to three verified dealers active in your chosen areas receive it.',
                    'Every assignment is logged and can be reviewed by our team.',
                ]],
            ] as [$icon, $title, $steps])
                <section class="card card-body">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$icon" /></span>
                        <h2 class="text-lg font-extrabold">{{ $title }}</h2>
                    </div>
                    <ol class="mt-5 space-y-3">
                        @foreach ($steps as $i => $step)
                            <li class="flex gap-3 text-[15px]"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-gold-50 text-xs font-bold text-gold-700">{{ $i + 1 }}</span> {{ $step }}</li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>
