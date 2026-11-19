<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A short advertised-price trend from a project's sourced research facts (owner's decision,
 * 19 Nov 2026): the Zameen.com price index gives today's asking price per plot size and its
 * 1- and 2-year change, so the prices one and two years earlier follow from them.
 *
 * Only figures on file are used; nothing is estimated beyond that arithmetic. These are
 * advertised (asking) prices, never sales, and every chart says so with its source.
 */
class PriceIndexTrend
{
    public const SIZES = ['5 marla' => '5 Marla', '10 marla' => '10 Marla', '1 kanal' => '1 Kanal', 'all sizes' => 'All sizes'];

    /**
     * @param  array<int, array<string, mixed>>|null  $facts  a project's research_facts
     * @return array<string, array{points: list<array{label: string, amount: int, derived: bool}>, source: string, url: ?string, basis: string}>
     *                                                                                                                                           size key => trend, only sizes with a usable price
     */
    public static function forFacts(?array $facts): array
    {
        $trends = [];
        foreach (array_keys(self::SIZES) as $size) {
            if ($trend = self::forSize($facts ?? [], $size)) {
                $trends[$size] = $trend;
            }
        }

        return $trends;
    }

    public static function forSize(array $facts, string $size): ?array
    {
        $find = function (string $label) use ($facts) {
            foreach ($facts as $f) {
                if (is_array($f) && strcasecmp(trim((string) ($f['label'] ?? '')), $label) === 0) {
                    return $f;
                }
            }

            return null;
        };

        $label = self::label($size);
        $price = $find('Advertised price ('.$label.')');
        $now = $price ? self::amount((string) $price['value']) : null;
        $month = $price ? self::month((string) $price['value']) : null;
        if (! $now || ! $month) {
            return null;
        }
        $meta = ['source' => (string) ($price['source'] ?? 'Zameen.com price index'), 'url' => $price['url'] ?? null,
            'basis' => (string) ($price['basis'] ?? 'Advertised (asking) price index, not verified sales')];

        // Dated prices read from the page itself (oldest first): every point is published, none worked out.
        $series = collect($price['series'] ?? [])->map(fn ($p) => is_array($p) && count($p) === 2 && ($a = self::amount((string) $p[1]))
            ? ['label' => (string) $p[0], 'amount' => $a, 'derived' => false] : null)->filter()->values()->all();
        if (count($series) >= 2) {
            return ['points' => $series] + $meta;
        }

        $points = [];
        foreach ([2 => 'Price change, 2 years ('.$label.')', 1 => 'Price change, 1 year ('.$label.')'] as $years => $label) {
            $change = ($f = $find($label)) ? self::percent((string) $f['value']) : null;
            // A change of -100% or less cannot be undone into an earlier price.
            if ($change !== null && $change > -100) {
                $points[] = ['label' => $month->copy()->subYears($years)->format('M Y'), 'amount' => (int) round($now / (1 + $change / 100)), 'derived' => true];
            }
        }
        if ($points === []) {
            return null;
        }
        $points[] = ['label' => $month->format('M Y'), 'amount' => $now, 'derived' => false];

        return ['points' => $points] + $meta;
    }

    /** The size part of a fact label: "10 marla plot", or "plot, all sizes" for areas priced as one average. */
    public static function label(string $size): string
    {
        return $size === 'all sizes' ? 'plot, all sizes' : $size.' plot';
    }

    /** "PKR 2.73 Crore (Sep 2026)" → 27300000; Lakh and plain rupee figures too. */
    public static function amount(string $value): ?int
    {
        if (! preg_match('/([\d.,]+)\s*(crore|cr|lakh|lac|million)?/i', $value, $m)) {
            return null;
        }
        $number = (float) str_replace(',', '', $m[1]);
        $unit = match (strtolower($m[2] ?? '')) {
            'crore', 'cr' => 10_000_000,
            'lakh', 'lac' => 100_000,
            'million' => 1_000_000,
            default => 1,
        };

        return $number > 0 ? (int) round($number * $unit) : null;
    }

    /** The month in brackets: "PKR 2.73 Crore (Sep 2026)" → 1 Sep 2026. */
    public static function month(string $value): ?Carbon
    {
        if (! preg_match('/\(([A-Za-z]{3,9})\s+(\d{4})\)/', $value, $m)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!M Y', substr($m[1], 0, 3).' '.$m[2]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** "+14%" → 14.0, "-3.5%" → -3.5 */
    public static function percent(string $value): ?float
    {
        return preg_match('/([+-]?\d+(?:\.\d+)?)\s*%/', $value, $m) ? (float) $m[1] : null;
    }

    /** Rs in Crore or Lakh, as the tables show prices. */
    public static function format(int $amount): string
    {
        return $amount >= 10_000_000 ? 'Rs '.rtrim(rtrim(number_format($amount / 10_000_000, 2), '0'), '.').' Cr' : 'Rs '.rtrim(rtrim(number_format($amount / 100_000, 1), '0'), '.').' Lakh';
    }
}
