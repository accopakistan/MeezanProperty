<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number someone can actually be reached on: digits only (spaces,
 * dashes and brackets allowed between them), an optional leading +, 10 to 15
 * digits. Pakistani mobiles must be complete: 03XX XXXXXXX or +92 3XX XXXXXXX.
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);
        if (! preg_match('/^\+?[0-9][0-9\s\-()]*$/', $value)) {
            $fail('Enter a valid phone number, e.g. 0300 1234567.');

            return;
        }
        $digits = preg_replace('/\D+/', '', $value);
        $international = str_starts_with($value, '+') || str_starts_with($digits, '00');
        $digits = str_starts_with($digits, '00') ? substr($digits, 2) : $digits;

        $ok = strlen($digits) >= 10 && strlen($digits) <= 15;
        if (str_starts_with($digits, '03')) {
            $ok = strlen($digits) === 11;
        } elseif (str_starts_with($digits, '923') && ($international || strlen($digits) === 12)) {
            $ok = strlen($digits) === 12;
        }

        if (! $ok) {
            $fail('Enter a complete phone number, e.g. 0300 1234567 or +92 300 1234567.');
        }
    }
}
