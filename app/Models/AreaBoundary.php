<?php

namespace App\Models;

use App\Support\BoundaryGeometry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a society / area outline (GeoJSON, [lng, lat]).
 *
 * draft -> verified -> published -> archived. Geometry can only change while
 * a draft; a verified or published outline is revised by copying it to a new
 * draft. Publishing a boundary archives the area's previously published one.
 */
class AreaBoundary extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'verified' => 'Verified',
        'published' => 'Published',
        'archived' => 'Archived',
    ];

    public const SOURCES = [
        'admin_drawn' => 'Drawn by Meezan Property staff',
        'imported' => 'Imported (verified) data',
        'openstreetmap' => 'OpenStreetMap (ODbL)',
    ];

    protected $fillable = ['source', 'source_reference', 'notes'];

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'min_lat' => 'float',
            'max_lat' => 'float',
            'min_lng' => 'float',
            'max_lng' => 'float',
            'area_sq_km' => 'float',
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Boundaries whose bounding box overlaps the given box. */
    public function scopeIntersecting(Builder $query, float $south, float $west, float $north, float $east): Builder
    {
        return $query->where('min_lat', '<=', $north)->where('max_lat', '>=', $south)
            ->where('min_lng', '<=', $east)->where('max_lng', '>=', $west);
    }

    /** Store a validated geometry and its derived bounding box and size. */
    public function setGeometry(BoundaryGeometry $geometry): static
    {
        $this->forceFill([
            'geometry' => $geometry->geometry,
            ...$geometry->bounds(),
            'vertex_count' => $geometry->vertexCount(),
            'area_sq_km' => $geometry->areaSqKm(),
        ]);

        return $this;
    }

    public function shape(): BoundaryGeometry
    {
        return BoundaryGeometry::parse($this->geometry);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** Transitions allowed from the current status. */
    public function nextActions(): array
    {
        return match ($this->status) {
            'draft' => ['verify' => 'Mark verified', 'archive' => 'Archive'],
            'verified' => ['publish' => 'Publish', 'reopen' => 'Back to draft', 'archive' => 'Archive'],
            'published' => ['unpublish' => 'Unpublish'],
            default => [],
        };
    }
}
