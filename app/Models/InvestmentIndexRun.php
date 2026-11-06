<?php

namespace App\Models;

use App\Services\InvestmentIndex\IndexBrowser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One automatic Investment Index calculation. A successful run is
 * published at once and supersedes the previous one, which is kept with
 * its scores as history.
 */
class InvestmentIndexRun extends Model
{
    protected function casts(): array
    {
        return ['summary' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // A newly published run must be seen at once by the per-request Index browser.
        static::saved(fn () => app()->forgetInstance(IndexBrowser::class));
    }

    public static function latestPublished(): ?self
    {
        return static::where('status', 'published')->latest('published_at')->latest('id')->first();
    }

    public function methodology(): BelongsTo
    {
        return $this->belongsTo(InvestmentIndexMethodology::class, 'methodology_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(InvestmentIndexScore::class, 'run_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(InvestmentIndexChange::class, 'run_id');
    }
}
