<?php

namespace App\Support;

class Format
{
    public const LAKH = 100_000;

    public const CRORE = 10_000_000;

    /**
     * Pakistani price notation, e.g. "PKR 2.5 Crore", "PKR 85 Lakh".
     */
    public static function price(?int $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        return 'PKR '.static::amount($amount);
    }

    public static function amount(int $amount): string
    {
        if ($amount >= self::CRORE) {
            return static::trim($amount / self::CRORE).' Crore';
        }

        if ($amount >= self::LAKH) {
            return static::trim($amount / self::LAKH).' Lakh';
        }

        return number_format($amount);
    }

    /**
     * Compact label for map markers, e.g. "2.5 Cr", "85 L", "45K".
     */
    public static function compactPrice(int $amount): string
    {
        return match (true) {
            $amount >= self::CRORE => static::trim($amount / self::CRORE, 1).' Cr',
            $amount >= self::LAKH => static::trim($amount / self::LAKH, 1).' L',
            $amount >= 1000 => static::trim($amount / 1000, 0).'K',
            default => (string) $amount,
        };
    }

    public static function number(float $value): string
    {
        return static::trim($value);
    }

    protected static function trim(float $value, int $decimals = 2): string
    {
        return rtrim(rtrim(number_format($value, $decimals, '.', ','), '0'), '.');
    }
}
