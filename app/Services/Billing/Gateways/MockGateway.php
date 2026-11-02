<?php

namespace App\Services\Billing\Gateways;

use App\Models\Payment;

/**
 * Test gateway for local development. It is not a payment provider: no
 * money moves, every record it touches is flagged as a test, and it only
 * works when MEEZAN_MOCK_PAYMENTS=true and APP_ENV is local or testing.
 * Outcomes go through PaymentProcessor
 * exactly like a real gateway's, from the test checkout page or from a
 * webhook signed with MEEZAN_MOCK_WEBHOOK_SECRET (HMAC-SHA256 of the body,
 * sent as "X-Mock-Signature: sha256=<hex>").
 */
class MockGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'mock';
    }

    public function label(): string
    {
        return 'Test payment (no real money)';
    }

    public function isAvailable(): bool
    {
        return config('meezan.billing.gateways.mock.enabled') && app()->environment(['local', 'testing']);
    }

    public function isTest(): bool
    {
        return true;
    }

    public function checkoutUrl(Payment $payment): string
    {
        return route('dealer.billing.mock', $payment);
    }

    public function supportsWebhooks(): bool
    {
        return $this->isAvailable() && filled(config('meezan.billing.gateways.mock.webhook_secret'));
    }

    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        $secret = config('meezan.billing.gateways.mock.webhook_secret');

        return $secret && $signature && hash_equals('sha256='.hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    public function parseEvent(array $payload): ?GatewayEvent
    {
        if (! isset($payload['id'], $payload['payment_reference'], $payload['outcome']) || ! in_array($payload['outcome'], [GatewayEvent::SUCCEEDED, GatewayEvent::FAILED], true)) {
            return null;
        }

        return new GatewayEvent(
            gateway: $this->key(),
            eventId: (string) $payload['id'],
            paymentReference: (string) $payload['payment_reference'],
            outcome: $payload['outcome'],
            gatewayReference: isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            amount: isset($payload['amount']) ? (string) $payload['amount'] : null,
            currency: $payload['currency'] ?? null,
            failureReason: $payload['failure_reason'] ?? null,
        );
    }
}
