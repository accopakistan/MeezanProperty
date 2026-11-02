<?php

namespace App\Services\Billing\Gateways;

/**
 * A payment outcome reported by a gateway. eventId is the provider's own
 * id for the notification, used to ignore repeated deliveries.
 */
final class GatewayEvent
{
    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $gateway,
        public readonly string $eventId,
        public readonly string $paymentReference,
        public readonly string $outcome,
        public readonly ?string $gatewayReference = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $failureReason = null,
    ) {}
}
