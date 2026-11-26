<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Models\AppSetting;
use App\Models\Property;

/**
 * Decides whether a submitted listing goes live straight away or waits for
 * an admin.
 *
 * - "flagged" (default): a listing is published at once unless the trust
 *   checks flag it, or its dealer is unverified and already has the allowed
 *   number of live listings. Admins review flagged listings and can still
 *   suspend, reject or request changes on any live one.
 * - "all": every listing waits for an admin (the original behaviour).
 *
 * Dealer verification (documents, review, verified badge) is separate and
 * unchanged.
 */
class ListingModeration
{
    public const REVIEW_FLAGGED = 'flagged';

    public const REVIEW_ALL = 'all';

    public const SETTING = 'listings.moderation';

    /** Edits to these fields on a live listing re-run the trust checks. */
    public const MATERIAL_FIELDS = ['price', 'purpose', 'type', 'city', 'area_id', 'latitude', 'longitude'];

    public static function mode(): string
    {
        $mode = AppSetting::get(self::SETTING, config('meezan.listing.moderation', self::REVIEW_FLAGGED));

        return in_array($mode, [self::REVIEW_FLAGGED, self::REVIEW_ALL], true) ? $mode : self::REVIEW_FLAGGED;
    }

    public static function reviewsAll(): bool
    {
        return self::mode() === self::REVIEW_ALL;
    }

    /** Live listings an unverified dealer may have before new ones wait for review (0 = no limit). */
    public static function unverifiedLiveLimit(): int
    {
        return max(0, (int) config('meezan.listing.unverified_live_limit', 5));
    }

    /**
     * Why this listing must wait for an admin; empty when it may go live now.
     * Call after the trust checks have run (TrustSignals::atSubmission).
     *
     * @return list<string>
     */
    public function reviewReasons(Property $property): array
    {
        $reasons = [];
        if (self::reviewsAll()) {
            $reasons[] = 'All listings are reviewed before publishing.';
        }
        // An admin who rejected it or asked for changes checks the update.
        if (in_array($property->status, [PropertyStatus::Rejected, PropertyStatus::ChangesRequested], true)) {
            $reasons[] = 'An admin asked for changes, so they check the update.';
        }
        if ($property->flagged_at) {
            $reasons[] = 'The automatic checks flagged it for a look.';
        }
        if (! array_key_exists((string) $property->publication_basis, Property::PUBLICATION_BASES)) {
            $reasons[] = 'It has no publication basis.';
        }
        if ($property->is_demo) {
            $reasons[] = 'It is sample content.';
        }

        $limit = self::unverifiedLiveLimit();
        if ($limit > 0 && ! $property->dealer?->dealerProfile?->isVerified()) {
            $live = Property::where('user_id', $property->user_id)->whereKeyNot($property->id)
                ->where('status', PropertyStatus::Published)->count();
            if ($live >= $limit) {
                $reasons[] = 'Unverified accounts can have '.$limit.' live listings; get verified to publish more without review.';
            }
        }

        return $reasons;
    }

    /** Puts the listing live for a full listing period (the same fields an admin approval sets). */
    public function publish(Property $property): void
    {
        $property->status = PropertyStatus::Published;
        $property->rejection_reason = null;
        $property->published_at ??= now();
        $property->expires_at = now()->addDays((int) config('meezan.listing.lifetime_days'));
        $property->expiry_reminder_sent_at = null;
    }

    /** True when an edit to a live listing touches a field worth re-checking. */
    public static function isMaterialEdit(Property $property): bool
    {
        return $property->isDirty(self::MATERIAL_FIELDS);
    }
}
