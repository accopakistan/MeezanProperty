@php
    use App\Enums\SubscriptionStatus;
    $limits = ['listings' => $plan->limit('listings'), 'leads' => $plan->limit('leads'), 'featured' => $plan->limit('featured')];
    // Featured listings can also be chosen by staff, so being over the featured
    // allowance only blocks featuring more; it is not reported as a plan breach.
    $over = collect($limits)->except('featured')->filter(fn ($limit, $key) => $limit !== null && $usage[$key] > $limit)->keys();
    $nearLimit = collect($limits)->except('featured')->filter(fn ($limit, $key) => $limit !== null && $usage[$key] >= $limit * 0.8)->isNotEmpty();
    $renews = $subscription?->renewsOrEndsAt();
@endphp
<x-layouts.dashboard title="Packages & Payments" section="dealer" heading="Packages & Payments" subheading="Your package, what you have used, and your billing history.">
    @if ($pending)
        <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
            <x-icon name="clock" class="size-5 shrink-0" />
            <p class="min-w-0 flex-1"><strong>{{ $pending->name() }}</strong> is waiting for payment. It starts as soon as the payment is confirmed; until then your current limits apply.</p>
            @if ($invoice = $openInvoices->firstWhere('subscription_id', $pending->id))
                <a href="{{ route('dealer.billing.invoices.show', $invoice) }}" class="btn btn-primary btn-sm">Continue to payment</a>
            @endif
            <form method="POST" action="{{ route('dealer.billing.cancel') }}">
                @csrf <input type="hidden" name="checkout" value="1">
                <button class="btn btn-secondary btn-sm">Cancel checkout</button>
            </form>
        </div>
    @endif

    @foreach ($openInvoices->where('subscription_id', '!=', $pending?->id) as $invoice)
        <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
            <x-icon name="file-text" class="size-5 shrink-0" />
            <p class="min-w-0 flex-1">Invoice <strong>{{ $invoice->number }}</strong> ({{ $invoice->description }}) for {{ $invoice->currency }} {{ number_format((float) $invoice->amount) }} is unpaid{{ $invoice->due_at ? ", due ".$invoice->due_at->format('j M Y') : "" }}.</p>
            <a href="{{ route('dealer.billing.invoices.show', $invoice) }}" class="btn btn-primary btn-sm">View and pay</a>
        </div>
    @endforeach

    @if ($over->isNotEmpty())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-bold">You are over your package limits.</p>
            <p class="mt-1">Nothing has been removed: your existing listings stay live. You cannot add more {{ $over->implode(' or ') }} until you are under the limit or upgrade.</p>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
        <section class="card overflow-hidden" aria-labelledby="current-package">
            <div class="bg-brand-800 p-6 text-white">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="eyebrow text-gold-300">Current package</p>
                        <h2 id="current-package" class="mt-2 text-2xl font-extrabold">{{ $plan->name }}</h2>
                    </div>
                    @if ($subscription)
                        <x-status-badge :tone="$subscription->status->tone()" :label="$subscription->status->label()" />
                    @endif
                </div>

                @if ($subscription)
                    <p class="mt-2 text-sm text-white/75">
                        @if ($subscription->isPaid())
                            {{ $subscription->currency }} {{ number_format((float) $subscription->amount) }} / {{ $subscription->billing_interval === 'year' ? 'year' : 'month' }}
                        @else
                            Free package
                        @endif
                        @if ($subscription->is_test)<span class="ml-1 rounded bg-amber-400/20 px-1.5 py-0.5 text-xs font-semibold text-amber-200">Test payment</span>@endif
                    </p>
                    @if ($renews)
                        <p class="mt-1 text-sm text-white/75">
                            @if ($subscription->status === SubscriptionStatus::Trial) Trial ends
                            @elseif ($subscription->cancel_at_period_end || $subscription->scheduled_package_id) Ends
                            @elseif ($subscription->status === SubscriptionStatus::PastDue) Payment was due
                            @else Renews @endif
                            <strong class="text-white">{{ $renews->format('j M Y') }}</strong>
                        </p>
                    @endif
                @elseif ($plan->source === 'default')
                    <p class="mt-2 text-sm text-white/75">The free plan for every dealer. Choose a package for higher limits.</p>
                @else
                    <p class="mt-2 text-sm text-white/75">Launch access: listings and leads are not limited until Meezan Property sets a default plan. You can still choose a package.</p>
                @endif

                @if ($subscription?->cancel_at_period_end || $subscription?->scheduled_package_id)
                    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg bg-white/10 p-3 text-sm">
                        <p class="min-w-0 flex-1">
                            {{ $subscription->scheduled_package_id
                                ? 'Changing to '.($subscription->scheduledPackage?->name ?? 'another package').' on '.$subscription->current_period_end->format('j M Y').'.'
                                : 'Cancelled: you keep this package until '.$subscription->current_period_end->format('j M Y').'.' }}
                        </p>
                        <form method="POST" action="{{ route('dealer.billing.resume') }}">@csrf <button class="btn btn-on-dark btn-sm">Keep current package</button></form>
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('dealer.billing.packages') }}" class="btn btn-gold btn-sm">{{ $nearLimit || ! $subscription ? 'Upgrade package' : 'Compare packages' }}</a>
                    @if ($subscription && $subscription->status->inForce() && ! $subscription->cancel_at_period_end)
                        <details class="group">
                            <summary class="btn btn-on-dark btn-sm cursor-pointer list-none">Cancel package</summary>
                            <form method="POST" action="{{ route('dealer.billing.cancel') }}" class="mt-3 w-72 max-w-full rounded-lg bg-white p-4 text-ink shadow-lift">
                                @csrf
                                <p class="text-sm">{{ $subscription->isPaid() && $subscription->current_period_end?->isFuture() ? 'You keep '.$subscription->name().' until '.$subscription->current_period_end->format('j M Y').'. No further invoices are issued.' : 'Your account moves to the default plan now.' }}</p>
                                <x-field name="reason" label="Reason (optional)" class="mt-3" />
                                <button class="btn btn-danger btn-sm mt-3 w-full">Confirm cancellation</button>
                            </form>
                        </details>
                    @endif
                </div>
            </div>

            <div class="grid gap-6 p-6 sm:grid-cols-3">
                <x-usage-meter label="Active listings" :used="$usage['listings']" :limit="$limits['listings']" hint="Live and in review" />
                <x-usage-meter label="Leads this month" :used="$usage['leads']" :limit="$limits['leads']" hint="Resets on the 1st" />
                @if ($plan->hasFeature('featured_listings'))
                    <x-usage-meter label="Featured listings" :used="$usage['featured']" :limit="$limits['featured']" hint="Live listings you feature" />
                @else
                    <div class="min-w-0">
                        <p class="text-[13px] font-semibold text-ink">Featured listings</p>
                        <p class="mt-1 text-sm text-muted"><span class="font-bold text-ink">{{ $usage['featured'] }}</span> live · featuring your own listings is not included in this package</p>
                    </div>
                @endif
            </div>
            <p class="border-t border-line px-6 py-4 text-xs text-muted">Drafts, rejected, expired and archived listings don’t count. Leads are buyer requirements we share with you; enquiries on your own listings always reach you and aren’t counted, and spam or duplicates marked by our team aren’t counted either.</p>
        </section>

        <section aria-labelledby="upgrade-options">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="upgrade-options" class="text-base font-bold">Packages</h2>
                <a href="{{ route('dealer.billing.packages') }}" class="text-sm font-semibold text-brand-700 hover:underline">Compare all</a>
            </div>
            @forelse ($packages->reject(fn ($p) => $p->id === $subscription?->package_id)->take(3) as $package)
                <a href="{{ route('dealer.billing.checkout', $package) }}" class="card mb-3 flex items-center gap-4 p-4 transition hover:border-brand-200 hover:shadow-lift">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full {{ $package->is_recommended ? 'bg-gold-100 text-gold-600' : 'bg-brand-50 text-brand-600' }}"><x-icon name="star" class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-bold">{{ $package->name }} @if ($package->is_demo)<span class="badge ml-1 bg-amber-50 text-amber-700 ring-1 ring-amber-200 ring-inset">Draft</span>@endif</span>
                        <span class="block text-xs text-muted">{{ $package->listing_limit === null ? 'Unlimited' : $package->listing_limit }} listings · {{ $package->lead_limit === null ? 'unlimited' : $package->lead_limit }} leads a month</span>
                    </span>
                    <span class="text-right text-sm font-bold whitespace-nowrap">{{ $package->isFree() ? 'Free' : $package->currency.' '.number_format((float) $package->price) }}@unless ($package->isFree())<span class="block text-xs font-normal text-muted">/ {{ $package->intervalLabel() }}</span>@endunless</span>
                </a>
            @empty
                <x-empty-state icon="credit-card" title="No packages yet">Packages will appear here once Meezan Property publishes them.</x-empty-state>
            @endforelse
        </section>
    </div>

    <section class="mt-8" aria-labelledby="billing-history">
        <h2 id="billing-history" class="mb-3 text-base font-bold">Billing history</h2>
        @if ($invoices->isEmpty())
            <div class="card card-body text-sm text-muted">No invoices yet. Invoices appear here when you choose a paid package.</div>
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Invoice</th><th>Description</th><th>Issued</th><th class="text-right">Amount</th><th>Status</th><th class="sr-only">Open</th></tr></thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td class="font-semibold whitespace-nowrap">{{ $invoice->number }} @if ($invoice->is_test)<span class="badge ml-1 bg-amber-50 text-amber-700">Test</span>@endif</td>
                                    <td class="min-w-48 text-[13px]">{{ $invoice->description }}</td>
                                    <td class="whitespace-nowrap text-muted">{{ $invoice->issued_at->format('j M Y') }}</td>
                                    <td class="text-right whitespace-nowrap tabular-nums">{{ $invoice->currency }} {{ number_format((float) $invoice->amount) }}</td>
                                    <td><x-status-badge :tone="$invoice->status->tone()" :label="$invoice->status->label()" /></td>
                                    <td class="text-right"><a href="{{ route('dealer.billing.invoices.show', $invoice) }}" class="btn btn-secondary btn-sm">{{ $invoice->status->isOpen() ? 'Pay' : 'View' }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $invoices->links() }}</div>
        @endif
    </section>

    <section class="mt-8" aria-labelledby="payment-history">
        <h2 id="payment-history" class="mb-3 text-base font-bold">Payment history</h2>
        @if ($payments->isEmpty())
            <div class="card card-body text-sm text-muted">No payments yet.</div>
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Payment</th><th>Invoice</th><th>Method</th><th class="text-right">Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="font-semibold whitespace-nowrap">
                                        @if ($payment->gateway === 'manual')<a href="{{ route('dealer.billing.payments.show', $payment) }}" class="hover:text-brand-700">{{ $payment->reference }}</a>@else{{ $payment->reference }}@endif
                                    </td>
                                    <td class="whitespace-nowrap text-muted">{{ $payment->invoice?->number }}</td>
                                    <td class="text-[13px] whitespace-nowrap">{{ $payment->gatewayLabel() }}</td>
                                    <td class="text-right whitespace-nowrap tabular-nums">{{ $payment->currency }} {{ number_format((float) $payment->amount) }}</td>
                                    <td><x-status-badge :tone="$payment->status->tone()" :label="$payment->status->label()" />@if ($payment->failure_reason && $payment->status->value === 'failed')<p class="mt-1 max-w-56 text-xs text-muted">{{ $payment->failure_reason }}</p>@endif</td>
                                    <td class="whitespace-nowrap text-muted">{{ ($payment->confirmed_at ?? $payment->created_at)->format('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $payments->links() }}</div>
        @endif
    </section>
</x-layouts.dashboard>
