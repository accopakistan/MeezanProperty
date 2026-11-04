<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ranking snapshot row: one entity (city, area/society or project) in one
 * ranking category and asset class, as calculated by one run with one
 * methodology version. Rows are never updated after their run is published.
 */
class InvestmentRanking extends Model
{
    public const CONFIDENCE = ['high' => 'High Confidence', 'medium' => 'Medium Confidence', 'limited' => 'Limited Data', 'insufficient' => 'Insufficient Data'];

    public const RISK = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'insufficient' => 'Insufficient Data'];

    public const ENTITY_TYPES = ['city' => 'City', 'area' => 'Area / society', 'project' => 'Project'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['factors' => 'array', 'metrics' => 'array', 'score' => 'float', 'confidence_score' => 'float', 'is_demo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (InvestmentRanking $r) {
            if (in_array($r->calculation()->value('status'), ['published', 'superseded'], true)) {
                throw new \LogicException('Published ranking snapshots are immutable.');
            }
        });
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(InvestmentCalculation::class, 'calculation_id');
    }

    public function scopeRanked(Builder $query): Builder
    {
        return $query->where('status', 'ranked');
    }

    public function isRanked(): bool
    {
        return $this->status === 'ranked';
    }

    public function categoryLabel(): string
    {
        return config('meezan.investment.categories.'.$this->category.'.label') ?? $this->category;
    }

    public function confidenceLabel(): string
    {
        return self::CONFIDENCE[$this->confidence] ?? $this->confidence;
    }

    public function riskLabel(): string
    {
        return self::RISK[$this->risk] ?? $this->risk;
    }

    public function entityTypeLabel(): string
    {
        return self::ENTITY_TYPES[$this->entity_type] ?? $this->entity_type;
    }

    public function metric(string $key, mixed $default = null): mixed
    {
        return data_get($this->metrics, $key, $default);
    }
}
