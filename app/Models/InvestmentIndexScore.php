<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One project's result in one run: its category score and rank (when
 * ranked), Overall Index position, Data Confidence Score, risk class,
 * legal status, and the factor-by-factor breakdown with the evidence used.
 */
class InvestmentIndexScore extends Model
{
    protected function casts(): array
    {
        return ['score' => 'float', 'normalized_score' => 'float', 'overall_index' => 'float', 'confidence_score' => 'float', 'coverage' => 'float',
            'verified_yield' => 'float', 'development_progress' => 'float', 'last_verified_at' => 'datetime',
            'factors' => 'array', 'reasons' => 'array', 'risks' => 'array', 'source_ids' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(InvestmentIndexRun::class, 'run_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isRanked(): bool
    {
        return $this->status === 'ranked';
    }

    public function statusLabel(): string
    {
        return config('investment_index.statuses.'.$this->status, $this->status);
    }

    public function riskLabel(): string
    {
        return config('investment_index.risk_classes.'.$this->risk_class, $this->risk_class);
    }

    /**
     * Risk as shown to visitors: only for projects the Index has actually scored (ranked or
     * not eligible) or excluded for a legal issue. A project still under research has no
     * assessed risk, so it reads "Not assessed" rather than a risk class worked out from gaps.
     */
    public function publicRiskLabel(): string
    {
        return in_array($this->status, ['ranked', 'not_eligible', 'excluded'], true) ? $this->riskLabel() : 'Not assessed';
    }

    public function categoryLabel(): string
    {
        return config('investment_index.categories.'.$this->category.'.label', $this->category);
    }
}
