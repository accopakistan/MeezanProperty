<?php

namespace App\Support;

class Geo
{
    /**
     * Great-circle distance in kilometres.
     *
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    public static function km(array $a, array $b): float
    {
        [$lat1, $lng1, $lat2, $lng2] = array_map('deg2rad', [$a[0], $a[1], $b[0], $b[1]]);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(sqrt($h));
    }
}
