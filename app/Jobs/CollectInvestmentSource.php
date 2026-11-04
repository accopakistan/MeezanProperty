<?php

namespace App\Jobs;

use App\Models\MarketSource;
use App\Services\InvestmentIndex\Collection\CollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Collects one official source on the queue (a full list can take a minute of polite paging). One at a time per source. */
class CollectInvestmentSource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(public int $sourceId, public string $trigger = 'manual') {}

    public function uniqueId(): string
    {
        return 'investment-collect-'.$this->sourceId;
    }

    public function handle(CollectionService $collector): void
    {
        $source = MarketSource::find($this->sourceId);
        $source && $collector->collect($source, $this->trigger);
    }
}
