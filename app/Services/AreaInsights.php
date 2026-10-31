<?php

namespace App\Services;

use App\Enums\AreaUnit;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Models\Property;
use App\Support\LocationResolver;
use Illuminate\Support\Collection;

/**
 * Area-level statistics from published listings. Every figure is an ASKING
 * price, and a figure is only produced when at least MIN_SAMPLE comparable
 * listings exist — otherwise it is reported as "not enough data".
 */
class AreaInsights
{
    public const MIN_SAMPLE = 5;

    /** @var array<string, ?int> per-instance cache for medianPerMarla() */
    protected array $perMarlaCache = [];

    /**
     * @return array<string, mixed>
     */
    public function for(Area $area): array
    {
        // A society's guide counts its phases, sectors and blocks too.
        $listings = Property::published()->whereIn('area_id', LocationResolver::withDescendants([$area->id]))
            ->get(['id', 'purpose', 'type', 'price', 'area_sqft', 'area_unit', 'published_at']);

        $marla = (int) config('meezan.marla_sqft', 225);
        $perMarla = fn (Collection $rows) => $rows
            ->filter(fn ($p) => in_array($p->area_unit, [AreaUnit::Marla, AreaUnit::Kanal], true) && $p->area_sqft > 0)
            ->map(fn ($p) => $p->price / ($p->area_sqft / $marla));

        $sale = $listings->where('purpose', Purpose::Sale);
        $rent = $listings->where('purpose', Purpose::Rent);

        return [
            'total' => $listings->count(),
            'sale' => $sale->count(),
            'rent' => $rent->count(),
            'newThisMonth' => $listings->filter(fn ($p) => $p->published_at?->gt(now()->subDays(30)))->count(),
            'byType' => $listings->countBy(fn ($p) => $p->type->label())->sortDesc(),
            'metrics' => [
                $this->metric('Houses for sale — asking price per marla', $perMarla($sale->where('type', PropertyType::House))),
                $this->metric('Residential plots — asking price per marla', $perMarla($sale->where('type', PropertyType::ResidentialPlot))),
                $this->metric('Houses & portions for rent — asking rent per month', $rent->whereIn('type', [PropertyType::House, PropertyType::UpperPortion, PropertyType::LowerPortion])->pluck('price')),
                $this->metric('Apartments for rent — asking rent per month', $rent->where('type', PropertyType::Apartment)->pluck('price')),
            ],
            'minSample' => self::MIN_SAMPLE,
        ];
    }

    /**
     * Median asking price per marla for sale listings of one type in an area,
     * or null when fewer than MIN_SAMPLE comparable listings exist.
     */
    public function medianPerMarla(int $areaId, PropertyType $type): ?int
    {
        $key = $areaId.'|'.$type->value;

        if (! array_key_exists($key, $this->perMarlaCache)) {
            $marla = (int) config('meezan.marla_sqft', 225);
            $values = Property::published()->where('area_id', $areaId)->where('type', $type)->where('purpose', Purpose::Sale)
                ->whereIn('area_unit', [AreaUnit::Marla, AreaUnit::Kanal])->where('area_sqft', '>', 0)
                ->get(['price', 'area_sqft'])
                ->map(fn ($p) => $p->price / ($p->area_sqft / $marla));

            $this->perMarlaCache[$key] = $this->metric('', $values)['median'];
        }

        return $this->perMarlaCache[$key];
    }

    /**
     * @param  Collection<int, int|float>  $values
     * @return array{label: string, sample: int, median: ?int, low: ?int, high: ?int}
     */
    protected function metric(string $label, Collection $values): array
    {
        $sorted = $values->sort()->values();
        $n = $sorted->count();

        if ($n < self::MIN_SAMPLE) {
            return ['label' => $label, 'sample' => $n, 'median' => null, 'low' => null, 'high' => null];
        }

        $median = $n % 2 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2;

        return ['label' => $label, 'sample' => $n, 'median' => (int) round($median), 'low' => (int) round($sorted->first()), 'high' => (int) round($sorted->last())];
    }
}
