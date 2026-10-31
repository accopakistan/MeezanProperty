<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Verification evidence uploaded by a dealer. Files live on the private
 * "local" disk and are only ever streamed through authorised routes.
 */
class DealerDocument extends Model
{
    public const DISK = 'local';

    public const TYPES = [
        'cnic_front' => 'CNIC (front)',
        'cnic_back' => 'CNIC (back)',
        'agency_registration' => 'Agency registration / licence',
        'office_proof' => 'Office proof (utility bill or rent agreement)',
        'ownership_proof' => 'Ownership document (for private owners)',
        'other' => 'Other supporting document',
    ];

    public const STATUSES = [
        'pending' => 'Awaiting review',
        'approved' => 'Approved',
        'rejected' => 'Needs replacing',
        'superseded' => 'Replaced',
    ];

    /** Most current (not replaced) documents a profile may hold at once. */
    public const MAX_PER_PROFILE = 10;

    protected $fillable = ['type', 'original_name'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'Awaiting review';
    }

    public function statusTone(): string
    {
        return ['approved' => 'success', 'rejected' => 'danger', 'superseded' => 'muted'][$this->status] ?? 'warning';
    }

    /** Documents still in play (replaced ones are history). */
    public function scopeCurrent($query)
    {
        return $query->where('status', '!=', 'superseded');
    }

    /** @return list<string> document types this business type must have approved */
    public static function requiredFor(?string $businessType): array
    {
        $required = config('meezan.verification.required_documents');

        return $required[$businessType] ?? $required['agency'] ?? [];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_profile_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }

    /** Inline, never cached, never sniffed as another type. */
    public function streamResponse(): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($this->path, $this->original_name, [
            'Content-Type' => $this->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex',
        ], 'inline');
    }

    public function deleteFile(): void
    {
        Storage::disk(self::DISK)->delete($this->path);
    }
}
