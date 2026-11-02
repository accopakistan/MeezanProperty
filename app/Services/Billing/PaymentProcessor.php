<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BillingAdjustment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BillingAdminAlert;
use App\Notifications\BillingUpdated;
use App\Services\AuditLogger;
use App\Services\Billing\Gateways\GatewayEvent;
use App\Services\Billing\Gateways\GatewayManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only place payments change state. Gateway events (webhooks, the test
 * checkout) and staff decisions on bank transfers all end up in confirm()
 * or fail(), inside a transaction with the payment row locked, so the same
 * outcome applied twice changes nothing the second time.
 */
class PaymentProcessor
{
    public function __construct(protected GatewayManager $gateways) {}

    /** Start a payment attempt for an open invoice. */
    public function start(Invoice $invoice, string $gatewayKey, User $by): Payment
    {
        if (! $invoice->status->isOpen()) {
            throw new InvalidArgumentException('This invoice is not awaiting payment.');
        }
        if (! $this->gateways->has($gatewayKey) || ! $this->gateways->get($gatewayKey)->isAvailable()) {
            throw new InvalidArgumentException('That payment method is not available.');
        }
        if ($invoice->payments()->where('status', PaymentStatus::AwaitingReview)->exists()) {
            throw new InvalidArgumentException('A payment for this invoice is already awaiting review.');
        }
        $gateway = $this->gateways->get($gatewayKey);

        return DB::transaction(function () use ($invoice, $gateway, $by) {
            // Only one live attempt per invoice.
            $invoice->payments()->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Cancelled->value, 'failure_reason' => 'Replaced by a new attempt', 'updated_at' => now()]);

            $payment = new Payment;
            $payment->forceFill([
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'gateway' => $gateway->key(),
                'status' => PaymentStatus::Pending,
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
                'is_test' => $gateway->isTest(),
            ])->save();

            if ($invoice->status === InvoiceStatus::Failed) {
                $invoice->forceFill(['status' => InvoiceStatus::Pending])->save();
            }

            AuditLogger::record('payment.started', $payment, ['invoice' => $invoice->number, 'gateway' => $gateway->key(), 'amount' => (string) $invoice->amount], $by->id);
            if (! $gateway->isTest()) {
                $invoice->user->notify(new BillingUpdated('payment_pending', $invoice->subscription, ['invoice_id' => $invoice->id, 'number' => $invoice->number, 'payment_id' => $payment->id]));
            }

            return $payment;
        });
    }

    /**
     * Dealer reports a bank transfer. Nothing is activated: the payment
     * waits for staff, who check the money has actually arrived.
     */
    public function submitTransfer(Payment $payment, string $payerReference, ?UploadedFile $proof, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $payerReference, $proof, $by) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->gateway !== 'manual' || $payment->status !== PaymentStatus::Pending) {
                throw new InvalidArgumentException('This payment cannot take a transfer reference.');
            }

            $proofData = [];
            if ($proof) {
                $path = $proof->storeAs('billing-proofs/'.$payment->user_id, Str::uuid().'.'.$proof->extension(), 'local');
                $proofData = ['proof_path' => $path, 'proof_mime' => $proof->getMimeType(), 'proof_sha256' => hash_file('sha256', $proof->getRealPath())];
            }

            $payerReference = trim($payerReference);
            $reused = Payment::whereKeyNot($payment->id)->where('payer_reference', $payerReference)->whereIn('status', [PaymentStatus::AwaitingReview->value, PaymentStatus::Succeeded->value])->exists();

            $payment->forceFill(['status' => PaymentStatus::AwaitingReview, 'payer_reference' => $payerReference, 'submitted_at' => now()] + $proofData)->save();

            AuditLogger::record('payment.transfer_submitted', $payment, array_filter(['invoice' => $payment->invoice->number, 'proof' => $proof ? true : null, 'reference_used_before' => $reused ?: null]), $by->id);
            Notification::send(User::activeAdmins(), new BillingAdminAlert('transfer_submitted', $payment, $reused ? 'This transaction reference was already used on another payment.' : null));

            return $payment;
        });
    }

    public function approveTransfer(Payment $payment, User $staff, ?string $note = null): void
    {
        DB::transaction(function () use ($payment, $staff, $note) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== PaymentStatus::AwaitingReview) {
                throw new InvalidArgumentException('This payment is not awaiting review.');
            }
            $payment->forceFill(['reviewed_by' => $staff->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            AuditLogger::record('payment.transfer_approved', $payment, ['invoice' => $payment->invoice->number, 'from' => 'awaiting_review', 'to' => 'succeeded', 'note' => $note], $staff->id);
            $this->confirm($payment, null);
        });
    }

    public function rejectTransfer(Payment $payment, User $staff, string $reason): void
    {
        DB::transaction(function () use ($payment, $staff, $reason) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== PaymentStatus::AwaitingReview) {
                throw new InvalidArgumentException('This payment is not awaiting review.');
            }
            $payment->forceFill(['reviewed_by' => $staff->id, 'reviewed_at' => now(), 'review_note' => $reason])->save();
            AuditLogger::record('payment.transfer_rejected', $payment, ['invoice' => $payment->invoice->number, 'from' => 'awaiting_review', 'to' => 'failed', 'reason' => $reason], $staff->id);
            $this->fail($payment, $reason, 'payment_rejected');
        });
    }

    /**
     * Apply a gateway's outcome. Returns "applied", "duplicate" (already in
     * that state) or "ignored" (conflicts with a final state; logged).
     */
    public function handle(GatewayEvent $event): string
    {
        return DB::transaction(function () use ($event) {
            $payment = Payment::where('reference', $event->paymentReference)->lockForUpdate()->first();
            if (! $payment || $payment->gateway !== $event->gateway) {
                throw new InvalidArgumentException('Unknown payment reference.');
            }

            $wanted = $event->outcome === GatewayEvent::SUCCEEDED ? PaymentStatus::Succeeded : PaymentStatus::Failed;
            if ($payment->status->isFinal()) {
                if ($payment->status === $wanted) {
                    return 'duplicate';
                }
                AuditLogger::record('payment.conflicting_event', $payment, ['event' => $event->eventId, 'current' => $payment->status->value, 'reported' => $event->outcome]);

                return 'ignored';
            }

            if ($wanted === PaymentStatus::Succeeded) {
                // Never confirm a payment for a different amount or currency than invoiced.
                if (($event->amount !== null && self::cents($event->amount) !== self::cents($payment->amount)) || ($event->currency !== null && strtoupper($event->currency) !== $payment->currency)) {
                    AuditLogger::record('payment.amount_mismatch', $payment, ['event' => $event->eventId, 'reported' => trim($event->amount.' '.$event->currency), 'expected' => $payment->amount.' '.$payment->currency]);
                    Notification::send(User::activeAdmins(), new BillingAdminAlert('payment_problem', $payment, 'The gateway reported a different amount or currency. Nothing was activated.'));
                    $this->fail($payment, 'Amount or currency did not match the invoice', 'payment_failed');

                    return 'applied';
                }
                $this->confirm($payment, $event->gatewayReference);
            } else {
                $this->fail($payment, $event->failureReason ?: 'The payment was not completed', 'payment_failed');
            }

            return 'applied';
        });
    }

    /**
     * Staff record a refund made through the bank or gateway. Does not move
     * money. A full refund marks the invoice refunded; ending the
     * subscription is a separate, explicit choice.
     */
    public function recordRefund(Invoice $invoice, string $amount, string $reason, User $staff, ?string $externalReference = null, bool $endSubscription = false): BillingAdjustment
    {
        return DB::transaction(function () use ($invoice, $amount, $reason, $staff, $externalReference, $endSubscription) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Refunded], true)) {
                throw new InvalidArgumentException('Only a paid invoice can be refunded.');
            }
            if (self::cents($amount) <= 0 || self::cents($amount) > self::cents($invoice->refundable())) {
                throw new InvalidArgumentException('The refund must be more than zero and no more than '.$invoice->currency.' '.number_format($invoice->refundable(), 2).'.');
            }

            $adjustment = new BillingAdjustment(['type' => 'refund', 'amount' => $amount, 'currency' => $invoice->currency, 'reason' => $reason, 'external_reference' => $externalReference]);
            $adjustment->user_id = $invoice->user_id;
            $adjustment->invoice_id = $invoice->id;
            $adjustment->payment_id = $invoice->payments()->where('status', PaymentStatus::Succeeded)->value('id');
            $adjustment->created_by = $staff->id;
            $adjustment->save();

            $refunded = number_format((self::cents($invoice->refunded_amount) + self::cents($amount)) / 100, 2, '.', '');
            $full = self::cents($refunded) >= self::cents($invoice->amount);
            $invoice->forceFill(['refunded_amount' => $refunded, 'status' => $full ? InvoiceStatus::Refunded : $invoice->status])->save();
            if ($full) {
                $invoice->payments()->where('status', PaymentStatus::Succeeded)->update(['status' => PaymentStatus::Refunded->value, 'updated_at' => now()]);
            }

            AuditLogger::record('invoice.refund_recorded', $invoice, ['amount' => $amount, 'full' => $full, 'reason' => $reason, 'external_reference' => $externalReference, 'end_subscription' => $endSubscription ?: null], $staff->id);

            if ($endSubscription && $invoice->subscription && ($invoice->subscription->status->inForce() || $invoice->subscription->status === SubscriptionStatus::Suspended)) {
                app(SubscriptionManager::class)->cancel($invoice->subscription, $staff, 'Refunded: '.$reason, immediately: true);
            }
            $invoice->user->notify(new BillingUpdated('refund_recorded', $invoice->subscription, ['invoice_id' => $invoice->id, 'number' => $invoice->number, 'amount' => $invoice->currency.' '.number_format((float) $amount, 2)]));

            return $adjustment;
        });
    }

    /** Staff record a credit or debit (an audit record; balances are not applied automatically). */
    public function recordAdjustment(User $dealer, string $type, string $amount, string $reason, User $staff, ?Invoice $invoice = null): BillingAdjustment
    {
        if (! in_array($type, ['credit', 'debit'], true) || self::cents($amount) <= 0) {
            throw new InvalidArgumentException('Enter a credit or debit greater than zero.');
        }
        $adjustment = new BillingAdjustment(['type' => $type, 'amount' => $amount, 'currency' => $invoice?->currency ?? config('meezan.billing.currency'), 'reason' => $reason]);
        $adjustment->user_id = $dealer->id;
        $adjustment->invoice_id = $invoice?->id;
        $adjustment->created_by = $staff->id;
        $adjustment->save();
        AuditLogger::record('billing.adjustment_recorded', $dealer, ['type' => $type, 'amount' => $amount, 'reason' => $reason, 'invoice' => $invoice?->number], $staff->id);

        return $adjustment;
    }

    /** Payment succeeded: mark it, mark the invoice paid and start or extend the subscription. */
    protected function confirm(Payment $payment, ?string $gatewayReference): void
    {
        $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();

        $payment->forceFill(['status' => PaymentStatus::Succeeded, 'confirmed_at' => now(), 'gateway_reference' => $gatewayReference ?? $payment->gateway_reference, 'failure_reason' => null])->save();
        AuditLogger::record('payment.succeeded', $payment, ['invoice' => $invoice->number, 'gateway' => $payment->gateway, 'test' => $payment->is_test ?: null]);

        if ($invoice->status === InvoiceStatus::Paid) {
            // Paid twice (e.g. a transfer and a gateway payment): keep both records, ask staff to refund one.
            AuditLogger::record('invoice.paid_twice', $invoice, ['payment' => $payment->reference]);
            Notification::send(User::activeAdmins(), new BillingAdminAlert('payment_problem', $payment, 'This invoice was already paid. Check whether a refund is due.'));

            return;
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'payment_method' => $payment->gateway,
            'gateway_reference' => $payment->gateway_reference ?? $payment->payer_reference,
            'is_test' => $invoice->is_test || $payment->is_test,
        ])->save();
        AuditLogger::record('invoice.paid', $invoice, ['payment' => $payment->reference, 'amount' => (string) $invoice->amount]);
        $invoice->user->notify(new BillingUpdated('payment_succeeded', $invoice->subscription, ['invoice_id' => $invoice->id, 'number' => $invoice->number]));

        if ($invoice->subscription_id) {
            app(SubscriptionManager::class)->activate($invoice, $payment->is_test);
        }
    }

    /** Money compared in paisa, avoiding float rounding. */
    public static function cents(string|float|int|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    protected function fail(Payment $payment, string $reason, string $notify): void
    {
        $payment->forceFill(['status' => PaymentStatus::Failed, 'failure_reason' => mb_substr($reason, 0, 250)])->save();
        // The invoice stays payable: the dealer can try again.
        if ($payment->invoice->status === InvoiceStatus::Pending) {
            $payment->invoice->forceFill(['status' => InvoiceStatus::Failed])->save();
        }
        AuditLogger::record('payment.failed', $payment, ['invoice' => $payment->invoice->number, 'reason' => $reason]);
        $payment->invoice->user->notify(new BillingUpdated($notify, $payment->invoice->subscription, ['invoice_id' => $payment->invoice_id, 'number' => $payment->invoice->number, 'reason' => $reason]));
    }
}
