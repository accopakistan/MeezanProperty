<?php

namespace App\Services\Billing;

use App\Models\Package;
use App\Models\Subscription;

/**
 * The limits and features that apply to a dealer right now, from one of:
 * - "subscription": an in-force subscription (its snapshot),
 * - "default": the package an admin marked as the default plan,
 * - "fallback": config('meezan.billing.fallback_limits'), when neither exists.
 */
final class Plan
{
    /**
     * @param  array<string, int|null>  $limits  null = no limit
     * @param  list<string>  $features
     */
    public function __construct(
        public readonly string $source,
        public readonly string $name,
        public readonly array $limits,
        public readonly array $features,
        public readonly ?Subscription $subscription = null,
        public readonly ?Package $package = null,
    ) {}

    public static function fromSubscription(Subscription $subscription): self
    {
        return new self('subscription', $subscription->name(), $subscription->package_snapshot['limits'] ?? [], $subscription->package_snapshot['features'] ?? [], $subscription);
    }

    public static function fromPackage(Package $package): self
    {
        $snapshot = $package->snapshot();

        return new self('default', $package->name, $snapshot['limits'], $snapshot['features'], null, $package);
    }

    public static function fallback(): self
    {
        return new self('fallback', 'Launch access', config('meezan.billing.fallback_limits'), []);
    }

    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }
}
