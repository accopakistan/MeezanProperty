<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** SEO Manager: internal SEO audit and content briefs from site data. No live ranking claims without Search Console. */
class SeoManagerAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'audit' => ['seo', 'audit', 'sitemap', 'index', 'organic', 'schema', 'meta'],
            'brief' => ['brief', 'content idea', 'keyword', 'city page', 'opportunit'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return $intent === 'brief' ? $this->brief($ctx) : $this->audit($ctx);
    }

    protected function audit(AgentContext $ctx): Report
    {
        $r = new Report('SEO status');
        $a = $this->need($ctx, $r, 'seo.audit');
        if ($a) {
            $r->limitation($a['search_console']);
            $r->finding("Listings in the sitemap: {$a['sitemap_listing_urls']} (demo listings excluded: {$a['published_demo_listings_excluded']}); indexable city category pages: {$a['indexable_city_category_pages']}.");
            $r->finding("Real listings without photos: {$a['real_listings_without_photos']}; with short descriptions: {$a['real_listings_short_description']}.");
            $a['indexable_city_category_pages'] === 0 && $r->recommend('No category page has 3+ real listings yet, so none is indexed; real inventory is the main SEO lever.');
            $a['real_listings_short_description'] && $r->recommend('Ask dealers to expand short descriptions (300+ characters).');
        }

        return $r;
    }

    protected function brief(AgentContext $ctx): Report
    {
        $city = $this->city($ctx->task->request) ?? 'Lahore';
        $r = new Report("SEO content brief — {$city}");
        $stats = $this->need($ctx, $r, 'city.statistics', ['days' => 30]);
        $c = $stats['cities'][$city] ?? null;
        $body = implode("\n", [
            "Page: Property in {$city} (city guide).",
            "Suggested title: Property for Sale & Rent in {$city} | Meezan Property.",
            'Suggested description (under 155 characters): Browse reviewed listings in '.$city.' with verified dealers, area guides and map search on Meezan Property.',
            'Search intents: "houses for sale in '.$city.'", "plots in '.$city.'", "rent in '.$city.'" (no search volumes: Search Console is not connected).',
            'Facts to use (platform data): '.($c ? "{$c['areas']} areas, {$c['live_listings']} live listings, {$c['verified_dealers']} verified dealers." : 'unavailable').'.',
            'Internal links: city category pages, popular area guides, map search, investment methodology.',
            'Do not state prices or growth without published, verified data.',
        ]);
        $r->section('Brief', explode("\n", $body));
        $d = $ctx->tool('content.save_draft', ['type' => 'seo_brief', 'title' => "SEO brief — {$city}", 'body' => $body]);
        $r->source('content.save_draft');
        $d['ok'] && $r->recommend('Brief saved as draft #'.$d['data']['draft_id'].'; publishing content needs approval.');

        return $r;
    }
}
