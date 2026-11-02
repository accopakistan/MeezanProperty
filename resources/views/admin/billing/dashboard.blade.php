@php
    $money = fn ($sums) => $sums->isEmpty() ? '—' : $sums->map(fn ($amount, $currency) => $currency.' '.number_format($amount))->implode(' · ');
@endphp
<x-layouts.dashboard title="Billing" section="admin" heading="Billing" subheading="Subscriptions and payments from recorded data. Test (mock) payments are never counted as revenue.">
    <div class="mb-5 flex flex-wrap gap-3">
        <a href="{{ route('admin.packages.index') }}" class="btn btn-secondary btn-sm">Packages</a>
        <a href="{{ route('admin.subscriptions') }}" class="btn btn-secondary btn-sm">Subscriptions</a>
        <a href="{{ route('admin.payments') }}" class="btn btn-secondary btn-sm">Payments</a>
    </div>

    @if ($counts['awaiting_review'])
        <a href="{{ route('admin.payments', ['status' => 'awaiting_review']) }}" class="mb-6 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900 hover:bg-amber-100">
            <x-icon name="clock" class="size-5" /> {{ $counts['awaiting_review'] }} bank {{ \Illuminate\Support\Str::plural('transfer', $counts['awaiting_review']) }} awaiting review <x-icon name="arrow-right" class="ml-auto size-4" />
        </a>
    @endif

    <section aria-labelledby="revenue" class="mb-8">
        <h2 id="revenue" class="mb-3 text-base font-bold">Revenue</h2>
        <div class="grid gap-3 md:grid-cols-3">
            <div class="card card-body">
                <p class="text-[13px] text-muted">Paid this month (net)</p>
                <p class="mt-1 text-2xl font-bold">{{ $money($paid) }}</p>
                <p class="mt-1 text-xs text-muted">Invoices paid {{ now()->format('F Y') }}: {{ $money($gross) }}; refunds recorded: {{ $money($refunds) }}</p>
            </div>
            <div class="card card-body">
                <p class="text-[13px] text-muted">Pending (unpaid invoices)</p>
                <p class="mt-1 text-2xl font-bold">{{ $money($pending) }}</p>
                <p class="mt-1 text-xs text-muted">Issued but not paid. Not revenue until paid.</p>
            </div>
            <div class="card card-body">
                <p class="text-[13px] text-muted">Expected monthly (running paid plans)</p>
                <p class="mt-1 text-2xl font-bold">{{ $money($expected) }}</p>
                <p class="mt-1 text-xs text-muted">If every running paid subscription renews; yearly plans ÷ 12. An expectation, not income.</p>
            </div>
        </div>
        @if ($counts['test_payments'])
            <p class="mt-3 text-xs text-amber-700">{{ $counts['test_payments'] }} test {{ \Illuminate\Support\Str::plural('payment', $counts['test_payments']) }} and {{ $counts['test_subscriptions'] }} running test {{ \Illuminate\Support\Str::plural('subscription', $counts['test_subscriptions']) }} exist (mock gateway). They are excluded from every figure on this page.</p>
        @endif
    </section>

    <section aria-labelledby="subs" class="mb-8">
        <h2 id="subs" class="mb-3 text-base font-bold">Subscriptions and payments</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Active subscriptions', $counts['active'], route('admin.subscriptions', ['status' => 'active'])],
                ['On trial', $counts['trial'], route('admin.subscriptions', ['status' => 'trial'])],
                ['Payment overdue', $counts['past_due'], route('admin.subscriptions', ['status' => 'past_due'])],
                ['Ending in 7 days', $counts['expiring'], route('admin.subscriptions')],
                ['Awaiting payment (checkouts)', $counts['pending_payment'], route('admin.subscriptions', ['status' => 'pending_payment'])],
                ['Payments pending', $counts['payments_pending'], route('admin.payments', ['status' => 'pending'])],
                ['Transfers to review', $counts['awaiting_review'], route('admin.payments', ['status' => 'awaiting_review'])],
                ['Failed payments (30 days)', $counts['failed_30d'], route('admin.payments', ['status' => 'failed'])],
            ] as [$label, $count, $url])
                <a href="{{ $url }}" class="card card-body transition hover:shadow-lift"><p class="text-[13px] text-muted">{{ $label }}</p><p class="mt-1 text-3xl font-bold {{ $count ? 'text-ink' : 'text-muted' }}">{{ number_format($count) }}</p></a>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card card-body" aria-labelledby="distribution">
            <h2 id="distribution" class="font-bold">Dealers by package</h2>
            @php $total = max(1, $distribution->sum('count')); @endphp
            <div class="mt-4 space-y-3">
                @foreach ($distribution as $row)
                    <div>
                        <div class="flex justify-between text-sm"><span>{{ $row['name'] }}</span><span class="font-semibold tabular-nums">{{ $row['count'] }}</span></div>
                        <div class="mt-1 h-2 rounded-full bg-page"><div class="h-full rounded-full bg-brand-600" style="width: {{ round($row['count'] / $total * 100) }}%"></div></div>
                    </div>
                @endforeach
            </div>
            @unless ($hasDefault)
                <p class="mt-4 text-xs text-muted">No default plan is set, so dealers without a subscription use the fallback limits. <a href="{{ route('admin.packages.index') }}" class="font-semibold text-brand-700 hover:underline">Set one</a>.</p>
            @endunless
        </section>

        <section class="card card-body" aria-labelledby="gateways">
            <h2 id="gateways" class="font-bold">Payment methods</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @foreach ($gateways as $key => $gateway)
                    <li class="flex items-center justify-between gap-3">
                        <span>{{ $gateway->label() }}@if ($gateway->isTest())<span class="block text-xs text-muted">Local testing only; never available in production</span>@endif</span>
                        <x-status-badge :tone="$gateway->isAvailable() ? 'success' : 'muted'" :label="$gateway->isAvailable() ? 'Available' : 'Not configured'" />
                    </li>
                @endforeach
                <li class="flex items-center justify-between gap-3 text-muted"><span>Online gateway (JazzCash, Easypaisa, card)</span><x-status-badge tone="muted" label="Not integrated" /></li>
            </ul>
        </section>
    </div>

    <section class="mt-8" aria-labelledby="recent">
        <h2 id="recent" class="mb-3 text-base font-bold">Recent payments</h2>
        @if ($recent->isEmpty())
            <div class="card card-body text-sm text-muted">No payments recorded yet.</div>
        @else
            <div class="card overflow-hidden"><div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Payment</th><th>Dealer</th><th>Invoice</th><th>Method</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $payment)
                            <tr>
                                <td><a href="{{ route('admin.payments.show', $payment) }}" class="font-semibold hover:text-brand-700">{{ $payment->reference }}</a> @if ($payment->is_test)<span class="badge bg-amber-50 text-amber-700">Test</span>@endif</td>
                                <td class="text-[13px]">{{ $payment->user?->name }}</td>
                                <td class="text-muted">{{ $payment->invoice?->number }}</td>
                                <td class="text-[13px]">{{ $payment->gatewayLabel() }}</td>
                                <td class="text-right tabular-nums">{{ $payment->currency }} {{ number_format((float) $payment->amount) }}</td>
                                <td><x-status-badge :tone="$payment->status->tone()" :label="$payment->status->label()" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
        @endif
    </section>
</x-layouts.dashboard>
