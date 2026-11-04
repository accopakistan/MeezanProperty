<?php

namespace App\Models;

use App\Models\Concerns\HasEvidenceProvenance;
use Illuminate\Database\Eloquent\Model;

/**
 * One verified-or-not fact about a project for one index factor: a
 * percentage (occupancy, progress), a gross yield, or a level (location,
 * maintenance, safety...), observed on a date, with its source URL and
 * collection time. Never estimated: what is not on file is missing.
 */
class InvestmentEvidence extends Model
{
    use HasEvidenceProvenance;

    protected $table = 'investment_evidence';

    protected $fillable = ['project_id', 'factor', 'subject', 'value_numeric', 'value_level', 'unit', 'observed_on',
        'source_id', 'source_url', 'collected_at', 'content_hash', 'excerpt', 'collector', 'verification_method', 'meta'];

    protected function casts(): array
    {
        return ['observed_on' => 'date', 'collected_at' => 'datetime', 'verified_at' => 'datetime', 'value_numeric' => 'float', 'meta' => 'array'];
    }

    /** Factors and context evidence that can be recorded. */
    public static function kinds(): array
    {
        return array_keys(config('investment_index.factors') + config('investment_index.context_evidence'));
    }

    public function acceptedSourceTypes(): array
    {
        return config("investment_index.factors.{$this->factor}.sources")
            ?? config("investment_index.context_evidence.{$this->factor}.sources")
            ?? [];
    }

    protected function fingerprintFor(): string
    {
        return hash('sha256', implode('|', [$this->project_id, $this->factor, $this->subject, $this->value_numeric, $this->value_level,
            $this->observed_on?->toDateString(), $this->source_id, $this->source_url]));
    }
}
