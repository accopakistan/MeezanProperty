<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingUpdated;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Every subscription change goes through here.
 *
 * - Free packages start at once. Paid packages start only when a payment
 *   for their invoice succeeds (PaymentProcessor calls activate()); choosing
 *   one creates a subscription "awaiting payment" and an unpaid invoice.
 * - A package with trial days starts a trial (no payment) for a dealer
 *   who has never had one; the first invoice is issued before it ends.
 * - Upgrades take effect when paid; the new period starts that day (the
 *   unused part of the old period is not credited automatically).
 * - Downgrades from a paid plan take effect at the end of the paid period.
 * - Nothing is deleted: a replaced subscription is ended with a reason and
 *   the new one links back to it.
 */
class SubscriptionManager
{
    public function __construct(protected Entitlements $entitlements, protected PaymentProcessor $payments) {}

    public function current(User $dealer): ?Subscription
    {
        return Subscription::inForce()->where('user_id', $dealer->id)->latest('id')->first();
    }

    public function pendingCheckout(User $dealer): ?Subscription
    {
        return Subscription::where('user_id', $dealer->id)->where('status', SubscriptionStatus::PendingPayment)->latest('id')->first();
    }

    /** "upgrade", "downgrade" or "change", compared on monthly price with the dealer's current plan. */
    public function changeType(User $dealer, Package $package): string
    {
        $current = $this->current($dealer);
        $currentMonthly = $current ? $this->monthly((float) $current->amount, $current->billing_interval) : 0.0;
        $target = $package->monthlyPrice();

        return match (true) {
            ! $current && $target > 0 => 'upgrade',
            $target > $currentMonthly => 'upgrade',
            $target < $currentMonthly => 'downgrade',
            default => 'change',
        };
    }

    /**
     * What choosing this package would do: "scheduled" (a downgrade from a
     * paid period, applied when it ends), "activated" (free, now), "trial"
     * (first trial, now) or "payment_required".
     */
    public function outcome(User $dealer, Package $package): string
    {
        $current = $this->current($dealer);

        return match (true) {
            $this->changeType($dealer, $package) === 'downgrade' && $current?->isPaid() && $current->current_period_end?->isFuture() && $current->status !== SubscriptionStatus::Trial => 'scheduled',
            $package->isFree() => 'activated',
            $package->trial_days > 0 && ! $current?->isPaid() && ! Subscription::where('user_id', $dealer->id)->whereNotNull('trial_ends_at')->exists() => 'trial',
            default => 'payment_required',
        };
    }

    /**
     * A dealer chooses a package.
     *
     * @return array{result: string, subscription: Subscription, payment: ?Payment}
     *                                                                              result: activated, trial, scheduled, payment_required
     */
    public function choose(User $dealer, Package $package, ?string $gateway = null): array
    {
        if (! $dealer->isDealer()) {
            throw new InvalidArgumentException('Only dealer accounts can subscribe.');
        }
        if (! $package->is_active) {
            throw new InvalidArgumentException('This package is not available.');
        }
        if (Payment::where('user_id', $dealer->id)->where('status', PaymentStatus::AwaitingReview)->exists()) {
            throw new InvalidArgumentException('A bank transfer is awaiting review. You can change package once it has been checked.');
        }

        $current = $this->current($dealer);
        if ($current && $current->package_id === $package->id && ! $current->scheduled_package_id) {
            throw new InvalidArgumentException('You are already on '.$package->name.'.');
        }

        $type = $this->changeType($dealer, $package);
        $this->guardOverLimit($dealer, $package);

        switch ($this->outcome($dealer, $package)) {
            case 'scheduled':
                return ['result' => 'scheduled', 'subscription' => $this->scheduleChange($current, $package, $dealer), 'payment' => null];
            case 'activated':
                return ['result' => 'activated', 'subscription' => $this->startFree($dealer, $package, $type, $dealer), 'payment' => null];
            case 'trial':
                return ['result' => 'trial', 'subscription' => $this->startTrial($dealer, $package, $type), 'payment' => null];
        }

        if (! $gateway) {
            throw new InvalidArgumentException('Choose a payment method.');
        }

        return DB::transaction(function () use ($dealer, $package, $type, $gateway, $current) {
            $this->cancelPendingCheckouts($dealer, 'Replaced by a new package choice');

            $subscription = $this->newSubscription($dealer, $package, SubscriptionStatus::PendingPayment, $type, $current);
            $invoice = $this->issueInvoice($subscription, $type, null, null);
            $payment = $this->payments->start($invoice, $gateway, $dealer);

            AuditLogger::record('subscription.checkout_started', $subscription, ['package' => $package->slug, 'type' => $type, 'invoice' => $invoice->number, 'gateway' => $gateway], $dealer->id);

            return ['result' => 'payment_required', 'subscription' => $subscription, 'payment' => $payment];
        });
    }

