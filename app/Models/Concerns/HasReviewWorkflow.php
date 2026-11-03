<?php

namespace App\Models\Concerns;

use App\Models\Area;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Market data review workflow: draft -> submitted -> reviewed -> verified,
 * with rejection (back to draft for correction) at any stage before
 * verification. Only verified records are used in calculations.
 */
trait HasReviewWorkflow
{
    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'reviewed' => 'Reviewed',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ];

    /** action => [from statuses, to status] */
    public const TRANSITIONS = [
        'submit' => [['draft', 'rejected'], 'submitted'],
        'review' => [['submitted'], 'reviewed'],
        'verify' => [['reviewed'], 'verified'],
        'reject' => [['submitted', 'reviewed'], 'rejected'],
        'reopen' => [['verified'], 'draft'],
    ];

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), 'verified');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketSource::class, 'source_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Draft and rejected records can be corrected; anything further along is locked. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    /** @return list<string> actions allowed now */
    public function allowedActions(): array
    {
        return array_keys(array_filter(self::TRANSITIONS, fn ($t) => in_array($this->status, $t[0], true)));
    }

    /** Apply a workflow action as $user. Returns false when it is not allowed from the current status. */
    public function transition(string $action, User $user, ?string $reason = null): bool
    {
        if (! in_array($action, $this->allowedActions(), true)) {
            return false;
        }

        $to = self::TRANSITIONS[$action][1];
        $changes = ['status' => $to];
        match ($action) {
            'submit' => $changes += ['submitted_by' => $user->id, 'submitted_at' => now(), 'rejection_reason' => null],
            'review' => $changes += ['reviewed_by' => $user->id, 'reviewed_at' => now()],
            'verify' => $changes += ['verified_by' => $user->id, 'verified_at' => now()],
            'reject' => $changes += ['rejection_reason' => $reason],
            'reopen' => $changes += ['verified_by' => null, 'verified_at' => null, 'reviewed_by' => null, 'reviewed_at' => null],
        };
        $this->forceFill($changes)->save();

        return true;
    }

    /** "city" | "area" | "project": the level this record describes. */
    public function entityType(): string
    {
        return $this->project_id ? 'project' : ($this->area_id ? 'area' : 'city');
    }
}
