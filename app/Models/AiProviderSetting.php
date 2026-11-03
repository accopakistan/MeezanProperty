<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Settings for one language-model provider. A saved API key is encrypted with
 * the application key and never leaves the server: it is hidden from
 * serialisation and no view reads it. Without one, the key in .env is used.
 */
class AiProviderSetting extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['api_key'];

    /** @var list<string>|null */
    protected static ?array $storedKeys = null;

    protected static int $storedKeysAt = 0;

    protected static function booted(): void
    {
        static::saved(fn () => static::$storedKeys = null);
        static::deleted(fn () => static::$storedKeys = null);
    }

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'monthly_budget_usd' => 'float',
            'available_models' => 'array',
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function hasSavedKey(): bool
    {
        return filled($this->getRawOriginal('api_key'));
    }

    /** The saved key, or null when none is saved or it can no longer be decrypted (APP_KEY changed). */
    public function savedKey(): ?string
    {
        if (! $this->hasSavedKey()) {
            return null;
        }
        try {
            return $this->api_key;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Saved keys, so output filtering can remove them.
     *
     * @return list<string>
     */
    public static function storedKeys(): array
    {
        // Re-read now and then: a queue worker outlives a key replaced in the admin panel.
        if (static::$storedKeys === null || time() - static::$storedKeysAt > 60) {
            try {
                static::$storedKeys = static::query()->whereNotNull('api_key')->get()->map->savedKey()->filter()->values()->all();
                static::$storedKeysAt = time();
            } catch (Throwable) {
                return []; // table not migrated yet
            }
        }

        return static::$storedKeys;
    }
}
