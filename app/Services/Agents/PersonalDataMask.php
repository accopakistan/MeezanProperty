<?php

namespace App\Services\Agents;

/**
 * Masks personal data in text before it is sent to an external AI service:
 * the model never needs a buyer's phone number, e-mail address, CNIC or
 * card number to classify a request or word a summary. Prices, sizes and
 * dates are left as they are. On by default (meezan.agents.redact_personal_data).
 */
class PersonalDataMask
{
    public static function text(string $text): string
    {
        return preg_replace([
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',                 // e-mail addresses
            '/(?<!\d)\d{5}-\d{7}-\d(?!\d)/',                                       // CNIC 12345-1234567-1
            '/(?<!\d)(?:\d{4}[ \-]){3}\d{1,7}(?!\d)|(?<!\d)\d{13,19}(?!\d)/',      // card-like numbers
            '/(?<![\d\w])(?:\+|00)?92[\s\-]?3\d{2}[\s\-]?\d{7}(?!\d)|(?<!\d)03\d{2}[\s\-]?\d{7}(?!\d)/', // Pakistani mobiles
            '/(?<![\d\w])\+\d{1,3}[\s\-]?\d{2,4}[\s\-]?\d{3,4}[\s\-]?\d{3,4}(?!\d)/', // other international numbers
        ], ['[email]', '[cnic]', '[number]', '[phone]', '[phone]'], $text) ?? $text;
    }
}
