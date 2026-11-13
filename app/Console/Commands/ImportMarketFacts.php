<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

/**
 * Loads advertised market figures (database/data/investment/market_price_facts.php) into each project's
 * research facts. A figure is stored with its source, link and the date it was read, and is labelled as
 * an advertised price index: it never becomes verified evidence and never changes an Index score.
 * Safe to run again: a project's advertised figures are replaced as a set; its other research facts are untouched.
 */
class ImportMarketFacts extends Command
{
    protected $signature = 'meezan:market-facts {--dry-run : Show what would change without saving}';

    protected $description = 'Load sourced advertised price figures onto projects (shown as advertised, not verified)';

    public const BASIS = 'Advertised (asking) price index, not verified sales';

    public function handle(): int
    {
        $rows = [];
        // All of a project's advertised figures are replaced together, so a renamed or dropped figure never lingers.
        foreach (collect(require database_path('data/investment/market_price_facts.php'))->groupBy('project') as $projectId => $entries) {
            $project = Project::whereKey($projectId)->first(['id', 'name', 'research_facts']);
            if (! $project) {
                $rows[] = [$projectId, '—', 'project not found, skipped'];

                continue;
            }
            $facts = collect($project->research_facts ?? [])->reject(fn ($f) => ($f['basis'] ?? null) === self::BASIS)->values()->all();
            foreach ($entries as $entry) {
                foreach ($entry['facts'] as $label => $value) {
                    $facts[] = ['label' => $label, 'value' => $value, 'source' => $entry['source'], 'url' => $entry['url'], 'date' => $entry['read_on'], 'basis' => self::BASIS];
                }
            }
            if (! $this->option('dry-run')) {
                $project->forceFill(['research_facts' => $facts])->save();
            }
            $rows[] = [$project->id, $project->name, $entries->flatMap(fn ($e) => $e['facts'])->count().' figures'];
        }
        $this->table(['Project', 'Name', $this->option('dry-run') ? 'Would set' : 'Set'], $rows);

        return self::SUCCESS;
    }
}
