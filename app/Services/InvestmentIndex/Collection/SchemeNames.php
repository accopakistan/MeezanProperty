<?php

namespace App\Services\InvestmentIndex\Collection;

/**
 * Scheme names as official lists write them ("“Green Palms” Housing
 * Scheme", "AL GHANI GARDEN PHASE-II", "Abdalians Cooperative Housing
 * Society phase-I") compared with project names. Phases must agree: a
 * Phase II listing never matches a Phase I project.
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
        $score = $baseA === $baseB ? 1.0 : 1 - levenshtein(mb_substr($baseA, 0, 255), mb_substr($baseB, 0, 255)) / max(strlen($baseA), strlen($baseB), 1);
        if (($pa[1] xor $pb[1]) && $score > 0) {
            $score = min($score, (float) config('investment_index.collection.match_auto') - 0.01);
        }

        return round(max(0.0, $score), 3);
    }
}
