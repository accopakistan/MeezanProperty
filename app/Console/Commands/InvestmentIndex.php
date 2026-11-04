<?php

namespace App\Console\Commands;

use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentLegalRecord;
use App\Services\InvestmentIndex\IndexEngine;
use Illuminate\Console\Command;

/**
 * The Meezan Investment Index.
 *   run     recalculate and publish now (normally automatic)
 *   status  the published run, its counts, and the evidence on file
 */
class InvestmentIndex extends Command
{
    protected $signature = 'meezan:investment-index {action=status : run|status}';

    protected $description = 'Recalculate or show the Meezan Investment Index';

    public function handle(IndexEngine $engine): int
    {
        if ($this->argument('action') === 'run') {
            $run = $engine->run('command');
            $this->info("Run #{$run->id}: {$run->status}".($run->error ? " ({$run->error})" : '').'. '.json_encode($run->summary));

            return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
        }

        $m = InvestmentIndexMethodology::current();
        $run = InvestmentIndexRun::latestPublished();
        $this->line("Methodology: version {$m->version} ({$m->name}).");
        $this->line($run ? "Published run #{$run->id} at {$run->published_at}: ".json_encode($run->summary) : 'No published run yet.');
        $this->table(['Record', 'Verified', 'Unverified', 'Rejected'], [
            ['Evidence', InvestmentEvidence::verified()->count(), InvestmentEvidence::where('verification_status', 'unverified')->count(), InvestmentEvidence::where('verification_status', 'rejected')->count()],
            ['Legal records', InvestmentLegalRecord::verified()->count(), InvestmentLegalRecord::where('verification_status', 'unverified')->count(), InvestmentLegalRecord::where('verification_status', 'rejected')->count()],
        ]);

        return self::SUCCESS;
    }
}
