<x-layouts.auth title="List as an owner" heading="List your property as an owner" subheading="Create a free account. You can add your listing straight after.">
    <form method="POST" action="{{ route('sell.owner.store') }}" class="space-y-5">
        @csrf
        <x-field name="name" label="Full name" required autocomplete="name" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="email" type="email" label="Email" required autocomplete="email" />
            <x-field name="phone" type="tel" label="Mobile number" placeholder="0300 1234567" required autocomplete="tel" hint="Not shown publicly." />
        </div>
        <x-field name="city" type="select" label="City" :value="old('city', config('meezan.default_city'))" required :options="array_combine(config('meezan.cities'), config('meezan.cities'))" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="password" type="password" label="Password" required autocomplete="new-password" />
            <x-field name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
        </div>
        <div>
            @include('auth.partials.terms-consent')
            @error('terms')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn-primary btn-lg w-full">Create owner account</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">A dealer or agency? <a href="{{ route('dealer.register') }}" class="link">Register your agency</a></p>
</x-layouts.auth>
