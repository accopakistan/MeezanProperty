@php
    $isDowngradeLater = $outcome === 'scheduled';
    $trial = $outcome === 'trial';
    $needsPayment = $outcome === 'payment_required';
    $overListings = $package->listing_limit !== null && $usage['listings'] > $package->listing_limit;
@endphp
<x-layouts.dashboard :title="'Choose '.$package->name" section="dealer" :heading="'Choose '.$package->name" subheading="Check the details before you continue.">
    <x-breadcrumbs :items="[['Packages & Payments', route('dealer.subscription')], ['Compare packages', route('dealer.billing.packages')], [$package->name, null]]" class="mb-6" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="card card-body">
            <h2 class="text-lg font-extrabold">Summary</h2>
            <dl class="mt-4 divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Package</dt><dd class="font-semibold">{{ $package->name }} @if ($package->is_demo)<span class="badge ml-1 bg-amber-50 text-amber-700 ring-1 ring-amber-200 ring-inset">Draft</span>@endif</dd></div>
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Price</dt><dd class="font-semibold">{{ $package->isFree() ? 'Free' : $package->currency.' '.number_format((float) $package->price, 2).' / '.$package->intervalLabel() }}</dd></div>
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Currently</dt><dd>{{ $current?->name() ?? $planName }}</dd></div>
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Starts</dt><dd class="text-right">
                    @if ($isDowngradeLater) {{ $current->current_period_end->format('j M Y') }}, when your paid period ends
                    @elseif ($trial) Today, with a {{ $package->trial_days }}-day free trial
                    @elseif ($needsPayment) As soon as your payment is confirmed
                    @else Today @endif
                </dd></div>
            </dl>

            @if ($type === 'upgrade' && $current?->isPaid() && $needsPayment)
                <p class="mt-4 rounded-lg bg-page p-3 text-xs text-muted">The new package starts a fresh {{ $package->intervalLabel() }} from the day it is paid. Unused time on your current package is not credited automatically; contact us if you need an adjustment.</p>
            @endif
            @if ($overListings)
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">You have {{ $usage['listings'] }} active listings and {{ $package->name }} allows {{ $package->listing_limit }}.
                    {{ config('meezan.billing.over_limit_policy') === 'require_within_limit' ? 'Archive listings first to switch to this package.' : 'Nothing will be removed: your listings stay live, but you cannot add new ones until you are under the limit.' }}</p>
            @endif

            @error('package')<p class="mt-4 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700" role="alert">{{ $message }}</p>@enderror
            @error('gateway')<p class="mt-4 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700" role="alert">{{ $message }}</p>@enderror

            @if ($current?->package_id === $package->id && ! $current->scheduled_package_id)
                <p class="mt-6 text-sm font-semibold text-muted">This is your current package.</p>
            @elseif ($awaitingReview)
                <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">A bank transfer is awaiting review. You can change package once it has been checked.</p>
            @else
                <form method="POST" action="{{ route('dealer.billing.choose', $package) }}" class="mt-6">
                    @csrf
                    @if ($needsPayment)
                        <fieldset>
                            <legend class="label">Payment method</legend>
                            @forelse ($gateways as $key => $gateway)
                                <label class="mt-2 flex cursor-pointer items-start gap-3 rounded-xl border border-line p-4 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="gateway" value="{{ $key }}" class="mt-1 accent-brand-600" @checked($loop->first) required>
                                    <span>
                                        <span class="block font-semibold">{{ $gateway->label() }}</span>
                                        <span class="block text-xs text-muted">
                                            {{ $gateway->isTest() ? 'For testing only. No money moves and the package is marked as a test.' : ($key === 'manual' ? 'Transfer the amount and send us the transaction reference. We activate the package once the money has arrived.' : '') }}
                                        </span>
                                    </span>
                                </label>
                            @empty
                                <p class="rounded-lg bg-page p-3 text-sm text-muted">Online payment is not available yet. Please contact Meezan Property to arrange payment.</p>
                            @endforelse
                        </fieldset>
                        @if ($gateways)
                            <button class="btn btn-primary mt-6 w-full sm:w-auto">Continue to payment</button>
                            <p class="mt-2 text-xs text-muted">An invoice is created now. Your package changes only after payment is confirmed.</p>
                        @endif
                    @else
                        <button class="btn btn-primary w-full sm:w-auto">{{ $isDowngradeLater ? 'Schedule the change' : ($trial ? 'Start free trial' : 'Switch to '.$package->name) }}</button>
                    @endif
                </form>
            @endif
        </section>

        <x-package-card :package="$package" />
    </div>
</x-layouts.dashboard>
