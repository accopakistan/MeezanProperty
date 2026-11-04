<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Evidence a project's owner or manager submits with documents (a rent
 * roll, occupancy statement, audited accounts, a safety certificate, an
 * approval letter). It counts only after Meezan staff check the documents
 * once; then it becomes a verified evidence or legal record with this
 * submission as its traceable source. Files stay on the private disk.
 */
class InvestmentEvidenceSubmission extends Model
{
    public const DISK = 'local';

    public const MAX_FILES = 3;

    public const DOCUMENT_TYPES = [
        'owner_statement' => 'Owner / management statement (rent roll, occupancy register, progress report)',
        'third_party' => 'Issued by an independent party (auditor, valuer, engineer, authority)',
    ];

    public const STATUSES = ['pending' => 'Awaiting staff check', 'verified' => 'Verified', 'rejected' => 'Not accepted'];

    /** Factors an owner can document. Structural safety needs a third-party certificate. */
    public const OWNER_FACTORS = ['development_progress', 'occupancy', 'rental_income', 'utilities', 'footfall', 'tenant_quality', 'maintenance',
        'operational_performance', 'financial_stability', 'structural_safety', 'delivery_status', 'operational_status'];

    public const THIRD_PARTY_ONLY = ['structural_safety'];

    protected $fillable = ['organisation', 'submitter_role', 'kind', 'factor', 'subject', 'value_numeric', 'value_level', 'figures', 'observed_on',
        'authority', 'record_type', 'legal_status', 'reference', 'issued_on', 'document_type', 'issuer', 'notes'];

    protected function casts(): array
    {
        return ['figures' => 'array', 'files' => 'array', 'observed_on' => 'date', 'issued_on' => 'date', 'reviewed_at' => 'datetime', 'value_numeric' => 'float'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(InvestmentEvidence::class, 'evidence_id');
    }

    public function legalRecord(): BelongsTo
    {
        return $this->belongsTo(InvestmentLegalRecord::class, 'legal_record_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Plain-language summary of what was claimed. */
    public function claim(): string
    {
        if ($this->kind === 'legal') {
            return ($this->authority ?: 'Authority').': '.config('investment_index.legal.statuses.'.$this->legal_status, (string) $this->legal_status)
                .($this->reference ? " ({$this->reference})" : '');
        }
        $label = ucfirst(str_replace('_', ' ', (string) $this->factor));
        $value = $this->value_level !== null ? str_replace('_', ' ', $this->value_level) : rtrim(rtrim(number_format((float) $this->value_numeric, 2), '0'), '.');
        $unit = in_array($this->factor, ['development_progress', 'occupancy', 'rental_income'], true) ? '%' : '';

        return $label.($this->subject ? ' ('.$this->subject.')' : '').': '.$value.$unit;
    }

    /** Inline, never cached, never sniffed as another type. */
    public function streamFile(int $index): StreamedResponse
    {
        $file = $this->files[$index] ?? abort(404);

        return Storage::disk(self::DISK)->response($file['path'], $file['name'], [
            'Content-Type' => $file['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex',
        ], 'inline');
    }
}
