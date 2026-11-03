<?php

namespace App\Console\Commands;

use App\Services\Investment\RankingCalculator;
use Illuminate\Console\Command;

/**
 * Recalculate investment rankings from verified data with the active
 * methodology. The run is stored for review; it is never published here.
 */
class InvestmentRecalculate extends Command
{
    protected $signature = 'meezan:investment-recalculate';

    protected $description = 'Recalculate investment rankings from verified market data (stored for admin review, not published)';

    public function handle(RankingCalculator $calculator): int
    {
        $calc = $calculator->run(null, 'scheduled');
        $this->line("Calculation #{$calc->id}: {$calc->status}".($calc->summary ? ' — '.($calc->summary['ranked'] ?? 0).' ranked, '.($calc->summary['insufficient'] ?? 0).' insufficient' : '').'.');

        return $calc->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
