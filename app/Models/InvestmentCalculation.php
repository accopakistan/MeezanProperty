<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One recalculation run. Completed runs are reviewed and then published by
 * an admin; nothing is published automatically. Publishing supersedes the
 * previous published run, whose rankings are kept as history.
 */
class InvestmentCalculation extends Model
{
    public const STATUSES = [
        'running' => 'Running',
        'completed' => 'Completed (not published)',
        'failed' => 'Failed',
        'published' => 'Published',
        'superseded' => 'Superseded (history)',
    ];

    protected function casts(): array
    {
        return [
            'inputs' => 'array', 'summary' => 'array', 'includes_demo' => 'boolean',
            'started_at' => 'datetime', 'finished_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        // Demo mode prefers the demo snapshot (which also contains any real verified data);
        // otherwise only a snapshot built from real verified data is shown.
        return static::where('status', 'published')
            ->when(static::demoMode(), fn ($q) => $q->orderByDesc('includes_demo'), fn ($q) => $q->where('includes_demo', false))
            ->latest('published_at')->latest('id')->first();
    }

    /** INVESTMENT_DEMO_MODE, unless an admin switched it in the Demo Data screen. */
    public static function demoMode(): bool
    {
        $override = AppSetting::get('investment.demo_mode');

        return $override === null ? (bool) config('meezan.investment.demo_mode') : (bool) $override;
    }

    public function methodology(): BelongsTo
    {
        return $this->belongsTo(InvestmentMethodology::class, 'methodology_id');
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(InvestmentRanking::class, 'calculation_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
