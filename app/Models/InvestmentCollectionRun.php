<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One automatic collection from an official source: what was fetched, found, matched and recorded. */
class InvestmentCollectionRun extends Model
{
    public const STATUSES = ['running' => 'Running', 'completed' => 'Completed', 'partial' => 'Partial (not all pages or too few entries)',
        'failed' => 'Failed', 'blocked' => 'Blocked (not authorized or not allowed)'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketSource::class, 'source_id');
    }
}
