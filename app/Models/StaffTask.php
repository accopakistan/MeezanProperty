<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An internal task for staff, created by staff, by an agent, or from an approved action. */
class StaffTask extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }
}
