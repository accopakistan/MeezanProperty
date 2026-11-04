<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Line-chart geometry for a verified comparable price series, drawn as inline
 * SVG (no chart library, no client-side data). Only real observations are
 * plotted: with fewer than two points in the period there is no chart.
 */
class PriceChart
{
    public const PERIODS = ['1y' => ['1Y', 12], '2y' => ['2Y', 24], '5y' => ['5Y', 60], 'all' => ['All', null]];

    public const WIDTH = 640;

    public const HEIGHT = 240;

    protected const PAD = ['top' => 16, 'right' => 16, 'bottom' => 30, 'left' => 64];

    /**
     * @param  list<array{date: string, amount: int|float}>  $series  oldest first
     * @return array{points: list<array{x: float, y: float, date: string, label: string}>, path: string, area: string, ticks: list<array{y: float, label: string}>, labels: list<array{x: float, label: string}>, count: int}|null
     */
    public static function build(array $series, string $period = 'all'): ?array
    {
        $months = self::PERIODS[$period][1] ?? null;
        $points = collect($series)->map(fn ($p) => ['date' => Carbon::parse($p['date']), 'amount' => (float) $p['amount']])->sortBy(fn ($p) => $p['date']->timestamp)->values();
        if ($months && $points->isNotEmpty()) {
            $from = $points->last()['date']->copy()->subMonths($months);
            $points = $points->filter(fn ($p) => $p['date']->gte($from))->values();
        }
        if ($points->count() < 2) {
            return null;
        }

        [$w, $h] = [self::WIDTH - self::PAD['left'] - self::PAD['right'], self::HEIGHT - self::PAD['top'] - self::PAD['bottom']];
        $t0 = $points->first()['date']->timestamp;
        $span = max(1, $points->last()['date']->timestamp - $t0);
        $min = $points->min('amount');
        $max = $points->max('amount');
        if ($max == $min) {
            [$min, $max] = [$min * 0.95, $max * 1.05];
        }
        $pad = ($max - $min) * 0.1;
        [$lo, $hi] = [max(0, $min - $pad), $max + $pad];

        $x = fn ($ts) => round(self::PAD['left'] + ($ts - $t0) / $span * $w, 1);
        $y = fn ($v) => round(self::PAD['top'] + (1 - ($v - $lo) / ($hi - $lo)) * $h, 1);

        $plotted = $points->map(fn ($p) => ['x' => $x($p['date']->timestamp), 'y' => $y($p['amount']), 'date' => $p['date']->format('M Y'), 'label' => Format::price((int) $p['amount'])])->all();
        $path = 'M'.collect($plotted)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' L');
        $base = self::PAD['top'] + $h;

        return [
            'points' => $plotted,
            'path' => $path,
            'area' => $path.' L'.end($plotted)['x'].','.$base.' L'.$plotted[0]['x'].','.$base.' Z',
            'ticks' => collect(range(0, 3))->map(fn ($i) => ['y' => $y($lo + ($hi - $lo) * $i / 3), 'label' => Format::compactPrice((int) round($lo + ($hi - $lo) * $i / 3))])->all(),
            // First and last date labels anchor inwards so they are not cut off at the edges.
            'labels' => collect([0, intdiv(count($plotted) - 1, 2), count($plotted) - 1])->unique()->map(fn ($i) => ['x' => $plotted[$i]['x'], 'label' => $plotted[$i]['date'],
                'anchor' => $i === 0 ? 'start' : ($i === count($plotted) - 1 ? 'end' : 'middle')])->values()->all(),
            'count' => count($plotted),
        ];
    }
}
