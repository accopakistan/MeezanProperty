<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One unit of agent work. Delegated subtasks point at their parent and the
 * root task, with a depth that the orchestrator caps.
 */
class AiTask extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'running' => 'Running',
        'awaiting_approval' => 'Awaiting Approval',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = ['title', 'request', 'input', 'priority', 'source', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'input' => 'array', 'result' => 'array', 'cost_usd' => 'float',
            'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (AiTask $t) => $t->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class, 'task_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AiApproval::class, 'task_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }
}
