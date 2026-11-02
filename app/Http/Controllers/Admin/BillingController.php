<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\BillingController as DealerBilling;
use App\Models\AuditLog;
use App\Models\BillingAdjustment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BillingReport;
use App\Services\Billing\Entitlements;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\PaymentProcessor;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Billing administration: dashboard, subscriptions, payments (including
 * bank-transfer review), invoices, refunds and adjustments. Figures come
 * from recorded data only (BillingReport); nothing is estimated.
 */
class BillingController extends Controller
{
    public function dashboard(BillingReport $report, GatewayManager $gateways): View
    {
        $month = [now()->startOfMonth(), now()->endOfMonth()];

        return view('admin.billing.dashboard', [
            'counts' => $report->counts(),
            'paid' => $report->paidRevenue(...$month),
            'gross' => $report->grossPaid(...$month),
            'refunds' => $report->refunds(...$month),
            'pending' => $report->pendingRevenue(),
            'expected' => $report->expectedMonthly(),
            'distribution' => $report->distribution(),
            'recent' => Payment::with(['user:id,name', 'invoice:id,number'])->latest('id')->take(8)->get(),
            'gateways' => $gateways->all(),
            'hasDefault' => Package::defaultPlan() !== null,
        ]);
    }

    public function subscriptions(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'package' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return view('admin.billing.subscriptions', [
            'subscriptions' => Subscription::query()
                ->with(['user:id,name,email', 'user.dealerProfile:id,user_id,agency_name'])
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['package'] ?? null, fn ($q, $p) => $q->where('package_id', $p))
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('dealerProfile', fn ($d) => $d->where('agency_name', 'like', "%{$term}%"))))
                ->latest('id')->paginate(20)->withQueryString(),
            'counts' => Subscription::groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status'),
            'packages' => Package::ordered()->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function subscription(Subscription $subscription, Entitlements $entitlements): View
    {
        $subscription->load(['user.dealerProfile', 'previous', 'scheduledPackage:id,name', 'invoices.payments']);
        $dealer = $subscription->user;

        return view('admin.billing.subscription', [
            'subscription' => $subscription,
            'dealer' => $dealer,
            'plan' => $entitlements->plan($dealer),
            'usage' => $entitlements->usage($dealer),
            'history' => $dealer->subscriptions()->get(),
            'adjustments' => BillingAdjustment::where('user_id', $dealer->id)->with('creator:id,name', 'invoice:id,number')->latest('id')->get(),
            'log' => AuditLog::with('user:id,name')->where('auditable_type', $subscription->getMorphClass())->where('auditable_id', $subscription->id)->latest('id')->take(30)->get(),
        ]);
    }

    public function updateSubscription(Request $request, Subscription $subscription, SubscriptionManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['suspend', 'reinstate', 'cancel', 'cancel_now'])],
            'reason' => [Rule::requiredIf(in_array($request->input('decision'), ['suspend', 'cancel', 'cancel_now'], true)), 'nullable', 'string', 'max:255'],
        ]);

        try {
            match ($data['decision']) {
                'suspend' => $manager->suspend($subscription, $request->user(), $data['reason']),
                'reinstate' => $manager->reinstate($subscription, $request->user()),
                'cancel' => $manager->cancel($subscription, $request->user(), $data['reason']),
                'cancel_now' => $manager->cancel($subscription, $request->user(), $data['reason'], immediately: true),
            };
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['decision' => $e->getMessage()]);
        }

        return back()->with('status', 'Subscription updated.');
    }

    public function payments(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'gateway' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return view('admin.billing.payments', [
            'payments' => Payment::query()
                ->with(['user:id,name,email', 'invoice:id,number,description'])
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['gateway'] ?? null, fn ($q, $g) => $q->where('gateway', $g))
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$term}%")->orWhere('payer_reference', 'like', "%{$term}%")
                    ->orWhereHas('invoice', fn ($i) => $i->where('number', 'like', "%{$term}%"))))
                ->orderByRaw("case when status = 'awaiting_review' then 0 else 1 end")->latest('id')
                ->paginate(25)->withQueryString(),
            'counts' => Payment::groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status'),
            'filters' => $filters,
        ]);
    }

    public function payment(Payment $payment): View
    {
        $payment->load(['user.dealerProfile', 'invoice.subscription', 'reviewer:id,name']);

        return view('admin.billing.payment', [
            'payment' => $payment,
            'sameReference' => $payment->payer_reference
                ? Payment::whereKeyNot($payment->id)->where('payer_reference', $payment->payer_reference)->with('invoice:id,number')->get()
                : collect(),
            'log' => AuditLog::with('user:id,name')->where('auditable_type', $payment->getMorphClass())->where('auditable_id', $payment->id)->latest('id')->get(),
        ]);
    }

    public function reviewPayment(Request $request, Payment $payment, PaymentProcessor $processor): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => [Rule::requiredIf($request->input('decision') === 'reject'), 'nullable', 'string', 'max:500'],
            'confirm_received' => [Rule::requiredIf($request->input('decision') === 'approve'), 'accepted_if:decision,approve'],
        ], [
            'note.required' => 'Give the dealer a reason for rejecting the transfer.',
            'confirm_received.*' => 'Confirm that the amount has arrived in the account before approving.',
        ]);

        try {
            $data['decision'] === 'approve'
                ? $processor->approveTransfer($payment, $request->user(), $data['note'] ?? null)
                : $processor->rejectTransfer($payment, $request->user(), $data['note']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['decision' => $e->getMessage()]);
        }

        return back()->with('status', $data['decision'] === 'approve' ? 'Transfer approved; the invoice is paid and the package is active.' : 'Transfer rejected; the dealer has been told why.');
    }

    public function proof(Payment $payment): StreamedResponse
    {
        return DealerBilling::streamProof($payment);
    }

    public function invoice(Invoice $invoice): View
    {
        return view('billing.invoice', [
            'invoice' => $invoice->load(['payments', 'adjustments.creator:id,name', 'user', 'subscription']),
            'gateways' => [],
            'back' => $invoice->subscription_id ? route('admin.subscriptions.show', $invoice->subscription_id) : route('admin.payments'),
            'admin' => true,
        ]);
    }

    public function refund(Request $request, Invoice $invoice, PaymentProcessor $processor): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'end_subscription' => ['nullable', 'boolean'],
        ]);

        try {
            $processor->recordRefund($invoice, number_format((float) $data['amount'], 2, '.', ''), $data['reason'], $request->user(), $data['external_reference'] ?? null, $request->boolean('end_subscription'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Refund recorded.');
    }

    public function adjustment(Request $request, User $dealer, PaymentProcessor $processor): RedirectResponse
    {
        abort_unless($dealer->isDealer(), 404);
        $data = $request->validate([
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $processor->recordAdjustment($dealer, $data['type'], number_format((float) $data['amount'], 2, '.', ''), $data['reason'], $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back()->with('status', ucfirst($data['type']).' recorded.');
    }
}
