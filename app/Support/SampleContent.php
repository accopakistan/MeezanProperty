<?php

namespace App\Support;

use App\Models\User;

/**
 * Recognises test and sample accounts, so their records never reach the public.
 * An address on a reserved test domain (RFC 2606/6761: .test, .example, .invalid,
 * .localhost) cannot belong to a real customer, so its dealer profile and listings
 * are created as samples: hidden from public pages, never "Verified", never sent leads.
 */
class SampleContent
{
    public const RESERVED_TLDS = ['test', 'example', 'invalid', 'localhost'];

    public static function isTestEmail(?string $email): bool
    {
        $domain = mb_strtolower((string) substr(strrchr((string) $email, '@') ?: '', 1));
        $tld = substr(strrchr('.'.$domain, '.') ?: '', 1);

        return $domain !== '' && in_array($tld, self::RESERVED_TLDS, true);
    }

    public static function isTestAccount(?User $user): bool
    {
        return $user !== null && static::isTestEmail($user->email);
    }
}
