<x-layouts.app title="Contact Meezan Property" description="Contact Meezan Property in Lahore: office phone, WhatsApp, email and office address, or send us a message." :canonical="route('pages.contact')">
    <div class="container-page grid gap-10 py-10 lg:grid-cols-[1fr_1.2fr]">
        <div>
            <x-breadcrumbs :items="[['Contact', null]]" />
            <h1 class="mt-6 text-3xl font-extrabold sm:text-4xl">Contact us</h1>
            <p class="mt-3 text-[15px] leading-7 text-muted">Questions about a listing, dealer registration, the Investment Index or your account? Call the office, message us on WhatsApp, email us or use the form.</p>
            <x-site.contact-list class="mt-8" />
            <div class="mt-6 flex flex-wrap gap-3">
                @if (\App\Support\Contact::phone())<a href="{{ \App\Support\Contact::phoneHref() }}" class="btn btn-primary"><x-icon name="phone" class="size-4" /> Call the office</a>@endif
                @if (\App\Support\Contact::whatsapp())<a href="{{ \App\Support\Contact::whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-icon name="whatsapp" class="size-4" /> WhatsApp us</a>@endif
            </div>
            <p class="mt-4 text-[13px] text-muted">WhatsApp opens a chat with our team. It is a person, not an automated assistant.</p>
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
