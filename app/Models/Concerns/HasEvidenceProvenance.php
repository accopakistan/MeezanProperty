<?php

namespace App\Models\Concerns;

use App\Jobs\RecalculateInvestmentIndex;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provenance for Investment Index records: every record names its source,
 * source URL and collection time, and is unverified until verified. A
 * record can only be verified when that provenance is complete and real:
 * a named, active, non-demo source of a type the record accepts, on a
 * real (non-demo) project. Asking prices and demo data never verify.
 * A verified change queues an automatic recalculation.
 */
trait HasEvidenceProvenance
{
    public const VERIFICATION_STATUSES = ['unverified' => 'Unverified', 'verified' => 'Verified', 'rejected' => 'Rejected', 'superseded' => 'Superseded'];

    /** Source types that can never verify anything in the index. */
    public const UNTRUSTED_SOURCE_TYPES = ['demo', 'public_asking_prices'];

    protected static function bootHasEvidenceProvenance(): void
    {
        static::saving(function ($record) {
            $record->fingerprint = $record->fingerprintFor();
            if ($record->verification_status === 'verified') {
                $record->assertVerifiable();
                $record->verified_at ??= now();
            }
        });

        // Only changes that can alter a score start a recalculation: a verified record
        // saved, or a record leaving "verified" ("saved" fires before originals sync).
        static::saved(function ($record) {
            if ($record->verification_status === 'verified'
                || ($record->wasChanged('verification_status') && $record->getOriginal('verification_status') === 'verified')) {
                RecalculateInvestmentIndex::schedule('evidence');
            }
        });
        static::deleted(fn ($record) => $record->verification_status === 'verified' && RecalculateInvestmentIndex::schedule('evidence'));
    }

    /** Source types whose verified records this record may come from. */
    abstract public function acceptedSourceTypes(): array;

    abstract protected function fingerprintFor(): string;

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('verification_status'), 'verified');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketSource::class, 'source_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @throws DomainException when the record may not be verified */
    public function assertVerifiable(): void
    {
        $source = $this->source_id ? MarketSource::find($this->source_id) : null;
        $project = $this->project_id ? Project::find($this->project_id) : null;
        $problem = match (true) {
            ! $project => 'it is not linked to a project',
            (bool) $project->is_demo => 'the project is demo data',
            ! $source => 'it has no source',
            ! $source->is_active => 'its source is not active',
            (bool) $source->is_demo || in_array($source->type, self::UNTRUSTED_SOURCE_TYPES, true) => 'its source type ('.$source->type.') cannot verify index evidence',
            ! in_array($source->type, $this->acceptedSourceTypes(), true) => 'a '.$source->type.' source is not accepted for this record',
            ! filter_var($this->source_url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', (string) $this->source_url) => 'it has no valid source URL',
            ! $this->collected_at => 'it has no collection time',
            default => null,
        };
        if ($problem) {
            throw new DomainException("This record cannot be verified: {$problem}.");
        }
    }

    public function verificationLabel(): string
    {
        return self::VERIFICATION_STATUSES[$this->verification_status] ?? $this->verification_status;
    }
}
