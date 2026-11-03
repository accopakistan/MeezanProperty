<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit record of one tool invocation by an agent. Append-only. */
class AiToolCall extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['input' => 'array', 'summary' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Tool call records are immutable.'));
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AiTask::class, 'task_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }
}
