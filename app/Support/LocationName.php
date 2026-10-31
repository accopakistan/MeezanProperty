<?php

namespace App\Support;

/**
 * Normalised form of a location name, used to match what buyers and dealers
 * type ("Gulshan-e-Iqbal", "gulshan e iqbal", "DHA Ph V", "F7") against the
 * stored names and aliases. Only for matching, never shown to people.
 */
class LocationName
{
    private const ROMAN = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9, 'x' => 10, 'xi' => 11, 'xii' => 12, 'xiii' => 13];

    public static function normalize(string $name): string
    {
        $s = strtolower(trim($name));
        $s = str_replace(['&', '’', "'"], [' and ', '', ''], $s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        $s = preg_replace([
            '/\bph\b/', '/\bsec\b|\bsect\b/', '/\bblk\b/', '/\bext\b|\bextn\b/',
            '/\bco ?op(erative)?\b|\bcooperative\b/', '/\bsoc\b/', '/\bh ?s\b/', '/\bdefence housing authority\b/', '/\bcantonment\b/', '/\bnr\b/',
        ], ['phase', 'sector', 'block', 'extension', 'cooperative', 'society', 'housing scheme', 'dha', 'cantt', 'near'], $s);

        // Roman numerals after "phase" etc.: "phase v" → "phase 5". Not after
        // sector/block, which are lettered in Pakistan (DHA Lahore Sector V, X).
        $s = preg_replace_callback('/\b(phase|zone|precinct|stage)\s+(x?(?:ix|iv|v?i{0,3}))\b/', function ($m) {
            return isset(self::ROMAN[$m[2]]) ? $m[1].' '.self::ROMAN[$m[2]] : $m[0];
        }, $s);
        // A trailing numeral names a sub-area: "gulberg iii" → "gulberg 3".
        $s = preg_replace_callback('/(?<!sector|block) (i|ii|iii|iv|v|vi|vii|viii)$/', fn ($m) => ' '.self::ROMAN[$m[1]], $s);
        // Islamabad-style sectors: "f 7", "f-7" → "f7" (before dropping connectors).
        $s = preg_replace('/\b([a-i])\s+(\d{1,2})\b/', '$1$2', $s);
        // Urdu/Arabic connectors: "gulshan e iqbal" and "gulshan iqbal" match.
        // A letter after "sector"/"block" is a name ("Sector E"), and one after a
        // number is a suffix ("Phase 7-E"), not a connector.
        $s = preg_replace('/(?<!sector|block|\d)\s(e|i|ul|ud|ur)\b/', ' ', ' '.$s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
