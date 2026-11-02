@php use App\Enums\PaymentStatus; @endphp
<x-layouts.dashboard :title="'Payment '.$payment->reference" section="dealer" heading="Pay by bank transfer" :subheading="'Invoice '.$payment->invoice->number">
    <x-breadcrumbs :items="[['Packages & Payments', route('dealer.subscription')], [$payment->invoice->number, route('dealer.billing.invoices.show', $payment->invoice_id)], [$payment->reference, null]]" class="mb-6" />

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card card-body">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-lg font-extrabold">Amount due</h2>
                <x-status-badge :tone="$payment->status->tone()" :label="$payment->status->label()" />
            </div>
            <p class="mt-2 text-3xl font-extrabold tabular-nums">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</p>
            <p class="mt-1 text-sm text-muted">{{ $payment->invoice->description }}</p>

            @if ($account)
                <dl class="mt-6 divide-y divide-line rounded-xl border border-line text-sm">
                    @foreach ($account as $label => $value)
                        <div class="flex justify-between gap-4 px-4 py-2.5"><dt class="text-muted">{{ $label }}</dt><dd class="text-right font-semibold break-all">{{ $value }}</dd></div>
                    @endforeach
                    <div class="flex justify-between gap-4 bg-gold-50 px-4 py-2.5"><dt class="text-muted">Payment reference</dt><dd class="font-mono font-bold">{{ $payment->invoice->number }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-muted">Please write the payment reference in the transfer description so we can match your payment.</p>
            @else
                <p class="mt-6 rounded-lg bg-page p-3 text-sm text-muted">Bank details are not available. Please contact Meezan Property.</p>
            @endif
        </section>

        <section class="card card-body">
            @if ($payment->status === PaymentStatus::Pending)
                <h2 class="text-lg font-extrabold">After you have transferred</h2>
                <p class="mt-1 text-sm text-muted">Enter the transaction reference from your bank or app. We check that the money has arrived before activating your package.</p>
                <form method="POST" action="{{ route('dealer.billing.payments.transfer', $payment) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf
                    <x-field name="payer_reference" label="Transaction reference" required maxlength="120" placeholder="e.g. FT2611021234" />
                    <div>
                        <label for="proof" class="label">Receipt or screenshot (optional)</label>
                        <input id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="input py-2">
                        <p class="mt-1 text-xs text-muted">JPG, PNG, WebP or PDF, up to {{ intdiv(config('meezan.billing.gateways.manual.max_proof_kb'), 1024) }} MB. Stored privately; only you and Meezan Property staff can open it. Do not include card numbers, PINs or passwords.</p>
                        @error('proof')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn btn-primary">Submit for confirmation</button>
                </form>
            @elseif ($payment->status === PaymentStatus::AwaitingReview)
                <h2 class="text-lg font-extrabold">Awaiting confirmation</h2>
                <p class="mt-2 text-sm text-muted">You reported transaction <strong class="text-ink">{{ $payment->payer_reference }}</strong> on {{ $payment->submitted_at->format('j M Y, g:i a') }}. We will activate your package once the transfer is confirmed, and let you know.</p>
                @if ($payment->proof_path)
                    <a href="{{ route('dealer.billing.payments.proof', $payment) }}" class="btn btn-secondary btn-sm mt-4" target="_blank" rel="noopener">View the receipt you sent</a>
                @endif
            @elseif ($payment->status === PaymentStatus::Succeeded)
                <h2 class="text-lg font-extrabold text-emerald-700">Payment confirmed</h2>
                <p class="mt-2 text-sm text-muted">Confirmed on {{ $payment->confirmed_at?->format('j M Y') }}. <a href="{{ route('dealer.billing.invoices.show', $payment->invoice_id) }}" class="font-semibold text-brand-700 hover:underline">View receipt</a></p>
            @else
                <h2 class="text-lg font-extrabold">{{ $payment->status->label() }}</h2>
                @if ($payment->review_note || $payment->failure_reason)<p class="mt-2 text-sm">Reason: {{ $payment->review_note ?: $payment->failure_reason }}</p>@endif
                @if ($payment->invoice->status->isOpen())
                    <a href="{{ route('dealer.billing.invoices.show', $payment->invoice_id) }}" class="btn btn-primary btn-sm mt-4">Try again</a>
                @endif
            @endif
        </section>
    </div>
</x-layouts.dashboard>
