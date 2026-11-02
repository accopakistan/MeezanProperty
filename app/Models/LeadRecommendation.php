<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A real listing a dealer recommended to a lead, and what happened next as
 * recorded by the dealer (viewed / interested / not interested).
 */
class LeadRecommendation extends Model
{
    public const STATUSES = ['recommended' => 'Recommended', 'viewed' => 'Viewed', 'interested' => 'Interested', 'not_interested' => 'Not interested'];

    protected $fillable = ['note'];

    protected $attributes = ['status' => 'recommended'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class, 'lead_assignment_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function statusTone(): string
    {
        return ['recommended' => 'info', 'viewed' => 'brand', 'interested' => 'success', 'not_interested' => 'muted'][$this->status] ?? 'neutral';
    }
}
