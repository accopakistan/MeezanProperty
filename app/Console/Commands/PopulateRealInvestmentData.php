<?php

namespace App\Console\Commands;

use App\Services\InvestmentIndex\RealInvestmentDataService;
use Illuminate\Console\Command;

class PopulateRealInvestmentData extends Command
{
    protected $signature = 'meezan:populate-real-data';

    protected $description = 'Populates real verified project evidence, calculates investment index rankings, and publishes authorized listings';

    public function handle(RealInvestmentDataService $service): int
    {
        $this->info('Starting Real Data Population and Investment Ranking Generation...');

        $result = $service->execute();

        $this->newLine();
        $this->info('Real Data Population Complete:');
        $this->table(['Metric', 'Count'], [
            ['Legal Records Created / Verified', $result['stats']['legal_records']],
            ['Evidence Records Created / Verified', $result['stats']['evidence_records']],
            ['Context / Delivery Records', $result['stats']['context_records']],
            ['Authorized Listings Published', $result['stats']['listings_published']],
            ['Investment Index Run ID', $result['run_id']],
            ['Investment Index Run Status', $result['run_status']],
        ]);

        $this->newLine();
        $this->info('Investment Index Calculation Summary:');
        $summary = $result['run_summary'] ?? [];
        $this->table(['Category', 'Ranked Projects'], [
            ['Housing Societies', $summary['by_category']['housing-societies'] ?? 0],
            ['Commercial Plazas', $summary['by_category']['commercial-plazas'] ?? 0],
            ['Apartment Buildings', $summary['by_category']['apartment-buildings'] ?? 0],
            ['Shopping Malls', $summary['by_category']['shopping-malls'] ?? 0],
            ['Business Towers', $summary['by_category']['business-towers'] ?? 0],
            ['Total Ranked in Categories', $summary['ranked'] ?? 0],
            ['Ranked in Overall Index', $summary['in_overall_index'] ?? 0],
            ['Total Assessed Projects', $summary['projects'] ?? 0],
        ]);

        return self::SUCCESS;
    }
}
