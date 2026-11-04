<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentIndexMethodology;
use App\Models\MarketSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The Data Confidence Score (0-100), separate from the investment score:
 * how much of the category's weight has verified evidence, how recent and
 * how authoritative that evidence is, how many independent publishers it
 * comes from, and how much of it is corroborated. Explained in plain words.
 */
class DataConfidence
{
    /**
     * @param  float  $coverage  share (0-1) of the weight with verified evidence
     * @param  Collection<int, array{source: ?MarketSource, date: ?CarbonInterface}>  $used  every record used
     * @param  int  $corroborated  factors confirmed by two or more independent publishers
     * @return array{score: float, level: string, components: array<string, float>, reasons: list<string>}
     */
    public static function assess(float $coverage, Collection $used, int $scoredFactors, int $corroborated, InvestmentIndexMethodology $m, CarbonInterface $today): array
    {
        $used = $used->filter(fn ($u) => $u['source'] && $u['date']);
        if ($used->isEmpty()) {
            return ['score' => 0.0, 'level' => 'insufficient', 'components' => [], 'reasons' => ['No verified evidence is on file.']];
        }

        $fresh = (int) $m->parameter('fresh_months');
        $maxAge = (int) $m->parameter('max_age_months');
        $authority = config('investment_index.source_authority');

        $ages = $used->map(fn ($u) => max(0, $u['date']->diffInMonths($today)));
        $recency = $ages->avg(fn ($age) => $age <= $fresh ? 1.0 : max(0, 1 - ($age - $fresh) / max(1, $maxAge - $fresh)));
        $trust = $used->avg(fn ($u) => (float) ($authority[$u['source']->type] ?? 0));
        $publishers = $used->map(fn ($u) => $u['source']->independenceKey())->unique()->count();
        $independence = min(1, $publishers / max(1, (int) $m->parameter('full_sources')));
        $corroboration = $scoredFactors ? $corroborated / $scoredFactors : 0;

        $components = ['coverage' => max(0, min(1, $coverage)), 'recency' => $recency, 'authority' => $trust, 'independence' => $independence, 'corroboration' => $corroboration];
        $weights = $m->parameter('confidence_weights');
        $score = round(array_sum(array_map(fn ($k) => ($weights[$k] ?? 0) * $components[$k], array_keys($components))), 1);
        $levels = $m->parameter('confidence_levels');
        $level = $score >= $levels['high'] ? 'high' : ($score >= $levels['medium'] ? 'medium' : ($score >= $levels['limited'] ? 'limited' : 'insufficient'));

        $newest = (int) round($ages->min());
        $reasons = [
            'Verified evidence covers '.round($coverage * 100).'% of the scoring weight.',
            'Newest evidence is '.$newest.' '.($newest === 1 ? 'month' : 'months').' old; average age '.round($ages->avg()).' months.',
            'Average source authority '.round($trust * 100).'% (official records score highest).',
            $publishers.' independent '.($publishers === 1 ? 'publisher' : 'publishers').'.',
            $corroborated.' of '.$scoredFactors.' scored '.($scoredFactors === 1 ? 'factor is' : 'factors are').' confirmed by two or more publishers.',
        ];

        return ['score' => $score, 'level' => $level, 'components' => array_map(fn ($v) => round($v, 3), $components), 'reasons' => $reasons];
    }
}
