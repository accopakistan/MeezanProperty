<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime switches and small operational state (never credentials).
 * Reads are cached for a minute and the cache is cleared on write.
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // Wrapped, so a missing setting is cached too: Cache::remember never stores a bare null.
        $stored = Cache::remember('app-setting:v2:'.$key, 60, fn () => ['row' => static::find($key)?->value]);

        return $stored['row']['v'] ?? $default;
    }

    public static function put(string $key, mixed $value, ?int $userId = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => ['v' => $value], 'updated_by' => $userId]);
        Cache::forget('app-setting:'.$key);
        Cache::forget('app-setting:v2:'.$key);
    }
}
