<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/**
 * Investment Analyst: works only from the published Investment Intelligence
 * snapshots and verified market data; separates verified data, calculated
 * metrics, interpretation and scenarios; reports gaps instead of guessing.
 */
class InvestmentAnalystAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'compare' => ['compare', 'versus', ' vs ', 'better investment', 'muqabla'],
            'yield' => ['yield', 'rental return', 'rent return', 'kiraya'],
            'rankings' => ['ranking', 'best investment', 'where to invest', 'where should i invest', 'top areas', 'investment report', 'score', 'invest', 'sarmaya'],
            'data_status' => ['market data', 'data quality', 'data confidence'],
            'methodology' => ['methodology change', 'change weights', 'new weights', 'propose weights'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'compare' => $this->compare($ctx),
            'yield' => $this->yield($ctx),
            'data_status' => $this->dataStatus($ctx),
            'methodology' => $this->propose($ctx, 'investment.methodology_change', 'Ranking methodology change request'),
            default => $this->rankings($ctx),
        };
    }

    protected function rankings(AgentContext $ctx): Report
    {
        $city = $this->city($ctx->task->request);
        $r = new Report('Investment rankings'.($city ? " — {$city}" : ''));
        $data = $this->need($ctx, $r, 'investment.rankings', array_filter(['city' => $city, 'limit' => 10]));
        if ($data && ! $data['published']) {
            $r->finding('No investment rankings are published yet, because no verified market data has been reviewed and published. I will not estimate prices or returns without it.');
            $r->missing('Published investment rankings (Market Data Centre).');

            return $r;
        }
        if ($data) {
            $r->period = 'Published '.$data['published_at'].' · methodology v'.$data['methodology_version'];
            $r->table('Calculated metrics (published snapshot)', ['Entry', 'City', 'Score', 'Confidence', 'Risk', '1-yr change', 'Gross yield'],
                collect($data['rows'])->map(fn ($x) => [$x['entity'], $x['city'], $x['score'] ?? 'not ranked', $x['confidence'], $x['risk'],
                    $x['change_1y'] !== null ? $x['change_1y'].'%' : 'Insufficient Historical Data', $x['gross_yield'] !== null ? $x['gross_yield'].'%' : 'no data'])->all());
            $r->finding('Scores and changes are calculated from verified records; see each analysis page for sources. These are not guarantees of future returns.');
        }

        return $r;
    }

    protected function compare(AgentContext $ctx): Report
    {
        $names = $ctx->task->input['names'] ?? $this->names($ctx->task->request);
        $city = $this->city($ctx->task->request);
        $r = new Report('Investment comparison: '.implode(' vs ', $names).($city ? " ({$city})" : ''));
        if (count($names) < 2) {
            $r->missing('Name at least two areas or projects to compare.');

            return $r;
        }
        $data = $this->need($ctx, $r, 'investment.compare', array_filter(['names' => $names, 'city' => $city]));
        foreach ($data['items'] ?? [] as $item) {
            if (! $item['found']) {
                $r->missing("{$item['query']}: {$item['note']}");
                $r->section($item['query'].' — what is on record', [
                    $item['locations'] ? 'Location records: '.collect($item['locations'])->map(fn ($l) => "{$l['name']} ({$l['city']}, authority status: {$l['approval_status']})")->implode('; ').'.' : 'No matching location record.',
                    "Verified market records: {$item['verified_observations']} price or rent observation(s), {$item['verified_indicators']} indicator(s).",
                ]);

                continue;
            }
            foreach ($item['rows'] as $x) {
                $r->section("{$x['entity']} ({$x['city']}, {$x['asset_class']})", [
                    'Verified data: latest price '.($x['latest_price'] ?? 'none').($x['price_basis'] ? ' ('.$x['price_basis'].')' : '').'; legal status '.($x['legal'] ?? 'unknown').'.',
                    'Calculated: score '.($x['score'] ?? 'not ranked').', confidence '.$x['confidence'].', risk '.$x['risk'].', 1-yr change '.($x['change_1y'] !== null ? $x['change_1y'].'%' : 'insufficient data').', gross yield '.($x['gross_yield'] !== null ? $x['gross_yield'].'%' : 'no data').'.',
                ]);
            }
        }
        $found = collect($data['items'] ?? [])->where('found', true)->count();
        $r->finding($found < 2 ? 'There is not enough published data to compare these places; the gaps are listed below.' : 'Interpretation: compare the scores together with their data confidence; a higher score with low confidence is weaker evidence.');

        return $r;
    }

    protected function yield(AgentContext $ctx): Report
    {
        $r = new Report('Rental yield');
        $price = $ctx->task->input['price'] ?? null;
        $rent = $ctx->task->input['monthly_rent'] ?? null;
        if (! $price || ! $rent) {
            preg_match_all('/(\d[\d,]*)/', $ctx->task->request, $m);
            $nums = array_map(fn ($n) => (int) str_replace(',', '', $n), $m[1] ?? []);
            rsort($nums);
            [$price, $rent] = [$nums[0] ?? null, $nums[1] ?? null];
        }
        if (! $price || ! $rent) {
            // No figures to calculate with: report what the verified rankings say instead.
            $r = $this->rankings($ctx);
            $r->missing('A property value and a monthly rent are needed to calculate a yield for a specific property.');

            return $r;
        }
        $y = $this->need($ctx, $r, 'investment.yield', ['price' => $price, 'monthly_rent' => $rent]);
        $y && $r->finding("Estimated gross yield: {$y['gross_yield_percent']}% (PKR ".number_format($rent).' × 12 ÷ PKR '.number_format($price).'). Gross, before costs.');

        return $r;
    }

    protected function dataStatus(AgentContext $ctx): Report
    {
        $r = new Report('Market data status');
        $q = $this->need($ctx, $r, 'market_data.quality');
        if ($q) {
            $r->finding('Verified observations by city: '.(collect($q['verified_observations_by_city'])->map(fn ($n, $c) => "{$c} {$n}")->implode(', ') ?: 'none').'.');
            $r->finding('Observations by status: '.(collect($q['observations_by_status'])->map(fn ($n, $s) => "{$s} {$n}")->implode(', ') ?: 'none').'.');
            empty($q['verified_observations_by_city']) && $r->recommend('Register sources and enter verified observations before rankings can be published.');
        }

        return $r;
    }

    /** "Compare DHA Lahore and Bahria Town Lahore" → ["DHA", "Bahria Town"]. */
    protected function names(string $request): array
    {
        $text = preg_replace('/^.*?\b(compare|comparison of|between)\b/i', '', $request);
        $parts = preg_split('/\b(and|vs\.?|versus|with|or)\b|,/i', $text);

        return collect($parts)->map(fn ($p) => trim(preg_replace('/\b(available|investment|data|for|the|in|'.implode('|', config('meezan.cities')).')\b/i', '', $p), ' .?'))
            ->filter(fn ($p) => mb_strlen($p) >= 3)->take(4)->values()->all();
    }
}
