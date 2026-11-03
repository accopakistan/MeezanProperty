<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentCatalog;
use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/**
 * AI CEO: answers business questions from real data, and for broad reports
 * delegates to specialist agents through the Central Brain, then
 * consolidates their findings, gaps and priorities. Recommends; never
 * executes sensitive actions.
 */
class CeoAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'business_report' => ['business performance', 'performing', 'performance report', 'how is meezan', 'executive report', 'overview', 'business report', 'prioritize', 'priority', 'this month', 'weekly report'],
            'expansion' => ['expansion', 'expansion performance', 'expand', 'launch strategy', 'seven-city', 'seven city', '7 city', 'new city', 'city launch'],
            'question' => ['which city', 'most leads', 'revenue', 'converting', 'listing growth'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'expansion' => $this->expansion($ctx),
            'question' => $this->question($ctx),
            default => $this->businessReport($ctx),
        };
    }

    protected function businessReport(AgentContext $ctx): Report
    {
        $report = new Report('Meezan Property business performance');
        $report->period = 'Last 30 days';
        $this->cityOverview($ctx, $report);

        $this->collect($ctx, $report, [
            'sales-manager' => 'Sales pipeline report for the last 30 days',
            'dealer-manager' => 'Dealer verification and activity report',
            'property-manager' => 'Property inventory report',
            'finance-manager' => 'Revenue report for the current month',
            'subscription-manager' => 'Subscription status and expiring subscriptions',
            'technical-operations' => 'Application health report',
            'investment-analyst' => 'Market data quality status',
        ]);
        $this->priorities($report);

        return $report;
    }

    protected function expansion(AgentContext $ctx): Report
    {
        $city = $this->city($ctx->task->request);
        $in = $city ? " in {$city}" : '';
        $report = new Report($city ? "{$city} expansion report" : 'Seven-city business expansion report');
        $report->period = 'Last 30 days';
        $this->cityOverview($ctx, $report);

        $this->collect($ctx, $report, [
            'business-development' => 'City expansion opportunities across the launch cities',
            'global-data-manager' => "Location data coverage{$in}",
            'sales-manager' => "Lead performance{$in}",
            'dealer-manager' => "Dealer performance{$in}",
            'marketing-director' => 'Marketing funnel performance',
            'seo-manager' => 'SEO audit of the sitemap',
            'competitor-research' => 'Competitor research summary',
            'finance-manager' => 'Revenue report for the current month',
        ]);
        $this->priorities($report);

        return $report;
    }

    protected function question(AgentContext $ctx): Report
    {
        $q = mb_strtolower($ctx->task->request);
        $report = new Report('Answer: '.mb_substr($ctx->task->request, 0, 120));
        if (str_contains($q, 'revenue')) {
            $rev = $this->need($ctx, $report, 'payments.revenue', []);
            $rev && $report->finding("Succeeded payments this month: {$rev['succeeded_payments']}, totalling PKR ".number_format($rev['succeeded_amount']).' (test payments excluded).');
        } elseif (str_contains($q, 'convert')) {
            $perf = $this->need($ctx, $report, 'dealers.performance', ['days' => 30]);
            $top = collect($perf['dealers'] ?? [])->where('received', '>', 0)->sortByDesc('won')->take(5);
            $top->isEmpty() ? $report->finding('No verified dealer received leads in the last 30 days, so conversion cannot be compared.')
                : $report->table('Dealers by won leads (30 days)', ['Agency', 'City', 'Received', 'Won', 'Lost'], $top->map(fn ($d) => [$d['agency'], $d['city'], $d['received'], $d['won'], $d['lost']])->values()->all());
        } else {
            $this->cityOverview($ctx, $report);
            str_contains($q, 'growth') && $report->missing('Listing growth per area over time is not tracked; the table shows current counts only.');
        }

        return $report;
    }

    protected function cityOverview(AgentContext $ctx, Report $report): void
    {
        $stats = $this->need($ctx, $report, 'city.statistics', ['days' => 30]);
        if (! $stats) {
            return;
        }
        $rows = collect($stats['cities'])->map(fn ($c, $city) => [$city, $c['live_listings'], $c['real_listings'], $c['verified_dealers'], $c['leads']])->values();
        $report->table('Seven cities at a glance (live data)', ['City', 'Live listings', 'Real (non-demo)', 'Verified dealers', 'Leads (30 days)'], $rows->all());
        $topLeads = $rows->sortByDesc(4)->first();
        $topLeads && $topLeads[4] > 0
            ? $report->finding("Most leads in the last 30 days: {$topLeads[0]} ({$topLeads[4]}).")
            : $report->finding('No leads were recorded in the last 30 days.');
        $demo = $rows->sum(1) - $rows->sum(2);
        if ($demo > 0) {
            $report->finding("{$demo} of ".$rows->sum(1).' live listings are demo listings, not real inventory.');
        }
    }

    /** Delegate to each agent and fold its findings into this report. */
    protected function collect(AgentContext $ctx, Report $report, array $requests): void
    {
        foreach ($requests as $slug => $request) {
            $r = $ctx->delegate($slug, $request);
            $label = AgentCatalog::name($slug);
            if (! $r['ok']) {
                $report->missing("{$label}: ".$r['error']);
                $ctx->memory()->remember('subtask', "{$label}: failed.", 'delegation');

                continue;
            }
            $sub = $r['report'];
            $ctx->memory()->remember('subtask', "{$label}: {$sub['title']}.", 'delegation');
            $report->section($sub['title'].' — '.$label, array_merge($sub['summary'], array_map(fn ($m) => 'Missing: '.$m, $sub['missing'])));
            foreach ($sub['recommendations'] as $rec) {
                $report->recommend("[{$label}] {$rec}");
            }
            foreach ($sub['missing'] as $m) {
                $report->missing("{$label}: {$m}");
            }
            $report->source('delegated:'.$slug.' (task '.$r['task'].')');
        }
        $report->section('Subtask log', $ctx->memory()->context()['task']->pluck('content')->all());
    }

    protected function priorities(Report $report): void
    {
        $recs = collect($report->recommendations);
        $report->finding($recs->isEmpty()
            ? 'No urgent issues were found in the available data.'
            : 'Priorities: '.$recs->take(3)->map(fn ($r) => preg_replace('/^\[[^\]]+\]\s*/', '', $r))->implode(' · '));
        $report->limitation('The CEO recommends; it does not execute financial, verification, publishing or reassignment actions.');
    }
}
