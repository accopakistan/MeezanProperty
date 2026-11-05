<?php

namespace App\Console\Commands;

use App\Services\InvestmentIndex\DataValidator;
use Illuminate\Console\Command;

/** Reports problems in the real-world ranking data. Changes nothing. Exits 1 when a check marked "error" finds something. */
class InvestmentValidate extends Command
{
    protected $signature = 'meezan:investment-validate';

    protected $description = 'Check the real-world ranking data for missing sources, unsupported scores, undated prices, duplicates and stale records';

    public function handle(DataValidator $validator): int
    {
        $report = $validator->report();
        $this->table(['Check', 'Severity', 'Count', 'Examples'], collect($report)->map(fn ($c) => [
            $c['label'], $c['severity'], $c['count'], mb_substr(implode(', ', array_slice($c['examples'], 0, 3)), 0, 60),
        ])->values()->all());

        return collect($report)->where('severity', 'error')->sum('count') > 0 ? self::FAILURE : self::SUCCESS;
    }
}
