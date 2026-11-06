<?php

namespace App\Support;

/**
 * Meezan Property's own public contact details, from config('meezan.contact').
 * The office phone and the WhatsApp number are different lines: never swap them.
 * A direct WhatsApp link opens a chat with the team; it is not the Cloud API assistant.
 */
class Contact
{
    public static function email(): ?string
    {
        return config('meezan.contact.email') ?: null;
    }

    public static function emailHref(): ?string
    {
        return static::email() ? 'mailto:'.static::email() : null;
    }

    /** The office phone, in international form: +923002358853. */
    public static function phone(): ?string
    {
        return static::international(config('meezan.contact.phone'));
    }

    public static function phoneHref(): ?string
    {
        return static::phone() ? 'tel:'.static::phone() : null;
    }

    public static function phoneDisplay(): ?string
    {
        return static::display(static::phone());
    }

    /** The WhatsApp number, in international form: +923111749849. */
    public static function whatsapp(): ?string
    {
        return static::international(config('meezan.contact.whatsapp'));
    }

    public static function whatsappDisplay(): ?string
    {
        return static::display(static::whatsapp());
    }

    /** A wa.me link with a prefilled message, or null when no number is set. */
    public static function whatsappUrl(?string $message = null): ?string
    {
        $number = static::whatsapp();
        if (! $number) {
            return null;
        }

        return 'https://wa.me/'.ltrim($number, '+').'?text='.rawurlencode($message ?? (string) config('meezan.contact.whatsapp_message'));
    }

    public static function address(): ?string
    {
        return config('meezan.contact.address') ?: null;
    }

    /** "03001234567", "923001234567" or "+92 300 1234567" → "+923001234567". Other formats are left as they are. */
    protected static function international(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '92'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    /** +923002358853 → "+92 300 2358853". */
    protected static function display(?string $number): ?string
    {
        if (! $number) {
            return null;
        }

        return preg_match('/^\+92(\d{3})(\d{7})$/', $number, $m) ? "+92 {$m[1]} {$m[2]}" : $number;
    }
}
