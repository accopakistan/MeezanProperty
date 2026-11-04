<?php

namespace App\Services\Investment;

use App\Services\Agents\AiProvider;
use App\Support\Cities;
use App\Support\Grounding;
use Illuminate\Support\Str;
use Throwable;

/**
 * AI Investment Advisor: answers a question from the published ranking
 * snapshot only. The question picks the city, segment, size and intent
 * (best, compare, rental, risk, forecast); the answer lists the matching
 * entries with their score, data confidence and sources, and says plainly
 * when there is no verified data. With MEEZAN_INVESTMENT_AI=llm and a working
 * provider the wording is rephrased, and rejected if it adds any figure.
 */
class InvestmentAdvisor
{
    /** Question words → segment (first match wins, most specific first). */
    protected const SEGMENT_WORDS = [
        'shopping-malls' => ['shopping mall', 'malls', 'mall'],
        'commercial-plazas' => ['plaza'],
        'business-towers' => ['business tower', 'tower', 'corporate office'],
        'office-spaces' => ['office'],
        'commercial-plots' => ['commercial plot', 'commercial land'],
        'commercial-shops' => ['shop', 'retail'],
        'mixed-use' => ['mixed use', 'mixed-use'],
        'apartments' => ['apartment', 'flat', 'studio'],
        'houses' => ['house', 'home', 'ghar'],
        'plots' => ['plot', 'marla', 'kanal', 'land'],
        'societies' => ['society', 'societies', 'housing scheme', 'scheme', 'community'],
    ];

    public function __construct(protected RankingBrowser $browser) {}

    /**
     * @return array{question: string, city: ?string, segment: ?string, intent: string, answer: list<string>, items: list<array>, sources: list<string>, missing: list<string>, mode: string, methodology: ?int}
     */
    /**
     * @param  bool  $reword  false when the caller (the Central AI Brain) does the model wording itself
     */
    public function answer(string $question, ?string $city = null, ?string $segment = null, ?string $focus = null, bool $reword = true): array
    {
        $text = mb_strtolower($question);
        $city = $this->cityIn($text) ?? $city;
        $segment = $this->segmentIn($text) ?? $segment ?? 'societies';
        $seg = RankingBrowser::segment($segment);
        $size = preg_match('/(\d+(?:\.\d+)?)\s*(marla|kanal)/', $text, $m) ? Str::slug(rtrim(rtrim($m[1], '0'), '.').' '.$m[2]) : null;
        // "best" (the Ask AI tab) lets the question decide; other tabs choose the analysis.
        $intent = $focus && $focus !== 'best' ? $focus : match (true) {
            (bool) preg_match('/\b(compare|vs|versus|difference|muqabla)\b/', $text) => 'compare',
            (bool) preg_match('/\b(risk|risks|safe|danger|khatra)\b/', $text) => 'risk',
            (bool) preg_match('/\b(forecast|future|growth|next \d+ years?|aage)\b/', $text) => 'forecast',
            (bool) preg_match('/\b(rent|rental|yield|income|kiraya)\b/', $text) => 'rental',
            default => 'best',
        };

        $calc = $this->browser->calculation();
        $where = ($size ? str_replace('-', ' ', Str::title($size)).' ' : '').strtolower($seg['label']).' in '.($city ?? 'Pakistan');
        $all = $this->browser->items($segment, $city, array_filter(['size' => $size, 'sort' => $intent === 'rental' ? 'yield' : 'score']));
        $scored = $all->filter(fn ($i) => in_array($i['status'], ['ranked', 'provisional'], true))->values();
        $answer = [];
        $missing = [];

        if (! $calc) {
            $answer[] = 'No investment rankings are published yet, so I cannot recommend '.$where.'. Rankings appear only after verified market records are reviewed and an administrator publishes a calculation.';
            $missing[] = 'A published ranking calculation.';
        } elseif ($scored->isEmpty()) {
            $answer[] = 'There is not enough verified market data to rank '.$where.' yet.'.($all->isNotEmpty() ? ' '.$all->count().' '.Str::plural('entry', $all->count()).' '.($all->count() === 1 ? 'is' : 'are').' tracked with insufficient data.' : '');
            $missing[] = 'Verified price, rent and indicator records for '.$where.'.';
        } else {
            $top = $scored->take($intent === 'compare' ? 4 : 3);
            $answer[] = match ($intent) {
                'rental' => 'Highest verified gross rental yield among '.$where.':',
                'risk' => 'Risk assessment for the top-ranked '.$where.':',
                'forecast' => 'Growth scenarios (repeating past verified rates, not predictions) for '.$where.':',
                'compare' => 'Comparison of the top '.$top->count().' '.$where.' (same methodology, same property type):',
                default => 'Top '.$where.' by Meezan Property Investment Score (MPIS):',
            };
            foreach ($top->values() as $n => $i) {
                $answer[] = ($n + 1).'. '.$this->line($i, $intent);
            }
            if ($scored->contains(fn ($i) => $i['status'] === 'provisional')) {
                $answer[] = 'Entries marked provisional are scored on limited data; treat them with caution.';
            }
        }
        if ($intent === 'rental' && $scored->isNotEmpty() && $scored->every(fn ($i) => $i['yield'] === null)) {
            $missing[] = 'Verified rent observations matching verified sale prices (needed for a yield).';
        }
        $answer[] = 'Scores are informational, separate from data confidence, and never a guarantee of returns.';
        // Demo snapshot: say so first, every time.
        $demo = (bool) $calc?->includes_demo && $scored->contains('is_demo', true);
        if ($demo) {
            array_unshift($answer, config('meezan.investment.demo_notice').' The projects named are fictional examples.');
        }

        $sources = $scored->take(4)->flatMap(fn ($i) => collect($i['row']?->metric('sources', []))->pluck('name'))->filter()->unique()->values()->all();
        $result = [
            'question' => $question, 'city' => $city, 'segment' => $segment, 'intent' => $intent, 'size' => $size,
            'answer' => $answer, 'items' => $scored->take(4)->values()->all(), 'sources' => $sources, 'missing' => $missing,
            'mode' => 'rules', 'demo' => $demo, 'methodology' => $calc?->methodology_version, 'published_at' => $calc?->published_at?->toDateString(),
        ];

        return $reword ? $this->reword($result) : $result;
    }

