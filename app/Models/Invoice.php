<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * A billing record. Numbers are sequential by id (MP-2026-000042) and an
 * invoice is never deleted; amounts are fixed once issued.
 */
class Invoice extends Model
{
    protected $fillable = [];

    protected $attributes = ['is_test' => false, 'refunded_amount' => 0];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'billing_snapshot' => 'array',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'is_test' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Invoice $invoice) => $invoice->number ??= 'TMP-'.Str::random(20));
        static::created(function (Invoice $invoice) {
            $invoice->number = sprintf('%s-%s-%06d', config('meezan.billing.invoice_prefix'), $invoice->issued_at->format('Y'), $invoice->id);
            $invoice->saveQuietly();
        });
        static::updating(function (Invoice $invoice) {
            if ($invoice->isDirty('amount') && $invoice->getOriginal('number') && ! str_starts_with($invoice->getOriginal('number'), 'TMP-')) {
                throw new LogicException('An issued invoice amount cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Invoices are never deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BillingAdjustment::class)->latest('id');
    }

    public function refundable(): float
    {
        return max(0, (float) $this->amount - (float) $this->refunded_amount);
    }
}
