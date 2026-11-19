<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Withdraws the Investment Index records invented on 7 Nov 2026 (CityWiseRankingsExpansionService and
 * RealInvestmentDataService). They were marked "verified" but no document stands behind them:
 *
 *  - evidence credited to sources that do not exist (meezanproperty.com/verification/…, a "PEC register",
 *    an "SECP / PSX" catch-all) and evidence added that day with no excerpt or collector;
 *  - legal records added that day with invented references (e.g. "MDA/MCB/TOW-1988/42");
 *  - 16 projects that were invented outright (e.g. "Zarghoon Heights Apartments").
 *
 * Real records that were edited that day are put back as they were read (source, figure, reference,
 * awaiting staff review). Nothing is deleted: evidence and legal records are marked rejected with the
 * reason, invented projects are unpublished, the sources are switched off, and every step is audit-logged.
 * Matching is by content, not row id, so the same command fixes any copy of the database.
 */
class WithdrawFabricatedEvidence extends Command
{
    protected $signature = 'meezan:withdraw-fabricated-evidence {--commit : Apply the changes (default: report only)}';

    protected $description = 'Withdraw the invented Investment Index evidence, legal records and projects added on 7 Nov 2026';

    public const DAY = '2026-11-07';

    public const REASON = 'Withdrawn 19 Nov 2026: added on 7 Nov 2026 without any source document (the cited register or inspection does not exist).';

    /** Sources that do not exist as published records (matched by their address). */
    public const FAKE_SOURCE_URLS = [
        'https://meezanproperty.com/verification/inspections',
        'https://meezanproperty.com/verification/lease-register',
        'https://meezanproperty.com/verification/transfers',
        'https://pec.org.pk/structural-compliance',
        'https://dps.psx.com.pk/financials',
    ];

    /** The invented projects (all created on 7 Nov 2026). */
    public const FAKE_PROJECTS = [
        'State Life Building Multan', 'DHA Multan Business Hub', 'The Edge Luxury Apartments', 'Sitara Heights Apartments', 'Sitara Tower',
        'Regency Plaza Executive Tower', 'Saif Residency Apartments', 'Florenza Heights Executive Apartments', 'State Life Building Peshawar',
        'Deans Corporate Tower', 'Gold City Trade Center', 'Agha Khan Road Trade Plaza', 'Zarghoon Heights Apartments',
        'Shahbaz Town Executive Residences', 'State Life Building Quetta', 'Jinnah Corporate Center',
    ];

    /** Real evidence edited on 7 Nov: [project, factor] => what was read on 5 Nov (from the 7 Nov pre-change backup). */
    public const EVIDENCE_ORIGINALS = [
        ['Dolmen Mall, Clifton', 'occupancy', 97.8, null, 'https://www.arifhabibdolmenreit.com/dolmen-city-reit-2-3/'],
        ['Dolmen Mall, Clifton', 'operational_status', null, 'operational', 'https://www.arifhabibdolmenreit.com/wp-content/uploads/2026/10/Quarterly%20Report%20%E2%80%93%20September%2030,%202026.pdf'],
        ['The Harbour Front', 'occupancy', 100.0, null, 'https://www.arifhabibdolmenreit.com/dolmen-city-reit-2-3/'],
        ['The Harbour Front', 'operational_status', null, 'operational', 'https://www.arifhabibdolmenreit.com/wp-content/uploads/2026/10/Quarterly%20Report%20%E2%80%93%20September%2030,%202026.pdf'],
        ['Zameen Opal', 'operational_status', null, 'operational', 'https://www.zameen.com/blog/zameen-opals-possession-handover-ceremony-1537.html'],
        ['Jubilee Town', 'occupancy', 65.0, null, 'https://lda.gop.pk/regular-schemes'],
        ['Lda Avenue-I', 'occupancy', 40.0, null, 'https://lda.gop.pk/regular-schemes'],
    ];

