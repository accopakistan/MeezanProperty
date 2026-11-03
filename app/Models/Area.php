<?php

namespace App\Models;

use App\Support\LocationName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A location inside a launch city: neighbourhood, housing society, phase,
 * sector or block. `parent_id` builds the hierarchy; approval fields record
 * what an authority's published list says, kept apart from searchability.
 */
class Area extends Model
{
    public const TYPES = [
        'neighbourhood' => 'Neighbourhood', 'housing_scheme' => 'Housing scheme', 'cooperative_society' => 'Cooperative society',
        'government_scheme' => 'Government scheme', 'defence_scheme' => 'Defence housing', 'cantonment' => 'Cantonment',
        'phase' => 'Phase', 'sector' => 'Sector', 'block' => 'Block', 'sub_area' => 'Sub-area',
        'commercial_area' => 'Commercial area', 'corridor' => 'Road / corridor',
    ];

    /** Labels are deliberately literal: they repeat what the source says, nothing more. */
    public const APPROVAL_STATUSES = [
        'approved' => 'Approved (per authority list)',
        'final_approved' => 'Final approval',
        'technical_approval' => 'Technical approval',
        'layout_plan_approved' => 'Layout plan approved, NOC not issued',
        'noc_issued' => 'NOC issued',
        'under_process' => 'Approval under process',
        'noc_not_issued' => 'NOC not issued',
        'cancelled' => 'Approval/NOC cancelled',
        'declared_illegal' => 'Listed as illegal by authority',
        'unverified' => 'Not verified',
        'not_applicable' => 'Not applicable',
    ];

    protected $fillable = [
        'city', 'city_id', 'parent_id', 'name', 'official_name', 'slug', 'type', 'authority', 'approval_status', 'approval_reference',
        'verification_source_url', 'source_ids', 'verified_on', 'confidence', 'needs_review', 'is_active', 'merged_into_id', 'research_key', 'notes',
        'is_popular', 'sort_order', 'latitude', 'longitude', 'coordinates_source', 'coordinate_precision',
    ];

    protected function casts(): array
    {
        return [
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'needs_review' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'verified_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Area $area) {
            $area->normalized_name = LocationName::normalize($area->name);
            if ($area->isDirty('city') || $area->city_id === null) {
                $area->city_id = City::idFor($area->city);
            }
            if ($area->isDirty('parent_id')) {
                $area->depth = $area->parent_id ? (int) static::whereKey($area->parent_id)->value('depth') + 1 : 0;
            }
        });
    }

    /** Approximate centre of the area, for listings without an exact pin. */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function cityModel(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Area::class, 'parent_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(AreaAlias::class);
    }

    /** Outline versions (draft, verified, published, archived). */
    public function boundaries(): HasMany
    {
        return $this->hasMany(AreaBoundary::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function dealers(): BelongsToMany
    {
        return $this->belongsToMany(DealerProfile::class);
    }

    /** "DHA Phase 5, Lahore" or "Sector C, DHA Phase 2, Lahore" (parent shown only when the name lacks it). */
    public function label(bool $withCity = true): string
    {
        $parts = [$this->name];
        if ($this->parent_id && $this->relationLoaded('parent') && $this->parent && ! str_contains($this->name, $this->parent->name)) {
            $parts[] = $this->parent->name;
        }
        if ($withCity) {
            $parts[] = $this->city;
        }

        return implode(', ', $parts);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function approvalLabel(): string
    {
        return self::APPROVAL_STATUSES[$this->approval_status] ?? 'Not verified';
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->where('is_popular', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInCity(Builder $query, ?string $city): Builder
    {
        return $city ? $query->where('city', $city) : $query;
    }

    /** Name, normalised name or alias match ("gulshan e iqbal", "F7", "DHA Phase V"). */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $norm = LocationName::normalize($term);
        if ($norm === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $q) => $q
            ->where('normalized_name', 'like', $norm.'%')
            ->orWhere('normalized_name', 'like', '% '.$norm.'%')
            ->orWhereHas('aliases', fn (Builder $a) => $a->where('normalized', 'like', $norm.'%')));
    }
}
