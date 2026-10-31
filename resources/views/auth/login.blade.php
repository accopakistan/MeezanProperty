<x-layouts.auth title="Sign in" heading="Welcome back" subheading="Sign in to your buyer, dealer or admin account.">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <x-field name="email" type="email" label="Email" required autofocus autocomplete="email" />
        <div>
            <x-field name="password" type="password" label="Password" required autocomplete="current-password" />
            <a href="{{ route('password.request') }}" class="mt-2 inline-block text-[13px] font-semibold text-brand-600 hover:underline">Forgot your password?</a>
        </div>
        <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="remember" value="1" class="checkbox"> Keep me signed in</label>
        <button class="btn btn-primary btn-lg w-full">Sign in</button>
    </form>
    <div class="mt-8 grid gap-3 border-t border-line pt-6 text-sm sm:grid-cols-2">
        <a href="{{ route('register') }}" class="rounded-xl border border-line p-4 transition hover:border-brand-300">
            <span class="font-semibold">Looking for property?</span>
            <span class="mt-0.5 block text-muted">Create a buyer account</span>
        </a>
        <a href="{{ route('dealer.register') }}" class="rounded-xl border border-line p-4 transition hover:border-brand-300">
            <span class="font-semibold">Property dealer?</span>
            <span class="mt-0.5 block text-muted">Register your agency</span>
        </a>
    </div>
</x-layouts.auth>
