<x-layouts.app title="Contact Meezan Property" description="Contact the Meezan Property team.">
    <div class="container-page grid gap-10 py-10 lg:grid-cols-[1fr_1.2fr]">
        <div>
            <x-breadcrumbs :items="[['Contact', null]]" />
            <h1 class="mt-6 text-3xl font-extrabold sm:text-4xl">Contact us</h1>
            <p class="mt-3 text-[15px] leading-7 text-muted">Questions about a listing, dealer verification or your account? Send us a message and our team will reply by email.</p>
            <ul class="mt-8 space-y-4 text-[15px]">
                <li class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="map-pin" /></span> Lahore, Pakistan</li>
                @if ($contact['email'])
                    <li class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="mail" /></span> <a href="mailto:{{ $contact['email'] }}" class="link">{{ $contact['email'] }}</a></li>
                @endif
                @if ($contact['phone'])
                    <li class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon name="phone" /></span> <a href="tel:{{ $contact['phone'] }}" class="link">{{ $contact['phone'] }}</a></li>
                @endif
            </ul>
        </div>
        <div class="card card-body">
            <form method="POST" action="{{ route('pages.contact.send') }}" class="space-y-4" novalidate>
                @csrf
                <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <x-field name="name" label="Full name" :value="auth()->user()?->name" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="email" type="email" label="Email" :value="auth()->user()?->email" required />
                    <x-field name="phone" type="tel" label="Phone" :value="auth()->user()?->phone" required />
                </div>
                <x-field name="message" type="textarea" label="Message" required />
                <button class="btn btn-primary">Send message</button>
            </form>
        </div>
    </div>
</x-layouts.app>
