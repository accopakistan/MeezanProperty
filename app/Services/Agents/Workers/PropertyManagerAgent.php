<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** Property Management: inventory, completeness, duplicates, price anomalies, stale listings. */
class PropertyManagerAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'inventory' => ['inventory', 'listings report', 'property report', 'distribution', 'how many listings', 'listing growth'],
            'incomplete' => ['incomplete', 'missing information', 'missing fields', 'completeness', 'improve listing'],
            'project_duplicates' => ['duplicate project', 'project duplicate', 'project record'],
            'duplicates' => ['duplicate', 'same listing', 'copied'],
            'anomalies' => ['anomal', 'price check', 'overpriced', 'underpriced', 'unusual price'],
            'stale' => ['outdated', 'stale', 'expiring', 'old listing', 'pending review', 'waiting for review'],
            'review' => ['review new listing', 'new listing', 'submitted listing', 'moderation'],
            'customer_listing' => [], // chosen only by the customer routes, never by keywords
        ];
    }

    /** Investment project records that may be the same development. Merging stays a person's decision. */
    protected function projectDuplicates(AgentContext $ctx): Report
    {
        $r = new Report('Project record quality');
        $c = $this->need($ctx, $r, 'investment_index.coverage');
        if ($c) {
            $r->finding("{$c['possible_duplicates']} possible duplicate project records and {$c['pending']['exceptions']} open data exceptions.");
            $c['possible_duplicates'] && $r->recommend('Review them in Admin → Investment Index → Coverage. They are never merged automatically.');
        }

        return $r;
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        $city = $this->city($ctx->task->request) ?? ($ctx->task->input['city'] ?? null);

        return match ($intent) {
            'incomplete' => $this->incomplete($ctx, $city),
            'project_duplicates' => $this->projectDuplicates($ctx),
            'duplicates' => $this->duplicates($ctx, $city),
            'anomalies' => $this->anomalies($ctx, $city),
            'stale' => $this->stale($ctx, $city),
            'review' => $this->review($ctx),
            'customer_listing' => $this->customerListing($ctx),
            default => $this->inventory($ctx, $city),
        };
    }

    /** A customer asks how listings work. Public information only. */
    protected function customerListing(AgentContext $ctx): Report
    {
        $r = new Report('Listing your property');
        $r->verbatim = true;
        $this->publicAnswer($ctx, $r, 'list property listing review process', $this->urdu($ctx->task->request)
            ? 'Aap apni property website par list kar sakte hain. Har listing live hone se pehle review hoti hai.'
            : 'You can list your property on our website. Every listing is reviewed before it goes live.');
        $r->finding('Start here: '.route('sell'));

        return $r;
    }

    protected function inventory(AgentContext $ctx, ?string $city): Report
    {
        $r = new Report('Property inventory'.($city ? " — {$city}" : ''));
        $inv = $this->need($ctx, $r, 'properties.inventory', array_filter(['city' => $city]));
        if ($inv) {
            $r->finding("Published listings: {$inv['published_total']} ({$inv['demo_published']} of them demo); pending review: {$inv['pending_review']}.");
            $r->table('Listings by city and status', ['City', 'Statuses'], collect($inv['by_city_status'])->map(fn ($s, $c) => [$c, collect($s)->map(fn ($n, $k) => "{$k}: {$n}")->implode(', ')])->values()->all());
            $r->table('Published by type', ['Type', 'Listings'], collect($inv['published_by_type'])->map(fn ($n, $t) => [$t, $n])->values()->all());
            $inv['pending_review'] > 0 && $r->recommend("Review the {$inv['pending_review']} pending listing(s).");
            $inv['published_total'] > 0 && $inv['demo_published'] === $inv['published_total'] && $r->recommend('All published listings are demo listings; onboard real inventory before launch.');
        }

        return $r;
    }

    protected function incomplete(AgentContext $ctx, ?string $city): Report
    {
        $r = new Report('Incomplete listings'.($city ? " in {$city}" : ''));
        $data = $this->need($ctx, $r, 'properties.incomplete', array_filter(['city' => $city, 'limit' => 25]));
        if ($data) {
            $r->finding("{$data['incomplete']} of {$data['checked']} live or pending listings are missing information.");
            $r->table('Listings to improve (lowest completeness first)', ['ID', 'Title', 'City', 'Status', 'Score', 'Missing'],
                collect($data['listings'])->map(fn ($l) => [$l['id'], $l['title'], $l['city'], $l['status'], $l['score'], implode('; ', $l['missing'])])->all(), 'Every checked listing is complete.');
            $data['incomplete'] > 0 && $r->recommend('Ask the listing owners to add the missing items; flagging only creates staff tasks, it never changes listings.');
        }

        return $r;
    }

    protected function duplicates(AgentContext $ctx, ?string $city): Report
    {
        $r = new Report('Duplicate listing candidates');
        $data = $this->need($ctx, $r, 'properties.duplicates', array_filter(['city' => $city]));
        if ($data) {
            $r->finding(count($data['candidates']).' possible duplicate pair(s) among '.$data['checked'].' recent listings.');
            $r->table('Candidates', ['Listings', 'City', 'Why'], collect($data['candidates'])->map(fn ($c) => [implode(' & ', $c['listing_ids']), $c['city'], implode('; ', $c['reasons'])])->all(), 'No candidates.');
            count($data['candidates']) && $r->recommend('Check the pairs in Admin → Duplicate Review; the agent does not remove listings.');
        }

        return $r;
    }

    protected function anomalies(AgentContext $ctx, ?string $city): Report
    {
        $r = new Report('Price anomalies');
        $data = $this->need($ctx, $r, 'properties.price_anomalies', array_filter(['city' => $city]));
        if ($data) {
            $r->finding('Rule: '.$data['rule']);
            $data['groups_with_enough_data'] === 0
                ? $r->missing('Not enough real listings (5 per city, purpose and type) to judge prices.')
                : $r->table('Possible anomalies', ['ID', 'Title', 'Group', '× median'], collect($data['anomalies'])->map(fn ($a) => [$a['id'], $a['title'], $a['group'], $a['times_median']])->all(), 'No anomalies.');
        }

        return $r;
    }

    protected function stale(AgentContext $ctx, ?string $city): Report
    {
        $r = new Report('Outdated and waiting listings');
        $data = $this->need($ctx, $r, 'properties.stale', array_filter(['city' => $city, 'days' => 14]));
        if ($data) {
            $r->finding(count($data['expiring']).' listing(s) expire within 14 days; '.count($data['oldest_pending']).' are waiting for review.');
            $r->table('Expiring soon', ['ID', 'Title', 'City', 'Expires'], collect($data['expiring'])->map(fn ($l) => array_values($l))->all(), 'None.');
            $r->table('Waiting for review', ['ID', 'Title', 'City', 'Since'], collect($data['oldest_pending'])->map(fn ($l) => array_values($l))->all(), 'None.');
        }

        return $r;
    }

    /** Event: a listing was submitted. Check completeness and duplicates; flag for staff when needed. */
    protected function review(AgentContext $ctx): Report
    {
        $id = (int) ($ctx->task->input['property_id'] ?? 0);
        $r = new Report('New listing review #'.$id);
        $inc = $this->need($ctx, $r, 'properties.incomplete', ['property_id' => $id]);
        $mine = collect($inc['listings'] ?? [])->firstWhere('id', $id);
        $dups = $this->need($ctx, $r, 'properties.duplicates', ['property_id' => $id]);
        $dup = collect($dups['candidates'] ?? [])->first(fn ($c) => in_array($id, $c['listing_ids'], true));
        $mine ? $r->finding('Missing: '.implode('; ', $mine['missing'])) : $r->finding('The listing passes the completeness check.');
        $dup && $r->finding('Possible duplicate of listing #'.collect($dup['listing_ids'])->reject($id)->first().' ('.implode('; ', $dup['reasons']).').');
        if ($id && ($mine || $dup)) {
            $flag = $ctx->tool('listings.flag', ['property_id' => $id, 'reason' => trim(($mine ? 'Incomplete: '.implode('; ', $mine['missing']).'. ' : '').($dup ? 'Possible duplicate.' : ''))]);
            $r->source('listings.flag');
            $flag['ok'] && $r->recommend('Flagged for staff review (staff task #'.$flag['data']['staff_task_id'].'). The listing itself was not changed.');
        }

        return $r;
    }
}
