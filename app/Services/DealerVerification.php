<?php

namespace App\Services;

use App\Enums\DealerStatus;
use App\Models\DealerDocument;
use App\Models\DealerProfile;
use App\Models\User;
use App\Notifications\DealerDocumentsSubmitted;
use App\Notifications\DealerVerificationUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Every change to a dealer's verification goes through here, so each one is
 * checked against the current state, written to the audit log (who, from,
 * to, reason) and, where the dealer should know, notified. Nothing here
 * verifies a dealer automatically: verification is always a staff decision,
 * and only after every required document has been approved.
 */
class DealerVerification
{
    /** Dealer: send uploaded documents for review. */
    public function submit(DealerProfile $profile, User $by): void
    {
        if (! $profile->verification_status->dealerCanSubmit()) {
            throw new InvalidArgumentException('Your application is already '.strtolower($profile->verification_status->label()).'.');
        }
        if (! $profile->requiredDocumentsUploaded()) {
            throw new InvalidArgumentException('Upload every required document before submitting.');
        }

        $this->transition($profile, DealerStatus::Submitted, 'dealer.verification_submitted', $by, null, fn () => $profile->forceFill([
            'verification_submitted_at' => now(),
            'changes_requested_at' => null,
        ]));

        Notification::send(User::activeAdmins(), new DealerDocumentsSubmitted($profile, 'Application submitted for review', $profile->documents()->current()->count()));
    }

    /** Staff: start reviewing (optional step, shows the dealer someone is on it). */
    public function startReview(DealerProfile $profile, User $by): void
    {
        $this->expect($profile, [DealerStatus::Submitted, DealerStatus::Pending]);
        $this->transition($profile, DealerStatus::UnderReview, 'dealer.review_started', $by, null, fn () => $profile->forceFill(['review_started_at' => now()]));
    }

    /** Staff: approve or reject one document (with a reason the dealer sees). */
    public function reviewDocument(DealerDocument $document, string $decision, ?string $note, User $by): void
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Unknown decision.');
        }
        if ($decision === 'rejected' && blank($note)) {
            throw new InvalidArgumentException('Give the dealer a reason for rejecting this document.');
        }
        if ($document->status === 'superseded') {
            throw new InvalidArgumentException('This document has been replaced.');
        }

        $from = $document->status;
        $document->forceFill(['status' => $decision, 'review_note' => $decision === 'rejected' ? $note : null, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();

        AuditLogger::record('dealer.document_'.$decision, $document->profile, [
            'document_id' => $document->id, 'type' => $document->type, 'from' => $from, 'to' => $decision, 'reason' => $note,
        ], $by->id);
        $document->profile->user?->notify(new DealerVerificationUpdated($document->profile, 'document_'.$decision, $note, $document->typeLabel()));
    }

    /** Staff: ask for more or replacement documents; the dealer can resubmit. */
    public function requestDocuments(DealerProfile $profile, string $reason, User $by): void
    {
        $this->expect($profile, [DealerStatus::Submitted, DealerStatus::UnderReview, DealerStatus::Pending]);
        $this->transition($profile, DealerStatus::Pending, 'dealer.documents_requested', $by, $reason, fn () => $profile->forceFill([
            'changes_requested_at' => now(),
            'rejection_reason' => $reason,
        ]), notify: 'documents_requested');
    }

    /** Staff: verify. Refused until every required document has been approved. */
    public function verify(DealerProfile $profile, User $by, ?string $reason = null): void
    {
        $this->expect($profile, [DealerStatus::Submitted, DealerStatus::UnderReview, DealerStatus::Pending, DealerStatus::Expired, DealerStatus::Rejected]);
        $missing = collect($profile->documentChecklist())->reject(fn ($row) => $row['state'] === 'approved')->pluck('label')->all();
        if ($missing) {
            throw new InvalidArgumentException('Approve every required document before verifying. Not yet approved: '.implode(', ', $missing).'.');
        }

        $months = config('meezan.verification.valid_months');
        $this->transition($profile, DealerStatus::Verified, 'dealer.verified', $by, $reason, fn () => $profile->forceFill([
            'verified_at' => now(),
            'verified_by' => $by->id,
            'rejection_reason' => null,
            'changes_requested_at' => null,
            'verification_expires_at' => $months ? now()->addMonths((int) $months) : null,
        ]), notify: 'verified', extra: ['documents_approved' => array_keys($profile->documentChecklist())]);
    }

    public function reject(DealerProfile $profile, string $reason, User $by): void
    {
        $this->expect($profile, [DealerStatus::Submitted, DealerStatus::UnderReview, DealerStatus::Pending]);
        $this->transition($profile, DealerStatus::Rejected, 'dealer.rejected', $by, $reason, fn () => $profile->forceFill(['rejection_reason' => $reason]), notify: 'rejected');
    }

    public function suspend(DealerProfile $profile, string $reason, User $by): void
    {
        $this->expect($profile, [DealerStatus::Verified, DealerStatus::Submitted, DealerStatus::UnderReview, DealerStatus::Pending, DealerStatus::Rejected, DealerStatus::Expired]);
        $this->transition($profile, DealerStatus::Suspended, 'dealer.suspended', $by, $reason, fn () => $profile->forceFill(['rejection_reason' => $reason]), notify: 'suspended');
    }

    /** Lift a suspension: back to verified only if the documents still stand, otherwise to "documents required". */
    public function reinstate(DealerProfile $profile, User $by): void
    {
        $this->expect($profile, [DealerStatus::Suspended]);
        $to = $profile->verified_at && $profile->requiredDocumentsApproved() ? DealerStatus::Verified : DealerStatus::Pending;
        $this->transition($profile, $to, 'dealer.reinstated', $by, null, fn () => $profile->forceFill(['rejection_reason' => null]), notify: $to === DealerStatus::Verified ? 'verified' : null);
    }

    /** Scheduled: verification past its expiry date. */
    public function expire(DealerProfile $profile): void
    {
        $this->expect($profile, [DealerStatus::Verified]);
        $this->transition($profile, DealerStatus::Expired, 'dealer.verification_expired', null, null, fn () => null, notify: 'expired');
    }

    /** Staff-only note, never shown to the dealer. */
    public function note(DealerProfile $profile, ?string $note, User $by): void
    {
        $profile->forceFill(['internal_note' => $note])->save();
        AuditLogger::record('dealer.internal_note_updated', $profile, [], $by->id);
    }

    /** @param  list<DealerStatus>  $allowed */
    protected function expect(DealerProfile $profile, array $allowed): void
    {
        if (! in_array($profile->verification_status, $allowed, true)) {
            throw new InvalidArgumentException('Not possible while the dealer is '.strtolower($profile->verification_status->label()).'.');
        }
    }

    /** @param  array<string, mixed>  $extra  more audit detail */
    protected function transition(DealerProfile $profile, DealerStatus $to, string $action, ?User $by, ?string $reason, callable $changes, ?string $notify = null, array $extra = []): void
    {
        DB::transaction(function () use ($profile, $to, $action, $by, $reason, $changes, $extra) {
            $from = $profile->verification_status;
            $changes();
            $profile->verification_status = $to;
            $profile->save();

            AuditLogger::record($action, $profile, array_filter(['from' => $from->value, 'to' => $to->value, 'reason' => $reason] + $extra, fn ($v) => $v !== null), $by?->id);
        });

        if ($notify) {
            $profile->user?->notify(new DealerVerificationUpdated($profile, $notify, $reason));
        }
    }
}
