<?php

namespace App\Console\Commands;

use App\Services\InvestmentIndex\DataValidator;
use App\Services\InvestmentIndex\IndexEngine;
use App\Services\InvestmentIndex\ProjectDiscovery;
use App\Services\InvestmentIndex\ResearchImport;
use Illuminate\Console\Command;

/**
 * Loads the hand-read research in database/data/investment. Safe to re-run:
 * nothing already there changes, and nothing is verified by this command.
 */
class InvestmentResearchImport extends Command
{
    protected $signature = 'meezan:investment-research-import';

    protected $description = 'Import hand-read official lists, REIT filings and FBR valuations as records awaiting staff review';

    public function handle(ProjectDiscovery $discovery, ResearchImport $import, DataValidator $validator, IndexEngine $engine): int
    {
        $lists = $discovery->importFile();
        $this->line("Hand-read lists: {$lists['sources']} sources, {$lists['entries']} official lines, {$lists['references']} reference lines.");
        $found = $discovery->fromRegistry();
        $this->line("From the official registry: {$found['created']} projects created, {$found['aliased']} matched to a project already there.");

        $t = $import->run();
        $this->line("Research: {$t['sources']} sources, {$t['projects']} new projects, {$t['evidence']} evidence records awaiting review, {$t['valuations']} FBR valuations awaiting review.");
        $this->line("Facts added to {$t['facts']} projects from research and {$t['official_facts']} from official lists.");
        $this->line("Market research: {$t['portal_projects']} new projects, {$t['asking']} asking-based records. CDA details on {$t['cda_details']} projects.");
        $run = $engine->run('research');
        $this->line("Investment Index run #{$run->id}: {$run->status}.");
        $this->line('Validator: '.$validator->problemCount().' issues. Run meezan:investment-validate for details.');

        return self::SUCCESS;
    }
}
