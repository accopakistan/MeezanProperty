<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Services\Billing\Gateways\GatewayManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * One payment attempt. Holds no card or banking credentials; status only
 * changes through App\Services\Billing\PaymentProcessor.
 */
class Payment extends Model
{
    protected $fillable = [];

    protected $attributes = ['is_test' => false];

    protected $hidden = ['proof_path'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_test' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Payment $payment) => $payment->reference ??= 'TMP-'.Str::random(20));
        static::created(function (Payment $payment) {
            $payment->reference = sprintf('PAY-%07d', $payment->id);
            $payment->saveQuietly();
        });
        static::deleting(fn () => throw new LogicException('Payments are never deleted.'));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function gatewayLabel(): string
    {
        return app(GatewayManager::class)->label($this->gateway);
    }
}
