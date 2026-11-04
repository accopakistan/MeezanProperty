<?php

namespace App\Console\Commands;

use App\Models\MarketSource;
use App\Services\InvestmentIndex\Collection\CollectionError;
use App\Services\InvestmentIndex\Collection\CollectionService;
use Illuminate\Console\Command;

/**
 * Collects the authorized official sources for the Investment Index.
 *   (no arguments)   every collectable source (the scheduled run)
 *   {source}         one source by id
 *   --dry-run        read and summarise only; nothing is stored
 *   --setup          add the built-in collector presets as sources (inactive, not authorized)
 */
class InvestmentCollect extends Command
{
    protected $signature = 'meezan:investment-collect {source? : source id} {--dry-run} {--setup}';

    protected $description = 'Collect official records for the Meezan Investment Index';

    public function handle(CollectionService $collector): int
    {
        if ($this->option('setup')) {
            foreach (config('investment_index.collectors') as $key => $preset) {
                if (($preset['configurable'] ?? false) || MarketSource::where('collector', $key)->exists()) {
                    continue;
                }
                $s = MarketSource::create(['name' => $preset['label'], 'type' => 'official_record', 'url' => $preset['url'], 'publisher' => $preset['authority'],
                    'reliability' => 'high', 'coverage' => $preset['city'], 'notes' => 'Added from the built-in collector preset. Authorize it before it is collected.']);
                $s->forceFill(['collector' => $key, 'is_active' => false])->save();
                $this->line("Added source #{$s->id}: {$s->name} (inactive, not authorized).");
            }

            return self::SUCCESS;
        }

        $sources = $this->argument('source')
            ? MarketSource::whereKey($this->argument('source'))->get()
            : MarketSource::whereNotNull('collector')->get()->filter->isCollectable();
        if ($sources->isEmpty()) {
            $this->line('No authorized, active collector sources.');

            return self::SUCCESS;
        }

        $failed = false;
        foreach ($sources as $source) {
            if ($this->option('dry-run')) {
                try {
                    $this->line("{$source->name}: ".json_encode($collector->collect($source, 'command', true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                } catch (CollectionError $e) {
                    $this->error("{$source->name}: {$e->kind}: {$e->getMessage()}");
                    $failed = true;
                }

                continue;
            }
            $run = $collector->collect($source, $this->argument('source') ? 'command' : 'schedule');
            $this->line("{$source->name}: run #{$run->id} {$run->status}. Entries {$run->entries_found} (new {$run->entries_new}, removed {$run->entries_removed}), matched {$run->matched}, legal records {$run->legal_records_created}, exceptions {$run->exceptions_opened}.".($run->error ? " {$run->error}" : ''));
            $failed = $failed || $run->status === 'failed';
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
