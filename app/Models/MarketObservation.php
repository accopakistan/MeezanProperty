<?php

namespace App\Models;

use App\Enums\AreaUnit;
use App\Models\Concerns\HasReviewWorkflow;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;

/**
 * One sale-price or rent observation for a city, area/society or project:
 * an average with its range, sample size, date, basis (asking, transaction
 * or valuation) and source. Rent amounts are per month.
 */
class MarketObservation extends Model
{
    use HasReviewWorkflow;

    public const KINDS = ['sale' => 'Sale price', 'rent' => 'Monthly rent'];

    protected $fillable = [
        'kind', 'price_basis', 'country', 'city', 'area_id', 'project_id', 'asset_class', 'property_type',
        'size_value', 'size_unit', 'currency', 'average_amount', 'min_amount', 'max_amount', 'observed_on',
        'sample_size', 'source_id', 'methodology', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'observed_on' => 'date', 'size_value' => 'float', 'size_sqft' => 'float',
            'average_amount' => 'integer', 'min_amount' => 'integer', 'max_amount' => 'integer', 'sample_size' => 'integer',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MarketObservation $o) {
            $o->size_sqft = static::squareFeet($o->size_value, $o->size_unit);
            $o->fingerprint = static::fingerprintFor($o->getAttributes());
        });
    }

    public static function squareFeet(mixed $value, ?string $unit): ?float
    {
        $unit = AreaUnit::tryFrom((string) $unit);

        return $unit && $value ? round($unit->toSquareFeet((float) $value), 2) : null;
    }

    /** Same kind, basis, place, type, size, currency, date, amount and source = a duplicate. */
    public static function fingerprintFor(array $a): string
    {
        $sqft = static::squareFeet($a['size_value'] ?? null, $a['size_unit'] ?? null);
        $date = $a['observed_on'] ?? '';
        $date = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);

        return hash('sha256', implode('|', [
            $a['kind'] ?? '', $a['price_basis'] ?? '', mb_strtolower((string) ($a['city'] ?? '')), $a['area_id'] ?? '', $a['project_id'] ?? '',
            $a['property_type'] ?? '', $sqft === null ? '' : round($sqft), strtoupper((string) ($a['currency'] ?? '')), $date,
            (int) ($a['average_amount'] ?? 0), $a['source_id'] ?? '',
        ]));
    }

    public function sizeLabel(): ?string
    {
        $unit = AreaUnit::tryFrom((string) $this->size_unit);

        return $unit && $this->size_value ? Format::number($this->size_value).' '.$unit->label() : null;
    }

    public function basisLabel(): string
    {
        return config('meezan.investment.price_bases')[$this->price_basis] ?? $this->price_basis;
    }

    public function amountLabel(?int $amount = null): string
    {
        $amount ??= $this->average_amount;

        return $this->currency === 'PKR' ? Format::price($amount) : $this->currency.' '.number_format($amount);
    }
}
