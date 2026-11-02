<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A dealer's private note on their lead assignment. Never shown to buyers, other dealers or in admin lists. */
class LeadNote extends Model
{
    protected $fillable = ['body'];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class, 'lead_assignment_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
