<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only record of a refund, credit or debit made by staff.
 */
class BillingAdjustment extends Model
{
    public const TYPES = ['refund' => 'Refund', 'credit' => 'Credit', 'debit' => 'Debit'];

    protected $fillable = ['type', 'amount', 'currency', 'reason', 'external_reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Billing adjustments are immutable.'));
        static::deleting(fn () => throw new LogicException('Billing adjustments are immutable.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
