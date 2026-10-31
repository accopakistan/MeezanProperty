<?php

namespace App\Enums;

/**
 * Listing lifecycle: draft → pending (review) → published, or back to the
 * dealer as changes_requested / rejected. Admins can suspend a live listing;
 * listings expire and can be archived. "Ready to submit" is not a status: it
 * is a draft whose completeness check passes.
 */
enum PropertyStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case ChangesRequested = 'changes_requested';
    case Published = 'published';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Archived = 'archived';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending review',
            self::ChangesRequested => 'Changes requested',
            self::Published => 'Published',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
            self::Archived => 'Archived',
            self::Expired => 'Expired',
        };
    }

    /**
     * Tone key consumed by the <x-status-badge> component.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Pending, self::ChangesRequested => 'warning',
            self::Published => 'success',
            self::Rejected, self::Suspended => 'danger',
            self::Archived, self::Expired => 'muted',
        };
    }

    public function isEditableByDealer(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Published, self::Pending, self::ChangesRequested], true);
    }
}
