<x-layouts.dashboard title="Profile" section="account" heading="Profile" subheading="Your contact details are only shared with dealers you contact or approve.">
    <form method="POST" action="{{ route('account.profile.update') }}" class="card card-body max-w-2xl space-y-5">
        @csrf @method('PUT')
        <x-field name="name" label="Full name" :value="$user->name" required autocomplete="name" />
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" value="{{ $user->email }}" class="input" disabled>
            <p class="field-hint">Contact support to change your email address.</p>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="phone" type="tel" label="Phone" :value="$user->phone" placeholder="0300 1234567" autocomplete="tel" />
            <x-field name="city" type="select" label="City" :value="$user->city" placeholder="Select" :options="array_combine(config('meezan.cities'), config('meezan.cities'))" />
        </div>
        <button class="btn btn-primary">Save profile</button>
    </form>
</x-layouts.dashboard>
