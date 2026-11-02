<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A viewing, meeting or call a dealer scheduled for a lead. Cancelled rather than deleted. */
class LeadAppointment extends Model
{
    public const STATUSES = ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No show'];

    protected $fillable = ['type', 'starts_at', 'duration_minutes', 'location', 'notes'];

    protected $attributes = ['status' => 'scheduled', 'duration_minutes' => 30];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'reminder_sent_at' => 'datetime', 'duration_minutes' => 'integer'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class, 'lead_assignment_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function typeLabel(): string
    {
        return config('meezan.crm.appointment_types.'.$this->type, ucfirst($this->type));
    }

    public function statusTone(): string
    {
        return ['scheduled' => 'info', 'completed' => 'success', 'cancelled' => 'muted', 'no_show' => 'warning'][$this->status] ?? 'neutral';
    }
}
