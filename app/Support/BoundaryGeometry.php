<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * A validated, normalised GeoJSON Polygon or MultiPolygon for an area
 * boundary. Positions are [longitude, latitude] (GeoJSON order).
 *
 * Accepted input: a geometry object, a Feature, or a FeatureCollection with
 * exactly one feature (as JSON text or a decoded array). Rejected: other
 * geometry types, unclosed rings, rings with fewer than three distinct
 * points, points outside Pakistan, self-intersecting rings, zero-area
 * shapes, holes outside their polygon and oversized shapes.
 */
class BoundaryGeometry
{
    /** Total vertices allowed (the self-intersection check is quadratic). */
    public const MAX_VERTICES = 2000;

    public const MAX_POLYGONS = 20;

    public const MAX_RINGS = 20;

    /**
     * @param  array{type: string, coordinates: array}  $geometry
     */
    protected function __construct(public readonly array $geometry) {}

    /**
     * @throws ValidationException (key: geometry)
     */
    public static function parse(string|array|null $input, string $key = 'geometry'): self
    {
        $fail = fn (string $message) => throw ValidationException::withMessages([$key => $message]);

        if (is_string($input)) {
            if (strlen($input) > 2_000_000) {
                $fail('The boundary is too large.');
            }
            $input = json_decode($input, true);
        }
        if (! is_array($input)) {
            $fail('The boundary is not valid GeoJSON.');
        }

        if (($input['type'] ?? null) === 'FeatureCollection') {
            $features = $input['features'] ?? null;
            if (! is_array($features) || count($features) !== 1) {
                $fail('Provide exactly one boundary (a FeatureCollection with one feature).');
            }
            $input = $features[0];
        }
        if (($input['type'] ?? null) === 'Feature') {
            $input = $input['geometry'] ?? null;
        }
        if (! is_array($input) || ! in_array($input['type'] ?? null, ['Polygon', 'MultiPolygon'], true) || ! is_array($input['coordinates'] ?? null)) {
            $fail('The boundary must be a GeoJSON Polygon or MultiPolygon.');
        }

        $polygons = $input['type'] === 'Polygon' ? [$input['coordinates']] : $input['coordinates'];
        if ($polygons === [] || count($polygons) > self::MAX_POLYGONS) {
            $fail('A boundary can have between 1 and '.self::MAX_POLYGONS.' polygons.');
        }

        $bounds = config('meezan.map.pakistan_bounds');
        $vertices = 0;
        $clean = [];

        foreach ($polygons as $p => $rings) {
            if (! is_array($rings) || $rings === [] || count($rings) > self::MAX_RINGS) {
                $fail('Polygon '.($p + 1).' has no outline.');
            }
            $cleanRings = [];
            foreach (array_values($rings) as $r => $ring) {
                $label = 'Polygon '.($p + 1).($r === 0 ? ' outline' : ' hole '.$r);
                if (! is_array($ring) || count($ring) < 4) {
                    $fail("{$label} needs at least 3 points and a closing point.");
                }

                $points = [];
                foreach (array_values($ring) as $position) {
                    if (! is_array($position) || count($position) < 2 || ! is_numeric($position[0] ?? null) || ! is_numeric($position[1] ?? null)) {
                        $fail("{$label} has a malformed position.");
                    }
                    [$lng, $lat] = [round((float) $position[0], 6), round((float) $position[1], 6)];
                    if (! is_finite($lng) || ! is_finite($lat)
                        || $lat < $bounds['south'] || $lat > $bounds['north'] || $lng < $bounds['west'] || $lng > $bounds['east']) {
                        $fail("{$label} has a point outside Pakistan (positions are [longitude, latitude]).");
                    }
                    // Drop consecutive duplicates.
                    if ($points === [] || end($points) !== [$lng, $lat]) {
                        $points[] = [$lng, $lat];
                    }
                }

                if ($points[0] !== end($points)) {
                    $fail("{$label} is not closed: its last point must repeat the first.");
                }
                $distinct = count(array_unique(array_map(fn ($pt) => $pt[0].','.$pt[1], array_slice($points, 0, -1))));
                if ($distinct < 3) {
                    $fail("{$label} needs at least 3 distinct points.");
                }
                if (self::selfIntersects($points)) {
                    $fail("{$label} crosses itself.");
                }
                if (abs(self::ringArea($points)) < 1e-10) {
                    $fail("{$label} has no area.");
                }
                if ($r > 0 && ! self::ringContains($cleanRings[0], $points[0][1], $points[0][0])) {
                    $fail("{$label} lies outside its polygon.");
                }

                $vertices += count($points) - 1;
                if ($vertices > self::MAX_VERTICES) {
                    $fail('The boundary has more than '.number_format(self::MAX_VERTICES).' points. Simplify it first.');
                }
                $cleanRings[] = $points;
            }
            $clean[] = $cleanRings;
        }

        return new self(count($clean) === 1
            ? ['type' => 'Polygon', 'coordinates' => $clean[0]]
            : ['type' => 'MultiPolygon', 'coordinates' => $clean]);
    }

