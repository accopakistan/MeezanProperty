<?php

namespace App\Support;

/**
 * A search area drawn on the map, passed in the URL as
 * "lat,lng;lat,lng;..." (5 decimals). Point-in-polygon uses ray casting,
 * which is accurate enough at city scale and works on any database.
 */
final class Polygon
{
    public const MIN_POINTS = 3;

    public const MAX_POINTS = 100;

    /**
     * @param  list<array{0: float, 1: float}>  $points  [lat, lng] pairs
     */
    private function __construct(public readonly array $points) {}

    public static function parse(?string $value): ?self
    {
        if (! $value) {
            return null;
        }

        $points = [];
        foreach (explode(';', $value) as $pair) {
            $parts = explode(',', $pair);
            if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                return null;
            }
            [$lat, $lng] = [(float) $parts[0], (float) $parts[1]];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return null;
            }
            $points[] = [$lat, $lng];
        }

        $count = count($points);

        return $count >= self::MIN_POINTS && $count <= self::MAX_POINTS ? new self($points) : null;
    }

    /**
     * @return array{south: float, north: float, west: float, east: float}
     */
    public function bounds(): array
    {
        $lats = array_column($this->points, 0);
        $lngs = array_column($this->points, 1);

        return ['south' => min($lats), 'north' => max($lats), 'west' => min($lngs), 'east' => max($lngs)];
    }

    public function contains(float $lat, float $lng): bool
    {
        $inside = false;
        $n = count($this->points);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$latI, $lngI] = $this->points[$i];
            [$latJ, $lngJ] = $this->points[$j];

            if (($latI > $lat) !== ($latJ > $lat)
                && $lng < ($lngJ - $lngI) * ($lat - $latI) / (($latJ - $latI) ?: 1e-12) + $lngI) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    public function toString(): string
    {
        return implode(';', array_map(fn ($p) => round($p[0], 5).','.round($p[1], 5), $this->points));
    }
}
