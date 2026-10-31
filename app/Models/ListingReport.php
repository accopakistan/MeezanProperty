<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingReport extends Model
{
    public const REASONS = [
        'duplicate' => 'Duplicate listing',
        'unavailable' => 'Already sold or rented',
        'wrong_details' => 'Wrong price, size or details',
        'misleading_photos' => 'Photos are not of this property',
        'fraud' => 'Suspected fraud or scam',
        'unauthorized' => 'Lister is not authorised',
        'other' => 'Something else',
    ];

    protected $fillable = ['reason', 'details', 'reporter_email'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
