<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dealer's follow-up task on a lead. "Overdue" is not stored: it is a
 * pending follow-up past its due time. Follow-ups are completed or
 * cancelled, never deleted.
 */
class LeadFollowUp extends Model
{
    protected $fillable = ['type', 'priority', 'due_at', 'notes'];

    protected $attributes = ['status' => 'pending', 'priority' => 'normal'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class, 'lead_assignment_id');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', 'pending')->where('due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_at->isPast();
    }

    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function typeLabel(): string
    {
        return config('meezan.crm.follow_up_types.'.$this->type, ucfirst($this->type));
    }

    public function statusTone(): string
    {
        return ['overdue' => 'danger', 'pending' => 'warning', 'completed' => 'success', 'cancelled' => 'muted'][$this->displayStatus()] ?? 'neutral';
    }
}
