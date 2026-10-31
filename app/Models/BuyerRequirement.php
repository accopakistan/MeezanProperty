<?php

namespace App\Models;

use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Support\Format;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyerRequirement extends Model
{
    protected $attributes = [
        'status' => 'active',
        'source' => 'account',
    ];

    protected $fillable = [
        'title',
        'purpose',
        'type',
        'city',
        'area_ids',
        'min_price',
        'max_price',
        'min_area_sqft',
        'max_area_sqft',
        'bedrooms',
        'notes',
        'preferences',
        'match_status',
        'last_match_count',
        'matched_at',
        'contact_name',
        'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => Purpose::class,
            'type' => PropertyType::class,
            'area_ids' => 'array',
            'preferences' => 'array',
            'matched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return Collection<int, Area>
     */
    public function areas(): Collection
    {
        return Area::whereIn('id', $this->area_ids ?? [])->ordered()->get();
    }

    public function summary(): string
    {
        $parts = [
            $this->type?->label() ?? 'Any property',
            $this->purpose->label(),
            'in '.($this->areas()->pluck('name')->implode(', ') ?: $this->city),
        ];

        if ($this->max_price) {
            $parts[] = 'up to '.Format::price($this->max_price);
        }

        return implode(' ', $parts);
    }
}
