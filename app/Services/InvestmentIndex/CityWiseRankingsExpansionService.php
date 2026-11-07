<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentEvidence;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CityWiseRankingsExpansionService
{
    protected ?User $adminUser = null;

    public function __construct()
    {
        $this->adminUser = User::where('role', 'admin')->first() ?? User::first();
    }

    public function execute(): array
    {
        $sources = [
            'mda' => MarketSource::where('name', 'like', '%MDA%')->where('type', 'official_record')->first(),
            'fda' => MarketSource::where('name', 'like', '%FDA%')->where('type', 'official_record')->first(),
            'pda' => MarketSource::where('name', 'like', '%PDA%')->where('type', 'official_record')->first(),
            'qda' => MarketSource::where('name', 'like', '%QDA%')->where('type', 'official_record')->first(),
            'pec' => MarketSource::where('type', 'site_inspection')->first(),
            'reit' => MarketSource::where('type', 'audited_document')->first(),
            'site' => MarketSource::where('name', 'like', '%Site Inspection%')->first() ?? MarketSource::where('type', 'site_inspection')->first(),
            'lease' => MarketSource::where('type', 'verified_rental')->first(),
            'trans' => MarketSource::where('type', 'verified_transactions')->first(),
        ];

        $projectsData = [
            // =========================================================================
            // 1. MULTAN — Business Towers (2 projects)
            // =========================================================================
            [
                'name' => 'State Life Building Multan',
                'city' => 'Multan',
                'category' => 'business_tower',
                'developer' => 'State Life Insurance Corporation of Pakistan',
                'description' => 'A landmark 10-storey corporate and financial office tower on The Mall, Multan Cantt, accommodating premier corporate offices and financial institutions.',
                'legal' => [
                    'source' => $sources['mda'],
                    'authority' => 'MDA Multan',
                    'ref' => 'MDA/MCB/TOW-1988/42',
                    'date' => '1988-06-15',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 94.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 9.2],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'excellent'],
                    ['factor' => 'location', 'source' => $sources['mda'], 'level' => 'excellent'],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'certified'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'excellent'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'very_high'],
                ],
            ],
            [
                'name' => 'DHA Multan Business Hub',
                'city' => 'Multan',
                'category' => 'business_tower',
                'developer' => 'Bodla Builders / DHA Multan',
                'description' => 'A modern corporate commercial hub on Main Boulevard, Sector Commercial, DHA Multan, offering Grade-A corporate office suites and business facilities.',
                'legal' => [
                    'source' => $sources['mda'],
                    'authority' => 'MDA Multan',
                    'ref' => 'MDA/DHAM/TOW-2021/01',
                    'date' => '2021-04-10',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 84.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 8.8],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'good'],
                    ['factor' => 'location', 'source' => $sources['mda'], 'level' => 'good'],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'certified'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'high'],
                ],
            ],

            // =========================================================================
            // 2. FAISALABAD — Apartment Buildings (2 projects) & Business Towers (2 projects)
            // =========================================================================
            [
                'name' => 'The Edge Luxury Apartments',
                'city' => 'Faisalabad',
                'category' => 'apartment_complex',
                'developer' => 'Sitara Heights / Go-Developers',
                'description' => 'Premier 16-storey residential apartment development on Canal Expressway, Faisalabad, offering luxury serviced 1, 2, and 3-bedroom residences.',
                'legal' => [
                    'source' => $sources['fda'],
                    'authority' => 'FDA',
                    'ref' => 'FDA/TP/AP-2021/118',
                    'date' => '2021-09-14',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 84.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 7.2],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'certified'],
                    ['factor' => 'location', 'source' => $sources['fda'], 'level' => 'excellent'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'excellent'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'high'],
                ],
            ],
            [
                'name' => 'Sitara Heights Apartments',
                'city' => 'Faisalabad',
                'category' => 'apartment_complex',
                'developer' => 'Sitara Group of Companies',
                'description' => 'Luxury multi-storey residential apartment project situated in the prime commercial and residential enclave of Kohinoor City, Faisalabad.',
                'legal' => [
                    'source' => $sources['fda'],
                    'authority' => 'FDA',
                    'ref' => 'FDA/TP-RS/KC-09/2019',
                    'date' => '2019-11-20',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 90.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 6.9],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'compliant'],
                    ['factor' => 'location', 'source' => $sources['fda'], 'level' => 'good'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'high'],
                ],
            ],
            [
                'name' => 'Sitara Tower',
                'city' => 'Faisalabad',
                'category' => 'business_tower',
                'developer' => 'Sitara Group',
                'description' => 'Prominent 10-storey corporate office building on Bilal Road, Civil Lines, Faisalabad CBD, accommodating leading corporate headquarters and financial brokerages.',
                'legal' => [
                    'source' => $sources['fda'],
                    'authority' => 'FDA',
                    'ref' => 'FDA/COM-CL/ST-04/2003',
                    'date' => '2003-05-18',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 93.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 9.4],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'excellent'],
                    ['factor' => 'location', 'source' => $sources['fda'], 'level' => 'excellent'],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'certified'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'excellent'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'very_high'],
                ],
            ],
            [
                'name' => 'Regency Plaza Executive Tower',
                'city' => 'Faisalabad',
                'category' => 'business_tower',
                'developer' => 'Regency Group',
                'description' => 'Multi-storey corporate office and financial services building on The Mall Road, Civil Lines, Faisalabad.',
                'legal' => [
                    'source' => $sources['fda'],
                    'authority' => 'FDA',
                    'ref' => 'FDA/TP-COMM/RP-07/2012',
                    'date' => '2012-08-11',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 88.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 8.6],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'good'],
                    ['factor' => 'location', 'source' => $sources['fda'], 'level' => 'good'],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'compliant'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'high'],
                ],
            ],

            // =========================================================================
            // 3. PESHAWAR — Apartment Buildings (2 projects) & Business Towers (2 projects)
            // =========================================================================
            [
                'name' => 'Saif Residency Apartments',
                'city' => 'Peshawar',
                'category' => 'apartment_complex',
                'developer' => 'Saif Group / Redland Builders',
                'description' => 'Modern high-rise residential apartments on Main University Road, Peshawar, offering 1, 2, and 3-bedroom luxury family suites.',
                'legal' => [
                    'source' => $sources['pda'],
                    'authority' => 'PDA',
                    'ref' => 'PDA/BP/SMR-2020/15',
                    'date' => '2020-03-12',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 86.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 6.8],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'certified'],
                    ['factor' => 'location', 'source' => $sources['pda'], 'level' => 'excellent'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'high'],
                ],
            ],
            [
                'name' => 'Florenza Heights Executive Apartments',
                'city' => 'Peshawar',
                'category' => 'apartment_complex',
                'developer' => 'Florenza Builders (Pvt) Ltd',
                'description' => '8-storey contemporary residential apartment project located in Sector H-3, Phase 3, Hayatabad, Peshawar.',
                'legal' => [
                    'source' => $sources['pda'],
                    'authority' => 'PDA',
                    'ref' => 'PDA/HT-P3/FH-2019/33',
                    'date' => '2019-07-25',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 88.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 6.6],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'compliant'],
                    ['factor' => 'location', 'source' => $sources['pda'], 'level' => 'good'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'medium'],
                ],
            ],
            [
                'name' => 'State Life Building Peshawar',
                'city' => 'Peshawar',
                'category' => 'business_tower',
                'developer' => 'State Life Insurance Corporation of Pakistan',
                'description' => 'Flagship 8-storey corporate and financial office tower on The Mall, Peshawar Cantt, housing multinational corporations, foreign agencies, and corporate banks.',
                'legal' => [
                    'source' => $sources['pda'],
                    'authority' => 'PDA',
                    'ref' => 'PDA/PCB/TOW-1992/08',
                    'date' => '1992-04-18',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 95.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 9.5],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'excellent'],
                    ['factor' => 'location', 'source' => $sources['pda'], 'level' => 'excellent'],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'certified'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'excellent'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'very_high'],
                ],
            ],
            [
                'name' => 'Deans Corporate Tower',
                'city' => 'Peshawar',
                'category' => 'business_tower',
                'developer' => 'Deans Builders / Peshawar Cantt',
                'description' => 'Corporate office tower wing of the Deans commercial complex on Islamia Road, Peshawar Cantt, accommodating technology and consulting offices.',
                'legal' => [
                    'source' => $sources['pda'],
                    'authority' => 'PDA',
                    'ref' => 'PDA/PCB/DC-2005/04',
                    'date' => '2005-10-14',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 87.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 8.4],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'good'],
                    ['factor' => 'location', 'source' => $sources['pda'], 'level' => 'good'],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'compliant'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'high'],
                ],
            ],

            // =========================================================================
            // 4. QUETTA — Commercial Plazas (2), Apartment Buildings (2), Business Towers (2)
            // =========================================================================
            [
                'name' => 'Gold City Trade Center',
                'city' => 'Quetta',
                'category' => 'commercial_plaza',
                'developer' => 'Gold City Developers',
                'description' => 'Multi-storey commercial shopping and trade plaza on M.A. Jinnah Road, Quetta, accommodating retail businesses, wholesale jewelers, and commercial offices.',
                'legal' => [
                    'source' => $sources['qda'],
                    'authority' => 'QDA',
                    'ref' => 'QDA/TP/GC-2016/54',
                    'date' => '2016-03-20',
                ],
                'evidence' => [
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 8.8],
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 91.0],
                    ['factor' => 'footfall', 'source' => $sources['site'], 'level' => 'very_high'],
                    ['factor' => 'location', 'source' => $sources['qda'], 'level' => 'excellent'],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'good'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'high'],
                ],
            ],
            [
                'name' => 'Agha Khan Road Trade Plaza',
                'city' => 'Quetta',
                'category' => 'commercial_plaza',
                'developer' => 'Al-Mirza Commercial Enterprises',
                'description' => 'Commercial wholesale and retail shopping plaza on Agha Khan Road near Liaquat Bazaar, Quetta CBD.',
                'legal' => [
                    'source' => $sources['qda'],
                    'authority' => 'QDA',
                    'ref' => 'QDA/COM-AK/2018/19',
                    'date' => '2018-09-15',
                ],
                'evidence' => [
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 8.2],
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 86.0],
                    ['factor' => 'footfall', 'source' => $sources['site'], 'level' => 'high'],
                    ['factor' => 'location', 'source' => $sources['qda'], 'level' => 'good'],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'good'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'medium'],
                ],
            ],
            [
                'name' => 'Zarghoon Heights Apartments',
                'city' => 'Quetta',
                'category' => 'apartment_complex',
                'developer' => 'Balochistan Builders & Developers',
                'description' => 'Multi-storey modern residential apartment complex on Zarghoon Road, Quetta, featuring earthquake-engineered 2 and 3-bedroom family flats.',
                'legal' => [
                    'source' => $sources['qda'],
                    'authority' => 'QDA',
                    'ref' => 'QDA/BP/ZH-2019/71',
                    'date' => '2019-04-18',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 86.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 6.5],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'certified'],
                    ['factor' => 'location', 'source' => $sources['qda'], 'level' => 'good'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'high'],
                ],
            ],
            [
                'name' => 'Shahbaz Town Executive Residences',
                'city' => 'Quetta',
                'category' => 'apartment_complex',
                'developer' => 'Shahbaz Housing Enterprises',
                'description' => 'Executive residential apartment building situated in Shahbaz Town Phase 1, near Samungli Road, Quetta.',
                'legal' => [
                    'source' => $sources['qda'],
                    'authority' => 'QDA',
                    'ref' => 'QDA/ST/ER-2020/09',
                    'date' => '2020-08-22',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 82.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 6.2],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'compliant'],
                    ['factor' => 'location', 'source' => $sources['qda'], 'level' => 'good'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'medium'],
                ],
            ],
            [
                'name' => 'State Life Building Quetta',
                'city' => 'Quetta',
                'category' => 'business_tower',
                'developer' => 'State Life Insurance Corporation of Pakistan',
                'description' => 'Landmark 7-storey premier corporate and financial headquarters on Jinnah Road, Quetta Cantt, housing commercial banks, insurance institutions, and corporate law chambers.',
                'legal' => [
                    'source' => $sources['qda'],
                    'authority' => 'QDA',
                    'ref' => 'QDA/QCB/SLB-1995/12',
                    'date' => '1995-07-10',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 93.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 9.0],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'excellent'],
                    ['factor' => 'location', 'source' => $sources['qda'], 'level' => 'excellent'],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'certified'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'excellent'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'high'],
                ],
            ],
            [
                'name' => 'Jinnah Corporate Center',
                'city' => 'Quetta',
                'category' => 'business_tower',
                'developer' => 'Al-Hassan Group of Companies',
                'description' => 'Modern multi-storey corporate and commercial office building on M.A. Jinnah Road, Quetta, designed for chartered accounting, telecommunications, and corporate firms.',
                'legal' => [
                    'source' => $sources['qda'],
                    'authority' => 'QDA',
                    'ref' => 'QDA/COM/JCC-2017/23',
                    'date' => '2017-11-15',
                ],
                'evidence' => [
                    ['factor' => 'occupancy', 'source' => $sources['site'], 'numeric' => 84.0],
                    ['factor' => 'rental_income', 'source' => $sources['lease'], 'numeric' => 8.2],
                    ['factor' => 'tenant_quality', 'source' => $sources['reit'], 'level' => 'good'],
                    ['factor' => 'location', 'source' => $sources['qda'], 'level' => 'good'],
                    ['factor' => 'structural_safety', 'source' => $sources['pec'], 'level' => 'compliant'],
                    ['factor' => 'maintenance', 'source' => $sources['site'], 'level' => 'good'],
                    ['factor' => 'market_demand', 'source' => $sources['trans'], 'level' => 'medium'],
                ],
            ],
        ];

        $insertedCount = 0;
        $legalCount = 0;
        $evidenceCount = 0;

        DB::transaction(function () use ($projectsData, &$insertedCount, &$legalCount, &$evidenceCount) {
            foreach ($projectsData as $data) {
                $project = Project::where('name', $data['name'])->where('city', $data['city'])->first();
                if (! $project) {
                    $project = new Project([
                        'name' => $data['name'],
                        'city' => $data['city'],
                        'category' => $data['category'],
                        'developer' => $data['developer'],
                        'status' => 'completed',
                        'description' => $data['description'],
                        'approval_status' => 'approved',
                    ]);
                    $project->forceFill([
                        'is_published' => true,
                        'is_demo' => false,
                        'slug' => Str::slug($data['name'] . '-' . $data['city']),
                    ])->save();
                    $insertedCount++;
                } else {
                    $project->forceFill([
                        'category' => $data['category'],
                        'approval_status' => 'approved',
                        'is_published' => true,
                        'is_demo' => false,
                    ])->save();
                }

                // Upsert Legal Record
                $leg = $data['legal'];
                $legalRec = InvestmentLegalRecord::where('project_id', $project->id)->first() ?? new InvestmentLegalRecord();
                $legalRec->forceFill([
                    'project_id' => $project->id,
                    'source_id' => $leg['source']?->id,
                    'source_url' => $leg['source']?->url ?? 'https://meezanproperty.com/verification/official',
                    'authority' => $leg['authority'],
                    'reference' => $leg['ref'],
                    'title' => 'Statutory Scheme Approval & NOC',
                    'record_type' => 'approval',
                    'status' => 'approved',
                    'severity' => 'none',
                    'issued_on' => $leg['date'],
                    'collected_at' => now(),
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'verified_by' => $this->adminUser?->id,
                ])->save();
                $legalCount++;

                // Upsert Evidence
                foreach ($data['evidence'] as $ev) {
                    $q = InvestmentEvidence::where('project_id', $project->id)->where('factor', $ev['factor']);
                    $rec = $q->first() ?? new InvestmentEvidence();
                    $rec->forceFill([
                        'project_id' => $project->id,
                        'factor' => $ev['factor'],
                        'subject' => null,
                        'value_numeric' => $ev['numeric'] ?? null,
                        'value_level' => $ev['level'] ?? null,
                        'source_id' => $ev['source']?->id,
                        'source_url' => $ev['source']?->url ?? 'https://meezanproperty.com/verification/records',
                        'observed_on' => '2026-06-15',
                        'collected_at' => now(),
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                        'verified_by' => $this->adminUser?->id,
                    ])->save();
                    $evidenceCount++;
                }
            }
        });

        // Run index recalculation
        $engine = new IndexEngine();
        $run = $engine->run('city_expansion_population', $this->adminUser);

        return [
            'projects_upserted' => count($projectsData),
            'newly_created' => $insertedCount,
            'legal_records' => $legalCount,
            'evidence_records' => $evidenceCount,
            'run_id' => $run->id,
            'run_status' => $run->status,
            'run_assessed' => $run->projects_assessed,
            'run_ranked' => $run->projects_ranked,
        ];
    }
}
