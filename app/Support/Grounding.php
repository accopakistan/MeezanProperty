<?php

namespace App\Support;

/**
 * Checks that model-written text uses only figures found in its evidence.
 * Figures are compared whole, as numbers: "45" is not supported by "145" or
 * by the time "10:45", and "6" is supported by "6.00" or "6". Dates and times
 * count as one figure. Thousands separators are ignored.
 */
class Grounding
{
    protected const FIGURE = '/\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?)?|\d{1,2}:\d{2}(?::\d{2})?|(?<![\d.,])\d+(?:,\d{3})*(?:\.\d+)?/';

    public static function supports(string $text, string $evidence): bool
    {
        return array_diff(static::figures($text), static::figures($evidence)) === [];
    }

    /** @return list<string> */
    protected static function figures(string $text): array
    {
        preg_match_all(self::FIGURE, $text, $matches);

        return array_values(array_unique(array_map(function (string $n) {
            if (str_contains($n, ':') || str_contains($n, '-')) {
                return $n;
            }
            $n = str_replace(',', '', $n);
            $n = str_contains($n, '.') ? rtrim(rtrim($n, '0'), '.') : $n;

            return ltrim($n, '0') ?: '0';
        }, $matches[0])));
    }
}
