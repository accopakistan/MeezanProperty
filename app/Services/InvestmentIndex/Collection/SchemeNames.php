<?php

namespace App\Services\InvestmentIndex\Collection;

/**
 * Scheme names as official lists write them ("“Green Palms” Housing
 * Scheme", "AL GHANI GARDEN PHASE-II", "Abdalians Cooperative Housing
 * Society phase-I") compared with project names. Phases must agree: a
 * Phase II listing never matches a Phase I project. The same goes for any
 * other part of a name that tells one section from another (Block-B, Sector
 * C & D, Colony-2, 3rd Part, Extension).
 */
class SchemeNames
{
    /** Words that describe the kind of scheme, not which one. */
    protected const NOISE = ['housing', 'scheme', 'schemes', 'society', 'societies', 'cooperative', 'co', 'operative', 'hs', 'chs', 'pvt', 'private',
        'ltd', 'limited', 'the', 'of', 'project', 'residential'];

    protected const ROMAN = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9, 'x' => 10, 'xi' => 11, 'xii' => 12];

    /** Readable text: repairs UTF-8 read as Windows-1252 ("â€œ"), straightens quotes, trims. */
    public static function clean(string $name): string
    {
        if (preg_match('/â€|Ã./u', $name)) {
            $fixed = @mb_convert_encoding($name, 'Windows-1252', 'UTF-8');
            if ($fixed !== false && mb_check_encoding($fixed, 'UTF-8')) {
                $name = $fixed;
            }
        }
        // Quotation marks around part of a name ("“Green Palms” Housing Scheme") are dropped.
        $name = str_replace(['“', '”', '"', '‘', '’', "\u{9d}"], ['', '', '', "'", "'", ''], $name);
        // Some rows end in a stray " rn" (a line break whose backslashes were lost).
        $name = preg_replace('/\s+rn$/u', '', preg_replace('/\s+/u', ' ', $name));

        // Stray punctuation at either end ("Abdullah Garden LSD,") is dropped; a closing bracket is kept.
        return trim($name, " \t\n\r\0\x0B\"',;:.-");
    }

    public static function normalize(string $name): string
    {
        $s = mb_strtolower(static::clean($name));
        $s = str_replace('&', ' and ', $s);
        // "phase-ii", "phase 2", "phase#2", "ph-ii" -> "phase 2" ("ph" only with a separator, so "phi..." is left alone)
        $s = preg_replace_callback('/\b(?:phase\s*[-.#]?|ph[-.])\s*([ivx]+|\d+)\b/u', fn ($m) => ' phase '.(self::ROMAN[$m[1]] ?? (int) $m[1]).' ', $s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
        $words = array_filter(explode(' ', $s), fn ($w) => $w !== '' && ! in_array($w, self::NOISE, true));

        return implode(' ', $words);
    }

    /** 0-1 similarity of two names. Different phases score 0; a phase on one side only is capped below automatic matching. */
    public static function similarity(string $a, string $b): float
    {
        $a = static::normalize($a);
        $b = static::normalize($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        preg_match_all('/phase (\d+)/', $a, $pa);
        preg_match_all('/phase (\d+)/', $b, $pb);
        if ($pa[1] && $pb[1] && $pa[1] !== $pb[1]) {
            return 0.0;
        }
        $baseA = trim(preg_replace('/phase \d+/', '', $a));
        $baseB = trim(preg_replace('/phase \d+/', '', $b));
        $marksA = static::marks($baseA);
        $marksB = static::marks($baseB);
        if ($marksA && $marksB && $marksA !== $marksB) {
            return 0.0;
        }
        $score = $baseA === $baseB ? 1.0 : 1 - levenshtein(mb_substr($baseA, 0, 255), mb_substr($baseB, 0, 255)) / max(strlen($baseA), strlen($baseB), 1);
        if ((($pa[1] xor $pb[1]) || ($marksA xor $marksB)) && $score > 0) {
            $score = min($score, (float) config('investment_index.collection.match_auto') - 0.01);
        }

        return round(max(0.0, $score), 3);
    }

    /** What tells one section of a scheme from another: numbers, roman numerals, single letters, codes such as V2, "extension". @return list<int|string> */
    protected static function marks(string $normalized): array
    {
        $marks = [];
        foreach (explode(' ', $normalized) as $word) {
            $mark = match (true) {
                isset(self::ROMAN[$word]) => self::ROMAN[$word],
                (bool) preg_match('/^(\d+)(st|nd|rd|th|rth)?$/', $word, $m) => (int) $m[1],
                mb_strlen($word) === 1 => $word,
                // "V2" and "V3", or "A1" and "A2", are different places however alike they look.
                (bool) preg_match('/^(?=.*\p{L})(?=.*\p{N})[\p{L}\p{N}]+$/u', $word) => $word,
                in_array($word, ['ext', 'extension'], true) => 'extension',
                default => null,
            };
            if ($mark !== null) {
                $marks[] = $mark;
            }
        }
        $marks = array_values(array_unique($marks));
        sort($marks);

        return $marks;
    }
}
