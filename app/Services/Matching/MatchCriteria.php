<?php

namespace App\Services\Matching;

use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Models\BuyerRequirement;
use App\Support\Format;

/**
 * Normalised buyer requirements consumed by the PropertyMatcher.
 */
final class MatchCriteria
{
    /**
     * @param  list<int>  $areaIds
     */
    public function __construct(
        public ?Purpose $purpose = null,
        public ?PropertyType $type = null,
        public ?string $group = null,
        /** '' = any launch city */
        public string $city = '',
        public array $areaIds = [],
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public ?int $minAreaSqft = null,
        public ?int $maxAreaSqft = null,
        public ?int $bedrooms = null,
        public ?int $bathrooms = null,
        /** @var list<string> amenity keys from config('meezan.amenities') */
        public array $amenities = [],
        /** @var array{question: string, options: list<string>}|null asked when a place name fits several cities */
        public ?array $clarify = null,
        /** A non-launch city the buyer named (e.g. Rawalpindi); nothing is searched on its behalf. */
        public ?string $unsupportedCity = null,
        /** furnished, semi_furnished or unfurnished: a preference, never a filter */
        public ?string $furnishing = null,
        /** @var list<string> stated reasons (investment, family, commercial_use): stored, never scored */
        public array $tags = [],
        /** The budget was given as "around …" */
        public bool $budgetApprox = false,
    ) {
        if ($this->type) {
            $this->group = $this->type->group();
        }
    }

    public static function fromRequirement(BuyerRequirement $requirement): self
    {
        return new self(
            purpose: $requirement->purpose,
            type: $requirement->type,
            city: $requirement->city,
            areaIds: array_map('intval', $requirement->area_ids ?? []),
            minPrice: $requirement->min_price,
            maxPrice: $requirement->max_price,
            minAreaSqft: $requirement->min_area_sqft,
            maxAreaSqft: $requirement->max_area_sqft,
            bedrooms: $requirement->bedrooms,
        );
    }

    /**
     * Overlay non-empty values from another set of criteria.
     */
    public function merge(self $other): self
    {
        $merged = clone $this;

        foreach (get_object_vars($other) as $key => $value) {
            if ($value !== null && $value !== [] && $value !== false && ! ($key === 'city' && $value === '')) {
                $merged->{$key} = $value;
            }
        }

        if ($other->type) {
            $merged->group = $other->type->group();
        }

        return $merged;
    }

    public function isEmpty(): bool
    {
        return ! $this->purpose && ! $this->type && ! $this->group && ! $this->areaIds && ! $this->city
            && ! $this->maxPrice && ! $this->minAreaSqft && ! $this->bedrooms && ! $this->bathrooms && ! $this->amenities;
    }

    /**
     * Plain-language list of what was understood, for display.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $parts = [];

        if ($this->purpose) {
            $parts[] = $this->purpose === Purpose::Rent ? 'To rent' : 'To buy';
        }
        if ($this->type) {
            $parts[] = $this->type->label();
        } elseif ($this->group) {
            $parts[] = PropertyType::groupLabel($this->group);
        }
        if ($this->areaIds) {
            $names = Area::whereIn('id', $this->areaIds)->ordered()->pluck('name');
            $parts[] = $names->count() > 3
                ? $names->take(2)->implode(', ').' +'.($names->count() - 2).' more'
                : $names->implode(', ');
        } else {
            $parts[] = $this->city ?: 'Any city';
        }
        if ($this->maxPrice) {
            $parts[] = 'Up to '.Format::price($this->maxPrice);
        }
        if ($this->minAreaSqft) {
            $marla = $this->minAreaSqft / (int) config('meezan.marla_sqft', 225);
            $parts[] = 'From '.Format::number(round($marla, 1)).' Marla';
        }
        if ($this->bedrooms) {
            $parts[] = $this->bedrooms.'+ bedrooms';
        }
        if ($this->bathrooms) {
            $parts[] = $this->bathrooms.'+ bathrooms';
        }
        foreach ($this->amenities as $key) {
            $parts[] = config('meezan.amenities')[$key] ?? $key;
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'purpose' => $this->purpose?->value,
            'type' => $this->type?->value,
            'group' => $this->group,
            'city' => $this->city,
            'area_ids' => $this->areaIds,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'min_area_sqft' => $this->minAreaSqft,
            'max_area_sqft' => $this->maxAreaSqft,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'amenities' => $this->amenities,
        ];
    }
}
