<?php

namespace App\Support;

/**
 * WhatsApp identifies people by international number without "+", e.g.
 * 923001234567. Accepts what Pakistani users type ("0300 1234567",
 * "+92 300 1234567", "92-300-1234567").
 */
class WhatsAppNumber
{
    public static function normalize(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '03')) {
            $digits = '92'.substr($digits, 1);
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }
}
