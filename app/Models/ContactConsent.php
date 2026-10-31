<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded consent (or its withdrawal). The phone number or email is kept
 * only as a SHA-256 hash so consent can be checked without storing it twice.
 */
class ContactConsent extends Model
{
    public const PURPOSES = [
        'share_with_dealers' => 'Share my requirement and contact with up to 3 matching verified dealers',
        'whatsapp_replies' => 'Receive replies about my requirement on WhatsApp',
        'listing_alerts' => 'Receive new-listing alerts',
    ];

    protected $fillable = ['user_id', 'conversation_id', 'channel', 'contact_hash', 'purpose', 'source', 'granted_at', 'revoked_at', 'ip_address'];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hash(?string $contact): ?string
    {
        $normalised = $contact ? strtolower(preg_replace('/[^0-9a-z@.+]/i', '', $contact)) : '';

        return $normalised === '' ? null : hash('sha256', $normalised);
    }

    public static function record(string $purpose, string $channel, string $source, ?int $userId = null, ?string $contact = null, ?int $conversationId = null): self
    {
        return static::create([
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'channel' => $channel,
            'contact_hash' => static::hash($contact),
            'purpose' => $purpose,
            'source' => $source,
            'granted_at' => now(),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /** Withdraw every active consent for a contact on a channel (e.g. WhatsApp "STOP"). */
    public static function revokeAll(string $channel, string $contact): int
    {
        return static::where('channel', $channel)->where('contact_hash', static::hash($contact))->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public static function granted(string $purpose, string $channel, ?string $contact): bool
    {
        return static::active()->where('purpose', $purpose)->where('channel', $channel)->where('contact_hash', static::hash($contact))->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
