<?php

namespace App\Models;

use App\Models\Concerns\HasReviewWorkflow;
use Illuminate\Database\Eloquent\Model;

/**
 * A market indicator for a city, area/society or project, with its
 * evidence: demand, supply, liquidity, development progress (%),
 * infrastructure, legal verification, developer status or growth drivers.
 */
class MarketIndicator extends Model
{
    use HasReviewWorkflow;

    protected $fillable = [
        'country', 'city', 'area_id', 'project_id', 'asset_class', 'indicator', 'level', 'numeric_value',
        'observed_on', 'source_id', 'methodology', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'observed_on' => 'date', 'numeric_value' => 'float', 'is_demo' => 'boolean',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (MarketIndicator $i) => $i->fingerprint = static::fingerprintFor($i->getAttributes()));
    }

    public static function fingerprintFor(array $a): string
    {
        $date = $a['observed_on'] ?? '';
        $date = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);

        return hash('sha256', implode('|', [
            mb_strtolower((string) ($a['city'] ?? '')), $a['area_id'] ?? '', $a['project_id'] ?? '', $a['asset_class'] ?? '', $a['indicator'] ?? '',
            $a['level'] ?? '', isset($a['numeric_value']) ? (float) $a['numeric_value'] : '', $date, $a['source_id'] ?? '',
        ]));
    }

    public function indicatorLabel(): string
    {
        return config('meezan.investment.indicators.'.$this->indicator.'.label') ?? $this->indicator;
    }

    public function valueLabel(): string
    {
        if ($this->numeric_value !== null) {
            return rtrim(rtrim(number_format($this->numeric_value, 1), '0'), '.').'%';
        }

        return ucwords(str_replace('_', ' ', (string) $this->level));
    }
}