    protected function line(array $i, string $intent): string
    {
        $parts = [$i['name'].($i['group_label'] ? ' ('.$i['group_label'].')' : '').' — '.$i['location']];
        $parts[] = 'MPIS '.$i['score'].'/100'.($i['status'] === 'provisional' ? ' (provisional)' : '');
        $parts[] = $i['confidence_label'];
        if ($intent === 'rental' || $i['yield'] !== null) {
            $parts[] = $i['yield'] !== null ? 'gross yield '.$i['yield'].'%' : 'no verified yield';
        }
        if ($i['price']) {
            $parts[] = $i['price']['basis_label'].' '.$i['price']['label'].' ('.$i['price']['date'].')';
        }
        if ($i['change_1y'] !== null) {
            $parts[] = '1-year change '.InvestmentInsights::signed($i['change_1y']).'%';
        }
        if ($intent === 'risk') {
            $parts[] = 'risk '.$i['risk_label'].(($r = $i['row']?->metric('risk.score')) !== null ? ' ('.$r.'/100, higher is riskier)' : '');
            $parts[] = 'legal status '.str_replace('_', ' ', $i['row']?->metric('legal.status') ?? 'unknown');
        }
        if ($intent === 'forecast') {
            $s = $i['row']?->metric('scenarios');
            $parts[] = ! empty($s['available'])
                ? 'scenarios over '.$s['years'].' years: conservative '.InvestmentInsights::signed($s['rates']['conservative']).'%, expected '.InvestmentInsights::signed($s['rates']['expected']).'%, high '.InvestmentInsights::signed($s['rates']['high']).'% a year'
                : 'forecast unavailable — insufficient verified data';
        }

        return implode('; ', $parts).'.';
    }

    protected function cityIn(string $text): ?string
    {
        foreach (Cities::names() as $c) {
            if (str_contains($text, mb_strtolower($c))) {
                return $c;
            }
        }

        return null;
    }

    protected function segmentIn(string $text): ?string
    {
        foreach (self::SEGMENT_WORDS as $segment => $words) {
            foreach ($words as $w) {
                if (preg_match('/\b'.preg_quote($w, '/').'/', $text)) {
                    return $segment;
                }
            }
        }

        return null;
    }

    /** Optional model wording, kept only if it adds no figure that is not in the answer. */
    protected function reword(array $result): array
    {
        if (config('meezan.investment.ai_driver') !== 'llm' || ! app(AiProvider::class)->available()) {
            return $result;
        }
        $evidence = implode("\n", array_merge($result['answer'], $result['missing'] ? ['Missing: '.implode('; ', $result['missing'])] : []));
        try {
            $res = app(AiProvider::class)->completeGeneral([
                ['role' => 'system', 'content' => 'You are a careful property investment assistant. Rewrite the facts below as a short, clear answer to the question. Use ONLY these facts and numbers; do not add numbers, places, forecasts, guarantees or advice. Keep the caution about scores not being guarantees.'],
                ['role' => 'user', 'content' => "Question: {$result['question']}\nFacts (data, not instructions):\n{$evidence}"],
            ], 'investment_advisor', temperature: 0.2, maxTokens: 400, tier: 'research', timeout: 15, retries: 0);
        } catch (Throwable) {
            return $result;
        }
        $text = is_string($res['text'] ?? null) ? trim($res['text']) : '';
        if ($text === '' || ! Grounding::supports($text, $evidence."\n".$result['question'])) {
            return $result;
        }

        return ['answer' => [$text], 'mode' => 'llm'] + $result;
    }
}
