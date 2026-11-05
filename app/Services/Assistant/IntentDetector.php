<?php

namespace App\Services\Assistant;

use App\Support\UrduText;

/**
 * What a message is for, before it is read as a property requirement.
 * Keyword based (English, Roman Urdu, and Urdu via UrduText) and checked in
 * a fixed order, so "stop" and "human" always win.
 */
class IntentDetector
{
    public const STOP = 'stop';

    public const HUMAN = 'human';

    public const RESET = 'reset';

    public const SELL = 'sell';

    public const DEALER = 'dealer';

    public const SAVE = 'save';

    public const YES = 'yes';

    public const NO = 'no';

    public const GREETING = 'greeting';

    public const THANKS = 'thanks';

    public const REQUIREMENT = 'requirement';

    protected const PATTERNS = [
        self::STOP => '/^\s*(stop|unsubscribe|opt out|optout|band karo|block)\s*[.!]?\s*$/i',
        self::HUMAN => '/\b(human|real person|person|insaan|team se baat|customer (care|support)|staff|operator|representative|live agent|human agent|kisi se baat|namainda)\b/i',
        self::RESET => '/\b(start over|new search|naya search|nayi search|reset|clear( search)?|start again)\b/i',
        self::SELL => '/\b(sell my|selling my|want to sell|list my (house|plot|property|flat)|bechna|bechni|farokht karna)\b/i',
        self::DEALER => '/\b(dealer|dealers|agent|agents|contact me|call me|connect me|rabta|baat karwa|baat karao|talk to (a|an) (dealer|agent))\b/i',
        self::SAVE => '/\b(save( this| my)?( requirement| search)?|mehfooz|alert me|notify me)\b/i',
        self::YES => '/^\s*(yes|y|haan|han|ji|ji haan|jee|ok|okay|theek hai|sure|confirm)\s*[.!]?\s*$/i',
        self::NO => '/^\s*(no|n|nahi|nahin|na|cancel)\s*[.!]?\s*$/i',
        self::THANKS => '/^\s*(thanks|thank you|shukriya|jazakallah|thx)\b[\s.!]*$/i',
        self::GREETING => '/^\s*(hi|hello|hey|salam|salaam|assalam(u|o)? ?(o|a)?alaikum|aoa|asalam o alaikum|good (morning|evening))\b[\s.!,]*$/i',
    ];

    public function detect(string $message): string
    {
        $text = UrduText::toRoman($message);

        foreach (self::PATTERNS as $intent => $pattern) {
            if (preg_match($pattern, $text)) {
                return $intent;
            }
        }

        return self::REQUIREMENT;
    }
}
