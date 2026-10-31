<x-layouts.auth title="Confirm your email" heading="Confirm your email address" :subheading="'We sent a confirmation link to '.auth()->user()->email.'. Open it to confirm this address.'">
    <div class="space-y-4 text-sm text-muted">
        <p>Confirming your email lets us send you inquiries, alerts and account messages{{ config('meezan.require_verified_email') ? ', and is needed before you share requirements with dealers or publish listings' : '' }}.</p>
        <p>Can’t find it? Check your spam folder, or send a new link. Links expire after {{ config('auth.verification.expire', 60) }} minutes.</p>
    </div>
    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <button class="btn btn-primary btn-lg w-full"><x-icon name="mail" class="size-4" /> Send a new link</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">
        <a href="{{ route(auth()->user()->role->homeRoute()) }}" class="link">Continue to your account</a>
    </p>
</x-layouts.auth>
