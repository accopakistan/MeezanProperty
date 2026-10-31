<?php

namespace App\Services\Matching;

use Illuminate\Support\Collection;

final class MatchResult
{
    /**
     * @param  Collection<int, PropertyMatch>  $matches
     * @param  list<string>  $hints
     */
    public function __construct(
        public readonly MatchCriteria $criteria,
        public readonly Collection $matches,
        public readonly array $hints = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->matches->isEmpty();
    }

    /**
     * Plain-text reply used by the website assistant and WhatsApp bot.
     */
    public function summaryText(): string
    {
        if ($this->isEmpty()) {
            return 'I could not find a published listing that fits: '.implode(' · ', $this->criteria->describe()).'. '.implode(' ', $this->hints);
        }

        $count = $this->matches->count();

        return 'Here '.($count === 1 ? 'is the best option' : "are the {$count} best options").' I found for: '
            .implode(' · ', $this->criteria->describe()).'.';
    }
}
