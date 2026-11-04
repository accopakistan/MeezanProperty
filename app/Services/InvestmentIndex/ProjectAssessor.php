<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentIndexMethodology;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Assesses one project in its category: legal status, each weighted factor,
 * coverage, Data Confidence Score, risk and the resulting status:
 *
 *   excluded      confirmed serious legal/regulatory issue or an unsafe building
 *   insufficient  legal status not verified, too little verified weight, or low confidence
 *   not_eligible  verified, but approval is only pending: scored, never ranked
 *   ranked        eligible for the leaderboard
 *
 * Only ranked and not-eligible projects carry a score; the others show
 * "Insufficient Verified Data" or the exclusion, never a number.
 */
class ProjectAssessor
{
    /** Shown on every profile when available, even where not weighted. */
    protected const PROFILE_FACTORS = ['development_progress', 'rental_income', 'market_performance', 'occupancy'];

    public function __construct(protected InvestmentIndexMethodology $methodology, protected CarbonInterface $today) {}

    /**
     * @param  Collection  $evidence  verified evidence of the project
     * @param  Collection  $legalRecords  verified legal records of the project
     * @param  Collection  $observations  verified, non-demo market observations of the project
     * @param  Collection  $deliveries  delivery records of the developer's other projects
     */
    public function assess(Project $project, string $category, Collection $evidence, Collection $legalRecords, Collection $observations, Collection $deliveries): array
    {
        $m = $this->methodology;
        $scorer = new FactorScorer($m, $this->today);
        $weights = $m->weightsFor($category);
        $labels = config("investment_index.categories.$category.factors");
        $legal = LegalStatus::resolve($legalRecords, $this->today);

        $factors = [];
        $raw = [];
        $used = collect();
        $weighted = 0.0;
        $scoredWeight = 0;
        $scored = 0;
        $corroborated = 0;
        foreach ($weights as $factor => $weight) {
            $r = $factor === 'legal_approval' ? $this->legalFactor($legal) : $scorer->score($factor, $evidence, $observations, $deliveries);
            $raw[$factor] = $r;
            $used = $used->concat($r['used']);
            if ($r['score'] !== null && $weight > 0) {
                $weighted += $weight * $r['score'];
                $scoredWeight += $weight;
                $scored++;
                $corroborated += $r['corroborated'] ? 1 : 0;
            }
            $factors[$factor] = $this->stored($r, $labels[$factor]['label'] ?? $factor, $weight);
        }
        // Profile facts outside the category's weights: shown, never scored.
        foreach (self::PROFILE_FACTORS as $factor) {
            if (! isset($factors[$factor])) {
                $r = $scorer->score($factor, $evidence, $observations, $deliveries);
                $raw[$factor] = $r;
                if ($r['score'] !== null) {
                    $factors[$factor] = $this->stored($r, config('investment_index.categories.housing-societies.factors.'.$factor.'.label') ?? ucfirst(str_replace('_', ' ', $factor)), 0) + ['display_only' => true];
                }
            }
        }

        $total = max(1, array_sum($weights));
        $coverage = round($scoredWeight / $total, 3);
        $confidence = DataConfidence::assess($coverage, $used, $scored, $corroborated, $m, $this->today);
        $context = ['delivery_status' => $scorer->contextLevel('delivery_status', $evidence), 'operational_status' => $scorer->contextLevel('operational_status', $evidence)];
        $risk = RiskClassifier::classify($legal, $raw, $context, $confidence['level'], $m);
        $score = $scoredWeight ? round($weighted / $scoredWeight * (1 - (float) $m->parameter('coverage_penalty') * (1 - $coverage)), 1) : null;

        [$status, $reasons] = $this->status($legal, $raw, $coverage, $confidence, $factors);
        if ($status === 'excluded') {
            $risk = ['class' => 'excluded', 'points' => max(10, $risk['points']), 'risks' => array_values(array_unique(array_merge($reasons, $risk['risks'])))];
        }

        return [
            'project_id' => $project->id,
            'category' => $category,
            'status' => $status,
            'score' => in_array($status, ['ranked', 'not_eligible'], true) ? $score : null,
            'confidence_score' => $confidence['score'],
            'confidence_level' => $confidence['level'],
            'coverage' => $coverage,
            'risk_class' => $risk['class'],
            'risk_points' => $risk['points'],
            'legal_status' => $legal['status'],
            'verified_yield' => $raw['rental_income']['score'] !== null ? $raw['rental_income']['raw'] : null,
            'development_progress' => $raw['development_progress']['score'] !== null ? $raw['development_progress']['raw'] : null,
            'operational_status' => $context['operational_status'],
            'last_verified_at' => $used->pluck('verified_at')->filter()->max()?->toDateTimeString(),
            'factors' => $factors,
            'reasons' => array_values(array_merge($reasons, array_map(fn ($r) => 'Confidence: '.$r, $confidence['reasons']))),
            'risks' => $risk['risks'],
            'source_ids' => $used->pluck('source')->filter()->pluck('id')->unique()->sort()->values()->all(),
        ];
    }

