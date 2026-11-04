<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of an official list (an approved scheme, an illegal scheme, a
 * scheme developed by the authority), as last read from the source, with
 * when it was first and last seen and which project it was matched to.
 */
class InvestmentRegistryEntry extends Model
{
    public const LIST_TYPES = ['approved' => 'Approved schemes', 'illegal' => 'Illegal / unapproved schemes', 'authority_scheme' => 'Developed by the authority'];

    public const MATCH_STATUSES = ['matched' => 'Matched automatically', 'manual' => 'Matched by staff', 'ambiguous' => 'Needs a match decision',
        'unmatched' => 'No project yet', 'rejected' => 'Not a project here'];

    /** Identity only; everything else is written by the collector with forceFill. */
    protected $fillable = ['source_id', 'external_key'];

    protected function casts(): array
    {
        return ['raw' => 'array', 'issued_on' => 'date', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'removed_at' => 'datetime', 'match_score' => 'float'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketSource::class, 'source_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isMatched(): bool
    {
        return in_array($this->match_status, ['matched', 'manual'], true) && $this->project_id;
    }
}
