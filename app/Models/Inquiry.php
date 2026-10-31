<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inquiry extends Model
{
    protected $attributes = [
        'status' => 'new',
        'source' => 'website',
        'preferred_contact' => 'call',
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
        'preferred_contact',
        'consent_to_share',
    ];

    protected function casts(): array
    {
        return [
            'consent_to_share' => 'boolean',
            'responded_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'new' => 'info',
            'contacted' => 'warning',
            'closed' => 'success',
            default => 'neutral',
        };
    }
}
