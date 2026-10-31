<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignment extends Model
{
    protected $fillable = ['score', 'reason'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dealer_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
