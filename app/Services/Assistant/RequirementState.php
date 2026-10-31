<?php

namespace App\Services\Assistant;

use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Services\Matching\MatchCriteria;
use App\Support\Format;

/**
 * The requirement a conversation has built so far, stored as JSON on the
 * conversation. Each message adds to it; a newly named city replaces the
 * old one and its areas, so "Karachi instead" never mixes cities.
 */
final class RequirementState
{
    /** @var array<string, mixed> */
    public array $data;

    /** Fields the buyer has stated (not counting bookkeeping keys). */
    protected const FIELDS = ['city', 'area_ids', 'purpose', 'type', 'group', 'min_price', 'max_price', 'budget_approx', 'min_area_sqft', 'bedrooms', 'bathrooms', 'amenities', 'furnishing', 'tags'];

    /** @param  array<string, mixed>|null  $data */
    public function __construct(?array $data = null)
    {
        $this->data = $data ?? [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        if ($value === null || $value === []) {
            unset($this->data[$key]);
        } else {
            $this->data[$key] = $value;
        }
    }

    public function isEmpty(): bool
    {
        return array_intersect_key($this->data, array_flip(self::FIELDS)) === [];
    }

    /** Add what a new message said. Returns true when anything changed. */
    public function absorb(MatchCriteria $c): bool
    {
        $before = json_encode($this->only());

        if ($c->city && $c->city !== $this->get('city')) {
            $this->set('city', $c->city);
            $this->set('area_ids', null); // areas from another city never carry over
        }
        if ($c->areaIds) {
            $this->set('area_ids', $c->areaIds);
            $this->set('city', $c->city ?: Area::whereKey($c->areaIds[0])->value('city'));
        }
        foreach ([
            'purpose' => $c->purpose?->value, 'type' => $c->type?->value, 'group' => $c->group,
            'min_price' => $c->minPrice, 'max_price' => $c->maxPrice, 'min_area_sqft' => $c->minAreaSqft,
            'bedrooms' => $c->bedrooms, 'bathrooms' => $c->bathrooms, 'furnishing' => $c->furnishing,
        ] as $key => $value) {
            if ($value !== null) {
                $this->set($key, $value);
            }
        }
        if ($c->maxPrice !== null) {
            $this->set('budget_approx', $c->budgetApprox ?: null);
            if ($c->minPrice === null) {
                $this->set('min_price', null);
            }
            $this->set('purpose_inferred', null);
        }
        if ($c->type) {
            $this->set('group', $c->type->group());
        }
        if ($c->purpose) {
            $this->set('purpose_inferred', null);
        }
        foreach (['amenities', 'tags'] as $list) {
            if ($c->{$list}) {
                $this->set($list, array_values(array_unique(array_merge($this->get($list, []), $c->{$list}))));
            }
        }

        return $before !== json_encode($this->only());
    }

    /**
     * Fill a missing purpose only when it is unambiguous: plots are bought,
     * and a budget of 50 lakh or more is not a monthly rent.
     */
    public function inferPurpose(): void
    {
        if ($this->get('purpose')) {
            return;
        }
        $isPlot = in_array($this->get('type'), ['residential_plot', 'commercial_plot', 'plot_file', 'agricultural_land'], true);
        if ($isPlot || $this->get('max_price', 0) >= (int) config('meezan.ai.assume_sale_from', 5_000_000)) {
            $this->set('purpose', Purpose::Sale->value);
            $this->set('purpose_inferred', true);
        }
    }

    public function toCriteria(): MatchCriteria
    {
        return new MatchCriteria(
            purpose: Purpose::tryFrom((string) $this->get('purpose')),
            type: PropertyType::tryFrom((string) $this->get('type')),
            group: $this->get('group'),
            city: (string) $this->get('city', ''),
            areaIds: array_map('intval', $this->get('area_ids', [])),
            minPrice: $this->get('min_price'),
            maxPrice: $this->get('max_price'),
            minAreaSqft: $this->get('min_area_sqft'),
            bedrooms: $this->get('bedrooms'),
            bathrooms: $this->get('bathrooms'),
            amenities: $this->get('amenities', []),
            furnishing: $this->get('furnishing'),
            tags: $this->get('tags', []),
            budgetApprox: (bool) $this->get('budget_approx', false),
        );
    }

    /** City + purpose + one of type/area/budget: enough to save or share. */
    public function isSubstantial(): bool
    {
        return $this->get('city') && $this->get('purpose') && ($this->get('type') || $this->get('group') || $this->get('area_ids') || $this->get('max_price'));
    }

    /**
     * The most useful missing detail to ask about next, never the same twice.
     */
    public function nextQuestion(): ?array
    {
        $asked = $this->get('asked', []);
        $questions = [
            'purpose' => [! $this->get('purpose'), 'Are you looking to buy or to rent?'],
            'type' => [! $this->get('type') && ! $this->get('group'), 'What kind of property — a house, flat, plot or commercial?'],
            'budget' => [! $this->get('max_price'), 'What is your budget? For example "80 lakh" or "4 crore"'.($this->get('purpose') === 'rent' ? ' (per month)' : '').'.'],
            'size' => [! $this->get('min_area_sqft') && ! in_array($this->get('group'), ['commercial'], true), 'What size do you need — for example 5 marla, 10 marla or 1 kanal?'],
        ];
        foreach ($questions as $key => [$missing, $question]) {
            if ($missing && ! in_array($key, $asked, true)) {
                return ['key' => $key, 'text' => $question];
            }
        }

        return null;
    }

    public function markAsked(string $key): void
    {
        $this->set('asked', array_values(array_unique(array_merge($this->get('asked', []), [$key]))));
    }

    /**
     * Readable summary for the requirement panel and replies.
     *
     * @return array<string, string>
     */
    public function summary(): array
    {
        $areas = $this->get('area_ids') ? Area::whereIn('id', $this->get('area_ids'))->orderBy('name')->pluck('name') : collect();
        $marla = (int) config('meezan.marla_sqft', 225);

        return array_filter([
            'City' => $this->get('city'),
            'Location' => $areas->count() > 3 ? $areas->take(2)->implode(', ').' +'.($areas->count() - 2).' more' : $areas->implode(', '),
            'Looking to' => match ($this->get('purpose')) {
                'sale' => 'Buy'.($this->get('purpose_inferred') ? ' (assumed)' : ''),
                'rent' => 'Rent',
                default => null,
            },
            'Property' => PropertyType::tryFrom((string) $this->get('type'))?->label() ?? ($this->get('group') ? PropertyType::groupLabel($this->get('group')) : null),
            'Size' => $this->get('min_area_sqft') ? 'From '.Format::number(round($this->get('min_area_sqft') / $marla, 1)).' Marla' : null,
            'Budget' => $this->get('max_price')
                ? ($this->get('min_price') ? Format::price($this->get('min_price')).' – ' : ($this->get('budget_approx') ? 'Around ' : 'Up to ')).Format::price($this->get('max_price'))
                : null,
            'Bedrooms' => $this->get('bedrooms') ? $this->get('bedrooms').'+' : null,
            'Furnishing' => $this->get('furnishing') ? ucfirst(str_replace('_', '-', $this->get('furnishing'))) : null,
        ]);
    }

    /** @return array<string, mixed> stated fields only (for change detection and message records) */
    public function only(): array
    {
        return array_intersect_key($this->data, array_flip(self::FIELDS));
    }
}
