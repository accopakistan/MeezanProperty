<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentIndexMethodology;

/**
 * Risk classification from verified findings only: legal problems, delivery
 * delays, operational and financial weakness, safety deficiencies, low
 * occupancy, a weak developer record and thin evidence. Each finding adds
 * points; the total sets the class. A legal exclusion is its own class.
 */
class RiskClassifier
{
    /** context evidence level => [points, text] */
    protected const CONTEXT = [
        'delivery_status' => ['delayed' => [2, 'Delivery is delayed.'], 'stalled' => [4, 'Development has stalled.'], 'abandoned' => [6, 'The project has been abandoned.']],
        'operational_status' => ['stalled' => [3, 'Operations have stalled.'], 'closed' => [4, 'The building is closed.']],
    ];

    /** factor level => [points, text] */
    protected const LEVELS = [
        'financial_stability' => ['weak' => [2, 'Financial position is weak.'], 'distressed' => [4, 'Financial distress is on record.']],
        'structural_safety' => ['minor_deficiencies' => [1, 'Minor safety deficiencies are on record.'], 'major_deficiencies' => [3, 'Major safety deficiencies are on record.']],
        'operational_performance' => ['weak' => [1, 'Operational performance is weak.'], 'distressed' => [3, 'Operations are distressed.']],
    ];

    /**
     * @param  array  $legal  LegalStatus::resolve() result
     * @param  array<string, array>  $factors  FactorScorer results, with "level" for level factors
     * @param  array<string, ?string>  $context  latest context levels (delivery_status, operational_status)
     * @return array{class: string, points: int, risks: list<string>}
     */
    public static function classify(array $legal, array $factors, array $context, string $confidenceLevel, InvestmentIndexMethodology $m): array
    {
        if ($legal['excluded']) {
            return ['class' => 'excluded', 'points' => 10, 'risks' => array_merge(array_column($legal['risks'], 'text'), $legal['reasons'])];
        }

        $found = $legal['risks'];
        foreach (self::CONTEXT as $kind => $levels) {
            if (isset($context[$kind], $levels[$context[$kind]])) {
                $found[] = ['points' => $levels[$context[$kind]][0], 'text' => $levels[$context[$kind]][1]];
            }
        }
        foreach (self::LEVELS as $factor => $levels) {
            $level = $factors[$factor]['level'] ?? null;
            if ($level && isset($levels[$level])) {
                $found[] = ['points' => $levels[$level][0], 'text' => $levels[$level][1]];
            }
        }
        $occupancy = $factors['occupancy']['raw'] ?? null;
        if ($occupancy !== null && $occupancy < 50) {
            $found[] = ['points' => $occupancy < 25 ? 2 : 1, 'text' => 'Verified occupancy is '.round($occupancy).'%.'];
        }
        $developer = $factors['developer_track_record']['score'] ?? null;
        if ($developer !== null && $developer < 40) {
            $found[] = ['points' => 2, 'text' => 'The developer has a weak delivery record.'];
        }
        if (in_array($confidenceLevel, ['limited', 'insufficient'], true)) {
            $found[] = ['points' => 1, 'text' => 'Verified evidence is limited.'];
        }

        $points = (int) array_sum(array_column($found, 'points'));
        $class = 'low';
        foreach ($m->parameter('risk_classes') as $name => $from) {
            if ($points >= $from) {
                $class = $name;
            }
        }

        return ['class' => $class, 'points' => $points, 'risks' => array_column($found, 'text')];
    }
}
