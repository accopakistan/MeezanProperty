<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provider usage for one model call: actual tokens; cost only when pricing is configured. */
class AiUsageRecord extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cost_usd' => 'float', 'ok' => 'boolean'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AiTask::class, 'task_id');
    }
}
