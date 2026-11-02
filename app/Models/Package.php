<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A subscription package, created and edited by admins. Limits are null
 * for "no limit". Subscriptions copy snapshot() when they start, so
 * editing a package never changes what an existing dealer bought.
 */
class Package extends Model
{
    public const INTERVALS = ['month' => 'Monthly', 'year' => 'Yearly'];

    /** Limit keys as used across billing: [column, label]. */
    public const LIMITS = [
        'listings' => ['listing_limit', 'Active listings'],
        'leads' => ['lead_limit', 'Leads per month'],
        'featured' => ['featured_limit', 'Featured listings'],
        'team_members' => ['team_member_limit', 'Team members'],
        'ai_requests' => ['ai_request_limit', 'AI requests per month'],
    ];

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'currency', 'billing_interval', 'trial_days',
        'listing_limit', 'lead_limit', 'featured_limit', 'team_member_limit', 'ai_request_limit',
        'features', 'is_active', 'is_default', 'is_recommended', 'is_demo', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_recommended' => 'boolean',
            'is_demo' => 'boolean',
            'trial_days' => 'integer',
            'listing_limit' => 'integer',
            'lead_limit' => 'integer',
            'featured_limit' => 'integer',
            'team_member_limit' => 'integer',
            'ai_request_limit' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Package $package) {
            $package->slug = Str::slug($package->slug ?: $package->name);
        });
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('price')->orderBy('id');
    }

    /** The plan for dealers without a subscription, if an admin has set one. */
    public static function defaultPlan(): ?self
    {
        return static::active()->where('is_default', true)->ordered()->first();
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /** Price per month, for comparing packages billed at different intervals. */
    public function monthlyPrice(): float
    {
        return $this->billing_interval === 'year' ? (float) $this->price / 12 : (float) $this->price;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function intervalLabel(): string
    {
        return $this->billing_interval === 'year' ? 'year' : 'month';
    }

    /** What a subscription keeps: price, interval, limits and features at the time. */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => (string) $this->price,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'limits' => collect(self::LIMITS)->mapWithKeys(fn ($limit, $key) => [$key => $this->{$limit[0]}])->all(),
            'features' => array_values($this->features ?? []),
            'is_demo' => $this->is_demo,
        ];
    }
}
