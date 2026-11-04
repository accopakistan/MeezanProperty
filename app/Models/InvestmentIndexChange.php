<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A change in a project's ranking between two published runs, with its reasons. Never edited. */
class InvestmentIndexChange extends Model
{
    protected function casts(): array
    {
        return ['reasons' => 'array', 'from_score' => 'float', 'to_score' => 'float'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(InvestmentIndexRun::class, 'run_id');
    }
}
