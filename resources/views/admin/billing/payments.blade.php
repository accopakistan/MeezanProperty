@php use App\Enums\PaymentStatus; @endphp
<x-layouts.dashboard title="Payments" section="admin" heading="Payments" subheading="Every payment attempt. Bank transfers awaiting review are listed first.">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="flex gap-1 overflow-x-auto rounded-xl border border-line bg-white p-1 scrollbar-none">
            <a href="{{ route('admin.payments') }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => empty($filters['status']), 'text-muted hover:text-ink' => ! empty($filters['status'])])>All <span class="text-xs opacity-75">{{ $counts->sum() }}</span></a>
            @foreach (PaymentStatus::cases() as $status)
                <a href="{{ route('admin.payments', ['status' => $status->value]) }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => ($filters['status'] ?? null) === $status->value, 'text-muted hover:text-ink' => ($filters['status'] ?? null) !== $status->value])>{{ $status->label() }} <span class="text-xs opacity-75">{{ $counts[$status->value] ?? 0 }}</span></a>
            @endforeach
        </div>
        <form method="GET" class="ml-auto flex gap-2">
            @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="input w-56 py-2" placeholder="Payment, invoice or transfer ref." aria-label="Search payments">
            <button class="btn btn-secondary btn-sm">Search</button>
        </form>
    </div>

    @if ($payments->isEmpty())
        <x-empty-state icon="file-text" title="No payments found">Payments appear here when dealers pay an invoice.</x-empty-state>
    @else
        <div class="card overflow-hidden"><div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Payment</th><th>Dealer</th><th>Invoice</th><th>Method</th><th class="text-right">Amount</th><th>Status</th><th>Date</th><th class="sr-only">Open</th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="whitespace-nowrap"><a href="{{ route('admin.payments.show', $payment) }}" class="font-semibold hover:text-brand-700">{{ $payment->reference }}</a> @if ($payment->is_test)<span class="badge bg-amber-50 text-amber-700">Test</span>@endif
                                @if ($payment->payer_reference)<p class="text-xs text-muted">Ref. {{ $payment->payer_reference }}</p>@endif</td>
                            <td class="text-[13px]"><p>{{ $payment->user?->name }}</p><p class="text-muted">{{ $payment->user?->email }}</p></td>
                            <td class="text-[13px]"><a href="{{ route('admin.invoices.show', $payment->invoice_id) }}" class="hover:text-brand-700">{{ $payment->invoice?->number }}</a></td>
                            <td class="text-[13px] whitespace-nowrap">{{ $payment->gatewayLabel() }}</td>
                            <td class="text-right whitespace-nowrap tabular-nums">{{ $payment->currency }} {{ number_format((float) $payment->amount) }}</td>
                            <td><x-status-badge :tone="$payment->status->tone()" :label="$payment->status->label()" /></td>
                            <td class="whitespace-nowrap text-muted">{{ ($payment->submitted_at ?? $payment->created_at)->format('j M Y') }}</td>
                            <td class="text-right"><a href="{{ route('admin.payments.show', $payment) }}" class="btn btn-secondary btn-sm">{{ $payment->status === PaymentStatus::AwaitingReview ? 'Review' : 'Open' }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
        <div class="mt-6">{{ $payments->links() }}</div>
    @endif
</x-layouts.dashboard>
