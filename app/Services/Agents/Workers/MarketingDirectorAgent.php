<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/**
 * Marketing Director: plans from real platform figures (no campaign or ad
 * data is connected), saves proposals as drafts, never publishes or spends.
 */
class MarketingDirectorAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'strategy' => ['strategy', 'marketing strategy', 'onboarding strategy', 'plan', 'onboarding campaign', 'launch', 'campaign', 'proposal', 'calendar', 'audience'],
            'funnel' => ['funnel', 'performance', 'acquisition', 'marketing report'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return $intent === 'funnel' ? $this->funnel($ctx) : $this->strategy($ctx);
    }

    protected function funnel(AgentContext $ctx): Report
    {
        $r = new Report('Marketing funnel (platform data)');
        $f = $this->need($ctx, $r, 'marketing.funnel', ['days' => 30]);
        if ($f) {
            $r->period = 'Last 30 days';
            $r->limitation($f['campaign_data']);
            $r->finding('New listings: '.array_sum($f['new_listings_by_city'])."; inquiries: {$f['inquiries']}; new dealers: {$f['new_dealer_signups']}; new buyers: {$f['new_buyer_signups']}.");
            $r->table('Leads by source', ['Source', 'Leads'], collect($f['leads_by_source'])->map(fn ($n, $s) => [$s, $n])->values()->all(), 'No leads.');
        }

        return $r;
    }

    protected function strategy(AgentContext $ctx): Report
    {
        $city = $this->city($ctx->task->request);
        $dealerFocus = str_contains(mb_strtolower($ctx->task->request), 'dealer');
        $r = new Report('Marketing proposal'.($city ? " — {$city}" : '').($dealerFocus ? ' (dealer onboarding)' : ''));
        $stats = $this->need($ctx, $r, 'city.statistics', ['days' => 30]);
        $f = $this->need($ctx, $r, 'marketing.funnel', ['days' => 30]);
        $c = $city ? ($stats['cities'][$city] ?? null) : null;
        $c && $r->finding("{$city} today: {$c['live_listings']} live listings ({$c['real_listings']} real), {$c['verified_dealers']} verified dealers, {$c['areas']} areas in the directory, {$c['leads']} leads in 30 days.");
        $f && $r->limitation($f['campaign_data']);

        $steps = $dealerFocus ? [
            'Audience: agencies in '.($city ?? 'the target city').' with active inventory; start with areas that already have Meezan area guides.',
            'Offer: verified badge, area-matched leads and the free/launch package (no paid spend until approved).',
            'Channels: direct outreach through the Business Development team, WhatsApp only to dealers who opted in, and an onboarding page.',
            'Measure: dealer sign-ups, verification completions and first listings per week (tracked on the platform).',
        ] : [
            'Audience: buyers and renters searching '.($city ?? 'the launch cities').' by area.',
            'Content: city and area guides, investment methodology explainer, verified-listing messaging.',
            'Channels: SEO city/category pages, social posts (drafts only until a Meta account is connected and approved).',
            'Measure: inquiries and leads by source on the platform.',
        ];
        $r->section('Proposed plan (draft for approval)', $steps);
        $draft = $ctx->tool('content.save_draft', ['type' => 'marketing', 'title' => $r->title, 'body' => implode("\n", $steps)]);
        $r->source('content.save_draft');
        $draft['ok'] && $r->recommend('Proposal saved as draft #'.$draft['data']['draft_id'].'. Publishing or spending needs explicit approval.');
        $r->limitation('No budget figures are proposed without connected cost and campaign data.');

        return $r;
    }
}
