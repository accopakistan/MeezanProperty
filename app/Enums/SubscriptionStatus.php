<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Payment overdue',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Trial => 'info',
            self::PendingPayment, self::PastDue => 'warning',
            self::Suspended => 'danger',
            self::Cancelled, self::Expired => 'muted',
        };
    }

    /** The package's limits and features apply. */
    public function inForce(): bool
    {
        return in_array($this, self::inForceCases(), true);
    }

    /** @return list<self> */
    public static function inForceCases(): array
    {
        return [self::Trial, self::Active, self::PastDue];
    }
}
