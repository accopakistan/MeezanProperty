<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** Business Development: expansion and dealer-acquisition analysis from real coverage; proposals are drafts, never binding offers. */
class BusinessDevelopmentAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'expansion' => ['expansion', 'expand', 'opportunit', 'city', 'growth'],
            'dealer_acquisition' => ['dealer acquisition', 'acquire dealers', 'onboard dealers', 'recruit'],
            'partnership' => ['partner', 'partnership', 'institution', 'bank', 'developer'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'dealer_acquisition' => $this->dealers($ctx),
            'partnership' => $this->partnership($ctx),
            default => $this->expansion($ctx),
        };
    }

    protected function expansion(AgentContext $ctx): Report
    {
        $r = new Report('City expansion opportunities');
        $s = $this->need($ctx, $r, 'city.statistics', ['days' => 30]);
        $cov = $this->need($ctx, $r, 'locations.coverage');
        if ($s) {
            $rows = collect($s['cities'])->map(fn ($c, $city) => ['city' => $city] + $c + ['with_centre' => $cov['cities'][$city]['with_centre'] ?? null]);
            $r->table('Readiness by city', ['City', 'Areas', 'With centre', 'Verified dealers', 'Real listings', 'Leads (30 d)'],
                $rows->map(fn ($c) => [$c['city'], $c['areas'], $c['with_centre'], $c['verified_dealers'], $c['real_listings'], $c['leads']])->values()->all());
            $gaps = $rows->filter(fn ($c) => $c['areas'] > 0 && $c['verified_dealers'] === 0)->pluck('city');
            $gaps->isNotEmpty() && $r->recommend('Cities with location data but no verified dealers: '.$gaps->implode(', ').'. Dealer onboarding there unlocks listings and leads.');
            $r->finding('Readiness is judged on platform data only (areas, dealers, real listings, leads); no market-size figures are assumed.');
        }

        return $r;
    }

    protected function dealers(AgentContext $ctx): Report
    {
        $r = new Report('Dealer acquisition');
        $s = $this->need($ctx, $r, 'city.statistics', ['days' => 30]);
        if ($s) {
            $order = collect($s['cities'])->sortBy('verified_dealers')->keys()->take(3);
            $r->finding('Fewest verified dealers: '.$order->implode(', ').'.');
            $r->section('Approach (draft)', ['Start with agencies in the areas that already have guides and buyer searches.', 'Offer verification and area-matched leads; explain the packages.', 'Track sign-ups and first listings weekly.']);
        }

        return $r;
    }

    protected function partnership(AgentContext $ctx): Report
    {
        $r = new Report('Partnership opportunities');
        $k = $this->need($ctx, $r, 'knowledge.search', ['query' => 'partnership '.$ctx->task->request, 'limit' => 3]);
        if (! ($k['found'] ?? 0)) {
            $r->missing('No approved partner research is on file, and live web research is not connected.');
        }
        $d = $ctx->tool('content.save_draft', ['type' => 'proposal', 'title' => 'Partnership outline (draft)', 'body' => "Purpose: a partnership with a developer, bank or institution to bring verified inventory or financing information to Meezan Property buyers.\nNext step: staff identify candidates and review terms. No offer is made by the AI."]);
        $r->source('content.save_draft');
        $d['ok'] && $r->recommend('Outline saved as draft #'.$d['data']['draft_id'].'. Binding offers and agreements need human approval.');

        return $r;
    }
}
