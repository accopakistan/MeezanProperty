<?php

namespace App\Services\Assistant;

use App\Services\Matching\MatchResult;

/**
 * What the assistant answered, independent of channel: the website renders
 * it as chat bubbles and cards, WhatsApp as plain text with links.
 */
final class AssistantReply
{
    /**
     * @param  list<array{label: string, value: string}>  $choices  quick replies (ambiguous place, buy/rent …)
     * @param  list<string>  $actions  save, contact_dealers, new_search, login, sell
     * @param  array<string, string>  $summary  requirement so far
     */
    public function __construct(
        public readonly string $text,
        public readonly string $intent,
        public readonly ?MatchResult $result = null,
        public readonly ?string $followUp = null,
        public readonly array $choices = [],
        public readonly array $actions = [],
        public readonly array $summary = [],
    ) {}

    /** Plain text for channels without rich cards (WhatsApp). */
    public function toPlainText(): string
    {
        $lines = [$this->text];

        if ($this->result && ! $this->result->isEmpty()) {
            foreach ($this->result->matches as $i => $match) {
                $lines[] = sprintf('%d. %s — %s', $i + 1, $match->property->title, $match->property->priceLabel());
                $lines[] = '   '.$match->explanation();
                $lines[] = '   '.route('properties.show', $match->property);
            }
        }
        foreach ($this->choices as $i => $choice) {
            $lines[] = sprintf('%d. %s', $i + 1, $choice['label']);
        }
        if ($this->followUp) {
            $lines[] = $this->followUp;
        }

        return implode("\n", $lines);
    }
}