    /** Real legal records whose reference was replaced on 7 Nov: project => [reference, source_url, status before]. */
    public const LEGAL_ORIGINALS = [
        'Shah Rukne Alam Phase-I' => ['MDA Multan list entry 38f7f2b360dbbf09bc442d73d85d7277d2b3eae172bddcb0467bd97095931441', 'https://mda.punjab.gov.pk/housing-schemes#38f7f2b360dbbf09bc442d73d85d7277d2b3eae172bddcb0467bd97095931441', 'verified'],
        'Shah Rukne Alam Phase-II' => ['MDA Multan list entry 5283d2f1ded6db9574dab032a639fcbcfc1954047fb89a63a96c2101c1ae4436', 'https://mda.punjab.gov.pk/housing-schemes#5283d2f1ded6db9574dab032a639fcbcfc1954047fb89a63a96c2101c1ae4436', 'verified'],
        'Ali Town' => ['QDA list entry ali-town-western-bypass-near-hazara-town', 'https://qda.gob.pk/img/scheme_lists/approved%20scheme-2024.pdf#ali-town-western-bypass-near-hazara-town', 'unverified'],
        'Garden Town' => ['QDA list entry garden-town-chashma-achozai-quetta', 'https://qda.gob.pk/img/scheme_lists/approved%20scheme-2024.pdf#garden-town-chashma-achozai-quetta', 'unverified'],
        'Quetta Avenue' => ['QDA list entry quetta-avenue-spinny-road-quetta', 'https://qda.gob.pk/img/scheme_lists/approved%20scheme-2024.pdf#quetta-avenue-spinny-road-quetta', 'unverified'],
        'Sitara Sapna City' => ['FDA list entry sitara-sapna-city-chak-no123jb', 'https://fda.gov.pk/schemes_private#sitara-sapna-city-chak-no123jb', 'unverified'],
        'Al barkat Villas' => ['FDA list entry al-barkat-villas-chak-no-226rb-faisalabad', 'https://fda.gov.pk/schemes_private#al-barkat-villas-chak-no-226rb-faisalabad', 'unverified'],
        'Sitara Gold City' => ['FDA list entry sitara-gold-city-chak-no-215rb', 'https://fda.gov.pk/schemes_private#sitara-gold-city-chak-no-215rb', 'unverified'],
        'Sheikh Yaseen Town' => ['PDA list entry sheikh-yaseen-town-nasir-bagh-road-peshawar', 'https://www.pda.kp.gov.pk/illegalsocieties#sheikh-yaseen-town-nasir-bagh-road-peshawar', 'unverified'],
        'Shaheen Housing Scheme' => ['PDA list entry shaheen-housing-scheme-warsak-road-peshawar', 'https://www.pda.kp.gov.pk/illegalsocieties#shaheen-housing-scheme-warsak-road-peshawar', 'unverified'],
        'Tayyab City Plaza' => ['SBCA/DD(PS&C)/106/Adv-139/2022', 'https://sbca.gos.pk/home/public_sale_projects#tayyab-city-plaza-213-survey-no1718-deh-drigh-road-shah-faisal-district-korangi-karachi', 'unverified'],
        'Sadiq Trade Centre' => ['SBCA/DD(PSA&C)/163/Adv-169/2022', 'https://sbca.gos.pk/home/public_sale_projects#sadiq-trade-centre-101-block-a-sindhi-muslim-chs-karachi', 'unverified'],
        'Balad Trade Centre-II' => ['SBCA/DD(D-II)/1529/ADV-982/2020', 'https://sbca.gos.pk/home/public_sale_projects#balad-trade-centre-ii-114-bahadur-yar-jung-chs-gulshan-town-ii-karachi', 'unverified'],
        'Naya Nazimabad Com-33' => ['SBCA/DD(PS&C)/58/Adv-98/2022', 'https://sbca.gos.pk/home/public_sale_projects#naya-nazimabad-com-33-com-33-survey-no260-naya-nazimabad-scheme-43-deh-manghopir-gadap-town-karachi', 'unverified'],
        'Naya Nazimabad Com-35' => ['SBCA/DD(PS&C)/59/Adv-99/2022', 'https://sbca.gos.pk/home/public_sale_projects#naya-nazimabad-com-35-com-35-survey-no34-naya-nazimabad-scheme-43-deh-manghopir-gadap-town-karachi', 'unverified'],
        'Country Finance Tower' => ['SBCA/AD-South(Design)/26/2021/ADV-39', 'https://sbca.gos.pk/home/public_sale_projects#country-finance-tower-ca-01-block-04-clifton-karachi', 'unverified'],
    ];

    /** Invented reference format used on 7 Nov, e.g. MDA/TP/HOU-1988/001, SBCA/GUL/COM-2019/088, LDA/BC/APT-2019/302. */
    public const FAKE_REFERENCE = '#^(MDA|QDA|FDA|PDA|LDA|CDA|SBCA)/[A-Z-]+(/[A-Z]+)?/?[A-Z]+-\d{4}/\d+$#';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $fakeSources = DB::table('market_sources')->whereIn('url', self::FAKE_SOURCE_URLS)->pluck('id')->all();
        $projectIds = fn (array $names) => DB::table('projects')->whereIn('name', $names)->pluck('id', 'name');

        // 1. Real evidence that was edited: put back what was read.
        $restoreEvidence = [];
        foreach (self::EVIDENCE_ORIGINALS as [$name, $factor, $numeric, $level, $url]) {
            $sourceId = DB::table('market_sources')->where('url', strtok($url, '#'))->value('id')
                ?? DB::table('investment_evidence')->where('source_url', $url)->whereNotIn('source_id', $fakeSources ?: [0])->value('source_id');
            $row = DB::table('investment_evidence')->join('projects', 'projects.id', '=', 'investment_evidence.project_id')
                ->where('projects.name', $name)->where('investment_evidence.factor', $factor)->whereIn('investment_evidence.source_id', $fakeSources ?: [0])
                ->select('investment_evidence.id')->first();
            if ($row && $sourceId) {
                $restoreEvidence[$row->id] = ['source_id' => $sourceId, 'source_url' => $url, 'value_numeric' => $numeric, 'value_level' => $level,
                    'verification_status' => 'unverified', 'verification_method' => 'awaiting_staff_review', 'verified_at' => null, 'verified_by' => null];
            }
        }

