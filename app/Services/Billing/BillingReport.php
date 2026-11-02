<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\BillingAdjustment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin billing figures, from recorded data only:
 * - paid: invoices marked paid in the period, less refunds recorded in it;
 * - pending: unpaid invoices (issued, not yet paid);
 * - expected: monthly value of running paid subscriptions (yearly plans /12).
 * Test (mock gateway) records are always excluded and counted separately.
 * Sums are grouped by currency; amounts in different currencies are never added.
 */
class BillingReport
{
    /** @return Collection<string, float> currency => amount */
    public function paidRevenue(\DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        $paid = Invoice::where('is_test', false)->whereIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Refunded->value])
            ->whereBetween('paid_at', [$from, $to])->groupBy('currency')->selectRaw('currency, sum(amount) as total')->pluck('total', 'currency');
        $refunds = $this->refunds($from, $to);

        return $paid->keys()->merge($refunds->keys())->unique()->mapWithKeys(fn ($c) => [$c => round((float) ($paid[$c] ?? 0) - (float) ($refunds[$c] ?? 0), 2)]);
    }

    /** @return Collection<string, float> */
    public function grossPaid(\DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return Invoice::where('is_test', false)->whereIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Refunded->value])
            ->whereBetween('paid_at', [$from, $to])->groupBy('currency')->selectRaw('currency, sum(amount) as total')->pluck('total', 'currency')->map(fn ($v) => (float) $v);
    }

    /** @return Collection<string, float> */
    public function refunds(\DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return BillingAdjustment::where('type', 'refund')->whereBetween('billing_adjustments.created_at', [$from, $to])
            ->whereHas('invoice', fn ($q) => $q->where('is_test', false))
            ->groupBy('currency')->selectRaw('currency, sum(amount) as total')->pluck('total', 'currency')->map(fn ($v) => (float) $v);
    }

    /** @return Collection<string, float> */
    public function pendingRevenue(): Collection
    {
        return Invoice::where('is_test', false)->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Failed->value])
            ->groupBy('currency')->selectRaw('currency, sum(amount) as total')->pluck('total', 'currency')->map(fn ($v) => (float) $v);
    }

    /** @return Collection<string, float> monthly value of running paid subscriptions that will renew */
    public function expectedMonthly(): Collection
    {
        return Subscription::where('is_test', false)->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->where('amount', '>', 0)->where('cancel_at_period_end', false)->whereNull('scheduled_package_id')
            ->get(['amount', 'currency', 'billing_interval'])
            ->groupBy('currency')->map(fn ($subs) => round($subs->sum(fn ($s) => $s->billing_interval === 'year' ? (float) $s->amount / 12 : (float) $s->amount), 2));
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $byStatus = Subscription::where('is_test', false)->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status');

        return [
            'active' => (int) ($byStatus['active'] ?? 0),
            'trial' => (int) ($byStatus['trial'] ?? 0),
            'past_due' => (int) ($byStatus['past_due'] ?? 0),
            'pending_payment' => (int) ($byStatus['pending_payment'] ?? 0),
            'expiring' => Subscription::inForce()->where('is_test', false)->whereBetween('current_period_end', [now(), now()->addDays(7)])->count(),
            'payments_pending' => Payment::where('is_test', false)->where('status', PaymentStatus::Pending)->count(),
            'awaiting_review' => Payment::where('status', PaymentStatus::AwaitingReview)->count(),
            'failed_30d' => Payment::where('is_test', false)->where('status', PaymentStatus::Failed)->where('updated_at', '>=', now()->subDays(30))->count(),
            'test_payments' => Payment::where('is_test', true)->count(),
            'test_subscriptions' => Subscription::where('is_test', true)->inForce()->count(),
        ];
    }

    /**
     * Dealers per package right now, including those on the default plan
     * (no running subscription).
     *
     * @return Collection<int, array{name: string, count: int}>
     */
    public function distribution(): Collection
    {
        $running = Subscription::inForce()->where('is_test', false)
            ->join('packages', 'packages.id', '=', 'subscriptions.package_id')
            ->groupBy('packages.id', 'packages.name')->select('packages.name', DB::raw('count(distinct subscriptions.user_id) as total'))
            ->orderByDesc('total')->get()->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->total]);

        $withPlan = Subscription::inForce()->distinct()->count('user_id');
        $dealers = User::where('role', Role::Dealer)->count();
        $default = Package::defaultPlan();

        return $running->push(['name' => ($default?->name ?? 'Launch access').' (no subscription)', 'count' => max(0, $dealers - $withPlan)]);
    }
}
