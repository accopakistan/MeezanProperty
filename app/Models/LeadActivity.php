<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only timeline entry for a lead assignment (or, for admin events
 * such as a reassignment, the lead). Entries hold ids, stages and short
 * labels, never note text.
 */
class LeadActivity extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'stage_changed' => 'Stage changed',
        'priority_changed' => 'Priority changed',
        'note_added' => 'Note added',
        'note_edited' => 'Note edited',
        'follow_up_created' => 'Follow-up created',
        'follow_up_completed' => 'Follow-up completed',
        'follow_up_cancelled' => 'Follow-up cancelled',
        'appointment_scheduled' => 'Appointment scheduled',
        'appointment_updated' => 'Appointment updated',
        'appointment_completed' => 'Appointment completed',
        'appointment_cancelled' => 'Appointment cancelled',
        'appointment_no_show' => 'Buyer did not attend',
        'property_recommended' => 'Property recommended',
        'recommendation_updated' => 'Recommendation updated',
        'flagged_stale' => 'Flagged: no activity',
        'assigned' => 'Lead assigned',
        'assignment_removed' => 'Assignment removed by staff',
    ];

    protected $fillable = ['type', 'properties'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Lead activities are append-only.'));
        static::deleting(fn () => throw new LogicException('Lead activities are append-only.'));
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class, 'lead_assignment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
