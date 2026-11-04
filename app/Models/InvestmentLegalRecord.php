<?php

namespace App\Models;

use App\Models\Concerns\HasEvidenceProvenance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A legal fact about a project from an official authority: an approval or
 * NOC, a cancellation, an official illegal-schemes listing, a court case,
 * a regulatory warning or restriction, or a clearance. Only verified
 * records from official sources decide the project's legal status.
 */
class InvestmentLegalRecord extends Model
{
    use HasEvidenceProvenance;

    protected $fillable = ['project_id', 'authority', 'record_type', 'status', 'severity', 'reference', 'title', 'issued_on', 'valid_until',
        'source_id', 'source_url', 'collected_at', 'content_hash', 'excerpt', 'collector', 'verification_method', 'meta'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'valid_until' => 'date', 'collected_at' => 'datetime', 'verified_at' => 'datetime', 'meta' => 'array'];
    }

    public function acceptedSourceTypes(): array
    {
        return config('investment_index.factors.legal_approval.sources');
    }

    /** The date the record speaks for: when issued, else when collected. */
    public function effectiveDate(): CarbonInterface
    {
        return $this->issued_on ?? $this->collected_at;
    }

    public function statusLabel(): string
    {
        return config('investment_index.legal.statuses.'.$this->status, $this->status);
    }

    protected function fingerprintFor(): string
    {
        return hash('sha256', implode('|', [$this->project_id, mb_strtolower($this->authority), $this->record_type, $this->status, $this->reference,
            $this->issued_on?->toDateString(), $this->source_url]));
    }
}
