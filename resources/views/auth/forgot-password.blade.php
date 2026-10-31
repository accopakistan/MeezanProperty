<x-layouts.auth title="Reset password" heading="Forgot your password?" subheading="Enter your email and we’ll send you a link to choose a new one.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-field name="email" type="email" label="Email" required autofocus autocomplete="email" />
        <button class="btn btn-primary btn-lg w-full">Email reset link</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted"><a href="{{ route('login') }}" class="link">Back to sign in</a></p>
</x-layouts.auth>
