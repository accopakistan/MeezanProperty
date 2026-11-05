<?php

namespace App\Services\InvestmentIndex\Collection;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Turns an official list's wording into a legal status, conservatively:
 * wording it cannot read returns null (staff are asked), never "approved".
 */
class OfficialStatus
{
    /** @return ?string approved | partially_approved | pending | cancelled | illegal, or null when not recognised */
    public static function for(string $listType, ?string $raw, ?string $stages = null): ?string
    {
        if ($listType === 'illegal') {
            return 'illegal';
        }
        if ($listType === 'authority_scheme') {
            return 'approved';
        }
        if ($stages === 'cda') {
            return static::cda((string) $raw);
        }
        if ($stages !== 'lda') {
            return 'approved'; // a list of approved schemes without stage wording
        }

        // LDA approval stages are free text ("Finally Appoved", "Technical Approval Granted dated 13-06-2020 Final Approval is pending").
        $s = mb_strtolower(trim((string) $raw));
        if ($s === '') {
            return null;
        }

        return match (true) {
            (bool) preg_match('/pending|not issued|not yet/', $s) => 'partially_approved',
            (bool) preg_match('/\bfina?n?l|\bfinally|\bfianl/', $s) => 'approved',
            (bool) preg_match('/technical/', $s) => 'partially_approved',
            (bool) preg_match('/\bap+(r|e)?o+v|\bapprov/', $s) => 'approved',
            default => null,
        };
    }

    /**
     * CDA's two steps, "LOP: Approved / NOC: Issued": a layout plan, then a
     * No Objection Certificate. Anything cancelled is cancelled; a layout plan
     * alone is only partial, and only an issued NOC is approval.
     */
    protected static function cda(string $raw): ?string
    {
        $s = mb_strtolower($raw);

        return match (true) {
            str_contains($s, 'cancel') => 'cancelled',
            (bool) preg_match('/noc:\s*(issued|approved)/', $s) => 'approved',
            str_contains($s, 'provisional') => 'pending',
            (bool) preg_match('/lop:\s*approved/', $s) => 'partially_approved',
            default => null,
        };
    }

    /** A date from official wording ("2022-12-23", "31.03.2011 (Revised Plan)", "20.07.09"), or null. A bare year is not a date. */
    public static function date(?string ...$texts): ?CarbonInterface
    {
        foreach ($texts as $text) {
            $text = (string) $text;
            if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})/', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
            }
            if (preg_match('/\b(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{2}|\d{4})\b/', $text, $m)) {
                $year = (int) $m[3];
                if ($year < 100) {
                    $year += $year <= (int) now()->format('y') ? 2000 : 1900;
                }
                if (checkdate((int) $m[2], (int) $m[1], $year)) {
                    return Carbon::create($year, (int) $m[2], (int) $m[1])->startOfDay();
                }
            }
        }

        return null;
    }
}
