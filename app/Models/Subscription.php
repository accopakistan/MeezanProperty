<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A dealer's contract for one package. Limits and features come from the
 * snapshot taken when it was created, never from the live package row.
 * Rows are only changed by App\Services\Billing\SubscriptionManager.
 */
class Subscription extends Model
{
    protected $fillable = [];

    protected $attributes = ['is_test' => false, 'cancel_at_period_end' => false, 'change_type' => 'new'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'amount' => 'decimal:2',
            'package_snapshot' => 'array',
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
            'ended_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
            'is_test' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_subscription_id');
    }

    public function scheduledPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'scheduled_package_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('issued_at');
    }

    public function scopeInForce(Builder $query): void
    {
        $query->whereIn('status', SubscriptionStatus::inForceCases());
    }

    public function name(): string
    {
        return $this->package_snapshot['name'] ?? 'Package';
    }

    /** @return int|null null = no limit */
    public function limit(string $key): ?int
    {
        $value = $this->package_snapshot['limits'][$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->package_snapshot['features'] ?? [], true);
    }

    public function isPaid(): bool
    {
        return (float) $this->amount > 0;
    }

    /** The date the current period ends: renewal for paid plans, trial end for trials. */
    public function renewsOrEndsAt(): ?Carbon
    {
        return $this->status === SubscriptionStatus::Trial ? $this->trial_ends_at : $this->current_period_end;
    }
}
