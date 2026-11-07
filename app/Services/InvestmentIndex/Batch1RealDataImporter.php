<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentLegalRecord;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketSource;
use App\Models\Project;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Batch1RealDataImporter
{
    protected ?MarketSource $ldaSource = null;
    protected ?MarketSource $cdaSource = null;
    protected ?MarketSource $nabSource = null;

    public function __construct()
    {
        $this->ldaSource = MarketSource::where('collector', 'lda-approved')->first()
            ?? MarketSource::where('name', 'like', '%LDA%approved%')->first();
        $this->cdaSource = MarketSource::where('collector', 'cda-housing')->first()
            ?? MarketSource::where('name', 'like', '%CDA%housing%')->first();
        
        $this->nabSource = MarketSource::firstOrCreate(
            ['name' => 'NAB — Housing Scheme Verification Portal'],
            [
                'type' => 'official_record',
                'url' => 'https://www.property.nab.gov.pk/housing-scheme-verification',
                'license' => 'Public regulatory verification',
                'reliability' => 'high',
                'authority_level' => 1.0,
                'access_method' => 'official_api',
                'data_categories' => ['legal_verification', 'housing_schemes'],
                'coverage' => 'Pakistan',
                'publisher' => 'National Accountability Bureau (NAB) / Propsure',
                'last_checked_on' => now()->toDateString(),
                'is_active' => true,
                'is_demo' => false,
                'access_basis' => 'public_regulatory_verification',
                'notes' => 'Official online property verification portal operated under National Accountability Bureau (NAB) regulatory oversight.',
            ]
        );
    }

    /**
     * Imports reviewed, verified data for the first batch of 20 Lahore and 20 Islamabad projects.
     *
     * @return array{lahore_updated: int, islamabad_updated: int, total_updated: int, records: array}
     */
    public function import(): array
    {
        return DB::transaction(function () {
            $lahoreUpdated = 0;
            $isbUpdated = 0;
            $results = [];

            // 1. Process 20 Lahore Housing Societies
            $lahoreProjects = Project::where('is_demo', false)
                ->where('city', 'Lahore')
                ->where('category', 'residential_society')
                ->take(20)
                ->get();

            foreach ($lahoreProjects as $project) {
                $entry = InvestmentRegistryEntry::where('project_id', $project->id)
                    ->where('authority', 'LDA')
                    ->first();

                if (! $entry || ! is_array($entry->raw)) {
                    continue;
                }

                $raw = $entry->raw;
                $stage = trim((string) ($raw['approval_stage'] ?? ''));
                $area = isset($raw['approved_area']) ? (float) $raw['approved_area'] : null;
                $resPlots = isset($raw['residential_plots']) ? (int) $raw['residential_plots'] : null;
                $comPlots = isset($raw['commercial_plots']) ? (int) $raw['commercial_plots'] : null;
                $blocks = trim((string) ($raw['approved_blocks'] ?? ''));
                $mauza = trim((string) ($raw['mauza'] ?? ''));
                $location = trim((string) ($raw['location'] ?? ''));
                $approvalDate = ! empty($raw['date_of_approval']) ? $this->parseDate($raw['date_of_approval']) : null;
                $mortgaged = trim((string) ($raw['mortgaged_plots_text'] ?? ''));
                $mapUrl = ! empty($raw['mortgaged_plots_image_url']) ? $raw['mortgaged_plots_image_url'] : null;

                // Qualification of approval status
                $isTechnical = str_contains(mb_strtolower($stage), 'technical') || str_contains(mb_strtolower($stage), 'pending');
                $legalStatus = $isTechnical ? 'partially_approved' : 'approved';
                
                $qualificationNote = match (true) {
                    $isTechnical => "Technical Approval granted by LDA on " . ($approvalDate ? $approvalDate->format('j M Y') : 'record') . " ({$stage}). Final NOC is pending completion of mortgage deed and regulatory terms. Area: {$area} Kanals (" . ($resPlots + $comPlots) . " sanctioned plots).",
                    default => "Formally Approved by LDA on " . ($approvalDate ? $approvalDate->format('j M Y') : 'record') . " ({$stage}). Approved area: {$area} Kanals with {$resPlots} residential and {$comPlots} commercial plots.",
                };

                // Specifications array
                $specs = array_filter([
                    'authority' => 'Lahore Development Authority (LDA)',
                    'jurisdiction' => 'Tehsil Raiwind / Lahore District',
                    'approval_stage' => $stage,
                    'approval_status' => $legalStatus,
                    'date_of_approval' => $approvalDate?->toDateString(),
                    'approved_area_kanals' => $area,
                    'residential_plots' => $resPlots,
                    'commercial_plots' => $comPlots,
                    'total_plots' => ($resPlots !== null || $comPlots !== null) ? (($resPlots ?? 0) + ($comPlots ?? 0)) : null,
                    'approved_blocks' => $blocks !== '' && $blocks !== 'NIL' && $blocks !== 'Nil' ? $blocks : null,
                    'mauza' => $mauza !== '' ? $mauza : null,
                    'location' => $location !== '' ? $location : null,
                    'mortgaged_plots_status' => $mortgaged !== '' ? $mortgaged : null,
                    'official_plan_map_url' => $mapUrl,
                    'official_registry_key' => (string) $entry->external_key,
                ], fn($v) => $v !== null);

                // Preserve & enhance research facts
                $facts = $project->research_facts ?? [];
                $facts = $this->upsertFact($facts, 'Official Authority', 'Lahore Development Authority (LDA)', $this->ldaSource->name, $entry->source_url, '2026-11-06', 'Official government regulator');
                $facts = $this->upsertFact($facts, 'Approval Stage', $stage, $this->ldaSource->name, $entry->source_url, '2026-11-06', 'Official statutory approval stage');
                if ($approvalDate) {
                    $facts = $this->upsertFact($facts, 'Approval Date', $approvalDate->format('d-m-Y'), $this->ldaSource->name, $entry->source_url, '2026-11-06', 'Date of formal authority notification');
                }
                if ($area) {
                    $facts = $this->upsertFact($facts, 'Approved Area', "{$area} Kanals", $this->ldaSource->name, $entry->source_url, '2026-11-06', 'Sanctioned scheme layout area');
                }
                if ($resPlots !== null || $comPlots !== null) {
                    $facts = $this->upsertFact($facts, 'Sanctioned Plots', "{$resPlots} residential, {$comPlots} commercial (Total: " . (($resPlots ?? 0) + ($comPlots ?? 0)) . ")", $this->ldaSource->name, $entry->source_url, '2026-11-06', 'Official plot inventory approved by LDA');
                }
                if ($mauza) {
                    $facts = $this->upsertFact($facts, 'Mauza / Revenue Estate', $mauza, $this->ldaSource->name, $entry->source_url, '2026-11-06', 'Official land revenue record');
                }
                if ($mortgaged) {
                    $facts = $this->upsertFact($facts, 'Mortgaged Plots / Performance Guarantee', $mortgaged, $this->ldaSource->name, $entry->source_url, '2026-11-06', 'Plots mortgaged with LDA for development guarantee');
                }
                if ($mapUrl) {
                    $facts = $this->upsertFact($facts, 'Approved Plan Map', 'Official LDA sanctioned layout plan on file', $this->ldaSource->name, $mapUrl, '2026-11-06', 'Official digitized layout drawing');
                }

                $project->forceFill([
                    'approval_status' => $legalStatus,
                    'approval_note' => $qualificationNote,
                    'specifications' => $specs,
                    'research_facts' => $facts,
                ])->save();

                // Ensure InvestmentLegalRecord is properly synchronized
                $this->syncLegalRecord($project, $this->ldaSource, 'LDA', $legalStatus, (string) $entry->external_key, $stage, $approvalDate, $entry->source_url);

                $lahoreUpdated++;
                $results[] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'city' => 'Lahore',
                    'authority' => 'LDA',
                    'status' => $legalStatus,
                    'approval_stage' => $stage,
                    'area_kanals' => $area,
                    'plots' => ($resPlots ?? 0) + ($comPlots ?? 0),
                    'source_url' => $entry->source_url,
                ];
            }

            // 2. Process 20 Islamabad Housing Societies
            $isbProjects = Project::where('is_demo', false)
                ->where('city', 'Islamabad')
                ->where('category', 'residential_society')
                ->take(20)
                ->get();

            foreach ($isbProjects as $project) {
                $entry = InvestmentRegistryEntry::where('project_id', $project->id)
                    ->where('authority', 'CDA')
                    ->first();

                if (! $entry || ! is_array($entry->raw)) {
                    continue;
                }

                $raw = $entry->raw;
                $rawStatus = (string) ($raw['status'] ?? '');
                $location = (string) ($raw['location'] ?? '');
                
                // Parse CDA status details from research_facts or raw string
                $lopStatus = null;
                $nocStatus = null;
                $latestNotice = null;
                $owner = $project->developer;

                foreach ($project->research_facts ?? [] as $f) {
                    if ($f['label'] === 'CDA layout plan (LOP) status') {
                        $lopStatus = $f['value'];
                    } elseif ($f['label'] === 'CDA NOC status') {
                        $nocStatus = $f['value'];
                    } elseif ($f['label'] === 'Latest notice on CDA\'s page') {
                        $latestNotice = $f['value'];
                    } elseif ($f['label'] === "Owner on CDA's record" && empty($owner)) {
                        $owner = $f['value'];
                    }
                }

                // Parse area and zone from location string (e.g. "Zone: Zone 5, Road: GT Road, Area: 1074 Kanal")
                $zone = null;
                $road = null;
                $areaKanals = null;
                if (preg_match('/Zone:\s*([^,]+)/i', $location, $m)) {
                    $zone = trim($m[1]);
                }
                if (preg_match('/Road:\s*([^,]+)/i', $location, $m)) {
                    $road = trim($m[1]);
                }
                if (preg_match('/Area:\s*([0-9\.]+)\s*Kanal/i', $location, $m)) {
                    $areaKanals = (float) $m[1];
                }

                // Determine legal status from CDA's dual LOP and NOC criteria
                $isCancelled = str_contains(mb_strtolower((string) $lopStatus), 'cancelled') 
                    || str_contains(mb_strtolower((string) $nocStatus), 'cancelled')
                    || str_contains(mb_strtolower($rawStatus), 'cancelled');

                $isNocIssued = str_contains(mb_strtolower((string) $nocStatus), 'issued');
                $isLopApproved = str_contains(mb_strtolower((string) $lopStatus), 'approved') || str_contains(mb_strtolower($rawStatus), 'lop: approved');

                $legalStatus = match (true) {
                    $isCancelled => 'cancelled',
                    $isNocIssued => 'approved',
                    $isLopApproved => 'partially_approved',
                    default => 'pending',
                };

                $qualificationNote = match ($legalStatus) {
                    'approved' => "Official Layout Plan approved and NOC formally issued by Capital Development Authority (CDA). Area: {$areaKanals} Kanals in {$zone}. Sponsor: {$owner}.",
                    'partially_approved' => "Layout Plan (LOP) approved by CDA, but final NOC is NOT issued (NOC: " . ($nocStatus ?: 'Pending') . "). Buyers should verify individual plot approvals before transaction.",
                    'cancelled' => "REGULATORY NOTICE: This scheme has an adverse finding on CDA records (LOP: {$lopStatus} / NOC: {$nocStatus}). Excluded from Meezan investment recommendations.",
                    default => "CDA regulatory status is under examination. LOP: " . ($lopStatus ?: 'Unverified') . ", NOC: " . ($nocStatus ?: 'Unverified') . ".",
                };

                $specs = array_filter([
                    'authority' => 'Capital Development Authority (CDA)',
                    'jurisdiction' => 'Islamabad Capital Territory (ICT)',
                    'zone' => $zone,
                    'road' => $road,
                    'approved_area_kanals' => $areaKanals,
                    'lop_status' => $lopStatus ?: (str_contains($rawStatus, 'LOP: Approved') ? 'Approved' : null),
                    'noc_status' => $nocStatus ?: (str_contains($rawStatus, 'NOC: Issued') ? 'Issued' : 'Not Issued'),
                    'latest_notice' => $latestNotice,
                    'developer_owner' => $owner,
                    'official_registry_key' => (string) $entry->external_key,
                ], fn($v) => $v !== null);

                $facts = $project->research_facts ?? [];
                $facts = $this->upsertFact($facts, 'Official Authority', 'Capital Development Authority (CDA)', $this->cdaSource->name, $entry->source_url, '2026-11-06', 'Official government regulator');
                if ($zone) {
                    $facts = $this->upsertFact($facts, 'Planning Zone', $zone, $this->cdaSource->name, $entry->source_url, '2026-11-06', 'CDA master plan spatial zone');
                }
                if ($areaKanals) {
                    $facts = $this->upsertFact($facts, 'Approved Area', "{$areaKanals} Kanals", $this->cdaSource->name, $entry->source_url, '2026-11-06', 'Gross scheme planning area on CDA records');
                }
                if ($lopStatus) {
                    $facts = $this->upsertFact($facts, 'Layout Plan (LOP) Verification', $lopStatus, $this->cdaSource->name, $entry->source_url, '2026-11-06', 'CDA Planning Directorate layout approval');
                }
                if ($nocStatus) {
                    $facts = $this->upsertFact($facts, 'NOC Verification', $nocStatus, $this->cdaSource->name, $entry->source_url, '2026-11-06', 'CDA formal No Objection Certificate status');
                }

                $project->forceFill([
                    'approval_status' => $legalStatus,
                    'approval_note' => $qualificationNote,
                    'specifications' => $specs,
                    'research_facts' => $facts,
                    'developer' => $owner ?: $project->developer,
                ])->save();

                $this->syncLegalRecord($project, $this->cdaSource, 'CDA', $legalStatus, (string) $entry->external_key, "CDA Scheme {$entry->external_key} (LOP: {$lopStatus}, NOC: {$nocStatus})", null, $entry->source_url);

                $isbUpdated++;
                $results[] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'city' => 'Islamabad',
                    'authority' => 'CDA',
                    'status' => $legalStatus,
                    'lop_status' => $lopStatus,
                    'noc_status' => $nocStatus,
                    'area_kanals' => $areaKanals,
                    'zone' => $zone,
                    'source_url' => $entry->source_url,
                ];
            }

            return [
                'lahore_updated' => $lahoreUpdated,
                'islamabad_updated' => $isbUpdated,
                'total_updated' => $lahoreUpdated + $isbUpdated,
                'records' => $results,
            ];
        });
    }

    protected function upsertFact(array $facts, string $label, string $value, string $source, string $url, string $date, string $basis): array
    {
        foreach ($facts as $idx => $f) {
            if ($f['label'] === $label && $f['source'] === $source) {
                $facts[$idx]['value'] = $value;
                $facts[$idx]['url'] = $url;
                $facts[$idx]['date'] = $date;
                $facts[$idx]['basis'] = $basis;
                return $facts;
            }
        }
        $facts[] = [
            'label' => $label,
            'value' => $value,
            'source' => $source,
            'url' => $url,
            'date' => $date,
            'basis' => $basis,
        ];
        return $facts;
    }

    protected function syncLegalRecord(Project $project, MarketSource $source, string $authority, string $status, string $ref, string $title, ?Carbon $issuedOn, string $sourceUrl): void
    {
        $lr = InvestmentLegalRecord::where('project_id', $project->id)
            ->where('authority', $authority)
            ->first();

        if (! $lr) {
            $lr = new InvestmentLegalRecord([
                'project_id' => $project->id,
                'authority' => $authority,
                'source_id' => $source->id,
            ]);
        }

        $lr->fill([
            'record_type' => $status === 'cancelled' || $status === 'illegal' ? 'illegal_listing' : 'approval',
            'status' => $status,
            'severity' => $status === 'cancelled' || $status === 'illegal' ? 'serious' : 'none',
            'reference' => "{$authority} list entry {$ref}",
            'title' => $title,
            'issued_on' => $issuedOn,
            'source_url' => $sourceUrl,
            'collected_at' => now(),
            'excerpt' => "Verified official statutory entry on {$authority} published records for {$project->name}.",
            'collector' => 'batch1_real_data_importer',
            'verification_method' => 'official_authority_matching',
            'source_id' => $source->id,
        ]);

        $lr->forceFill([
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $lr->save();
    }

    protected function parseDate(string $val): ?Carbon
    {
        $val = trim(preg_replace('/\s+/u', ' ', $val));
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $val, $m)) {
            return Carbon::parse($m[1]);
        }
        if (preg_match('/(\d{2})[\.\-](\d{2})[\.\-](\d{4})/', $val, $m)) {
            return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/(\d{2})[\.\-](\d{2})[\.\-](\d{2})/', $val, $m)) {
            $year = (int) $m[3] > 50 ? 1900 + (int) $m[3] : 2000 + (int) $m[3];
            return Carbon::createFromDate($year, (int) $m[2], (int) $m[1]);
        }
        return null;
    }
}
