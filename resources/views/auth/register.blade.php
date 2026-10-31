<x-layouts.auth title="Create an account" heading="Create your buyer account" subheading="Save properties, track your inquiries and get matched to new listings.">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
        @csrf
        <x-field name="name" label="Full name" required autofocus autocomplete="name" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="email" type="email" label="Email" required autocomplete="email" />
            <x-field name="phone" type="tel" label="Phone (optional)" placeholder="0300 1234567" autocomplete="tel" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="password" type="password" label="Password" required autocomplete="new-password" hint="At least 8 characters." />
            <x-field name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
        </div>
        <div>
            <label class="flex items-start gap-2.5 text-sm text-muted">
                <input type="checkbox" name="terms" value="1" class="checkbox mt-0.5" @checked(old('terms'))>
                <span>I agree to the <a href="{{ route('pages.terms') }}" class="link">terms of use</a> and <a href="{{ route('pages.privacy') }}" class="link">privacy policy</a>.</span>
            </label>
            @error('terms')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn-primary btn-lg w-full">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">Already have an account? <a href="{{ route('login') }}" class="link">Sign in</a> · Dealer? <a href="{{ route('dealer.register') }}" class="link">Register here</a></p>
</x-layouts.auth>
