<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Controlled agent memory. Scopes: task (one task), conversation (one
 * conversation), agent (an agent's own long-term notes) and shared (approved
 * business knowledge for every agent). Agents may only propose agent or
 * shared memory; an admin approves it. Approved entries are never edited in
 * place: a change is a new version and the old one is archived.
 */
class AiMemory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
