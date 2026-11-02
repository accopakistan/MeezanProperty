<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingReview = 'awaiting_review';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingReview => 'Awaiting review',
            self::Succeeded => 'Successful',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Succeeded => 'success',
            self::Pending => 'info',
            self::AwaitingReview => 'warning',
            self::Failed => 'danger',
            self::Cancelled => 'muted',
            self::Refunded => 'info',
        };
    }

    /** No further outcome can be applied. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled, self::Refunded], true);
    }
}
