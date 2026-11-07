<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Launch-city details from config('meezan.cities') / city_details.
 */
class Cities
{
    /** @return list<string> */
    public static function names(): array
    {
        return config('meezan.cities');
    }

    public static function slug(string $city): string
    {
        return config("meezan.city_details.{$city}.slug") ?? Str::slug($city);
    }

    public static function province(string $city): ?string
    {
        return config("meezan.city_details.{$city}.province");
    }

    public static function fromSlug(string $slug): ?string
    {
        foreach (self::names() as $city) {
            if (self::slug($city) === $slug) {
                return $city;
            }
        }

        return null;
    }

    public static function canonical(?string $city): ?string
    {
        if (! $city) {
            return null;
        }

        foreach (self::names() as $name) {
            if (strcasecmp($name, $city) === 0 || self::slug($name) === Str::slug($city)) {
                return $name;
            }
        }

        return self::fromSlug($city);
    }

    /** @return array<string, string> name => name, for <select> options */
    public static function options(): array
    {
        return array_combine(self::names(), self::names());
    }
}
