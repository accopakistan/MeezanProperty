<?php

namespace App\Enums;

/**
 * Dealer verification lifecycle:
 *
 *   pending (documents required) → submitted → under_review → verified
 *                ↑                       ↓             ↓
 *        (more documents requested) ─────┴── rejected  suspended / expired
 *
 * Only "verified" earns the public badge and buyer leads.
 */
enum DealerStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Expired = 'expired';

    /** Neutral public/admin wording. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Verification pending',
            self::Submitted => 'Submitted for review',
            self::UnderReview => 'Under review',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
            self::Expired => 'Verification expired',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending, self::Submitted, self::UnderReview => 'warning',
            self::Verified => 'success',
            self::Rejected, self::Suspended => 'danger',
            self::Expired => 'muted',
        };
    }

    /** Waiting on Meezan Property staff (counts as the admin review queue). */
    public function awaitsReview(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview], true);
    }

    /** The dealer may add or replace documents and submit. */
    public function dealerCanSubmit(): bool
    {
        return in_array($this, [self::Pending, self::Rejected, self::Expired], true);
    }

    /** @return list<self> */
    public static function reviewQueue(): array
    {
        return [self::Submitted, self::UnderReview];
    }
}
