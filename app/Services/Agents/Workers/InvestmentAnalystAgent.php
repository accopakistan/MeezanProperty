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
            'rankings' => ['ranking', 'best investment', 'where to invest', 'where should i invest', 'top areas', 'investment report', 'score', 'invest', 'sarmaya',
                'investment index', 'leaderboard', 'shopping mall', 'commercial plaza', 'business tower', 'housing societ', 'apartment building'],
            'data_status' => ['market data', 'data quality', 'data confidence'],
            'methodology' => ['methodology change', 'change weights', 'new weights', 'propose weights'],
            'advisor' => ['investment advisor'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'compare' => $this->compare($ctx),
            'yield' => $this->yield($ctx),
            'data_status' => $this->dataStatus($ctx),
            'methodology' => $this->propose($ctx, 'investment.methodology_change', 'Ranking methodology change request'),
            'advisor' => $this->advisor($ctx),
            default => $this->rankings($ctx),
        };
    }

    /** Index categories by the words people use for them. */
    protected const INDEX_CATEGORIES = [
        'housing-societies' => ['housing societ', 'society', 'societies', 'housing scheme'],
        'commercial-plazas' => ['plaza'],
        'apartment-buildings' => ['apartment building', 'apartment', 'flat'],
        'shopping-malls' => ['shopping mall', 'mall'],
        'business-towers' => ['business tower', 'tower', 'office building'],
    ];

    /**
     * The Meezan Investment Index first: the category asked about, or all
     * five leaderboards. Scores come only from the Index engine; a category
     * without ranked projects is reported as Insufficient Verified Data. The
     * earlier MPIS snapshot is added only when one is published.
     */
    protected function rankings(AgentContext $ctx): Report
    {
        $text = mb_strtolower($ctx->task->request);
        $asked = collect(self::INDEX_CATEGORIES)->filter(fn ($words) => collect($words)->contains(fn ($w) => str_contains($text, $w)))->keys()->first();
        $r = new Report('Meezan Investment Index'.($asked ? ' — '.config("investment_index.categories.$asked.label") : ''));
        $ranked = [];
        foreach ($asked ? [$asked] : array_keys(config('investment_index.categories')) as $category) {
            $board = $this->need($ctx, $r, 'investment_index.leaderboard', ['category' => $category, 'limit' => $asked ? 10 : 3]);
            if (! $board) {
                continue;
            }
            if (! $board['published']) {
                $r->finding('No Investment Index run has been published yet: Insufficient Verified Data.');
                break;
            }
            $r->period = 'Published '.$board['published_at'].' · methodology v'.$board['methodology_version'];
            $c = $board['counts'];
            $r->finding("{$board['category']}: {$c['ranked']} ranked, {$c['insufficient_verified_data']} with insufficient verified data, {$c['excluded']} excluded, of {$c['assessed']} assessed.");
            foreach ($board['items'] as $x) {
                $ranked[] = [$board['category'], $x['rank'], $x['name'], $x['city'], $x['score'], $x['confidence'], $x['risk'], $x['legal_status']];
            }
            foreach ($board['missing'] as $m) {
                $r->missing($board['category'].': '.$m);
            }
        }
        if ($ranked) {
            $r->table('Investment Index leaderboards (published)', ['Category', 'Rank', 'Project', 'City', 'Score', 'Confidence', 'Risk', 'Legal status'], $ranked);
            $r->finding('Scores come from verified, traceable evidence only and are not guarantees of future returns.');
        }

        // The earlier area / segment snapshot (MPIS), only when one is published.
        $city = $this->city($ctx->task->request);
        $data = $this->need($ctx, $r, 'investment.rankings', array_filter(['city' => $city, 'limit' => 10]));
        if ($data && $data['published']) {
            $r->finding('Earlier area and segment snapshot (MPIS), published '.$data['published_at'].' with methodology v'.$data['methodology_version'].'.');
            $r->table('Earlier area and segment snapshot (MPIS)', ['Entry', 'City', 'Score', 'Confidence', 'Risk', '1-yr change', 'Gross yield'],
                collect($data['rows'])->map(fn ($x) => [$x['entity'], $x['city'], $x['score'] ?? 'not ranked', $x['confidence'], $x['risk'],
                    $x['change_1y'] !== null ? $x['change_1y'].'%' : 'Insufficient Historical Data', $x['gross_yield'] !== null ? $x['gross_yield'].'%' : 'no data'])->all());
            $data['includes_demo'] ?? false
                ? $r->limitation(config('meezan.investment.demo_notice'))
                : $r->finding('Scores and changes are calculated from verified records; see each analysis page for sources. These are not guarantees of future returns.');
        }

        return $r;
    }

    /** The Investment Rankings advisor (website panel and chat): city / property-type questions. */
    protected function advisor(AgentContext $ctx): Report
    {
        $in = $ctx->task->input ?? [];
        $r = new Report('Investment advisor');
        $a = $this->need($ctx, $r, 'investment.advisor', array_filter(['question' => $ctx->task->request, 'city' => $in['city'] ?? null,
            'segment' => $in['segment'] ?? null, 'focus' => $in['focus'] ?? null]));
        if (! $a) {
            return $r;
        }
        foreach ($a['answer'] as $line) {
            $r->finding($line);
        }
        foreach ($a['missing'] as $m) {
            $r->missing($m);
        }
        if ($a['items']) {
            $r->table('Entries', ['Entry', 'Location', 'Group', 'MPIS', 'Status', 'Confidence', 'Gross yield', 'Demo', 'Key', 'Image'],
                collect($a['items'])->map(fn ($x) => [$x['name'], $x['location'], $x['group'], $x['score'], $x['status'], $x['confidence'], $x['yield'], $x['demo'] ? 'yes' : 'no', $x['key'], $x['image']])->all());
        }
        if ($a['sources']) {
            $r->section('Sources', $a['sources']);
        }
        $r->period = $a['methodology'] ? 'Methodology v'.$a['methodology'].($a['published_at'] ? ' · published '.$a['published_at'] : '') : null;
        $r->section('Question', [json_encode(['city' => $a['city'], 'segment' => $a['segment'], 'intent' => $a['intent'], 'size' => $a['size']])]);
        if ($a['demo']) {
            $r->limitation(config('meezan.investment.demo_notice'));
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
