<?php

namespace App\Console\Commands;

use App\Services\InvestmentIndex\Batch1RealDataImporter;
use Illuminate\Console\Command;

class ImportRealProjectBatch extends Command
{
    protected $signature = 'meezan:import-real-projects {--dry-run : Report without saving changes}';
    protected $description = 'Import reviewed, official authority data for Batch 1 (20 Lahore + 20 Islamabad housing societies)';

    public function handle(Batch1RealDataImporter $importer): int
    {
        $this->info('Starting Batch 1 Real Project Import (20 Lahore + 20 Islamabad Housing Societies)...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE: No database changes will be written.');
            return self::SUCCESS;
        }

        $res = $importer->import();

        $this->info("Successfully updated {$res['total_updated']} projects:");
        $this->info(" - Lahore housing societies updated: {$res['lahore_updated']}");
        $this->info(" - Islamabad housing societies updated: {$res['islamabad_updated']}");

        $tableData = array_map(function ($r) {
            return [
                $r['id'],
                mb_strimwidth($r['name'], 0, 30, '…'),
                $r['city'],
                $r['authority'],
                $r['status'],
                $r['area_kanals'] ?? 'N/A',
                $r['plots'] ?? ($r['lop_status'] ?? 'N/A'),
            ];
        }, $res['records']);

        $this->table(['ID', 'Name', 'City', 'Authority', 'Status', 'Area (Kanal)', 'Plots / LOP'], $tableData);

        return self::SUCCESS;
    }
}
