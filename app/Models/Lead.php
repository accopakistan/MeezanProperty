<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'purpose',
        'type',
        'source',
        'consent_to_share',
        'dedupe_key',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'purpose' => Purpose::class,
            'type' => PropertyType::class,
            'consent_to_share' => 'boolean',
            'invalidated_at' => 'datetime',
        ];
    }

    public const HOLD_REASONS = ['capacity' => 'Every suitable dealer has used their lead allowance'];

    public const INVALID_REASONS = ['spam' => 'Spam', 'duplicate' => 'Duplicate', 'invalid' => 'Invalid contact details'];

    public function isInvalid(): bool
    {
        return $this->invalidated_at !== null;
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(BuyerRequirement::class, 'buyer_requirement_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)->latest('assigned_at');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)->where('status', 'active');
    }

    public function contactName(): ?string
    {
        return $this->inquiry?->name ?? $this->requirement?->user?->name;
    }
}
