<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something the automatic pipeline will not decide on its own: a source it
 * cannot access, a list whose format changed, an official entry that could
 * match more than one project, a scheme removed from an official list, a
 * conflict between official lists, a stage it cannot read, or a security
 * concern. Everything routine runs without it; this is the human queue.
 */
class InvestmentException extends Model
{
    public const TYPES = [
        'source_access' => 'Source access problem',
        'format_change' => 'Source format changed',
        'ambiguous_match' => 'Match needs a decision',
        'removed_from_list' => 'Removed from an official list',
        'legal_conflict' => 'Conflicting official records',
        'unrecognised_status' => 'Official status not recognised',
        'security' => 'Security concern',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'resolved_at' => 'datetime'];
    }

    /** Opens an exception, or counts another occurrence of the same open one. */
    public static function raise(string $type, string $title, ?string $details = null, array $refs = [], array $payload = []): self
    {
        $fingerprint = hash('sha256', implode('|', [$type, $refs['source_id'] ?? '', $refs['project_id'] ?? '', $refs['registry_entry_id'] ?? '', $payload['key'] ?? $title]));
        $open = static::where('fingerprint', $fingerprint)->where('status', 'open')->first();
        if ($open) {
            $open->forceFill(['occurrences' => $open->occurrences + 1, 'details' => $details ?? $open->details, 'payload' => $payload ?: $open->payload])->save();

            return $open;
        }
        $e = new static;
        $e->forceFill(['type' => $type, 'title' => mb_substr($title, 0, 250), 'details' => $details, 'payload' => $payload ?: null, 'fingerprint' => $fingerprint,
            'source_id' => $refs['source_id'] ?? null, 'project_id' => $refs['project_id'] ?? null, 'registry_entry_id' => $refs['registry_entry_id'] ?? null])->save();

        return $e;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketSource::class, 'source_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function registryEntry(): BelongsTo
    {
        return $this->belongsTo(InvestmentRegistryEntry::class, 'registry_entry_id');
    }
}
