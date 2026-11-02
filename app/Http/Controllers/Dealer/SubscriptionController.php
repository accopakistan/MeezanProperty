<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Billing\Entitlements;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The dealer's package: current plan, usage, history, and choosing,
 * cancelling or undoing a change. Paid packages only start once their
 * payment succeeds (see BillingController and PaymentProcessor).
 */
class SubscriptionController extends Controller
{
    public function index(Request $request, Entitlements $entitlements, SubscriptionManager $subscriptions): View
    {
        $dealer = $request->user();

        return view('dealer.subscription', [
            'plan' => $entitlements->plan($dealer),
            'usage' => $entitlements->usage($dealer),
            'subscription' => $subscriptions->current($dealer)?->load('scheduledPackage:id,name'),
            'pending' => $subscriptions->pendingCheckout($dealer),
            'openInvoices' => $dealer->invoices()->whereIn('status', ['pending', 'failed'])->with('payments')->get(),
            'invoices' => $dealer->invoices()->paginate(10, ['*'], 'invoices')->withQueryString(),
            'payments' => $dealer->payments()->with('invoice:id,number')->paginate(10, ['*'], 'payments')->withQueryString(),
            'packages' => Package::active()->ordered()->get(),
        ]);
    }

    public function packages(Request $request, Entitlements $entitlements, SubscriptionManager $subscriptions): View
    {
        return view('dealer.billing.packages', [
            'packages' => Package::active()->ordered()->get(),
            'current' => $subscriptions->current($request->user()),
            'plan' => $entitlements->plan($request->user()),
            'usage' => $entitlements->usage($request->user()),
        ]);
    }

    public function checkout(Request $request, Package $package, SubscriptionManager $subscriptions, GatewayManager $gateways, Entitlements $entitlements): View|RedirectResponse
    {
        abort_unless($package->is_active, 404);
        $dealer = $request->user();

        return view('dealer.billing.checkout', [
            'package' => $package,
            'current' => $subscriptions->current($dealer),
            'type' => $subscriptions->changeType($dealer, $package),
            'outcome' => $subscriptions->outcome($dealer, $package),
            'gateways' => $gateways->available(),
            'usage' => $entitlements->usage($dealer),
            'planName' => $entitlements->plan($dealer)->name,
            'awaitingReview' => Payment::where('user_id', $dealer->id)->where('status', PaymentStatus::AwaitingReview)->exists(),
        ]);
    }

    public function choose(Request $request, Package $package, SubscriptionManager $subscriptions, GatewayManager $gateways): RedirectResponse
    {
        $data = $request->validate(['gateway' => ['nullable', 'string', 'max:30']]);

        try {
            $result = $subscriptions->choose($request->user(), $package, $data['gateway'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['package' => $e->getMessage()]);
        }

        return match ($result['result']) {
            'payment_required' => redirect()->to($gateways->get($result['payment']->gateway)->checkoutUrl($result['payment'])),
            'scheduled' => redirect()->route('dealer.subscription')->with('status', 'Your change to '.$package->name.' takes effect on '.$result['subscription']->current_period_end->format('j M Y').'. You keep your current package until then.'),
            'trial' => redirect()->route('dealer.subscription')->with('status', 'Your '.$package->name.' trial has started. It ends on '.$result['subscription']->trial_ends_at->format('j M Y').'.'),
            default => redirect()->route('dealer.subscription')->with('status', $package->name.' is now active.'),
        };
    }

    public function cancel(Request $request, SubscriptionManager $subscriptions): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255'], 'checkout' => ['nullable', 'boolean']]);
        $subscription = $request->boolean('checkout') ? $subscriptions->pendingCheckout($request->user()) : $subscriptions->current($request->user());
        abort_unless($subscription, 404);

        try {
            $subscriptions->cancel($subscription, $request->user(), $data['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dealer.subscription')->with('status', $request->boolean('checkout')
            ? 'Checkout cancelled. Nothing was charged.'
            : 'Done. '.(($s = $subscription->fresh())->cancel_at_period_end ? 'You keep '.$s->name().' until '.$s->current_period_end->format('j M Y').'.' : 'Your account is on the default plan now.'));
    }

    public function resume(Request $request, SubscriptionManager $subscriptions): RedirectResponse
    {
        $subscription = $subscriptions->current($request->user());
        abort_unless($subscription, 404);

        try {
            $subscriptions->resume($subscription, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Your '.$subscription->name().' package continues as before.');
    }
}
