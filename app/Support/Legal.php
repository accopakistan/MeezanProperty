<?php

namespace App\Support;

/**
 * Whether a legal page has owner- or counsel-approved wording. A page counts
 * as final only when both exist: an approval date in config (MEEZAN_*_APPROVED_ON)
 * and the approved text in resources/views/pages/legal/approved/{page}.blade.php.
 * Until then the site shows a plain summary, labelled as not final.
 */
class Legal
{
    public const PAGES = ['terms' => 'Terms of use', 'privacy' => 'Privacy policy', 'disclaimer' => 'Disclaimer'];

    public static function approved(string $page): bool
    {
        return filled(config("meezan.legal.$page.approved_on")) && view()->exists("pages.legal.approved.$page");
    }

    public static function approvedOn(string $page): ?string
    {
        return static::approved($page) ? (string) config("meezan.legal.$page.approved_on") : null;
    }
}
