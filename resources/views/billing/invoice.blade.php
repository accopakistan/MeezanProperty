@php
    use App\Enums\InvoiceStatus;
    $snapshot = $invoice->billing_snapshot;
    $isReceipt = in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Refunded], true);
@endphp
<x-layouts.dashboard :title="($isReceipt ? 'Receipt ' : 'Invoice ').$invoice->number" :section="$admin ? 'admin' : 'dealer'" :heading="($isReceipt ? 'Receipt ' : 'Invoice ').$invoice->number" :subheading="$invoice->description">
    <div class="mb-6 flex flex-wrap items-center gap-3 print:hidden">
        <a href="{{ $back }}" class="btn btn-secondary btn-sm"><x-icon name="arrow-left" class="size-4" /> Back</a>
        <button type="button" class="btn btn-secondary btn-sm" data-print>Print</button>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <article class="card card-body min-w-0 print:border-0 print:shadow-none" aria-label="Invoice document">
            <header class="flex flex-wrap items-start justify-between gap-6 border-b border-line pb-6">
                <div>
                    <x-logo class="h-12 w-auto" />
                    <p class="mt-3 text-xs text-muted">Meezan Property</p>
                </div>
                <div class="text-right">
                    <p class="eyebrow">{{ $isReceipt ? 'Payment receipt' : 'Invoice' }}</p>
                    <p class="mt-1 text-xl font-extrabold">{{ $invoice->number }}</p>
                    <div class="mt-2"><x-status-badge :tone="$invoice->status->tone()" :label="$invoice->status->label()" /></div>
                    @if ($invoice->is_test)<p class="mt-2 text-xs font-bold text-amber-700">TEST RECORD: no real payment</p>@endif
                </div>
            </header>

            <div class="grid gap-6 border-b border-line py-6 text-sm sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-muted uppercase">Billed to</p>
                    <p class="mt-1 font-semibold">{{ $snapshot['dealer']['agency'] ?? $snapshot['dealer']['name'] }}</p>
                    @if (! empty($snapshot['dealer']['agency']))<p>{{ $snapshot['dealer']['name'] }}</p>@endif
                    <p class="text-muted">{{ $snapshot['dealer']['email'] }}</p>
                    @if (! empty($snapshot['dealer']['city']))<p class="text-muted">{{ $snapshot['dealer']['city'] }}</p>@endif
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 sm:text-right">
                    <dt class="text-muted">Issued</dt><dd>{{ $invoice->issued_at->format('j M Y') }}</dd>
                    @if ($invoice->due_at && ! $isReceipt)<dt class="text-muted">Due</dt><dd>{{ $invoice->due_at->format('j M Y') }}</dd>@endif
                    @if ($invoice->paid_at)<dt class="text-muted">Paid</dt><dd>{{ $invoice->paid_at->format('j M Y') }}</dd>@endif
                    @if ($invoice->payment_method)<dt class="text-muted">Method</dt><dd>{{ app(\App\Services\Billing\Gateways\GatewayManager::class)->label($invoice->payment_method) }}</dd>@endif
                    @if ($invoice->gateway_reference)<dt class="text-muted">Reference</dt><dd class="break-all">{{ $invoice->gateway_reference }}</dd>@endif
                </dl>
            </div>

            <div class="mt-2 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Description</th><th>Period</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    <tr>
                        <td>{{ $invoice->description }}<p class="text-xs text-muted">{{ $snapshot['package']['name'] ?? '' }}: {{ ($snapshot['package']['limits']['listings'] ?? null) === null ? 'unlimited' : $snapshot['package']['limits']['listings'] }} listings, {{ ($snapshot['package']['limits']['leads'] ?? null) === null ? 'unlimited' : $snapshot['package']['limits']['leads'] }} leads a month</p></td>
                        <td class="text-[13px] whitespace-nowrap text-muted">{{ $invoice->period_start ? $invoice->period_start->format('j M Y').' – '.$invoice->period_end?->format('j M Y') : 'From payment date' }}</td>
                        <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr><td colspan="2" class="text-right font-bold">Total</td><td class="text-right text-lg font-extrabold whitespace-nowrap tabular-nums">{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td></tr>
                    @if ((float) $invoice->refunded_amount > 0)
                        <tr><td colspan="2" class="text-right text-muted">Refunded</td><td class="text-right whitespace-nowrap tabular-nums text-muted">− {{ $invoice->currency }} {{ number_format((float) $invoice->refunded_amount, 2) }}</td></tr>
                    @endif
                </tfoot>
            </table>
            </div>

            <p class="mt-6 border-t border-line pt-4 text-xs text-muted">This document records a Meezan Property subscription charge. It is not a tax invoice: tax treatment has not been reviewed for Pakistan, and no sales tax is charged or shown.</p>
        </article>

        <aside class="min-w-0 space-y-6 print:hidden">
            @if (! $admin && $invoice->status->isOpen())
                <section class="card card-body">
                    <h2 class="font-bold">Pay this invoice</h2>
                    @php $awaiting = $invoice->payments->firstWhere('status', \App\Enums\PaymentStatus::AwaitingReview); @endphp
                    @if ($awaiting)
                        <p class="mt-2 text-sm text-muted">Your bank transfer ({{ $awaiting->payer_reference }}) is awaiting confirmation.</p>
                        <a href="{{ route('dealer.billing.payments.show', $awaiting) }}" class="btn btn-secondary btn-sm mt-3">View</a>
                    @elseif ($gateways)
                        <form method="POST" action="{{ route('dealer.billing.invoices.pay', $invoice) }}" class="mt-3 space-y-2">
                            @csrf
                            @foreach ($gateways as $key => $gateway)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-line p-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="gateway" value="{{ $key }}" class="accent-brand-600" @checked($loop->first) required> {{ $gateway->label() }}
                                </label>
                            @endforeach
                            @error('gateway')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            <button class="btn btn-primary mt-2 w-full">Continue to payment</button>
                        </form>
                    @else
                        <p class="mt-2 text-sm text-muted">Online payment is not available yet. Please contact Meezan Property to arrange payment.</p>
                    @endif
                </section>
            @endif

            <section class="card card-body">
                <h2 class="font-bold">Payments</h2>
                @forelse ($invoice->payments as $payment)
                    <div class="mt-3 flex items-start justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            @if ($admin)<a href="{{ route('admin.payments.show', $payment) }}" class="font-semibold hover:text-brand-700">{{ $payment->reference }}</a>@else<p class="font-semibold">{{ $payment->reference }}</p>@endif
                            <p class="text-xs text-muted">{{ $payment->gatewayLabel() }} · {{ $payment->created_at->format('j M Y') }}</p>
                        </div>
                        <x-status-badge :tone="$payment->status->tone()" :label="$payment->status->label()" />
                    </div>
                @empty
                    <p class="mt-2 text-sm text-muted">No payment attempts yet.</p>
                @endforelse
            </section>

            @if ($invoice->adjustments->isNotEmpty())
                <section class="card card-body">
                    <h2 class="font-bold">Refunds and adjustments</h2>
                    @foreach ($invoice->adjustments as $adjustment)
                        <div class="mt-3 text-sm">
                            <p class="font-semibold">{{ \App\Models\BillingAdjustment::TYPES[$adjustment->type] }}: {{ $adjustment->currency }} {{ number_format((float) $adjustment->amount, 2) }}</p>
                            <p class="text-xs text-muted">{{ $adjustment->created_at->format('j M Y') }}@if ($admin) · {{ $adjustment->creator?->name }}@endif · {{ $adjustment->reason }}</p>
                        </div>
                    @endforeach
                </section>
            @endif

            @if ($admin && in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Refunded], true) && $invoice->refundable() > 0)
                <section class="card card-body">
                    <h2 class="font-bold">Record a refund</h2>
                    <p class="mt-1 text-xs text-muted">Records a refund already made through the bank or gateway. It does not send money.</p>
                    <form method="POST" action="{{ route('admin.invoices.refund', $invoice) }}" class="mt-3 space-y-3">
                        @csrf
                        <x-field name="amount" type="number" label="Amount" step="0.01" min="0.01" :max="$invoice->refundable()" :value="$invoice->refundable()" required />
                        <x-field name="reason" label="Reason" required maxlength="500" />
                        <x-field name="external_reference" label="Bank or gateway reference" maxlength="120" />
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="end_subscription" value="1" class="accent-brand-600"> End the subscription now</label>
                        <button class="btn btn-danger btn-sm w-full">Record refund</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
