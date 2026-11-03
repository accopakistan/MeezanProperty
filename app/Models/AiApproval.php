<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An action an agent proposed that needs a human decision before anything happens. */
class AiApproval extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'expired' => 'Expired'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'evidence' => 'array', 'execution' => 'array', 'decided_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AiTask::class, 'task_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
