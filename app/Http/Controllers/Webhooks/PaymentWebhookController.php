<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\BillingAdminAlert;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\PaymentProcessor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Throwable;

/**
 * Server-to-server payment notifications: POST /webhooks/payments/{gateway}.
 *
 * Order of checks: gateway known and accepting webhooks → signature →
 * payload shape → duplicate event (provider event id, recorded in
 * webhook_events) → PaymentProcessor. Payloads and signatures are never
 * logged. A processing failure answers 500 so the provider retries; the
 * retry is safe because the processor is idempotent.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, GatewayManager $gateways, PaymentProcessor $processor): JsonResponse
    {
        if (! $gateways->has($gateway) || ! $gateways->get($gateway)->supportsWebhooks()) {
            return response()->json(['message' => 'Not available.'], 404);
        }
        $driver = $gateways->get($gateway);

        $raw = $request->getContent();
        if (! $driver->verifySignature($raw, $request->header('X-Mock-Signature') ?? $request->header('X-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($raw, true);
        $event = is_array($payload) ? $driver->parseEvent($payload) : null;
        $provider = 'pay-'.$gateway;
        if (! $event) {
            WebhookEvent::firstOrCreate(['provider' => $provider, 'payload_hash' => hash('sha256', $raw)], ['status' => 'rejected', 'error' => 'Unexpected payload shape']);

            return response()->json(['message' => 'Invalid payload.'], 422);
        }

        // One row per provider event id: a redelivered event is answered without being applied again.
        $key = hash('sha256', $event->eventId);
        try {
            $record = WebhookEvent::create(['provider' => $provider, 'payload_hash' => $key, 'status' => 'processing']);
        } catch (UniqueConstraintViolationException) {
            $record = WebhookEvent::where('provider', $provider)->where('payload_hash', $key)->first();
            if ($record && $record->status !== 'failed') {
                return response()->json(['received' => true, 'duplicate' => true]);
            }
        }

        try {
            $result = $processor->handle($event);
            $record->update(['status' => $result === 'applied' ? 'processed' : $result, 'error' => null]);
        } catch (InvalidArgumentException $e) {
            $record->update(['status' => 'rejected', 'error' => mb_substr($e->getMessage(), 0, 250)]);

            return response()->json(['message' => 'Unknown payment.'], 422);
        } catch (Throwable $e) {
            $record->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
            Log::error('Payment webhook processing failed', ['gateway' => $gateway, 'event' => $record->id, 'error' => $e->getMessage()]);
            Notification::send(User::activeAdmins(), new BillingAdminAlert('payment_problem', null, 'Webhook event #'.$record->id.' from '.$driver->label().' could not be processed.'));

            return response()->json(['message' => 'Processing failed.'], 500);
        }

        return response()->json(['received' => true, 'result' => $result]);
    }
}