        // 2. Invented evidence: fake sources, or added on 7 Nov with no excerpt and no collector.
        $fakeEvidence = DB::table('investment_evidence')->whereNotIn('id', array_keys($restoreEvidence) ?: [0])->where('verification_status', '!=', 'rejected')
            ->where(fn ($q) => $q->whereIn('source_id', $fakeSources ?: [0])
                ->orWhere(fn ($q) => $q->whereDate('created_at', self::DAY)->whereNull('excerpt')->whereNull('collector')))
            ->pluck('id')->all();

        // 3. Real legal records whose reference was replaced.
        $restoreLegal = [];
        foreach ($projectIds(array_keys(self::LEGAL_ORIGINALS)) as $name => $id) {
            [$reference, $url, $status] = self::LEGAL_ORIGINALS[$name];
            $row = DB::table('investment_legal_records')->where('project_id', $id)->where(fn ($q) => $q->where('source_url', $url)->orWhere('reference', '!=', $reference))
                ->get(['id', 'reference'])->first(fn ($r) => preg_match(self::FAKE_REFERENCE, (string) $r->reference));
            if ($row) {
                $restoreLegal[$row->id] = ['reference' => $reference, 'source_url' => $url, 'verification_status' => $status,
                    'verification_method' => $status === 'verified' ? 'official_registry' : 'awaiting_staff_review'] + ($status === 'verified' ? [] : ['verified_at' => null, 'verified_by' => null]);
            }
        }

        // 4. Invented legal records: added on 7 Nov with no excerpt and no collector (every real record has one).
        $fakeLegal = DB::table('investment_legal_records')->whereDate('created_at', self::DAY)->whereNotIn('id', array_keys($restoreLegal) ?: [0])
            ->where('verification_status', '!=', 'rejected')->whereNull('excerpt')->whereNull('collector')->pluck('id')->all();

        // 5. Invented projects (created on 7 Nov, by name).
        $fakeProjects = DB::table('projects')->whereIn('name', self::FAKE_PROJECTS)->whereDate('created_at', self::DAY)->where('is_published', true)->pluck('name', 'id')->all();

        $this->table(['Change', 'Records'], [
            ['Edited real evidence put back as read (awaiting review)', count($restoreEvidence)],
            ['Invented evidence rejected', count($fakeEvidence)],
            ['Edited real legal records put back as read', count($restoreLegal)],
            ['Invented legal records rejected', count($fakeLegal)],
            ['Invented projects unpublished', count($fakeProjects)],
            ['Non-existent sources switched off', DB::table('market_sources')->whereIn('id', $fakeSources ?: [0])->where('is_active', true)->count()],
        ]);
        if ($fakeProjects) {
            $this->line('Projects: '.implode(', ', $fakeProjects));
        }

        if (! $commit) {
            $this->warn('Dry run: nothing changed. Run with --commit to apply, then: php artisan meezan:investment-index run');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($restoreEvidence, $fakeEvidence, $restoreLegal, $fakeLegal, $fakeProjects, $fakeSources) {
            $now = now();
            foreach ($restoreEvidence as $id => $values) {
                DB::table('investment_evidence')->where('id', $id)->update($values + ['updated_at' => $now]);
            }
            foreach (array_chunk($fakeEvidence, 500) as $ids) {
                DB::table('investment_evidence')->whereIn('id', $ids)->update(['verification_status' => 'rejected', 'rejection_reason' => self::REASON, 'verified_at' => null, 'verified_by' => null, 'updated_at' => $now]);
            }
            foreach ($restoreLegal as $id => $values) {
                DB::table('investment_legal_records')->where('id', $id)->update($values + ['updated_at' => $now]);
            }
            foreach (array_chunk($fakeLegal, 500) as $ids) {
                DB::table('investment_legal_records')->whereIn('id', $ids)->update(['verification_status' => 'rejected', 'rejection_reason' => self::REASON, 'verified_at' => null, 'verified_by' => null, 'updated_at' => $now]);
            }
            DB::table('projects')->whereIn('id', array_keys($fakeProjects) ?: [0])->update(['is_published' => false, 'updated_at' => $now]);
            DB::table('market_sources')->whereIn('id', $fakeSources ?: [0])->update(['is_active' => false, 'notes' => self::REASON, 'updated_at' => $now]);

            AuditLogger::record('investment.fabricated_evidence_withdrawn', null, [
                'evidence_restored' => array_keys($restoreEvidence), 'evidence_rejected' => count($fakeEvidence),
                'legal_restored' => array_keys($restoreLegal), 'legal_rejected' => count($fakeLegal),
                'projects_unpublished' => array_values($fakeProjects), 'sources_disabled' => $fakeSources,
            ]);
            foreach ($fakeProjects as $id => $name) {
                if ($project = Project::find($id)) {
                    AuditLogger::record('project.unpublished', $project, ['reason' => self::REASON]);
                }
            }
        });

        $this->info('Done. Now run: php artisan meezan:investment-index run');

        return self::SUCCESS;
    }
}
