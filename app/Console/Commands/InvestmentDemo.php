<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\Investment\DemoDataManager;
use Illuminate\Console\Command;

/**
 * Manage the Investment Rankings demonstration dataset.
 *   seed         create the demo projects and records (idempotent) and rank them
 *   refresh      regenerate the demo records of demo projects, then rank them
 *   recalculate  rank the existing demo data again
 *   remove       delete every demo record (real records are never touched)
 *   status       counts of demo and real data
 *   mode         --on / --off: show demo data on public dashboards
 */
class InvestmentDemo extends Command
{
    protected $signature = 'meezan:investment-demo {action : seed|refresh|recalculate|remove|status|mode} {--on} {--off} {--force : remove without asking}';

    protected $description = 'Seed, rank, remove or switch the Investment Rankings demo data';

    public function handle(DemoDataManager $demo): int
    {
        switch ($this->argument('action')) {
            case 'seed':
            case 'refresh':
                $created = $demo->seed(refresh: $this->argument('action') === 'refresh');
                $this->info("New demo projects: {$created['projects']}; observations written: {$created['observations']}; indicators written: {$created['indicators']}; skipped (real project with the same name): {$created['skipped_real']}.");
                // no break: rank what was seeded
            case 'recalculate':
                $calc = $demo->recalculate();
                $this->info("Demo rankings: calculation #{$calc->id} {$calc->status}".($calc->error ? " ({$calc->error})" : '').'. '.json_encode($calc->summary));

                return $calc->status === 'published' ? self::SUCCESS : self::FAILURE;
            case 'remove':
                if (! $this->option('force') && ! $this->confirm('Delete every demo project, record and demo ranking? Real data is not affected.')) {
                    return self::FAILURE;
                }
                $this->info('Removed: '.json_encode($demo->remove()));

                return self::SUCCESS;
            case 'mode':
                if ($this->option('on') === $this->option('off')) {
                    $this->error('Use --on or --off.');

                    return self::INVALID;
                }
                AppSetting::put('investment.demo_mode', (bool) $this->option('on'));
                $this->info('Demo mode '.($this->option('on') ? 'on' : 'off').'.');

                return self::SUCCESS;
            case 'status':
                $s = $demo->stats();
                $this->table(['', 'Demo', 'Real'], [
                    ['Projects', $s['projects'], $s['real']['projects']],
                    ['Price & rent records', $s['observations'], $s['real']['verified_observations'].' verified'],
                    ['Indicators', $s['indicators'], $s['real']['verified_indicators'].' verified'],
                    ['Sources', $s['sources'], $s['real']['sources']],
                    ['Published rankings', $s['demo_calculation'] ? '#'.$s['demo_calculation']->id : 'none', $s['real_calculation'] ? '#'.$s['real_calculation']->id : 'none'],
                ]);
                $this->line('Demo mode: '.($s['demo_mode'] ? 'on' : 'off'));

                return self::SUCCESS;
        }
        $this->error('Unknown action.');

        return self::INVALID;
    }
}
