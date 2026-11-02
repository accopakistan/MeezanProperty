<?php

namespace App\Models;

use App\Enums\PipelineStage;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One dealer's relationship with one lead. Besides routing (status
 * active/reassigned/removed) it carries that dealer's private CRM state:
 * pipeline stage, priority, milestones and outcome. Notes, follow-ups,
 * appointments and recommendations hang off it, so nothing one dealer
 * records is visible to another dealer on the same lead.
 */
class LeadAssignment extends Model
{
    protected $fillable = ['score', 'reason'];

    protected $attributes = ['pipeline_stage' => 'new', 'priority' => 'normal', 'status' => 'active'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
            'responded_at' => 'datetime',
            'pipeline_stage' => PipelineStage::class,
            'stage_changed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'first_contact_at' => 'datetime',
            'qualified_at' => 'datetime',
            'closed_at' => 'datetime',
            'stale_flagged_at' => 'datetime',
            'won_value' => 'decimal:2',
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

    public function wonProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'won_property_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest('id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(LeadFollowUp::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(LeadAppointment::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(LeadRecommendation::class)->latest('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('lead_assignments.status', 'active');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('pipeline_stage', ['won', 'lost', 'invalid', 'duplicate']);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** City of the lead: the area's city, else the requirement's or listing's. */
    public function city(): ?string
    {
        return $this->lead?->area?->city ?? $this->lead?->requirement?->city ?? $this->lead?->property?->city;
    }

    /** "10 Marla House · For Sale · Gulberg, Lahore · up to PKR 5 Crore", from loaded relations only. */
    public function headline(): string
    {
        $lead = $this->lead;
        $requirement = $lead?->requirement;
        if ($lead?->property && ! $requirement) {
            return 'Enquiry: '.$lead->property->title;
        }

        return collect([
            trim(($requirement?->min_area_sqft ? round($requirement->min_area_sqft / config('meezan.marla_sqft')).' Marla ' : '').($lead?->type?->label() ?? 'Any property')),
            $lead?->purpose?->label(),
            collect([$lead?->area?->name, $this->city()])->filter()->unique()->implode(', '),
            $requirement?->max_price ? 'up to '.Format::price($requirement->max_price) : null,
        ])->filter()->implode(' · ');
    }

    /** Buyer's name as shared with the dealer (requirement account or WhatsApp contact, or the enquiry). */
    public function buyerName(): string
    {
        $lead = $this->lead;

        return $lead?->requirement?->user?->name ?? $lead?->requirement?->contact_name ?? $lead?->inquiry?->name ?? 'Buyer';
    }

    public function priorityLabel(): string
    {
        return config('meezan.crm.priorities.'.$this->priority, ucfirst((string) $this->priority));
    }

    public function priorityTone(): string
    {
        return ['urgent' => 'danger', 'high' => 'warning', 'normal' => 'neutral', 'low' => 'muted'][$this->priority] ?? 'neutral';
    }
}
