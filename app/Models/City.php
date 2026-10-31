<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * A launch city. Names match `config('meezan.cities')`, which stays the
 * source of truth for validation and routes; this table gives locations and
 * dealer coverage a key to hang on.
 */
class City extends Model
{
    protected $fillable = ['name', 'slug', 'province', 'country', 'latitude', 'longitude', 'coordinates_source', 'is_launch', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_launch' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('meezan.cities.ids');
            Cache::forget('meezan.cities.centres');
        });
    }

    /**
     * Map centre for a city: its OpenStreetMap point when geocoded, else the
     * configured centre, else null (callers fall back to the Pakistan view).
     *
     * @return array{0: float, 1: float}|null
     */
    public static function centre(?string $name): ?array
    {
        $centres = Cache::remember('meezan.cities.centres', 3600, fn () => static::whereNotNull('latitude')->get(['name', 'latitude', 'longitude'])
            ->mapWithKeys(fn (City $c) => [$c->name => [$c->latitude, $c->longitude]])->all());

        return $centres[$name] ?? config('meezan.map.city_centres.'.$name);
    }

    /** City id by name, cached (seven rows; used on every area save). */
    public static function idFor(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        $ids = Cache::get('meezan.cities.ids');
        if (! $ids) {
            $ids = static::pluck('id', 'name')->all();
            if ($ids) {
                Cache::forever('meezan.cities.ids', $ids);
            }
        }

        return $ids[$name] ?? null;
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function dealers(): BelongsToMany
    {
        return $this->belongsToMany(DealerProfile::class);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function scopeLaunched(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereIn('name', config('meezan.cities'))->orderBy('sort_order');
    }
}
