<?php

namespace App\Models;

use App\Enums\DealerStatus;
use App\Enums\Role;
use App\Notifications\VerifyEmailAddress;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable. Role and status are set
     * explicitly by the application, never from request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'city',
        'notify_in_app',
        'notify_email',
        'notify_whatsapp',
        'notify_account_email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'notify_in_app' => 'boolean',
            'notify_email' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'notify_account_email' => 'boolean',
        ];
    }

    public function dealerProfile(): HasOne
    {
        return $this->hasOne(DealerProfile::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function savedProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'saved_properties')->withTimestamps();
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function receivedInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'recipient_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(BuyerRequirement::class);
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /** New published listings across all saved searches since each was last opened. */
    public function unseenAlertCount(): int
    {
        if (! $this->notify_in_app) {
            return 0;
        }

        return $this->savedSearches->sum(fn (SavedSearch $s) => $s->newMatchesQuery()->count());
    }

    public function leadAssignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class, 'dealer_id');
    }

    public function hasRole(Role|string $role): bool
    {
        $role = $role instanceof Role ? $role : Role::from($role);

        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isDealer(): bool
    {
        return $this->role === Role::Dealer;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Service emails about the user's own listings, inquiries and leads.
     * Suspended accounts get none.
     */
    public function wantsAccountEmail(): bool
    {
        return $this->isActive() && $this->notify_account_email !== false;
    }

    /**
     * Active administrators, the recipients of moderation emails.
     *
     * @return Collection<int, static>
     */
    public static function activeAdmins()
    {
        return static::where('role', Role::Admin)->where('status', 'active')->get();
    }

    /** Queued, so registration never waits on the mail server. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailAddress);
    }

    public function isVerifiedDealer(): bool
    {
        return $this->isDealer() && $this->dealerProfile?->verification_status === DealerStatus::Verified;
    }

    public function hasSaved(Property $property): bool
    {
        return $this->savedProperties()->whereKey($property->getKey())->exists();
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
