<?php

namespace App\Services\Investment;

use App\Models\InvestmentRanking;
use App\Services\Agents\AiProvider;
use App\Support\Grounding;
use Throwable;

/**
 * Plain-language insight for a ranking snapshot, built only from that
 * snapshot's verified data and calculated metrics. Sections are kept apart:
 * verified data, calculated metrics, interpretation (automated), scenarios
 * and what is missing.
 *
 * With MEEZAN_INVESTMENT_AI=llm and an OpenAI-compatible key, the
 * interpretation is rephrased by the model from the same structured data;
 * any reply that introduces a number not present in that data is rejected
 * and the rules-based text is used instead.
 */
class InvestmentInsights
{
    /**
     * @return array{verified: list<string>, calculated: list<string>, interpretation: list<string>, interpretation_source: string, scenarios: list<string>, missing: list<string>}
     */
    public function for(InvestmentRanking $r): array
    {
        $m = $r->metrics;
        $verified = [];
        $calculated = [];
        $missing = [];

        if ($cur = $m['current'] ?? null) {
            $verified[] = "Latest {$cur['basis_label']}: {$cur['label']} for ".trim(($cur['size'] ?? '').' '.str_replace('_', ' ', $cur['type'])).", observed {$cur['date']} (sample of {$cur['sample']}, source: {$cur['source']}).";
        } else {
            $missing[] = 'No verified sale-price observations.';
        }
        foreach ($m['indicators'] ?? [] as $i) {
            $verified[] = "{$i['label']}: {$i['display']} (as of {$i['date']}, source: {$i['source']}).";
        }
        $verified[] = 'Legal status: '.str_replace('_', ' ', $m['legal']['status'] ?? 'unknown').' — '.($m['legal']['basis'] ?? '').'.';

        foreach (['change_1y' => 'One-year', 'change_2y' => 'Two-year'] as $key => $label) {
            if ($c = $m[$key] ?? null) {
                $calculated[] = "{$label} change: ".static::signed($c['percent'])."% ({$c['from']['label']} on {$c['from']['date']} to {$c['to']['label']} on {$c['to']['date']}).";
            } else {
                $missing[] = "{$label} comparison: Insufficient Historical Data.";
            }
        }
        if ($y = $m['yield'] ?? null) {
            $calculated[] = "Estimated gross rental yield: {$y['gross']}% (rent {$y['rent']['label']}/month × 12 ÷ price {$y['sale']['label']}). Gross, before costs.";
        } else {
            $missing[] = 'Rental yield: no matching verified rent and sale observations.';
        }
        if ($r->isRanked()) {
            $calculated[] = "Score in {$r->categoryLabel()}: {$r->score}/100 (methodology v{$r->methodology_version}); data confidence: {$r->confidenceLabel()}.";
        } else {
            $missing[] = 'Not ranked: '.($r->factors['reason'] ?? 'insufficient data').'.';
        }
        $calculated[] = 'Risk: '.$r->riskLabel().($m['risk']['score'] !== null ? ' ('.$m['risk']['score'].'/100, higher is riskier)' : '').'.';

        foreach ($r->factors['items'] ?? [] as $factor => $f) {
            if ($f['score'] === null && $f['weight'] > 0) {
                $missing[] = config('meezan.investment.factors.'.$factor).': no verified data.';
            }
        }

        $interpretation = $this->rules($r);
        $source = 'rules';
        if (config('meezan.investment.ai_driver') === 'llm' && app(AiProvider::class)->available()) {
            if ($llm = $this->llm($r, $verified, $calculated, $missing)) {
                [$interpretation, $source] = [$llm, 'llm'];
            }
        }

        // Demo rows: say so, and never call illustrative figures verified.
        if ($r->is_demo) {
            $interpretation = array_map(fn ($line) => str_replace('verified data', 'illustrative data', $line), $interpretation);
            array_unshift($interpretation, 'These figures are illustrative, not verified market information.');
            $verified = array_map(fn ($line) => 'Illustrative: '.$line, $verified);
        }

        $scenarios = [];
        if (($s = $m['scenarios'] ?? null) && ! empty($s['available'])) {
            foreach (['conservative' => 'Conservative', 'expected' => 'Expected', 'high' => 'High growth'] as $k => $label) {
                $scenarios[] = "{$label}: ".static::signed($s['rates'][$k]).'% a year → about PKR '.number_format($s['ranges'][$k])." after {$s['years']} years (scenario, not a forecast).";
            }
        } else {
            $missing[] = 'Growth scenarios: '.($s['reason'] ?? 'not enough history').'.';
        }

        return compact('verified', 'calculated', 'interpretation', 'scenarios', 'missing') + ['interpretation_source' => $source];
    }

    /** @return list<string> */
    protected function rules(InvestmentRanking $r): array
    {
        $items = collect($r->factors['items'] ?? [])->filter(fn ($f) => $f['score'] !== null && $f['weight'] > 0);
        if ($items->isEmpty()) {
            return ['There is not enough verified data to interpret this entry.'];
        }

        $label = fn ($k) => strtolower(config('meezan.investment.factors.'.$k) ?? $k);
        $strong = $items->filter(fn ($f) => $f['score'] >= 70)->sortByDesc('score')->keys()->take(2)->map($label);
        $weak = $items->filter(fn ($f) => $f['score'] < 40)->sortBy('score')->keys()->take(2)->map($label);
        $out = [];
        if ($strong->isNotEmpty()) {
            $out[] = 'Strengths in the verified data: '.$strong->implode(' and ').'.';
        }
        if ($weak->isNotEmpty()) {
            $out[] = 'Weaker areas: '.$weak->implode(' and ').'.';
        }
        if (in_array($r->confidence, ['limited', 'insufficient'], true)) {
            $out[] = 'Data confidence is low, so treat this position with caution; more independent, recent observations would change it.';
        }
        if (($r->metrics['legal']['status'] ?? 'unknown') !== 'verified') {
            $out[] = 'Legal verification is not complete; check approvals before relying on this ranking.';
        }

        return $out ?: ['The verified factors are mostly moderate; no single factor stands out.'];
    }

    /** @return list<string>|null */
    protected function llm(InvestmentRanking $r, array $verified, array $calculated, array $missing): ?array
    {
        $evidence = json_encode(['entity' => $r->entity_name, 'category' => $r->categoryLabel(), 'verified' => $verified, 'calculated' => $calculated, 'missing' => $missing]);
        try {
            $res = app(AiProvider::class)->completeGeneral([
                ['role' => 'system', 'content' => 'You explain property investment rankings. Use ONLY the facts and numbers in the JSON. Do not add numbers, forecasts, guarantees or advice. Say when data is missing. Reply with 2-4 short sentences.'],
                ['role' => 'user', 'content' => $evidence],
            ], 'investment_insight', temperature: 0.2, maxTokens: 400, tier: 'research', timeout: 15, retries: 0);
            $text = $res['text'];
        } catch (Throwable) {
            return null;
        }
        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        // Grounding check: every number in the reply must appear in the evidence.
        if (! Grounding::supports($text, $evidence)) {
            return null;
        }

        return [trim($text)];
    }

    public static function signed(float $v): string
    {
        return ($v > 0 ? '+' : '').rtrim(rtrim(number_format($v, 2), '0'), '.');
    }
}
