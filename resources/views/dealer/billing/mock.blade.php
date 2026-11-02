<x-layouts.dashboard title="Test checkout" section="dealer" heading="Test checkout" subheading="Development only: no real payment provider and no real money.">
    <div class="mx-auto max-w-xl">
        <div class="mb-6 flex gap-3 rounded-xl border-2 border-dashed border-amber-400 bg-amber-50 p-4 text-sm text-amber-900" role="note">
            <x-icon name="alert" class="size-5 shrink-0" />
            <p><strong>TEST MODE.</strong> This page simulates a payment gateway so the billing workflow can be tested locally. It is not a payment provider, nothing is charged, and anything activated here is marked as a test and left out of revenue. It is never available on the live site.</p>
        </div>

        <section class="card card-body">
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Payment</dt><dd class="font-semibold">{{ $payment->reference }}</dd></div>
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Invoice</dt><dd>{{ $payment->invoice->number }}</dd></div>
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">For</dt><dd class="text-right">{{ $payment->invoice->description }}</dd></div>
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Amount</dt><dd class="font-bold">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</dd></div>
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-muted">Status</dt><dd><x-status-badge :tone="$payment->status->tone()" :label="$payment->status->label()" /></dd></div>
            </dl>

            @if ($payment->status->value === 'pending')
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <form method="POST" action="{{ route('dealer.billing.mock.complete', $payment) }}">@csrf <input type="hidden" name="outcome" value="succeeded"><button class="btn btn-primary w-full">Simulate successful payment</button></form>
                    <form method="POST" action="{{ route('dealer.billing.mock.complete', $payment) }}">@csrf <input type="hidden" name="outcome" value="failed"><button class="btn btn-secondary w-full">Simulate failed payment</button></form>
                </div>
            @else
                <a href="{{ route('dealer.billing.invoices.show', $payment->invoice_id) }}" class="btn btn-secondary mt-6">Back to the invoice</a>
            @endif
        </section>
    </div>
</x-layouts.dashboard>
