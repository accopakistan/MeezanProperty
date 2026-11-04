@php
    $contact = config('meezan.contact');
    $areas = \App\Support\Lookup::popularAreas(7);
@endphp
{{-- Layout follows the footer in public/Refrence/Meezan Property Real Estate UI Showcase.png --}}
<footer class="mt-20 bg-brand-900 text-white/70">
    <div class="mx-auto grid max-w-[1440px] gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr_1fr] lg:px-10">
        <div class="max-w-xs">
            {{-- Full logo (with the verse), transparent, light lettering for the dark footer. --}}
            <x-logo full variant="light" class="h-auto w-36 sm:w-40" />
            <p class="mt-5 text-sm leading-6">Pakistan’s property portal for buying, selling, renting and investing — with reviewed listings and verified dealers.</p>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Quick Links</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a class="hover:text-white" href="{{ route('properties.buy') }}">Buy</a></li>
                <li><a class="hover:text-white" href="{{ route('properties.rent') }}">Rent</a></li>
                <li><a class="hover:text-white" href="{{ route('properties.plots') }}">Plots</a></li>
                <li><a class="hover:text-white" href="{{ route('properties.commercial') }}">Commercial</a></li>
                <li><a class="hover:text-white" href="{{ route('properties.map') }}">Map Search</a></li>
                <li><a class="hover:text-white" href="{{ route('ai-finder') }}">AI Property Finder</a></li>
                <li><a class="hover:text-white" href="{{ route('rankings.index') }}">Investment Rankings</a></li>
                <li><a class="hover:text-white" href="{{ route('investment.index') }}">Investment Insights</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Popular Locations</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach ($areas as $area)
                    <li><a class="hover:text-white" href="{{ route('properties.index', ['area' => $area->slug]) }}">{{ $area->name }}</a></li>
                @endforeach
                <li><a class="hover:text-white" href="{{ route('properties.map') }}">All Locations</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Support</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a class="hover:text-white" href="{{ route('pages.how-it-works') }}">How it works</a></li>
                <li><a class="hover:text-white" href="{{ route('pages.terms') }}">Terms &amp; Conditions</a></li>
                <li><a class="hover:text-white" href="{{ route('pages.privacy') }}">Privacy Policy</a></li>
                <li><a class="hover:text-white" href="{{ route('dealer.register') }}">Dealer Registration</a></li>
                <li><a class="hover:text-white" href="{{ route('pages.about') }}">About Us</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Contact</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li class="flex items-center gap-2"><x-icon name="map-pin" class="size-4 text-gold-500" /> Head office: Lahore, Pakistan</li>
                @if ($contact['phone'])
                    <li class="flex items-center gap-2"><x-icon name="phone" class="size-4 text-gold-500" /> <a href="tel:{{ $contact['phone'] }}" class="hover:text-white">{{ $contact['phone'] }}</a></li>
                @endif
                @if ($contact['email'])
                    <li class="flex items-center gap-2"><x-icon name="mail" class="size-4 text-gold-500" /> <a href="mailto:{{ $contact['email'] }}" class="hover:text-white">{{ $contact['email'] }}</a></li>
                @endif
                <li><a class="hover:text-white" href="{{ route('pages.contact') }}">Send us a message</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-[1440px] flex-col gap-3 px-4 py-6 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-10">
            <p>&copy; {{ now()->year }} Meezan Property. All rights reserved.</p>
            <div class="flex gap-6">
                <a href="{{ route('pages.terms') }}" class="hover:text-white">Terms</a>
                <a href="{{ route('pages.privacy') }}" class="hover:text-white">Privacy</a>
                <a href="{{ route('sitemap') }}" class="hover:text-white">Sitemap</a>
            </div>
        </div>
    </div>
</footer>
