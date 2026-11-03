<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** Social Media Manager: drafts posts and calendars from real platform facts; publishing needs a connected account and approval. */
class SocialMediaManagerAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'posts' => ['post', 'caption', 'content', 'instagram', 'facebook', 'social'],
            'calendar' => ['calendar', 'content calendar', 'schedule', 'content plan'],
            'publish' => ['publish', 'post it', 'go live'],
            'engagement' => ['engagement', 'followers', 'likes', 'reach'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'publish' => $this->publish($ctx),
            'engagement' => $this->engagement($ctx),
            'calendar' => $this->calendar($ctx),
            default => $this->posts($ctx),
        };
    }

    protected function posts(AgentContext $ctx): Report
    {
        $city = $this->city($ctx->task->request) ?? 'Lahore';
        $r = new Report("Social content drafts — {$city}");
        $stats = $this->need($ctx, $r, 'city.statistics', ['days' => 30]);
        $c = $stats['cities'][$city] ?? ['areas' => null, 'verified_dealers' => null];
        $posts = [
            "Searching in {$city}? Every listing on Meezan Property is reviewed before it goes live. #MeezanProperty #{$city}",
            ($c['areas'] ? "Explore {$c['areas']} areas and societies in {$city} with our area guides." : "Explore area guides for {$city}.").' #PropertyPakistan',
            'Dealers: get verified and receive area-matched leads. Register on Meezan Property. #RealEstatePakistan',
        ];
        $r->section('Draft posts (not published)', $posts);
        $d = $ctx->tool('content.save_draft', ['type' => 'social', 'channel' => 'instagram', 'title' => "Social drafts — {$city}", 'body' => implode("\n\n", $posts)]);
        $r->source('content.save_draft');
        $d['ok'] && $r->recommend('Saved as draft #'.$d['data']['draft_id'].'. Publishing needs a connected Meta account and approval.');

        return $r;
    }

    /** A one-week draft plan built from platform facts; nothing is scheduled or posted. */
    protected function calendar(AgentContext $ctx): Report
    {
        $city = $this->city($ctx->task->request) ?? 'Lahore';
        $r = new Report("Content calendar draft — {$city}");
        $stats = $this->need($ctx, $r, 'city.statistics', ['days' => 30]);
        $c = $stats['cities'][$city] ?? null;
        $topics = [
            "City spotlight: why people search {$city}".($c ? " ({$c['areas']} areas in our directory)" : ''),
            'Buyer tip: what a reviewed listing means on Meezan Property',
            'Dealer call: get verified and receive area-matched leads',
            "Area guide of the week in {$city}",
            'How the AI Property Finder works, in English and Roman Urdu',
        ];
        $days = collect($topics)->map(fn ($t, $i) => now()->addDays($i + 1)->format('D j M').': '.$t)->all();
        $r->section('Draft plan (not scheduled, not published)', $days);
        $d = $ctx->tool('content.save_draft', ['type' => 'social', 'channel' => 'instagram', 'title' => "Content calendar — {$city}", 'body' => implode("\n", $days)]);
        $r->source('content.save_draft');
        $d['ok'] && $r->recommend('Saved as draft #'.$d['data']['draft_id'].'. Posting needs a connected Meta account and approval.');
        $r->limitation('Topics are suggestions from platform facts. No engagement data is connected, so best posting times are not stated.');

        return $r;
    }

    protected function publish(AgentContext $ctx): Report
    {
        $r = new Report('Publish request');
        $draftId = (int) ($ctx->task->input['draft_id'] ?? 0);
        if (! $draftId) {
            $r->missing('Which draft? Provide a draft id.');

            return $r;
        }
        $a = $ctx->tool('content.publish', ['draft_id' => $draftId, 'channel' => $ctx->task->input['channel'] ?? 'instagram']);
        $r->source('content.publish');
        $r->finding($a['ok'] ? 'Approval requested (#'.$a['data']['approval_id'].'). Nothing is published until staff approve, and only if the account is connected.' : 'Not requested: '.$a['error']);

        return $r;
    }

    protected function engagement(AgentContext $ctx): Report
    {
        $r = new Report('Social engagement');
        $c = $this->need($ctx, $r, 'connectors.status');
        $status = $c['meta_graph']['status'] ?? 'not_configured';
        $r->finding($status === 'not_configured' ? 'No social account is connected, so there is no engagement data to report.' : 'The Meta connector is '.$status.'; engagement reading is not implemented yet.');
        $r->missing('Social engagement data (Meta Graph API).');

        return $r;
    }
}
