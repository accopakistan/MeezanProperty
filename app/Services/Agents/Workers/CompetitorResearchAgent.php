<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/**
 * Competitor Research: reports only from approved research notes (with
 * source, date, method and confidence recorded by staff). Live web research
 * needs a configured search connector; it never scrapes.
 */
class CompetitorResearchAgent extends Agent
{
    public static function intents(): array
    {
        return ['summary' => ['competitor', 'competition', 'portal', 'market gap', 'pricing of', 'zameen', 'graana', 'research']];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        $r = new Report('Competitor research summary');
        $notes = $this->need($ctx, $r, 'knowledge.search', ['query' => 'competitor portal pricing features '.$ctx->task->request, 'category' => 'competitor_research', 'limit' => 5]);
        $connectors = $this->need($ctx, $r, 'connectors.status');
        if (($connectors['web_search']['status'] ?? 'not_configured') === 'not_configured') {
            $r->limitation('Live web research is not available: no web search connector is configured. Only approved research notes are used.');
        }
        if (! ($notes['found'] ?? 0)) {
            $r->missing('No approved competitor research notes exist yet (Command Center → Knowledge, category "Competitor research notes").');
            $r->finding('I have no approved competitor information to report, and I will not state unverified facts about other portals.');

            return $r;
        }
        foreach ($notes['documents'] as $d) {
            $r->section($d['title'], [mb_substr($d['excerpt'], 0, 600), 'Source: '.$d['source']]);
        }
        $r->finding("{$notes['found']} approved research note(s) used; check each note's date and source before relying on it.");

        return $r;
    }
}
