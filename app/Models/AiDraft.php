<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Draft content prepared by an agent. Publishing needs approval and a connected account. */
class AiDraft extends Model
{
    protected $guarded = ['id'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }
}