    /** @return array{0: string, 1: list<string>} */
    protected function status(array $legal, array $raw, float $coverage, array $confidence, array $factors): array
    {
        $m = $this->methodology;
        if ($legal['excluded']) {
            return ['excluded', array_merge(['Excluded from investment recommendations: confirmed legal or regulatory issue.'], $legal['reasons'])];
        }
        if (($raw['structural_safety']['level'] ?? null) === 'unsafe') {
            return ['excluded', ['Excluded from investment recommendations: a verified safety finding rates the building unsafe.']];
        }
        if ($legal['status'] === 'not_verified') {
            return ['insufficient', array_merge(['Insufficient Verified Data: legal status has not been verified with an official authority.'], $legal['reasons'])];
        }
        $min = (float) $m->parameter('min_coverage');
        if ($coverage < $min) {
            $missing = collect($factors)->filter(fn ($f) => $f['score'] === null && $f['weight'] > 0)->map(fn ($f) => $f['label'])->values();

            return ['insufficient', ['Insufficient Verified Data: verified evidence covers '.round($coverage * 100).'% of the scoring weight; '.round($min * 100).'% is needed.',
                'Missing: '.$missing->implode(', ').'.']];
        }
        if ($confidence['score'] < (float) $m->parameter('min_confidence')) {
            return ['insufficient', ['Insufficient Verified Data: Data Confidence Score '.$confidence['score'].' is below '.$m->parameter('min_confidence').'.']];
        }
        if (! $legal['eligible']) {
            return ['not_eligible', ['Not ranked: '.$legal['label'].'. Only approved or partially approved projects are ranked.']];
        }

        return ['ranked', ['Meets the evidence, confidence and legal requirements for ranking.']];
    }

    protected function legalFactor(array $legal): array
    {
        $records = $legal['records'];

        return ['score' => $legal['score'], 'value' => $legal['label'], 'basis' => implode(' ', $legal['reasons']) ?: null,
            'missing' => $legal['score'] === null ? 'Legal status not verified.' : null,
            'evidence_ids' => [], 'legal_record_ids' => $records->pluck('id')->all(), 'observation_ids' => [],
            'observed_on' => $records->max(fn ($r) => $r->effectiveDate()->toDateString()),
            'corroborated' => $records->where('record_type', 'approval')->map(fn ($r) => $r->source?->independenceKey())->filter()->unique()->count() >= 2,
            'raw' => null,
            'used' => $records->map(fn ($r) => ['source' => $r->source, 'date' => $r->effectiveDate(), 'verified_at' => $r->verified_at])->values()];
    }

    protected function stored(array $r, string $label, int $weight): array
    {
        return ['label' => $label, 'weight' => $weight, 'score' => $r['score'], 'value' => $r['value'], 'basis' => $r['basis'], 'missing' => $r['missing'],
            'contribution' => $r['score'] === null ? null : round($weight * $r['score'] / 100, 1),
            'evidence_ids' => $r['evidence_ids'], 'legal_record_ids' => $r['legal_record_ids'] ?? [], 'observation_ids' => $r['observation_ids'], 'observed_on' => $r['observed_on']];
    }
}
