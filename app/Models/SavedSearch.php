<?php

namespace App\Models;

use App\Services\PropertySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's stored search. Filters are the same query parameters the public
 * search page accepts, so a saved search always reproduces the same results.
 */
class SavedSearch extends Model
{
    public const FREQUENCIES = ['off' => 'No alerts', 'daily' => 'Daily', 'weekly' => 'Weekly'];

    protected $fillable = ['name', 'filters', 'alert_frequency'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'last_checked_at' => 'datetime',
            'last_alerted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function search(): PropertySearch
    {
        return new PropertySearch($this->filters ?? []);
    }

    /** Published listings matching this search that appeared after $since. */
    public function newMatchesQuery(?\DateTimeInterface $since = null): Builder
    {
        $since ??= $this->last_checked_at ?? $this->created_at;

        return $this->search()->query()->where('published_at', '>', $since);
    }

    public function url(): string
    {
        return route('properties.index', $this->filters ?? []);
    }
}
