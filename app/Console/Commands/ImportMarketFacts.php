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

    /** Read in this order; a later file's reading replaces an earlier one for the same project and size. */
    public const FILES = ['market_price_facts.php', 'market_price_readings_2026_11_19.php', 'market_area_readings_2026_11_19.php'];

    public function handle(): int
    {
        $rows = [];
        // Later readings replace earlier ones for the same project and plot size (the size is in the price label).
        $sizeOf = fn (array $e) => $e['size'] ?? (preg_match('/^Advertised price \((.+?)( plot)?\)$/', (string) array_key_first($e['facts']), $m) ? $m[1] : '');
        $all = collect(self::FILES)->flatMap(fn ($file) => require database_path('data/investment/'.$file))
            ->keyBy(fn ($e) => $e['project'].'|'.$sizeOf($e))->values();
        // All of a project's advertised figures are replaced together, so a renamed or dropped figure never lingers.
        foreach ($all->groupBy('project') as $projectId => $entries) {
            $project = Project::whereKey($projectId)->first(['id', 'name', 'research_facts']);
            if (! $project) {
                $rows[] = [$projectId, '—', 'project not found, skipped'];

                continue;
            }
            // Facts this command wrote earlier (its own basis, or marked as loaded by it) are replaced; others stay.
            $facts = collect($project->research_facts ?? [])->reject(fn ($f) => ($f['basis'] ?? null) === self::BASIS || ($f['loaded_by'] ?? null) === 'market-facts')->values()->all();
            foreach ($entries as $entry) {
                foreach ($entry['facts'] as $label => $value) {
                    $fact = ['label' => $label, 'value' => $value, 'source' => $entry['source'], 'url' => $entry['url'], 'date' => $entry['read_on'], 'basis' => $entry['basis'] ?? self::BASIS, 'loaded_by' => 'market-facts'];
                    // The dated prices the page shows (oldest first) travel with the price figure.
                    if (! empty($entry['series']) && str_starts_with($label, 'Advertised price')) {
                        $fact['series'] = $entry['series'];
                    }
                    $facts[] = $fact;
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
