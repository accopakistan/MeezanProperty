<?php

namespace App\Services\Matching;

use App\Models\Property;
use App\Support\Format;

final class PropertyMatch
{
    public const BUDGET_WITHIN = 'within';

    public const BUDGET_CLOSE = 'close';

    public const BUDGET_ABOVE = 'above';

    /**
     * @param  list<string>  $reasons
     * @param  list<string>  $caveats
     */
    public function __construct(
        public readonly Property $property,
        public readonly int $score,
        public readonly array $reasons,
        public readonly array $caveats,
        /** within, close (slightly above), above, or null when no budget was given */
        public readonly ?string $budgetStatus = null,
        /** False when the buyer named areas and this listing is outside them */
        public readonly bool $inArea = true,
        public readonly ?MatchCriteria $criteria = null,
    ) {}

    /**
     * Never describes a listing as a strong match while requirements are
     * unmet, and always says when the price is over the budget.
     */
    public function label(): string
    {
        return match (true) {
            $this->budgetStatus === self::BUDGET_ABOVE => 'Alternative — above budget',
            $this->budgetStatus === self::BUDGET_CLOSE => 'Close match — slightly above budget',
            ! $this->inArea => 'Partial match — outside your area',
            $this->score >= 85 && $this->caveats === [] => 'Strong match',
            $this->score >= 65 => 'Good match',
            default => 'Partial match',
        };
    }

    public function budgetRank(): int
    {
        return match ($this->budgetStatus) {
            self::BUDGET_CLOSE => 1,
            self::BUDGET_ABOVE => 0,
            default => 2,
        };
    }

    /**
     * One factual sentence from the listing's own data, e.g. "Good match:
     * located in DHA Phase 6, Lahore · 10 Marla · House for Sale · within
     * your PKR 4 Crore budget." No investment, return or approval claims.
     */
    public function explanation(): string
    {
        $p = $this->property;
        $facts = [
            'located in '.$p->locationLabel(),
            $p->areaLabel(),
            $p->type->label().' for '.($p->purpose->value === 'rent' ? 'Rent' : 'Sale'),
        ];
        if ($p->bedrooms) {
            $facts[] = $p->bedrooms.' '.($p->bedrooms === 1 ? 'bedroom' : 'bedrooms');
        }

        $budget = $this->criteria?->maxPrice;
        $facts[] = match ($this->budgetStatus) {
            self::BUDGET_WITHIN => 'within your '.Format::price($budget).' budget',
            self::BUDGET_CLOSE, self::BUDGET_ABOVE => Format::price($p->price).', above your '.Format::price($budget).' budget',
            default => Format::price($p->price),
        };

        return $this->label().': '.implode(' · ', array_filter($facts)).'.';
    }
}
