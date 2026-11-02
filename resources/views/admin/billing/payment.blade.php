@php use App\Enums\PaymentStatus; @endphp
<x-layouts.dashboard :title="'Payment '.$payment->reference" section="admin" :heading="'Payment '.$payment->reference" :subheading="$payment->gatewayLabel().' · '.($payment->user->dealerProfile?->agency_name ?? $payment->user->name)">
    <x-breadcrumbs :items="[['Payments', route('admin.payments')], [$payment->reference, null]]" class="mb-6" />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
            <section class="card card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-3xl font-extrabold tabular-nums">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</p>
                        <p class="text-sm text-muted">For <a href="{{ route('admin.invoices.show', $payment->invoice_id) }}" class="font-semibold text-brand-700 hover:underline">{{ $payment->invoice->number }}</a>: {{ $payment->invoice->description }}</p>
                    </div>
                    <x-status-badge :tone="$payment->status->tone()" :label="$payment->status->label()" />
                </div>
                @if ($payment->is_test)<p class="mt-3 rounded-lg bg-amber-50 p-2 text-sm font-semibold text-amber-800">Test payment (mock gateway). No money moved; excluded from revenue.</p>@endif
                <dl class="mt-5 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Dealer' => $payment->user->name.' · '.$payment->user->email,
                        'Method' => $payment->gatewayLabel(),
                        'Started' => $payment->created_at->format('j M Y, g:i a'),
                        'Transfer reference (from dealer)' => $payment->payer_reference ?? '—',
                        'Reported' => $payment->submitted_at?->format('j M Y, g:i a') ?? '—',
                        'Gateway reference' => $payment->gateway_reference ?? '—',
                        'Confirmed' => $payment->confirmed_at?->format('j M Y, g:i a') ?? '—',
                        'Reviewed' => $payment->reviewed_at ? $payment->reviewed_at->format('j M Y').' by '.$payment->reviewer?->name : '—',
                        'Note / reason' => $payment->review_note ?? $payment->failure_reason ?? '—',
                    ] as $label => $value)
                        <div class="border-b border-line py-1.5"><dt class="text-xs text-muted">{{ $label }}</dt><dd class="font-medium break-words">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($payment->proof_path)
                    <a href="{{ route('admin.payments.proof', $payment) }}" class="btn btn-secondary btn-sm mt-5" target="_blank" rel="noopener"><x-icon name="file-text" class="size-4" /> Open proof of payment</a>
                    <p class="mt-1 text-xs text-muted">SHA-256 {{ $payment->proof_sha256 }}</p>
                @endif
            </section>

            @if ($sameReference->isNotEmpty())
                <section class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-bold">This transaction reference also appears on:</p>
                    <ul class="mt-1 list-disc pl-5">@foreach ($sameReference as $other)<li><a href="{{ route('admin.payments.show', $other) }}" class="underline">{{ $other->reference }}</a> ({{ $other->invoice?->number }}, {{ $other->status->label() }})</li>@endforeach</ul>
                </section>
            @endif

            <section class="card card-body">
                <h2 class="font-bold">Activity</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($log as $entry)
                        <li><span class="font-semibold">{{ $entry->actionLabel() }}</span> <span class="text-xs text-muted">{{ $entry->created_at->format('j M Y, g:i a') }} · {{ $entry->user?->name ?? 'System' }}</span></li>
                    @empty
                        <li class="text-muted">No activity recorded.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside>
            @if ($payment->status === PaymentStatus::AwaitingReview)
                <section class="card card-body">
                    <h2 class="font-bold">Review this transfer</h2>
                    <p class="mt-1 text-sm text-muted">Check the bank statement for {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }} with reference {{ $payment->payer_reference }} before approving. A screenshot alone is not confirmation.</p>
                    @error('decision')<p class="mt-2 rounded-lg bg-red-50 p-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    <form method="POST" action="{{ route('admin.payments.review', $payment) }}" class="mt-4 space-y-3">
                        @csrf @method('PATCH')
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm_received" value="1" class="mt-1 accent-brand-600"> I have confirmed the money arrived in the account.</label>
                        @error('confirm_received')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <x-field name="note" type="textarea" label="Note (required to reject; sent to the dealer)" rows="3" maxlength="500" />
                        <div class="grid grid-cols-2 gap-2">
                            <button name="decision" value="approve" class="btn btn-primary btn-sm">Approve</button>
                            <button name="decision" value="reject" class="btn btn-danger btn-sm">Reject</button>
                        </div>
                    </form>
                </section>
            @else
                <section class="card card-body text-sm text-muted">
                    <h2 class="font-bold text-ink">No review needed</h2>
                    <p class="mt-1">{{ $payment->gateway === 'manual' ? ($payment->status === PaymentStatus::Pending ? 'The dealer has not reported a transfer yet.' : 'This transfer has been decided.') : 'Gateway payments are confirmed by the gateway, not by staff.' }}</p>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
