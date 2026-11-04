<?php

namespace App\Models;

use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A new housing scheme or development, curated by Meezan Property staff. */
class Project extends Model
{
    public const STATUSES = [
        'upcoming' => 'Upcoming',
        'under_construction' => 'Under construction',
        'completed' => 'Completed',
    ];

    protected $fillable = ['name', 'developer', 'category', 'city', 'area_id', 'latitude', 'longitude', 'status', 'launched_on', 'min_price', 'max_price',
        'description', 'specifications', 'website', 'approval_note', 'approval_status'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_demo' => 'boolean', 'is_featured' => 'boolean', 'min_price' => 'integer', 'max_price' => 'integer', 'launched_on' => 'date',
            'specifications' => 'array', 'gallery' => 'array', 'latitude' => 'float', 'longitude' => 'float'];
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

    public function categoryLabel(): ?string
    {
        return $this->category ? (config('meezan.investment.project_categories.'.$this->category.'.label') ?? $this->category) : null;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function coverUrl(): string
    {
        return $this->imageUrl($this->cover_path) ?? asset('images/placeholders/commercial.svg');
    }

    /** Uploaded files live in storage; bundled illustrations (demo data) under public/images. */
    public function imageUrl(?string $path): ?string
    {
        return match (true) {
            ! $path => null,
            str_starts_with($path, 'images/') => asset($path),
            default => asset('storage/'.$path),
        };
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
