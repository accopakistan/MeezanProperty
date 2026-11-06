@php
    use App\Support\Legal;
    $title = Legal::PAGES[$page];
    $approved = Legal::approved($page);
@endphp
<x-layouts.app :title="$title" :description="'Meezan Property '.strtolower($title).'.'" :canonical="route('pages.'.$page)" :robots="$approved ? null : 'noindex, follow'">
    <div class="container-page max-w-3xl py-10">
        <x-breadcrumbs :items="[[$title, null]]" />
        <h1 class="mt-6 text-3xl font-extrabold">{{ $title }}</h1>

        @if ($approved)
            <p class="mt-2 text-sm text-muted">Effective {{ \Illuminate\Support\Carbon::parse(Legal::approvedOn($page))->format('j F Y') }}</p>
            <div class="prose-listing mt-8 space-y-6 text-[15px]">
                @include('pages.legal.approved.'.$page)
            </div>
        @else
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
                <strong>Not yet final.</strong> Our final {{ strtolower($title) }} is being prepared for legal review. Until it is published here, this page only summarises how Meezan Property works. It is not presented as a final or binding document.
            </div>
            <div class="prose-listing mt-8 space-y-6 text-[15px]">
                @if ($page === 'terms')
                    <section><h2 class="mb-2 text-lg font-bold">Listings</h2><p>Dealers are responsible for the accuracy of the listings they submit. Meezan Property reviews listings before publishing but does not guarantee ownership, title, availability or condition. Verify all details and documents independently before any payment.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">Dealer accounts</h2><p>Dealer verification reflects checks carried out by Meezan Property at the time of review. Verification may be suspended or withdrawn if a dealer breaches the platform's rules.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">AI Property Finder</h2><p>Matching results are suggestions generated from published listings. They are not professional, legal or financial advice.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">Acceptable use</h2><p>Do not post misleading listings, duplicate listings, or content that infringes others’ rights. We may remove content and suspend accounts that do.</p></section>
                @elseif ($page === 'privacy')
                    <section><h2 class="mb-2 text-lg font-bold">What we collect</h2><p>Account details (name, email, phone), dealer agency details, the property requirements you save, inquiries you send, and basic technical logs.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">How we share it</h2><p>An inquiry on a listing is shared with that listing’s dealer. A saved requirement is shared with dealers only if you choose “share with verified dealers”, and then only with dealers who serve the areas you asked for.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">WhatsApp</h2><p>If you message us on WhatsApp, we store the conversation to answer you and to improve matching. A member of our team may read and reply to it.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">Your choices</h2><p>You can update your profile, close a requirement, or contact us to request deletion of your account data.</p></section>
                @else
                    <section><h2 class="mb-2 text-lg font-bold">Property listings</h2><p>Listings are submitted by dealers and owners. Meezan Property reviews them before publishing, but does not guarantee ownership, title, price, availability or condition. Check documents and the property yourself before paying anything.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">Investment Index and insights</h2><p>{{ config('meezan.investment.disclaimer') }} A project shown as “researched” or “awaiting our check” has not been verified, and an unknown legal status does not mean a project is illegal.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">Prices and market figures</h2><p>Asking prices are advertised prices, not sales. Tax valuations are not market prices. Neither is used as a verified price.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">AI answers</h2><p>The AI Property Finder and assistant suggest matches and explain the platform. They can be wrong, and they are not legal, financial or professional advice.</p></section>
                    <section><h2 class="mb-2 text-lg font-bold">Maps and locations</h2><p>Map positions may be approximate. Boundaries and pins are a guide, not a survey.</p></section>
                @endif
                <section><h2 class="mb-2 text-lg font-bold">Questions</h2><p>Contact us at <a href="{{ \App\Support\Contact::emailHref() }}" class="link">{{ \App\Support\Contact::email() }}</a> or through the <a href="{{ route('pages.contact') }}" class="link">contact page</a>.</p></section>
            </div>
        @endif
    </div>
</x-layouts.app>