    /** @return list<list<list<array{0: float, 1: float}>>> */
    public function polygons(): array
    {
        return $this->geometry['type'] === 'Polygon' ? [$this->geometry['coordinates']] : $this->geometry['coordinates'];
    }

    /** @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float} */
    public function bounds(): array
    {
        $points = array_merge(...array_map(fn ($rings) => $rings[0], $this->polygons()));
        $lngs = array_column($points, 0);
        $lats = array_column($points, 1);

        return ['min_lat' => min($lats), 'max_lat' => max($lats), 'min_lng' => min($lngs), 'max_lng' => max($lngs)];
    }

    public function vertexCount(): int
    {
        return array_sum(array_map(fn ($rings) => array_sum(array_map(fn ($ring) => count($ring) - 1, $rings)), $this->polygons()));
    }

    /** Approximate area in km² (equirectangular projection; fine at society scale). */
    public function areaSqKm(): float
    {
        $total = 0.0;
        foreach ($this->polygons() as $rings) {
            foreach ($rings as $i => $ring) {
                $lat0 = deg2rad(array_sum(array_column($ring, 1)) / count($ring));
                $km = array_map(fn ($p) => [$p[0] * 111.320 * cos($lat0), $p[1] * 110.574], $ring);
                $total += ($i === 0 ? 1 : -1) * abs(self::ringArea($km));
            }
        }

        return round(max($total, 0), 3);
    }

    public function contains(float $lat, float $lng): bool
    {
        foreach ($this->polygons() as $rings) {
            if (self::ringContains($rings[0], $lat, $lng)) {
                foreach (array_slice($rings, 1) as $hole) {
                    if (self::ringContains($hole, $lat, $lng)) {
                        continue 2;
                    }
                }

                return true;
            }
        }

        return false;
    }

    public function toJson(): string
    {
        return json_encode($this->geometry, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** Signed shoelace area of a closed ring in its own units. */
    protected static function ringArea(array $ring): float
    {
        $sum = 0.0;
        for ($i = 0, $n = count($ring) - 1; $i < $n; $i++) {
            $sum += $ring[$i][0] * $ring[$i + 1][1] - $ring[$i + 1][0] * $ring[$i][1];
        }

        return $sum / 2;
    }

    protected static function ringContains(array $ring, float $lat, float $lng): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** True when two non-adjacent edges of a closed ring cross or touch. */
    protected static function selfIntersects(array $ring): bool
    {
        $n = count($ring) - 1;
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 2; $j < $n; $j++) {
                if ($i === 0 && $j === $n - 1) {
                    continue; // first and last edges share the closing point
                }
                if (self::segmentsIntersect($ring[$i], $ring[$i + 1], $ring[$j], $ring[$j + 1])) {
                    return true;
                }
            }
        }

        return false;
    }

    protected static function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        // Quick bounding-box rejection.
        if (max($a[0], $b[0]) < min($c[0], $d[0]) || max($c[0], $d[0]) < min($a[0], $b[0])
            || max($a[1], $b[1]) < min($c[1], $d[1]) || max($c[1], $d[1]) < min($a[1], $b[1])) {
            return false;
        }

        $o = fn ($p, $q, $r) => ($q[0] - $p[0]) * ($r[1] - $p[1]) - ($q[1] - $p[1]) * ($r[0] - $p[0]);
        $d1 = $o($c, $d, $a);
        $d2 = $o($c, $d, $b);
        $d3 = $o($a, $b, $c);
        $d4 = $o($a, $b, $d);

        if ((($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0)) && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0))) {
            return true;
        }

        // Collinear overlaps.
        $on = fn ($p, $q, $r) => min($p[0], $q[0]) <= $r[0] && $r[0] <= max($p[0], $q[0]) && min($p[1], $q[1]) <= $r[1] && $r[1] <= max($p[1], $q[1]);

        return ($d1 == 0 && $on($c, $d, $a)) || ($d2 == 0 && $on($c, $d, $b))
            || ($d3 == 0 && $on($a, $b, $c)) || ($d4 == 0 && $on($a, $b, $d));
    }
}
