<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\Gateways\GatewayEvent;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\Gateways\ManualGateway;
use App\Services\Billing\PaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A dealer's invoices and payments. Every record is looked up through the
 * signed-in dealer, so one dealer can never open another's billing.
 */
class BillingController extends Controller
{
    public function invoice(Request $request, int $invoice, GatewayManager $gateways): View
    {
        $invoice = $request->user()->invoices()->with(['payments', 'adjustments'])->findOrFail($invoice);

        return view('billing.invoice', [
            'invoice' => $invoice,
            'gateways' => $invoice->status->isOpen() ? $gateways->available() : [],
            'back' => route('dealer.subscription'),
            'admin' => false,
        ]);
    }

    public function pay(Request $request, int $invoice, PaymentProcessor $processor, GatewayManager $gateways): RedirectResponse
    {
        $invoice = $request->user()->invoices()->findOrFail($invoice);
        $data = $request->validate(['gateway' => ['required', 'string', 'max:30']]);

        try {
            $payment = $processor->start($invoice, $data['gateway'], $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['gateway' => $e->getMessage()]);
        }

        return redirect()->to($gateways->get($payment->gateway)->checkoutUrl($payment));
    }

    /** Bank transfer instructions and the form to report the transfer. */
    public function payment(Request $request, int $payment, ManualGateway $manual): View
    {
        $payment = $request->user()->payments()->with('invoice')->findOrFail($payment);

        return view('dealer.billing.payment', [
            'payment' => $payment,
            'account' => $payment->gateway === 'manual' ? $manual->accountDetails() : [],
        ]);
    }

    public function transfer(Request $request, int $payment, PaymentProcessor $processor): RedirectResponse
    {
        $payment = $request->user()->payments()->findOrFail($payment);
        $data = $request->validate([
            'payer_reference' => ['required', 'string', 'min:4', 'max:120', 'regex:/^[A-Za-z0-9\-\/ ._]+$/'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('meezan.billing.gateways.manual.max_proof_kb')],
        ], ['payer_reference.regex' => 'Use only the letters, numbers and dashes of the bank’s transaction reference.']);

        try {
            $processor->submitTransfer($payment, $data['payer_reference'], $request->file('proof'), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dealer.billing.payments.show', $payment)->with('status', 'Thank you. We will check the transfer and activate your package once it is confirmed.');
    }

    public function proof(Request $request, int $payment): StreamedResponse
    {
        $payment = $request->user()->payments()->findOrFail($payment);

        return self::streamProof($payment);
    }

    /** Test checkout page for the mock gateway (local development only). */
    public function mock(Request $request, int $payment, GatewayManager $gateways): View
    {
        abort_unless($gateways->get('mock')->isAvailable(), 404);
        $payment = $request->user()->payments()->with('invoice')->where('gateway', 'mock')->findOrFail($payment);

        return view('dealer.billing.mock', ['payment' => $payment]);
    }

    /** The test outcome goes through PaymentProcessor exactly like a real gateway's. */
    public function mockComplete(Request $request, int $payment, GatewayManager $gateways, PaymentProcessor $processor): RedirectResponse
    {
        abort_unless($gateways->get('mock')->isAvailable(), 404);
        $payment = $request->user()->payments()->where('gateway', 'mock')->findOrFail($payment);
        $data = $request->validate(['outcome' => ['required', 'in:succeeded,failed']]);

        $processor->handle(new GatewayEvent(
            gateway: 'mock',
            eventId: 'checkout-'.$payment->id.'-'.Str::random(8),
            paymentReference: $payment->reference,
            outcome: $data['outcome'],
            gatewayReference: $data['outcome'] === 'succeeded' ? 'MOCK-'.strtoupper(Str::random(10)) : null,
            amount: (string) $payment->amount,
            currency: $payment->currency,
            failureReason: $data['outcome'] === 'failed' ? 'Test payment declined' : null,
        ));

        $payment->refresh();

        return redirect()->route('dealer.billing.invoices.show', $payment->invoice_id)->with(
            $payment->status === PaymentStatus::Succeeded ? 'status' : 'error',
            $payment->status === PaymentStatus::Succeeded ? 'Test payment succeeded. Your package is active.' : 'Test payment failed. Nothing was activated; you can try again.'
        );
    }

    /** Proof files live on the private disk and are only streamed after an ownership or admin check. */
    public static function streamProof(Payment $payment): StreamedResponse
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, $payment->reference.'-proof.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION), [
            'Content-Type' => $payment->proof_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
