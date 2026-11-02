<?php

namespace App\Services\Billing\Gateways;

use App\Models\Payment;

/**
 * A way to take payment. Gateways never change billing records themselves:
 * they turn a provider's confirmation into a GatewayEvent, and
 * PaymentProcessor applies it (the same path for every gateway).
 */
interface PaymentGateway
{
    /** Config key, stored on payments.gateway. */
    public function key(): string;

    public function label(): string;

    /** Configured and allowed in this environment. */
    public function isAvailable(): bool;

    /** No real money moves (payments are flagged and excluded from revenue). */
    public function isTest(): bool;

    /** Where the dealer goes to complete this payment. */
    public function checkoutUrl(Payment $payment): string;

    /** Whether the provider sends signed server-to-server notifications. */
    public function supportsWebhooks(): bool;

    public function verifySignature(string $rawBody, ?string $signature): bool;

    /** The provider's notification as an event, or null if it is not one we act on. */
    public function parseEvent(array $payload): ?GatewayEvent;
}