    /**
     * A paid invoice: start or extend the subscription. Called by
     * PaymentProcessor inside its transaction, never from a controller.
     */
    public function activate(Invoice $invoice, bool $isTest = false): Subscription
    {
        $subscription = Subscription::whereKey($invoice->subscription_id)->lockForUpdate()->firstOrFail();
        $from = $subscription->status;
        $interval = $subscription->billing_interval;

        if ($invoice->kind === 'renewal' && $subscription->status->inForce()) {
            // Next period follows on from the current one.
            $start = $subscription->current_period_end && $subscription->current_period_end->isFuture() ? $subscription->current_period_end : now();
        } else {
            $start = now();
            $this->endOthers($subscription, 'Replaced by '.$subscription->name());
            $subscription->starts_at ??= $start;
        }

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $start,
            'current_period_end' => $this->addInterval($start->copy(), $interval),
            'expiry_reminder_sent_at' => null,
            'ended_at' => null,
            'end_reason' => null,
            'is_test' => $subscription->is_test || $isTest,
        ])->save();

        $invoice->forceFill(['period_start' => $subscription->current_period_start, 'period_end' => $subscription->current_period_end])->save();

        AuditLogger::record('subscription.activated', $subscription, [
            'from' => $from->value, 'to' => 'active', 'invoice' => $invoice->number,
            'period_end' => $subscription->current_period_end->toDateString(), 'test' => $isTest ?: null,
        ]);
        $subscription->user->notify(new BillingUpdated('subscription_activated', $subscription));
        $this->entitlements->flush($subscription->user);

        return $subscription;
    }

    /** Dealer or staff cancels. Paid periods run to their end; anything else ends now. */
    public function cancel(Subscription $subscription, User $by, ?string $reason = null, bool $immediately = false): void
    {
        DB::transaction(function () use ($subscription, $by, $reason, $immediately) {
            $subscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($subscription->status === SubscriptionStatus::PendingPayment) {
                $this->closeCheckout($subscription, $reason ?? 'Cancelled');
                AuditLogger::record('subscription.checkout_cancelled', $subscription, ['reason' => $reason], $by->id);

                return;
            }
            if (! $subscription->status->inForce() && $subscription->status !== SubscriptionStatus::Suspended) {
                throw new InvalidArgumentException('This subscription has already ended.');
            }

            $this->cancelOpenInvoices($subscription, 'Subscription cancelled');

            if (! $immediately && $subscription->status->inForce() && $subscription->isPaid() && $subscription->current_period_end?->isFuture()) {
                $subscription->forceFill(['cancel_at_period_end' => true, 'scheduled_package_id' => null, 'cancelled_at' => now(), 'end_reason' => $reason])->save();
                AuditLogger::record('subscription.cancellation_scheduled', $subscription, ['ends_at' => $subscription->current_period_end->toDateString(), 'reason' => $reason], $by->id);
                $subscription->user->notify(new BillingUpdated('subscription_cancel_scheduled', $subscription));

                return;
            }

            $from = $subscription->status;
            $subscription->forceFill(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now(), 'ended_at' => now(), 'end_reason' => $reason ?? 'Cancelled'])->save();
            AuditLogger::record('subscription.cancelled', $subscription, ['from' => $from->value, 'to' => 'cancelled', 'reason' => $reason, 'immediately' => $immediately ?: null], $by->id);
            $subscription->user->notify(new BillingUpdated('subscription_ended', $subscription));
        });
        $this->entitlements->flush($subscription->user);
    }

    /** Undo a scheduled cancellation or downgrade while the period is still running. */
    public function resume(Subscription $subscription, User $by): void
    {
        if (! $subscription->status->inForce() || (! $subscription->cancel_at_period_end && ! $subscription->scheduled_package_id)) {
            throw new InvalidArgumentException('Nothing to undo on this subscription.');
        }
        $subscription->forceFill(['cancel_at_period_end' => false, 'cancelled_at' => null, 'scheduled_package_id' => null, 'end_reason' => null])->save();
        AuditLogger::record('subscription.resumed', $subscription, [], $by->id);
    }

    public function suspend(Subscription $subscription, User $by, string $reason): void
    {
        if (! $subscription->status->inForce()) {
            throw new InvalidArgumentException('Only a running subscription can be suspended.');
        }
        $from = $subscription->status;
        $subscription->forceFill(['status' => SubscriptionStatus::Suspended, 'end_reason' => $reason])->save();
        AuditLogger::record('subscription.suspended', $subscription, ['from' => $from->value, 'to' => 'suspended', 'reason' => $reason], $by->id);
        $subscription->user->notify(new BillingUpdated('subscription_suspended', $subscription, ['reason' => $reason]));
        $this->entitlements->flush($subscription->user);
    }

    public function reinstate(Subscription $subscription, User $by): void
    {
        if ($subscription->status !== SubscriptionStatus::Suspended) {
            throw new InvalidArgumentException('Only a suspended subscription can be reinstated.');
        }
        $ended = $subscription->current_period_end && $subscription->current_period_end->isPast();
        $to = $ended ? SubscriptionStatus::Expired : SubscriptionStatus::Active;
        $subscription->forceFill(['status' => $to, 'end_reason' => $ended ? 'Period ended while suspended' : null, 'ended_at' => $ended ? now() : null])->save();
        AuditLogger::record('subscription.reinstated', $subscription, ['from' => 'suspended', 'to' => $to->value], $by->id);
        if (! $ended) {
            $subscription->user->notify(new BillingUpdated('subscription_activated', $subscription));
        }
        $this->entitlements->flush($subscription->user);
    }

    /**
     * Daily lifecycle (meezan:billing-lifecycle). Idempotent: safe to run
     * more than once a day.
     *
     * @return array<string, int>
     */
    public function runLifecycle(): array
    {
        $done = ['renewal_invoices' => 0, 'reminders' => 0, 'past_due' => 0, 'expired' => 0, 'ended' => 0, 'changed' => 0, 'checkouts_closed' => 0];
        $notice = now()->addDays((int) config('meezan.billing.renewal_notice_days'));

        // 1. Renewal invoices (and trial conversions) shortly before the period ends.
        Subscription::inForce()->where('amount', '>', 0)->where('cancel_at_period_end', false)->whereNull('scheduled_package_id')
            ->whereNotNull('current_period_end')->where('current_period_end', '<=', $notice)
            ->with('user')->chunkById(100, function ($subs) use (&$done) {
                foreach ($subs as $subscription) {
                    $exists = Invoice::where('subscription_id', $subscription->id)->where('kind', 'renewal')
                        ->where('period_start', $subscription->current_period_end)->whereNotIn('status', [InvoiceStatus::Cancelled->value])->exists();
                    if (! $exists) {
                        $invoice = $this->issueInvoice($subscription, 'renewal', $subscription->current_period_end, $this->addInterval($subscription->current_period_end->copy(), $subscription->billing_interval));
                        AuditLogger::record('invoice.renewal_issued', $invoice, ['subscription_id' => $subscription->id]);
                        $subscription->user->notify(new BillingUpdated('renewal_due', $subscription, ['invoice_id' => $invoice->id, 'number' => $invoice->number]));
                        $done['renewal_invoices']++;
                    }
                }
            });

        // 2. Ending soon without renewal (cancelled or scheduled to change): remind once.
        Subscription::inForce()->whereNull('expiry_reminder_sent_at')->where(fn ($q) => $q->where('cancel_at_period_end', true)->orWhereNotNull('scheduled_package_id'))
            ->whereNotNull('current_period_end')->where('current_period_end', '<=', $notice)->where('current_period_end', '>', now())
            ->with('user')->each(function (Subscription $subscription) use (&$done) {
                $subscription->forceFill(['expiry_reminder_sent_at' => now()])->save();
                $subscription->user->notify(new BillingUpdated('subscription_expiring', $subscription));
                $done['reminders']++;
            });

        // 3. Periods that have ended.
        Subscription::inForce()->whereNotNull('current_period_end')->where('current_period_end', '<=', now())
            ->with('user')->each(function (Subscription $subscription) use (&$done) {
                DB::transaction(function () use ($subscription, &$done) {
                    if ($subscription->cancel_at_period_end) {
                        $this->end($subscription, SubscriptionStatus::Cancelled, 'Cancelled by the dealer at the end of the period', 'subscription_ended');
                        $done['ended']++;
                    } elseif ($subscription->scheduled_package_id) {
                        $this->applyScheduledChange($subscription);
                        $done['changed']++;
                    } elseif ($subscription->status === SubscriptionStatus::PastDue) {
                        if ($subscription->current_period_end->copy()->addDays((int) config('meezan.billing.grace_days'))->isPast()) {
                            $this->cancelOpenInvoices($subscription, 'Subscription expired');
                            $this->end($subscription, SubscriptionStatus::Expired, 'Renewal not paid', 'subscription_expired');
                            $done['expired']++;
                        }
                    } elseif (! $subscription->isPaid()) {
                        $this->end($subscription, SubscriptionStatus::Expired, 'Period ended', 'subscription_expired');
                        $done['expired']++;
                    } else {
                        $from = $subscription->status;
                        $subscription->forceFill(['status' => SubscriptionStatus::PastDue])->save();
                        AuditLogger::record('subscription.past_due', $subscription, ['from' => $from->value, 'to' => 'past_due']);
                        $subscription->user->notify(new BillingUpdated('payment_overdue', $subscription));
                        $done['past_due']++;
                    }
                });
            });

        // 4. Checkouts never paid.
        Subscription::where('status', SubscriptionStatus::PendingPayment)->where('created_at', '<', now()->subDays((int) config('meezan.billing.checkout_expiry_days')))
            ->each(function (Subscription $subscription) use (&$done) {
                if (! Payment::whereIn('invoice_id', $subscription->invoices()->select('id'))->where('status', PaymentStatus::AwaitingReview)->exists()) {
                    $this->closeCheckout($subscription, 'Not paid within '.config('meezan.billing.checkout_expiry_days').' days');
                    AuditLogger::record('subscription.checkout_expired', $subscription);
                    $done['checkouts_closed']++;
                }
            });

        $this->entitlements->flush();

        return $done;
    }

    /** Refuse a move to smaller limits when the policy requires the dealer to be within them first. */
    protected function guardOverLimit(User $dealer, Package $package): void
    {
        if (config('meezan.billing.over_limit_policy') !== 'require_within_limit' || $package->listing_limit === null) {
            return;
        }
        $used = $this->entitlements->usage($dealer)['listings'];
        if ($used > $package->listing_limit) {
            throw new InvalidArgumentException(sprintf('%s allows %d active listings and you have %d. Archive %d listing(s) first.', $package->name, $package->listing_limit, $used, $used - $package->listing_limit));
        }
    }

    protected function scheduleChange(Subscription $current, Package $package, User $by): Subscription
    {
        $this->cancelOpenInvoices($current, 'Package change scheduled');
        $current->forceFill(['scheduled_package_id' => $package->id, 'cancel_at_period_end' => false, 'expiry_reminder_sent_at' => null])->save();
        AuditLogger::record('subscription.downgrade_scheduled', $current, ['to_package' => $package->slug, 'effective' => $current->current_period_end->toDateString()], $by->id);
        $current->user->notify(new BillingUpdated('downgrade_scheduled', $current, ['package' => $package->name]));

        return $current;
    }

    protected function applyScheduledChange(Subscription $current): void
    {
        $package = Package::find($current->scheduled_package_id);
        $this->end($current, SubscriptionStatus::Cancelled, 'Changed to '.($package?->name ?? 'another package').' at the end of the period', null);

        if (! $package || ! $package->is_active) {
            $current->user->notify(new BillingUpdated('subscription_ended', $current));

            return;
        }
        if ($package->isFree()) {
            $this->startFree($current->user, $package, 'downgrade', null, $current);

            return;
        }
        $subscription = $this->newSubscription($current->user, $package, SubscriptionStatus::PendingPayment, 'downgrade', $current);
        $invoice = $this->issueInvoice($subscription, 'downgrade', null, null);
        AuditLogger::record('subscription.checkout_started', $subscription, ['package' => $package->slug, 'type' => 'downgrade', 'invoice' => $invoice->number, 'scheduled' => true]);
        $current->user->notify(new BillingUpdated('payment_pending', $subscription, ['invoice_id' => $invoice->id, 'number' => $invoice->number]));
    }

    protected function startFree(User $dealer, Package $package, string $type, ?User $by, ?Subscription $previous = null): Subscription
    {
        return DB::transaction(function () use ($dealer, $package, $type, $by, $previous) {
            $previous ??= $this->current($dealer);
            $this->cancelPendingCheckouts($dealer, 'Replaced by '.$package->name);
            $subscription = $this->newSubscription($dealer, $package, SubscriptionStatus::Active, $type, $previous);
            $this->endOthers($subscription, 'Replaced by '.$package->name);
            $subscription->forceFill(['starts_at' => now(), 'current_period_start' => now()])->save();

            AuditLogger::record('subscription.activated', $subscription, ['to' => 'active', 'package' => $package->slug, 'type' => $type, 'free' => true], $by?->id);
            $dealer->notify(new BillingUpdated('subscription_activated', $subscription));
            $this->entitlements->flush($dealer);

            return $subscription;
        });
    }

    protected function startTrial(User $dealer, Package $package, string $type): Subscription
    {
        return DB::transaction(function () use ($dealer, $package, $type) {
            $previous = $this->current($dealer);
            $this->cancelPendingCheckouts($dealer, 'Replaced by a trial of '.$package->name);
            $subscription = $this->newSubscription($dealer, $package, SubscriptionStatus::Trial, $type, $previous);
            $end = now()->addDays($package->trial_days);
            $this->endOthers($subscription, 'Replaced by a trial of '.$package->name);
            $subscription->forceFill(['starts_at' => now(), 'trial_ends_at' => $end, 'current_period_start' => now(), 'current_period_end' => $end])->save();

            AuditLogger::record('subscription.trial_started', $subscription, ['package' => $package->slug, 'trial_ends' => $end->toDateString()], $dealer->id);
            $dealer->notify(new BillingUpdated('trial_started', $subscription));
            $this->entitlements->flush($dealer);

            return $subscription;
        });
    }

    protected function newSubscription(User $dealer, Package $package, SubscriptionStatus $status, string $type, ?Subscription $previous): Subscription
    {
        $subscription = new Subscription;
        $subscription->forceFill([
            'user_id' => $dealer->id,
            'package_id' => $package->id,
            'previous_subscription_id' => $previous?->id,
            'status' => $status,
            'change_type' => $previous || $type !== 'upgrade' ? $type : 'new',
            'billing_interval' => $package->billing_interval,
            'amount' => $package->price,
            'currency' => $package->currency,
            'package_snapshot' => $package->snapshot(),
        ])->save();

        return $subscription;
    }

    public function issueInvoice(Subscription $subscription, string $kind, ?Carbon $periodStart, ?Carbon $periodEnd): Invoice
    {
        $subscription->loadMissing('user.dealerProfile');
        $dealer = $subscription->user;
        $label = ['renewal' => 'Renewal', 'upgrade' => 'Upgrade to', 'downgrade' => 'Change to', 'change' => 'Change to', 'new' => 'Subscription to'][$kind] ?? 'Subscription to';

        $invoice = new Invoice;
        $invoice->forceFill([
            'user_id' => $dealer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $subscription->package_id,
            'kind' => $kind,
            'description' => trim(($kind === 'renewal' ? 'Renewal of ' : $label.' ').$subscription->name().' ('.Package::INTERVALS[$subscription->billing_interval].')'),
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
            'status' => InvoiceStatus::Pending,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'issued_at' => now(),
            'due_at' => $periodStart && $periodStart->isFuture() ? $periodStart : now()->addDays((int) config('meezan.billing.invoice_due_days')),
            'billing_snapshot' => [
                'dealer' => ['name' => $dealer->name, 'email' => $dealer->email, 'agency' => $dealer->dealerProfile?->agency_name, 'city' => $dealer->dealerProfile?->city],
                'package' => $subscription->package_snapshot,
            ],
            'is_test' => $subscription->is_test,
        ])->save();

        return $invoice;
    }

    protected function end(Subscription $subscription, SubscriptionStatus $to, string $reason, ?string $notify): void
    {
        $from = $subscription->status;
        $subscription->forceFill(['status' => $to, 'ended_at' => now(), 'end_reason' => $reason])->save();
        AuditLogger::record('subscription.'.$to->value, $subscription, ['from' => $from->value, 'to' => $to->value, 'reason' => $reason]);
        if ($notify) {
            $subscription->user->notify(new BillingUpdated($notify, $subscription));
        }
    }

    /** End the dealer's other running subscriptions when a new one starts. */
    protected function endOthers(Subscription $subscription, string $reason): void
    {
        Subscription::inForce()->where('user_id', $subscription->user_id)->whereKeyNot($subscription->id)->get()
            ->each(function (Subscription $other) use ($reason) {
                $this->cancelOpenInvoices($other, $reason);
                $other->forceFill(['status' => SubscriptionStatus::Cancelled, 'ended_at' => now(), 'end_reason' => $reason, 'scheduled_package_id' => null])->save();
                AuditLogger::record('subscription.replaced', $other, ['from' => 'active', 'to' => 'cancelled', 'reason' => $reason]);
            });
    }

    protected function cancelPendingCheckouts(User $dealer, string $reason): void
    {
        Subscription::where('user_id', $dealer->id)->where('status', SubscriptionStatus::PendingPayment)->get()
            ->each(fn (Subscription $pending) => $this->closeCheckout($pending, $reason));
    }

    protected function closeCheckout(Subscription $subscription, string $reason): void
    {
        $this->cancelOpenInvoices($subscription, $reason);
        $subscription->forceFill(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now(), 'ended_at' => now(), 'end_reason' => $reason])->save();
    }

    /** Unpaid invoices of a subscription are cancelled (kept, never deleted), with their pending payments. */
    protected function cancelOpenInvoices(Subscription $subscription, string $reason): void
    {
        Invoice::where('subscription_id', $subscription->id)->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Failed->value, InvoiceStatus::Draft->value])->get()
            ->each(function (Invoice $invoice) use ($reason) {
                // A transfer awaiting review keeps its invoice open for staff to decide.
                if ($invoice->payments()->where('status', PaymentStatus::AwaitingReview)->exists()) {
                    return;
                }
                $invoice->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Cancelled->value, 'failure_reason' => $reason, 'updated_at' => now()]);
                $invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save();
                AuditLogger::record('invoice.cancelled', $invoice, ['reason' => $reason]);
            });
    }

    protected function addInterval(Carbon $date, string $interval): Carbon
    {
        return $interval === 'year' ? $date->addYearNoOverflow() : $date->addMonthNoOverflow();
    }

    protected function monthly(float $amount, string $interval): float
    {
        return $interval === 'year' ? $amount / 12 : $amount;
    }
}
