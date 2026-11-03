<?php

namespace App\Services\Investment;

/**
 * Category score (0-100) from factor scores and the methodology's weights.
 *
 * - Factors without data are left out, never filled with a neutral value.
 * - The score is the weighted mean of the factors that have data, reduced
 *   by `coverage_penalty` × the share of weight that is missing.
 * - Below `min_coverage`, or without a factor the category requires, the
 *   result is "insufficient_data" with the reason.
 */
class InvestmentScorer
{
    /**
     * @param  array<string, ?float>  $factorScores  factor => 0-100 or null
     * @param  array<string, int>  $weights  factor => weight (sum 100)
     * @param  list<string>  $requires
     * @return array{status: string, score: ?float, coverage: float, factors: array<string, array{score: ?float, weight: int}>, reason: ?string}
     */
    public static function score(array $factorScores, array $weights, array $requires, float $minCoverage, float $penalty): array
    {
        $total = array_sum($weights) ?: 100;
        $factors = [];
        $sum = 0.0;
        $available = 0;
        foreach ($weights as $factor => $weight) {
            $score = $factorScores[$factor] ?? null;
            $factors[$factor] = ['score' => $score, 'weight' => $weight];
            if ($score !== null && $weight > 0) {
                $sum += $weight * $score;
                $available += $weight;
            }
        }
        $coverage = round($available / $total, 3);

        foreach ($requires as $factor) {
            if (($factorScores[$factor] ?? null) === null) {
                $label = config('meezan.investment.factors.'.$factor) ?? $factor;

                return ['status' => 'insufficient_data', 'score' => null, 'coverage' => $coverage, 'factors' => $factors, 'reason' => "This ranking needs {$label} data, which is not available."];
            }
        }
        if ($available === 0 || $coverage < $minCoverage) {
            return ['status' => 'insufficient_data', 'score' => null, 'coverage' => $coverage, 'factors' => $factors,
                'reason' => 'Data covers '.round($coverage * 100).'% of this ranking\'s weight; at least '.round($minCoverage * 100).'% is needed.'];
        }

        $score = ($sum / $available) * (1 - $penalty * (1 - $coverage));

        return ['status' => 'ranked', 'score' => round($score, 1), 'coverage' => $coverage, 'factors' => $factors, 'reason' => null];
    }
}
