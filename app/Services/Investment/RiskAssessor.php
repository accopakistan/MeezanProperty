<?php

namespace App\Services\Investment;

/**
 * Investment risk from the available evidence (0-100, higher = riskier).
 *
 * Components: price volatility, development / project status, legal
 * verification uncertainty, oversupply, liquidity and infrastructure
 * dependency. At least `risk_min_components` are needed, otherwise the
 * result is "insufficient". Anything not fully legally verified can never
 * be classified Low risk. Legal status is reported separately as well.
 */
class RiskAssessor
{
    protected const LEGAL = ['verified' => 10, 'partially_verified' => 45, 'unverified' => 85, 'disputed' => 95];

    protected const DEVELOPER = ['completed' => 10, 'on_track' => 35, 'delayed' => 70, 'stalled' => 95];

    protected const SUPPLY = ['limited' => 20, 'balanced' => 40, 'high' => 75];

    protected const LIQUIDITY = ['high' => 15, 'medium' => 45, 'low' => 80];

    protected const INFRASTRUCTURE = ['excellent' => 15, 'good' => 25, 'fair' => 50, 'poor' => 70];

    /**
     * @param  array<string, array{level: ?string, value: ?float}>  $indicators  latest verified indicator per kind
     * @param  list<float>  $periodChanges  annualised changes between consecutive comparable observations
     * @return array{level: string, score: ?float, components: array<string, array{risk: float, basis: string}>, legal: string, warnings: list<string>}
     */
    public static function assess(array $indicators, array $periodChanges, string $legalStatus, int $minComponents, array $levels): array
    {
        $c = [];

        if (count($periodChanges) >= 2) {
            $mean = array_sum($periodChanges) / count($periodChanges);
            $sd = sqrt(array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $periodChanges)) / (count($periodChanges) - 1));
            // 0 percentage points of spread -> 10, 15+ points -> 90.
            $c['volatility'] = ['risk' => round(10 + min(80, $sd / 15 * 80), 1), 'basis' => 'Standard deviation of annualised price changes: '.round($sd, 1).' points'];
        }

        $developer = $indicators['developer_status']['level'] ?? null;
        $progress = $indicators['development_progress']['value'] ?? null;
        if ($developer && isset(self::DEVELOPER[$developer])) {
            $c['development'] = ['risk' => self::DEVELOPER[$developer], 'basis' => 'Developer / project status: '.str_replace('_', ' ', $developer)];
        } elseif ($progress !== null) {
            $c['development'] = ['risk' => round(90 - min(100, max(0, $progress)) * 0.8, 1), 'basis' => 'Development progress: '.$progress.'%'];
        }

        if (isset(self::LEGAL[$legalStatus])) {
            $c['legal'] = ['risk' => self::LEGAL[$legalStatus], 'basis' => 'Legal verification: '.str_replace('_', ' ', $legalStatus)];
        }

        foreach (['supply' => self::SUPPLY, 'liquidity' => self::LIQUIDITY, 'infrastructure' => self::INFRASTRUCTURE] as $kind => $map) {
            $level = $indicators[$kind]['level'] ?? null;
            if ($level && isset($map[$level])) {
                $name = ['supply' => 'oversupply', 'liquidity' => 'liquidity', 'infrastructure' => 'infrastructure_dependency'][$kind];
                $c[$name] = ['risk' => $map[$level], 'basis' => ucfirst($kind).': '.str_replace('_', ' ', $level)];
            }
        }

        $warnings = [];
        if ($legalStatus !== 'verified') {
            $warnings[] = $legalStatus === 'unknown'
                ? 'No verified legal or approval record is on file. Check approvals with the development authority before investing.'
                : 'Legal verification is '.str_replace('_', ' ', $legalStatus).'. Check approvals with the development authority before investing.';
        }

        if (count($c) < $minComponents) {
            return ['level' => 'insufficient', 'score' => null, 'components' => $c, 'legal' => $legalStatus, 'warnings' => $warnings];
        }

        $score = round(array_sum(array_column($c, 'risk')) / count($c), 1);
        $level = $score < $levels['low'] ? 'low' : ($score < $levels['medium'] ? 'medium' : 'high');
        if ($level === 'low' && $legalStatus !== 'verified') {
            $level = 'medium';
            $warnings[] = 'Shown as at least Medium risk because the legal status is not fully verified.';
        }

        return ['level' => $level, 'score' => $score, 'components' => $c, 'legal' => $legalStatus, 'warnings' => $warnings];
    }
}
