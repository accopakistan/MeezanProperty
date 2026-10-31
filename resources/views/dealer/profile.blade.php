<x-layouts.dashboard title="Agency profile" section="dealer" heading="Agency profile" subheading="How your agency appears to buyers, and where you receive leads.">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form method="POST" action="{{ route('dealer.profile.update') }}" class="space-y-6">
            @csrf @method('PUT')
            <section class="card card-body space-y-5">
                <h2 class="text-base font-bold">Contact person</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="name" label="Full name" :value="$dealer->name" required />
                    <x-field name="phone" type="tel" label="Mobile (private)" :value="$dealer->phone" required hint="Used by Meezan Property only." />
                </div>
            </section>

            <section class="card card-body space-y-5">
                <h2 class="text-base font-bold">Agency</h2>
                <x-field name="agency_name" label="Agency name" :value="$profile->agency_name" required hint="Changing this after verification is recorded for review." />
                <x-field name="office_address" label="Office address" :value="$profile->office_address" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="registration_number" label="Registration / licence no." :value="$profile->registration_number" />
                    <x-field name="years_in_business" type="number" min="0" max="80" label="Years in business" :value="$profile->years_in_business" />
                </div>
                <x-field name="bio" type="textarea" rows="4" label="About your agency" :value="$profile->bio" />
            </section>

            <section class="card card-body space-y-5">
                <div>
                    <h2 class="text-base font-bold">Public contact</h2>
                    <p class="mt-1 text-[13px] text-muted">Shown on your listings only once your agency is verified. Leave blank to receive inquiries through the website form only.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="public_phone" type="tel" label="Public phone" :value="$profile->public_phone" placeholder="042 1234567" />
                    <x-field name="whatsapp_number" type="tel" label="WhatsApp number" :value="$profile->whatsapp_number" placeholder="0300 1234567" />
                </div>
            </section>

            <section class="card card-body space-y-4">
                <h2 class="text-base font-bold">Cities, areas &amp; leads</h2>
                <p class="text-sm text-muted">Head office: {{ $profile->city }}</p>
                <x-coverage-picker :home-city="$profile->city" :cities="(array) old('cities', $profile->cities->pluck('name')->all())" :areas="$selectedAreas" :suggestions="$suggestions" />
                <input type="hidden" name="accepts_leads" value="0">
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="accepts_leads" value="1" class="checkbox" @checked(old('accepts_leads', $profile->accepts_leads))> Send me buyer requirement leads for these cities and areas</label>
            </section>

            <section class="card card-body space-y-3">
                <h2 class="text-base font-bold">Email notifications</h2>
                <input type="hidden" name="notify_account_email" value="0">
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" name="notify_account_email" value="1" class="checkbox mt-0.5" @checked(old('notify_account_email', auth()->user()->notify_account_email))>
                    <span>Email me about new inquiries and leads, review decisions on my listings, and listings about to expire
                        <span class="block text-xs text-muted">Sent to {{ auth()->user()->email }}{{ auth()->user()->hasVerifiedEmail() ? '' : ' (not confirmed yet)' }}. Everything is also shown in your dashboard.</span>
                    </span>
                </label>
            </section>

            <button class="btn btn-primary">Save profile</button>
        </form>

        <div class="space-y-6">
            <section class="card card-body">
                <p class="text-xs font-bold tracking-wide text-muted uppercase">Verification</p>
                <div class="mt-2"><x-status-badge :tone="$profile->verification_status->tone()" :label="$profile->verification_status->label()" /></div>
                @if ($profile->verified_at)
                    <p class="mt-2 text-[13px] text-muted">Verified on {{ $profile->verified_at->format('j M Y') }}</p>
                @endif
                @if ($profile->rejection_reason)
                    <p class="mt-3 rounded-lg bg-red-50 p-3 text-[13px] text-red-800">{{ $profile->rejection_reason }}</p>
                @endif
            </section>

            <form method="POST" action="{{ route('dealer.password.update') }}" class="card card-body space-y-4">
                @csrf @method('PUT')
                <h2 class="text-base font-bold">Change password</h2>
                <x-field name="current_password" type="password" label="Current password" required autocomplete="current-password" />
                <x-field name="password" type="password" label="New password" required autocomplete="new-password" />
                <x-field name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" />
                <button class="btn btn-secondary">Update password</button>
            </form>
        </div>
    </div>
</x-layouts.dashboard>
