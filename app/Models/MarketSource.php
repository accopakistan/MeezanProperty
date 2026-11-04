<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where market data comes from. Being publicly accessible does not make a
 * source verified: reliability is set by staff, and asking-price sources are
 * never treated as transaction records.
 */
class MarketSource extends Model
{
    public const RELIABILITY = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

    protected $fillable = ['name', 'type', 'url', 'license', 'reliability', 'coverage', 'publisher', 'last_checked_on', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['last_checked_on' => 'date', 'is_active' => 'boolean', 'is_demo' => 'boolean',
            'collector_config' => 'array', 'authorized_at' => 'datetime', 'last_collected_at' => 'datetime'];
    }

    /**
     * Collected automatically only when active, linked to a known collector,
     * and authorized: an admin has recorded once on what basis automated
     * access is permitted.
     */
    public function isCollectable(): bool
    {
        return $this->is_active && ! $this->is_demo && $this->authorized_at && $this->access_basis && $this->collectorPreset() !== null;
    }

    /** The collector preset merged with this source's own settings, or null. */
    public function collectorPreset(): ?array
    {
        $preset = $this->collector ? config('investment_index.collectors.'.$this->collector) : null;

        return $preset ? array_replace_recursive($preset, $this->collector_config ?? []) : null;
    }

    public function collectionRuns(): HasMany
    {
        return $this->hasMany(InvestmentCollectionRun::class, 'source_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(MarketObservation::class, 'source_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(MarketIndicator::class, 'source_id');
    }

    public function typeLabel(): string
    {
        return config('meezan.investment.source_types')[$this->type] ?? $this->type;
    }

    /** 0-1 quality weight used by the confidence score. */
    public function reliabilityWeight(): float
    {
        return (float) (config('meezan.investment.reliability')[$this->reliability] ?? 0.3);
    }

    /** Sources from one publisher count once for independence. */
    public function independenceKey(): string
    {
        return mb_strtolower(trim($this->publisher ?: $this->name));
    }
}
