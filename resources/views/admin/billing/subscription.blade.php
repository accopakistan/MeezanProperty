@php use App\Enums\SubscriptionStatus; @endphp
<x-layouts.dashboard :title="'Subscription #'.$subscription->id" section="admin" :heading="$subscription->name().' · '.($dealer->dealerProfile?->agency_name ?? $dealer->name)" :subheading="'Subscription #'.$subscription->id.' · '.$dealer->email">
    <x-breadcrumbs :items="[['Subscriptions', route('admin.subscriptions')], ['#'.$subscription->id, null]]" class="mb-6" />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <section class="card card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 class="font-bold">Details</h2>
                    <x-status-badge :tone="$subscription->status->tone()" :label="$subscription->status->label()" />
                </div>
                <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Package (as purchased)' => $subscription->name().($subscription->package_snapshot['is_demo'] ?? false ? ' (draft)' : ''),
                        'Change type' => ucfirst($subscription->change_type),
                        'Amount' => $subscription->isPaid() ? $subscription->currency.' '.number_format((float) $subscription->amount, 2).' / '.$subscription->billing_interval : 'Free',
                        'Started' => $subscription->starts_at?->format('j M Y, g:i a') ?? '—',
                        'Current period' => $subscription->current_period_start ? $subscription->current_period_start->format('j M Y').' – '.($subscription->current_period_end?->format('j M Y') ?? 'no end') : '—',
                        'Trial ends' => $subscription->trial_ends_at?->format('j M Y') ?? '—',
                        'Scheduled change' => $subscription->scheduledPackage?->name ?? '—',
                        'Cancels at period end' => $subscription->cancel_at_period_end ? 'Yes' : 'No',
                        'Ended' => $subscription->ended_at ? $subscription->ended_at->format('j M Y').' · '.$subscription->end_reason : '—',
                        'Previous subscription' => $subscription->previous ? '#'.$subscription->previous->id.' '.$subscription->previous->name() : '—',
                        'Test record' => $subscription->is_test ? 'Yes (mock gateway)' : 'No',
                    ] as $label => $value)
                        <div class="flex justify-between gap-3 border-b border-line py-1.5 sm:block sm:border-0"><dt class="text-muted">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-4 text-xs text-muted">Limits as purchased:
                    @foreach (\App\Models\Package::LIMITS as $key => [$column, $label]){{ $label }} {{ $subscription->limit($key) ?? '∞' }}@if (! $loop->last), @endif @endforeach.
                    Features: {{ collect($subscription->package_snapshot['features'] ?? [])->map(fn ($f) => config('meezan.billing.features.'.$f, $f))->implode(', ') ?: 'none' }}.</p>
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Dealer usage now</h2>
                <p class="mt-1 text-xs text-muted">Plan in force: {{ $plan->name }} ({{ $plan->source }})</p>
                <div class="mt-4 grid gap-6 sm:grid-cols-3">
                    <x-usage-meter label="Active listings" :used="$usage['listings']" :limit="$plan->limit('listings')" />
                    <x-usage-meter label="Leads this month" :used="$usage['leads']" :limit="$plan->limit('leads')" />
                    <x-usage-meter label="Featured" :used="$usage['featured']" :limit="$plan->limit('featured')" />
                </div>
            </section>

            <section class="card overflow-hidden">
                <h2 class="card-body pb-0 font-bold">Invoices</h2>
                @if ($subscription->invoices->isEmpty())
                    <p class="card-body text-sm text-muted">No invoices (free package or trial).</p>
                @else
                    <div class="mt-3 overflow-x-auto"><table class="table">
                        <thead><tr><th>Invoice</th><th>Kind</th><th class="text-right">Amount</th><th>Status</th><th>Payments</th></tr></thead>
                        <tbody>
                            @foreach ($subscription->invoices as $invoice)
                                <tr>
                                    <td><a href="{{ route('admin.invoices.show', $invoice) }}" class="font-semibold hover:text-brand-700">{{ $invoice->number }}</a><p class="text-xs text-muted">{{ $invoice->issued_at->format('j M Y') }}</p></td>
                                    <td class="text-[13px]">{{ ucfirst($invoice->kind) }}</td>
                                    <td class="text-right tabular-nums">{{ $invoice->currency }} {{ number_format((float) $invoice->amount) }}</td>
                                    <td><x-status-badge :tone="$invoice->status->tone()" :label="$invoice->status->label()" /></td>
                                    <td class="text-[13px]">@foreach ($invoice->payments as $payment)<a href="{{ route('admin.payments.show', $payment) }}" class="block hover:text-brand-700">{{ $payment->reference }} · {{ $payment->status->label() }}</a>@endforeach</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                @endif
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Dealer’s subscription history</h2>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($history as $row)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <a href="{{ route('admin.subscriptions.show', $row) }}" @class(['hover:text-brand-700', 'font-bold' => $row->is($subscription)])>#{{ $row->id }} {{ $row->name() }}</a>
                            <span class="text-xs text-muted">{{ $row->created_at->format('j M Y') }} · {{ $row->status->label() }}@if ($row->end_reason) · {{ $row->end_reason }}@endif</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Activity</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($log as $entry)
                        <li><span class="font-semibold">{{ $entry->actionLabel() }}</span> <span class="text-xs text-muted">{{ $entry->created_at->format('j M Y, g:i a') }} · {{ $entry->user?->name ?? 'System' }}</span>
                            @if ($entry->properties)<span class="block text-xs text-muted">{{ collect($entry->properties)->map(fn ($v, $k) => $k.': '.(is_array($v) ? json_encode($v) : $v))->implode(' · ') }}</span>@endif</li>
                    @empty
                        <li class="text-muted">No activity recorded.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card card-body">
                <h2 class="font-bold">Actions</h2>
                @error('decision')<p class="mt-2 rounded-lg bg-red-50 p-2 text-sm text-red-700">{{ $message }}</p>@enderror
                @if ($subscription->status->inForce())
                    <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}" class="mt-3 space-y-3">
                        @csrf @method('PATCH')
                        <x-field name="reason" label="Reason (shown in the audit log; sent to the dealer when suspending)" maxlength="255" />
                        <div class="grid gap-2">
                            <button name="decision" value="suspend" class="btn btn-secondary btn-sm">Suspend</button>
                            @if ($subscription->isPaid() && $subscription->current_period_end?->isFuture() && ! $subscription->cancel_at_period_end)
                                <button name="decision" value="cancel" class="btn btn-secondary btn-sm">Cancel at period end</button>
                            @endif
                            <button name="decision" value="cancel_now" class="btn btn-danger btn-sm">End now</button>
                        </div>
                    </form>
                @elseif ($subscription->status === SubscriptionStatus::Suspended)
                    <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}" class="mt-3 space-y-2">
                        @csrf @method('PATCH')
                        <button name="decision" value="reinstate" class="btn btn-primary btn-sm w-full">Reinstate</button>
                    </form>
                    <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}" class="mt-2 space-y-2">
                        @csrf @method('PATCH')
                        <x-field name="reason" label="Reason" maxlength="255" />
                        <button name="decision" value="cancel_now" class="btn btn-danger btn-sm w-full">End now</button>
                    </form>
                @elseif ($subscription->status === SubscriptionStatus::PendingPayment)
                    <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}" class="mt-3 space-y-2">
                        @csrf @method('PATCH')
                        <x-field name="reason" label="Reason" maxlength="255" />
                        <button name="decision" value="cancel_now" class="btn btn-secondary btn-sm w-full">Cancel this checkout</button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-muted">This subscription has ended. Paid packages can only start again through a new payment.</p>
                @endif
                <p class="mt-3 text-xs text-muted">Staff cannot activate a paid package without a payment. Approve a bank transfer under Payments instead.</p>
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Credits and debits</h2>
                <p class="mt-1 text-xs text-muted">A record for the accounts (e.g. a goodwill credit). It does not change invoices or limits.</p>
                <form method="POST" action="{{ route('admin.billing.adjustments', $dealer) }}" class="mt-3 space-y-3">
                    @csrf
                    <x-field name="type" type="select" label="Type" :options="['credit' => 'Credit', 'debit' => 'Debit']" required />
                    <x-field name="amount" type="number" label="Amount ({{ config('meezan.billing.currency') }})" step="0.01" min="0.01" required />
                    <x-field name="reason" label="Reason" maxlength="500" required />
                    <button class="btn btn-secondary btn-sm w-full">Record</button>
                </form>
                @foreach ($adjustments as $adjustment)
                    <p class="mt-3 text-xs"><span class="font-semibold">{{ \App\Models\BillingAdjustment::TYPES[$adjustment->type] }} {{ $adjustment->currency }} {{ number_format((float) $adjustment->amount, 2) }}</span> · {{ $adjustment->created_at->format('j M Y') }} · {{ $adjustment->creator?->name }}@if ($adjustment->invoice) · {{ $adjustment->invoice->number }}@endif<span class="block text-muted">{{ $adjustment->reason }}</span></p>
                @endforeach
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
