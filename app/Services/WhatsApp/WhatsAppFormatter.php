<?php

namespace App\Services\WhatsApp;

use App\Services\Assistant\AssistantReply;
use App\Services\Matching\PropertyMatch;

/**
 * Turns the assistant's channel-neutral reply into a short WhatsApp
 * message. Every listing detail comes from the matched Property record;
 * links use the configured APP_URL, so they are right on localhost,
 * staging and production alike.
 */
class WhatsAppFormatter
{
    public const MAX_LENGTH = 1500;

    /** Button ids and the text they stand for when the buyer taps them. */
    public const BUTTONS = [
        'dealer' => ['Talk to dealer', 'Please connect me with a dealer'],
        'new_search' => ['Change search', 'new search'],
        'save' => ['Save search', 'save my requirement'],
    ];

    /**
     * @return array{body: string, buttons: list<array{id: string, title: string}>, list: ?array<string, mixed>}
     */
    public function format(AssistantReply $reply): array
    {
        $lines = [];
        $list = $this->choiceList($reply);

        if ($reply->result && ! $reply->result->isEmpty()) {
            $count = $reply->result->matches->count();
            // Neutral headline: each listing carries its own match label.
            $lines[] = '🏠 '.$count.' '.($count === 1 ? 'property' : 'properties').' found';
            foreach ($reply->result->matches as $i => $match) {
                $lines[] = '';
                $lines = [...$lines, ...$this->listing($i + 1, $match)];
            }
            if ($reply->result->matches->contains(fn (PropertyMatch $m) => in_array($m->budgetStatus, [PropertyMatch::BUDGET_CLOSE, PropertyMatch::BUDGET_ABOVE], true))) {
                $lines[] = '';
                $lines[] = 'Listings marked “above budget” cost more than you mentioned.';
            }
        } else {
            $lines[] = $reply->text;
        }

        // With a list on screen the numbered copy would only repeat it.
        foreach ($list ? [] : $reply->choices as $i => $choice) {
            $lines[] = sprintf('%d. %s', $i + 1, $choice['label']);
        }
        if ($reply->followUp) {
            $lines[] = '';
            $lines[] = $reply->followUp;
        }

        $buttons = $this->buttons($reply);
        // Without interactive buttons, say how to ask for a dealer in words.
        if (! config('meezan.whatsapp.interactive_buttons') && in_array('contact_dealers', $reply->actions, true)) {
            $lines[] = '';
            $lines[] = 'Reply "dealer" to be connected with up to '.(int) config('meezan.leads.max_dealers_per_requirement', 3).' verified dealers.';
        }

        $body = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));

        return ['body' => mb_strlen($body) > self::MAX_LENGTH ? mb_substr($body, 0, self::MAX_LENGTH - 1).'…' : $body, 'buttons' => $buttons, 'list' => $list];
    }

    /** Map a tapped button or list row back to text the assistant understands. */
    public static function buttonText(string $id, ?string $title): ?string
    {
        if (str_starts_with($id, 'choice:')) {
            return substr($id, 7);
        }

        return self::BUTTONS[$id][1] ?? $title;
    }

    /**
     * More choices than three reply buttons can hold, shown as a WhatsApp list
     * (for example the seven cities). Only when interactive messages are on.
     *
     * @return array<string, mixed>|null
     */
    protected function choiceList(AssistantReply $reply): ?array
    {
        $count = count($reply->choices);
        if (! config('meezan.whatsapp.interactive_buttons') || $count < 4 || $count > 10) {
            return null;
        }

        return ['button' => 'Choose', 'sections' => [['rows' => array_map(
            fn ($c) => ['id' => 'choice:'.$c['value'], 'title' => $c['label']],
            $reply->choices,
        )]]];
    }

    /** @return list<string> */
    protected function listing(int $number, PropertyMatch $match): array
    {
        $p = $match->property;
        $above = in_array($match->budgetStatus, [PropertyMatch::BUDGET_CLOSE, PropertyMatch::BUDGET_ABOVE], true);

        return array_values(array_filter([
            $number.'. '.$p->title,
            // The same honest label the website shows (e.g. "Partial match — outside your area").
            '🏷 '.$match->label(),
            '💰 '.$p->priceLabel().($above ? ' (above budget)' : ''),
            '📍 '.collect([$p->area?->name, $p->city])->filter()->unique()->implode(', '),
            $p->bedrooms ? '🛏 '.$p->bedrooms.' '.($p->bedrooms === 1 ? 'Bedroom' : 'Bedrooms') : null,
            '📐 '.$p->areaLabel(),
            '🔗 '.route('properties.show', $p),
        ]));
    }

    /** @return list<array{id: string, title: string}> */
    protected function buttons(AssistantReply $reply): array
    {
        $ids = array_values(array_filter([
            in_array('contact_dealers', $reply->actions, true) ? 'dealer' : null,
            in_array('save', $reply->actions, true) ? 'save' : null,
            in_array('new_search', $reply->actions, true) ? 'new_search' : null,
        ]));

        return array_map(fn ($id) => ['id' => $id, 'title' => self::BUTTONS[$id][0]], array_slice($ids, 0, 3));
    }
}
