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
            'inputs' => 'array', 'summary' => 'array',
            'started_at' => 'datetime', 'finished_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::where('status', 'published')->latest('published_at')->latest('id')->first();
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
