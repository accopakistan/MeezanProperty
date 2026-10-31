<x-layouts.dashboard title="Settings" section="account" heading="Settings" subheading="Security and account preferences.">
    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        @php
            $mailReady = ! in_array(config('mail.default'), ['log', 'array'], true);
            $waReady = app(\App\Services\WhatsApp\WhatsAppClient::class)->canSend();
        @endphp
        <form method="POST" action="{{ route('account.notifications.update') }}" class="card card-body space-y-4 lg:col-span-2">
            @csrf @method('PUT')
            <h2 class="text-base font-bold">Notification preferences</h2>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="notify_in_app" value="1" class="checkbox mt-0.5" @checked($user->notify_in_app)>
                <span><span class="block font-semibold">In-app alerts</span><span class="text-muted">Show new listings for your saved searches under Alerts.</span></span>
            </label>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="notify_email" value="1" class="checkbox mt-0.5" @checked($user->notify_email)>
                <span><span class="block font-semibold">Email alerts for saved searches</span><span class="text-muted">Sent at the frequency you choose for each saved search.{{ $mailReady ? '' : ' Email delivery is not switched on yet; your choice is saved for when it is.' }}</span></span>
            </label>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="notify_whatsapp" value="1" class="checkbox mt-0.5" @checked($user->notify_whatsapp)>
                <span><span class="block font-semibold">WhatsApp updates</span><span class="text-muted">Only if you have contacted us on WhatsApp.{{ $waReady ? '' : ' WhatsApp messaging is not live yet; your choice is saved for when it is.' }}</span></span>
            </label>
            <button class="btn btn-primary w-max">Save preferences</button>
        </form>

        <form method="POST" action="{{ route('account.password.update') }}" class="card card-body space-y-4">
            @csrf @method('PUT')
            <h2 class="text-base font-bold">Change password</h2>
            <x-field name="current_password" type="password" label="Current password" required autocomplete="current-password" />
            <x-field name="password" type="password" label="New password" required autocomplete="new-password" />
            <x-field name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" />
            <button class="btn btn-primary">Update password</button>
        </form>
        <section class="card card-body">
            <h2 class="text-base font-bold">Your data</h2>
            <ul class="mt-3 space-y-2 text-sm text-muted">
                <li>Member since {{ $user->created_at->format('F Y') }}</li>
                <li>Last sign-in {{ $user->last_login_at?->diffForHumans() ?? '—' }}</li>
            </ul>
            <p class="mt-4 text-sm text-muted">To delete your account and data, <a href="{{ route('pages.contact') }}" class="link">contact our team</a>.</p>
        </section>
    </div>
</x-layouts.dashboard>
