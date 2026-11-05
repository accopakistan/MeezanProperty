<?php

namespace App\Console\Commands;

use App\Models\InvestmentCollectionRun;
use App\Models\MarketSource;
use App\Services\AuditLogger;
use App\Services\InvestmentIndex\Collection\CollectionService;
use App\Services\InvestmentIndex\Coverage;
use App\Services\InvestmentIndex\IndexEngine;
use App\Services\InvestmentIndex\ProjectDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Builds the real project database. It can be re-run: nothing already there
 * changes, and nothing is invented. Sources whose robots.txt forbids reading
 * are blocked by the fetcher and reported.
 *
 *   --authorize-public  record the access review for the built-in public official lists
 *   --no-collect        skip live collection (use what is already stored)
 */
class InvestmentDiscover extends Command
{
    protected $signature = 'meezan:investment-discover {--authorize-public} {--no-collect}';

    protected $description = 'Collect official lists, import hand-read sources and create genuine project records, then recalculate the Investment Index';

    public function handle(CollectionService $collector, ProjectDiscovery $discovery, IndexEngine $engine, Coverage $coverage): int
    {
        Artisan::call('meezan:investment-collect', ['--setup' => true]);

        if ($this->option('authorize-public')) {
            MarketSource::whereNotNull('collector')->whereNull('authorized_at')->get()->each(function (MarketSource $s) {
                if (($s->collectorPreset()['public_record'] ?? false) !== true) {
                    return;
                }
                $s->forceFill(['is_active' => true, 'access_basis' => 'public_record', 'authorized_at' => now(), 'access_method' => 'official_list', 'authority_level' => 1,
                    'access_note' => 'Public official list. Reviewed on '.now()->toDateString().': robots.txt allows reading, and the owner\'s 4 November 2026 brief asks for permitted public official sources. The site\'s terms were not separately reviewed: the owner should check them, and can switch this off in Official sources.'])->save();
                AuditLogger::record('investment_index.source_authorized', $s, ['basis' => 'public_record', 'by' => 'owner brief, 4 Nov 2026']);
                $this->line("Authorized: {$s->name}");
            });
        }

        if (! $this->option('no-collect')) {
            foreach (MarketSource::whereNotNull('collector')->get()->filter->isCollectable() as $s) {
                $run = $collector->collect($s, 'discover');
                if ($run->status === 'completed') {
                    $s->forceFill(['last_success_at' => $run->finished_at])->save();
                }
                $this->line("{$s->name}: {$run->status}, {$run->entries_found} entries, {$run->entries_new} new".($run->error ? ' ('.mb_substr($run->error, 0, 120).')' : ''));
            }
        }

        $imported = $discovery->importFile();
        $this->line("Hand-read sources: {$imported['sources']} sources, {$imported['entries']} official lines, {$imported['references']} reference lines.");
        $found = $discovery->fromRegistry();
        $this->line("From the official registry: {$found['created']} projects created, {$found['aliased']} matched to a project already there.");

        $run = $engine->run('discovery');
        $this->line("Investment Index run #{$run->id}: {$run->status}.");
        $t = $coverage->summary()['totals'];
        $this->line("Projects {$t['projects']} · legal verified {$t['legal_verified']} · ranked {$t['ranked']} · not eligible {$t['under_review']} · insufficient {$t['insufficient']} · excluded {$t['excluded']}");
        $failed = InvestmentCollectionRun::where('trigger', 'discover')->whereIn('status', ['failed', 'blocked'])->where('created_at', '>=', now()->subHour())->count();
        $failed && $this->warn("{$failed} source(s) could not be collected: see Admin → Investment Index → Official sources.");

        return self::SUCCESS;
    }
}
