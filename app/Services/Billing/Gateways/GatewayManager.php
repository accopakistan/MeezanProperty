<?php

namespace App\Services\Billing\Gateways;

use InvalidArgumentException;

/**
 * Resolves gateways by key. A new provider is a class implementing
 * PaymentGateway, added here and configured under meezan.billing.gateways.
 */
class GatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    protected const DRIVERS = [
        'manual' => ManualGateway::class,
        'mock' => MockGateway::class,
    ];

    /** @var array<string, PaymentGateway> */
    protected array $resolved = [];

    public function get(string $key): PaymentGateway
    {
        if (! isset(self::DRIVERS[$key])) {
            throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
        }

        return $this->resolved[$key] ??= app(self::DRIVERS[$key]);
    }

    public function has(string $key): bool
    {
        return isset(self::DRIVERS[$key]);
    }

    /** @return array<string, PaymentGateway> available gateways, real ones first */
    public function available(): array
    {
        return collect(array_keys(self::DRIVERS))
            ->map(fn ($key) => $this->get($key))
            ->filter(fn (PaymentGateway $gateway) => $gateway->isAvailable())
            ->sortBy(fn (PaymentGateway $gateway) => $gateway->isTest())
            ->keyBy(fn (PaymentGateway $gateway) => $gateway->key())
            ->all();
    }

    /** @return array<string, PaymentGateway> */
    public function all(): array
    {
        return collect(array_keys(self::DRIVERS))->mapWithKeys(fn ($key) => [$key => $this->get($key)])->all();
    }

    public function label(string $key): string
    {
        return $this->has($key) ? $this->get($key)->label() : ucfirst($key);
    }
}
