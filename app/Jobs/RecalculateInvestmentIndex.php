<?php

namespace App\Jobs;

use App\Services\InvestmentIndex\IndexEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Recalculates and publishes the Investment Index after verified data
 * changes. Unique and delayed, so a burst of changes (an import, a
 * collector run) produces one recalculation, not one per record.
 */
class RecalculateInvestmentIndex implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 900;

    public function __construct(public string $trigger = 'evidence') {}

    public static function schedule(string $trigger): void
    {
        static::dispatch($trigger)->delay(now()->addSeconds((int) config('investment_index.parameters.recalculate_delay')))->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'investment-index';
    }

    public function handle(IndexEngine $engine): void
    {
        $engine->run($this->trigger);
    }
}
