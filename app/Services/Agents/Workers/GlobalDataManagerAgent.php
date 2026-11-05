<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** Global Property Data Manager: location coverage, market-data quality and gaps; never invents records or publishes unverified data. */
class GlobalDataManagerAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'investment_data' => ['investment coverage', 'investment data', 'project coverage', 'project database', 'source freshness', 'data collection', 'real project'],
            'coverage' => ['coverage', 'location data', 'societies', 'boundaries', 'centres', 'location'],
            'quality' => ['market data', 'data quality', 'sources', 'import'],
            'review' => ['inconsistent', 'needs review', 'need review', 'location record', 'flagged', 'review queue', 'duplicate location', 'missing geographic', 'unverified location'],
            'markets' => ['uae', 'dubai', 'saudi', 'qatar', 'united kingdom', 'international', 'global'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'quality' => $this->quality($ctx),
            'markets' => $this->markets($ctx),
            'review' => $this->review($ctx),
            'investment_data' => $this->investmentData($ctx),
            default => $this->coverage($ctx),
        };
    }

    /** What the real-world project database covers, and what it does not. */
    protected function investmentData(AgentContext $ctx): Report
    {
        $r = new Report('Real-world investment data coverage');
        $c = $this->need($ctx, $r, 'investment_index.coverage');
        if ($c) {
            $t = $c['totals'];
            $r->finding("{$t['projects']} genuine projects on file; {$t['legal_verified']} have a verified legal status, {$t['ranked']} are ranked, {$t['insufficient']} have insufficient verified data, {$t['excluded']} are excluded on legal grounds.");
            $r->table('Per city', ['City', 'Projects', 'Legal verified', 'Operations verified', 'Price history', 'Rental data', 'Ranked'], collect($c['cities'])->map(fn ($x, $city) => [$city, $x['projects'], $x['legal_verified'], $x['operational_verified'], $x['history_verified'], $x['rental_verified'], $x['ranked']])->values()->all());
            $r->table('Sources', ['Source', 'Collected automatically', 'Last success', 'Projects found'], collect($c['sources'])->map(fn ($s) => [$s['name'], $s['automatically_collected'] ? 'yes' : 'no', $s['last_success'] ?? 'never', $s['projects_found']])->all());
            foreach ($c['limits'] as $limit) {
                $r->limitation($limit);
            }
            $c['pending']['exceptions'] && $r->recommend("{$c['pending']['exceptions']} exceptions and {$c['possible_duplicates']} possible duplicates need a person: Admin → Investment Index.");
            $t['ranked'] === 0 && $r->recommend('Nothing can rank on legal records alone: verified occupancy, rental, operational or inspection evidence is needed for at least 60% of a category\'s weight.');
        }

        return $r;
    }

    protected function coverage(AgentContext $ctx): Report
    {
        $r = new Report('Location data coverage');
        $c = $this->need($ctx, $r, 'locations.coverage');
        if ($c) {
            $named = $this->city($ctx->task->request);
            $rows = collect($c['cities'])->when($named, fn ($all) => $all->only([$named]))->map(fn ($x, $city) => [$city, $x['areas'], $x['with_centre'], $x['source_checked'], $x['published_boundaries'], $x['projects']])->values();
            $r->table('Per city', ['City', 'Areas', 'With map centre', 'Source checked', 'Published boundaries', 'Projects'], $rows->all());
            $areas = $rows->sum(1);
            $r->finding("{$areas} active areas; ".$rows->sum(2).' have a map centre, '.$rows->sum(4).' a published boundary.');
            $gap = $rows->where(1, '>', 0)->sortBy(fn ($x) => $x[2] / $x[1])->first();
            $gap && $r->recommend("Lowest centre coverage: {$gap[0]} ({$gap[2]} of {$gap[1]}). Add verified centres and outlines there first.");
        }

        return $r;
    }

    protected function quality(AgentContext $ctx): Report
    {
        $r = new Report('Market data quality');
        $q = $this->need($ctx, $r, 'market_data.quality');
        if ($q) {
            $r->finding('Observations: '.(collect($q['observations_by_status'])->map(fn ($n, $s) => "{$s} {$n}")->implode(', ') ?: 'none').'; indicators: '.(collect($q['indicators_by_status'])->map(fn ($n, $s) => "{$s} {$n}")->implode(', ') ?: 'none').'.');
            $r->finding('Sources by reliability: '.(collect($q['sources_by_reliability'])->map(fn ($n, $s) => "{$s} {$n}")->implode(', ') ?: 'none registered').'.');
            $q['asking_only_observations'] && $r->limitation("{$q['asking_only_observations']} verified observation(s) are asking prices, not transactions.");
            empty($q['observations_by_status']) && $r->recommend('Register approved sources and start entering verified observations for the seven cities.');
            $r->section('Importing market data', ['Use Admin → Market Data → Import with the CSV template: rows become drafts for review, and nothing is published automatically.']);
            $pending = ($q['observations_by_status']['submitted'] ?? 0) + ($q['observations_by_status']['reviewed'] ?? 0);
            $pending && $r->recommend("{$pending} observation(s) are waiting for review or verification.");
        }

        return $r;
    }

    protected function review(AgentContext $ctx): Report
    {
        $r = new Report('Location records to review');
        $q = $this->need($ctx, $r, 'locations.review_queue', array_filter(['city' => $this->city($ctx->task->request)]));
        if ($q) {
            $r->table('Per city', ['City', 'Flagged for review', 'No map centre', 'Popular, no centre', 'Approval unverified'],
                collect($q['cities'])->map(fn ($c, $city) => [$city, $c['needs_review'], $c['no_centre'], $c['popular_no_centre'], $c['approval_unverified']])->values()->all());
            $r->finding(count($q['possible_duplicates']).' possible duplicate name(s) in one city; nothing is merged automatically.');
            $r->table('Possible duplicates', ['City', 'Normalised name', 'Records'], collect($q['possible_duplicates'])->map(fn ($d) => [$d['city'], $d['name'], $d['records']])->all(), 'None.');
            $r->table('First flagged records', ['ID', 'City', 'Name', 'Note'], collect($q['flagged_sample'])->map(fn ($f) => [$f['id'], $f['city'], $f['name'], $f['note']])->all(), 'None.');
            $r->recommend('Work through Admin → Locations → "Flagged for review". The agent never edits, merges or publishes location records.');
        }

        return $r;
    }

    protected function markets(AgentContext $ctx): Report
    {
        $r = new Report('International markets');
        $c = $this->need($ctx, $r, 'locations.coverage');
        if ($c) {
            $r->table('Markets', ['Country', 'Status'], collect($c['countries'])->map(fn ($s, $k) => [$k, $s === 'active' ? 'Active (data collected)' : 'Planned — no data collected'])->values()->all());
            $r->finding('Only Pakistan is an active data market. UAE, Saudi Arabia, Qatar and the UK are configured (currency, units, legal checks) but hold no data.');
        }

        return $r;
    }
}
