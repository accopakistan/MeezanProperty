<?php

namespace App\Models;

use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** A new housing scheme or development, curated by Meezan Property staff. */
class Project extends Model
{
    public const STATUSES = [
        'upcoming' => 'Upcoming',
        'under_construction' => 'Under construction',
        'completed' => 'Completed',
    ];

    protected $fillable = ['name', 'developer', 'city', 'area_id', 'status', 'min_price', 'max_price', 'description', 'website', 'approval_note'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'min_price' => 'integer', 'max_price' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Project $project) {
            if (! $project->slug || $project->isDirty('name')) {
                $base = Str::slug($project->name) ?: 'project';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->when($project->id, fn ($q) => $q->whereKeyNot($project->id))->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $project->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function coverUrl(): string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : asset('images/placeholders/commercial.svg');
    }

    public function priceRange(): ?string
    {
        return match (true) {
            $this->min_price && $this->max_price => Format::price($this->min_price).' – '.Format::amount($this->max_price),
            (bool) $this->min_price => 'From '.Format::price($this->min_price),
            default => null,
        };
    }
}
