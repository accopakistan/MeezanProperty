@php
    $isTerms = $page === 'terms';
    $title = $isTerms ? 'Terms of use' : 'Privacy policy';
@endphp
<x-layouts.app :title="$title" :description="'Meezan Property '.strtolower($title).'.'">
    <div class="container-page max-w-3xl py-10">
        <x-breadcrumbs :items="[[$title, null]]" />
        <h1 class="mt-6 text-3xl font-extrabold">{{ $title }}</h1>
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Draft for review. This summary describes how the platform is built to work and must be reviewed by a qualified lawyer before launch.
        </div>
        <div class="prose-listing mt-8 space-y-6 text-[15px]">
            @if ($isTerms)
                <section><h2 class="mb-2 text-lg font-bold">Listings</h2><p>Dealers are responsible for the accuracy of the listings they submit. Meezan Property reviews listings before publishing but does not guarantee ownership, title, availability or condition of any property. Buyers and tenants should verify documents independently.</p></section>
                <section><h2 class="mb-2 text-lg font-bold">Dealer accounts</h2><p>Dealer verification reflects checks carried out by Meezan Property at the time of review. Verification may be suspended or withdrawn if a dealer breaches these terms.</p></section>
                <section><h2 class="mb-2 text-lg font-bold">AI Property Finder</h2><p>Matching results are suggestions generated from published listings. They are not professional, legal or financial advice.</p></section>
                <section><h2 class="mb-2 text-lg font-bold">Acceptable use</h2><p>Do not post misleading listings, duplicate listings, or content that infringes others’ rights. We may remove content and suspend accounts that do.</p></section>
            @else
                <section><h2 class="mb-2 text-lg font-bold">What we collect</h2><p>Account details (name, email, phone), dealer agency details, the property requirements you save, inquiries you send, and basic technical logs.</p></section>
                <section><h2 class="mb-2 text-lg font-bold">How we share it</h2><p>An inquiry on a listing is shared with that listing’s dealer. A saved requirement is shared with dealers only if you choose “share with verified dealers”, and then only with up to three verified dealers in your chosen areas.</p></section>
                <section><h2 class="mb-2 text-lg font-bold">WhatsApp</h2><p>If you message us on WhatsApp, we store the conversation to answer you and to improve matching. A member of our team may read and reply to it.</p></section>
                <section><h2 class="mb-2 text-lg font-bold">Your choices</h2><p>You can update your profile, close a requirement, or contact us to request deletion of your account data.</p></section>
            @endif
        </div>
    </div>
</x-layouts.app>
