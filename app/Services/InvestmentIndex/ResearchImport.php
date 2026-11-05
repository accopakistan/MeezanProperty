<?php

namespace App\Services\InvestmentIndex;

use App\Models\InvestmentEvidence;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Services\AuditLogger;
use Carbon\Carbon;
use RuntimeException;

/**
 * Loads hand-read research into the existing tables. Re-running it changes
 * nothing already there. It never verifies anything: evidence arrives
 * unverified and FBR valuations arrive as "submitted", so a person decides
 * what counts. FBR values are tax values, kept as their own price basis so they
 * reach no score.
 */
class ResearchImport
{
    public const VALUATION_BASIS = 'fbr_tax';

    /** @var array<string, MarketSource> */
    protected array $sources = [];

    public function __construct(protected ProjectDiscovery $discovery) {}

    /** @return array{sources: int, projects: int, evidence: int, facts: int, official_facts: int, valuations: int, portal_projects: int, asking: int, cda_details: int} */
    public function run(?string $evidenceFile = null, ?string $valuationFile = null): array
    {
        $data = require $evidenceFile ?? database_path('data/investment/real_world_evidence.php');
        $totals = ['sources' => 0, 'projects' => 0, 'evidence' => 0, 'facts' => 0, 'official_facts' => 0, 'valuations' => 0];

        foreach ($data['sources'] as $key => $def) {
            $this->sources[$key] = $this->source($def);
            $totals['sources']++;
        }
        foreach ($data['projects'] as $def) {
            $source = $this->sources[$def['source']];
            $result = $this->discovery->ensure([
                'name' => $def['name'], 'city' => $def['city'], 'category' => $def['category'], 'status' => $def['status'] ?? 'unverified', 'developer' => $def['developer'] ?? null,
                'website' => $def['website'] ?? null, 'description' => $def['description'], 'url' => $source->url, 'read_on' => '2026-11-05', 'category_basis' => 'stated',
            ], $source);
            $totals['projects'] += $result['created'] ? 1 : 0;
        }
        foreach ($data['evidence'] as $def) {
            $totals['evidence'] += $this->evidence($def) ? 1 : 0;
        }
        foreach ($data['facts'] as $def) {
            $totals['facts'] += $this->facts($def) ? 1 : 0;
        }
        $totals['official_facts'] = $this->officialFacts();
        $totals['evidence'] += $this->colonized();
        $totals['valuations'] = $this->valuations($valuationFile ?? database_path('data/investment/official_valuations.php'));

        return $totals + $this->marketResearch() + ['cda_details' => $this->cdaDetails()];
    }

    /** Projects advertised on portal pages robots.txt allows, large societies on no list read, and asking-based figures. @return array{portal_projects: int, asking: int} */
    protected function marketResearch(): array
    {
        $file = database_path('data/investment/market_research.php');
        if (! is_file($file)) {
            return ['portal_projects' => 0, 'asking' => 0];
        }
        $data = require $file;
        foreach ($data['sources'] as $key => $def) {
            $this->sources[$key] = $this->source($def);
        }
        $created = 0;
        $portal = $this->sources['zameen-new'];
        foreach ($data['portal_projects'] as $line) {
            [$name, $city, $category, $location, $type, $price] = explode('|', $line);
            $result = $this->discovery->ensure(['name' => $name, 'city' => $city, 'category' => $category, 'status' => 'unverified', 'url' => $portal->url, 'read_on' => '2026-11-05', 'category_basis' => 'inferred',
                'description' => "{$name} is advertised on Zameen's New Projects pages at {$location}. A marketing listing shows that a project is advertised, not that it is approved, built or sold."], $portal);
            $created += $result['created'] ? 1 : 0;
            $this->addFact($result['project'], 'Location advertised', $location, $portal->name, $portal->url, '2026-11-05', $portal->typeLabel());
            $this->addFact($result['project'], 'Types advertised', $type, $portal->name, $portal->url, '2026-11-05', $portal->typeLabel());
            if ($price !== 'Contact for price') {
                $this->addFact($result['project'], 'Advertised price, not a sale', $price, $portal->name, $portal->url, '2026-11-05', $portal->typeLabel());
            }
        }
        foreach ($data['societies'] as $def) {
            $source = $this->sources[$def['source']];
            $result = $this->discovery->ensure(['name' => $def['name'], 'city' => $def['city'], 'category' => 'residential_society', 'status' => 'unverified', 'developer' => $def['developer'] ?? null, 'url' => $source->url, 'read_on' => '2026-11-05', 'category_basis' => 'stated',
                'description' => "{$def['name']} is a large development in {$def['city']}. It is on no authority list read so far, so its legal status is not verified here."], $source);
            $created += $result['created'] ? 1 : 0;
            if ($def['instrument'] ?? null) {
                $this->addFact($result['project'], 'Legal instrument as reported by a secondary source', $def['instrument'].'. The instrument itself was not read.', $source->name, $source->url, '2026-11-05', $source->typeLabel());
            }
        }

        $asking = 0;
        foreach ($data['index'] as $row) {
            $project = $this->project($row['project'], $row['city']);
            $index = $this->sources['zameen-index'];
            foreach (['Average advertised house price, Sep 2026' => $row['avg'], '6 months earlier' => $row['six'], '1 year earlier' => $row['one'], '2 years earlier' => $row['two']] as $label => $value) {
                $this->addFact($project, $label, $value.' on the Zameen Index. Asking-based, not sales.', $index->name, $row['url'], '2026-09-30', $index->typeLabel());
            }
            foreach ($row['sizes'] as [$size, $unit, $amount]) {
                $observation = new MarketObservation(['kind' => 'sale', 'price_basis' => 'asking', 'country' => 'Pakistan', 'city' => $row['city'], 'project_id' => $project->id, 'asset_class' => 'residential', 'property_type' => 'house',
                    'size_value' => $size, 'size_unit' => $unit, 'currency' => 'PKR', 'sample_size' => 1, 'average_amount' => $amount, 'observed_on' => '2026-09-30', 'source_id' => $index->id,
                    'methodology' => 'Average for this size on the Zameen Index, read by hand from '.$row['url'].'. Asking-based: the page does not state its method. Not a sale.']);
                $observation->forceFill(['status' => 'submitted', 'is_demo' => false, 'submitted_at' => now()]);
                $observation->fingerprint = hash('sha256', implode('|', ['zameen-index', $project->id, $size, $unit, $amount]));
                if (MarketObservation::where('fingerprint', $observation->fingerprint)->exists()) {
                    continue;
                }
                $observation->saveQuietly();
                $asking++;
            }
        }

        return ['portal_projects' => $created, 'asking' => $asking];
    }

    /** Owner and dated approvals from CDA's own scheme pages. Fills a developer only where none is recorded. */
    protected function cdaDetails(): int
    {
        $file = database_path('data/investment/cda_scheme_details.php');
        $source = MarketSource::where('collector', 'cda-housing')->first();
        if (! is_file($file) || ! $source) {
            return 0;
        }
        $count = 0;
        foreach (require $file as $row) {
            $entry = InvestmentRegistryEntry::where('source_id', $source->id)->where('scheme_name', $row['name'])->whereNotNull('project_id')->first();
            $project = $entry?->project_id ? Project::where('is_demo', false)->find($entry->project_id) : null;
            if (! $project) {
                continue;
            }
            $url = $source->url;
            $changed = false;
            foreach (['Owner on CDA\'s record' => $row['owner'], 'CDA layout plan (LOP) status' => $row['lop_date'], 'CDA NOC status' => $row['noc_date'], 'Latest notice on CDA\'s page' => $row['latest_notice']] as $label => $value) {
                if ($value) {
                    $changed = $this->addFact($project, $label, $value, $source->name, $url, '2026-11-05', $source->typeLabel()) || $changed;
                }
            }
            if ($row['owner'] && blank($project->developer)) {
                $project->forceFill(['developer' => $row['owner']])->save();
                $changed = true;
            }
            $count += $changed ? 1 : 0;
        }

        return $count;
    }

    protected function project(string $name, string $city): Project
    {
        return Project::where('is_demo', false)->where('name', $name)->where('city', $city)->first()
            ?? throw new RuntimeException("No real project \"{$name}\" in {$city}.");
    }

    /** @param array<string, mixed> $def */
    protected function source(array $def): MarketSource
    {
        $source = MarketSource::firstOrNew(['name' => $def['name']]);
        $source->fill(['type' => $def['type'], 'url' => $def['url'], 'publisher' => $def['publisher'], 'reliability' => $def['type'] === 'official_record' ? 'high' : 'medium',
            'coverage' => $def['city'], 'last_checked_on' => $def['read_on'] ?? '2026-11-05']);
        $source->forceFill(['is_active' => true, 'is_demo' => false, 'authority_level' => $def['level'], 'access_method' => $def['method'], 'access_note' => $def['access'],
            'data_categories' => [$def['category']], 'last_success_at' => '2026-11-05', 'last_collected_at' => '2026-11-05'])->save();

        return $source;
    }

    /** @param array<string, mixed> $def */
    protected function evidence(array $def): bool
    {
        return $this->store($this->project($def['project'], $def['city']), $def['factor'], $this->sources[$def['source']], $def['on'], $def['excerpt'],
            $def['percent'] ?? null, $def['level'] ?? null, isset($def['percent']) ? 'percent' : null, 'manual_research');
    }

    protected function store(Project $project, string $factor, MarketSource $source, string $on, string $excerpt, ?float $percent, ?string $level, ?string $unit, string $collector): bool
    {
        $evidence = new InvestmentEvidence(['project_id' => $project->id, 'factor' => $factor, 'value_numeric' => $percent, 'value_level' => $level, 'unit' => $unit, 'observed_on' => $on,
            'source_id' => $source->id, 'source_url' => $source->url, 'collected_at' => '2026-11-05', 'excerpt' => mb_substr($excerpt, 0, 2000), 'collector' => $collector,
            'verification_method' => 'awaiting_staff_review']);
        $evidence->forceFill(['verification_status' => 'unverified']);
        $fingerprint = (fn () => $this->fingerprintFor())->call($evidence);
        if (InvestmentEvidence::where('fingerprint', $fingerprint)->exists()) {
            return false;
        }
        $evidence->save();
        AuditLogger::record('investment_index.research_imported', $evidence, ['factor' => $factor, 'project' => $project->id]);

        return true;
    }

    /** @param array<string, mixed> $def */
    protected function facts(array $def): bool
    {
        $source = $this->sources[$def['source']];
        $added = false;
        foreach ($def['items'] as $label => $value) {
            $added = $this->addFact($this->project($def['project'], $def['city']), $label, $value, $source->name, $source->url, $def['date'], $source->typeLabel()) || $added;
        }

        return $added;
    }

    /** A fact is added once and never overwrites anything. */
    protected function addFact(Project $project, string $label, string $value, string $sourceName, ?string $url, string $date, string $basis): bool
    {
        // A matched project may have been loaded without this column, and saving would lose what is on file.
        $facts = Project::whereKey($project->id)->first(['id', 'research_facts'])?->research_facts ?? [];
        if (collect($facts)->contains(fn ($f) => $f['label'] === $label && $f['source'] === $sourceName)) {
            return false;
        }
        $facts[] = ['label' => $label, 'value' => $value, 'source' => $sourceName, 'url' => $url, 'date' => $date, 'basis' => $basis];
        $project->forceFill(['research_facts' => $facts])->save();

        return true;
    }

    /** Figures an official list already publishes for its own schemes. @return int projects given a fact */
    protected function officialFacts(): int
    {
        $count = 0;
        InvestmentRegistryEntry::with(['source:id,name,type', 'project'])->whereNotNull('project_id')->whereIn('match_status', ['matched', 'manual'])->orderBy('id')
            ->chunkById(200, function ($entries) use (&$count) {
                foreach ($entries as $entry) {
                    $added = false;
                    foreach ($this->officialItems($entry) as $label => $value) {
                        if (! $entry->project || $entry->project->is_demo) {
                            continue;
                        }
                        $added = $this->addFact($entry->project, $label, $value, $entry->source?->name ?? $entry->authority, $entry->source_url,
                            ($entry->last_seen_at ?? $entry->first_seen_at ?? now())->toDateString(), $entry->source?->typeLabel() ?? 'Official') || $added;
                    }
                    // A list naming the developer fills a blank. It never overwrites one already recorded.
                    if ($entry->project && ! $entry->project->is_demo && blank($entry->project->developer) && filled($entry->raw['_developer'] ?? null)) {
                        $entry->project->forceFill(['developer' => $entry->raw['_developer']])->save();
                        $added = true;
                    }
                    $count += $added ? 1 : 0;
                }
            });

        return $count;
    }

    /** @return array<string, string> */
    protected function officialItems(InvestmentRegistryEntry $entry): array
    {
        $raw = $entry->raw ?? [];
        $items = [];
        if ($entry->location) {
            $items['Location on the official list'] = mb_substr($entry->location, 0, 200);
        }
        if (filled($raw['approved_area'] ?? null)) {
            $items['Approved area'] = $raw['approved_area'].' (the unit is not stated in LDA\'s published data)';
        }
        if (filled($raw['residential_plots'] ?? null) || filled($raw['commercial_plots'] ?? null)) {
            $items['Approved plots'] = (int) ($raw['residential_plots'] ?? 0).' residential, '.(int) ($raw['commercial_plots'] ?? 0).' commercial';
        }
        if (filled($raw['approved_blocks'] ?? null)) {
            $items['Approved blocks'] = mb_substr((string) $raw['approved_blocks'], 0, 200);
        }
        if (filled($raw['no_of_residential_plots'] ?? null)) {
            $items['Plots'] = (int) $raw['no_of_residential_plots'].' residential, '.(int) ($raw['no_of_commercial_plots'] ?? 0).' commercial';
        }
        // MDA's sanctioned list carries the area as a sixth field on the line.
        $line = explode('|', (string) ($raw['line'] ?? ''));
        if (filled($line[5] ?? null)) {
            $items['Sanctioned area'] = trim($line[5]).' kanal';
        }

        return $items;
    }

    /**
     * LDA publishes a "colonized" percentage for schemes it developed. It looks
     * like occupancy, but LDA does not define it, so staff decide. A published 0%
     * sits beside schemes that are visibly built up, so it is treated as not reported.
     */
    protected function colonized(): int
    {
        $count = 0;
        foreach (InvestmentRegistryEntry::with(['source', 'project'])->whereNotNull('project_id')->where('list_type', 'authority_scheme')->where('authority', 'LDA')->get() as $entry) {
            $value = $entry->raw['colonized'] ?? null;
            if (! is_string($value) || ! preg_match('/^(\d{1,3})\s*%$/', trim($value), $m) || (int) $m[1] === 0 || (int) $m[1] > 100 || ! $entry->project || $entry->project->is_demo) {
                continue;
            }
            $count += $this->store($entry->project, 'occupancy', $entry->source, ($entry->last_seen_at ?? now())->toDateString(),
                "LDA publishes \"colonized: {$value}\" for this scheme. LDA does not define the term; check that it means occupancy before verifying.",
                (float) $m[1], null, 'percent', 'official_list') ? 1 : 0;
        }

        return $count;
    }

    /** FBR rows, as "submitted" records for Market Data review. */
    protected function valuations(string $file): int
    {
        $count = 0;
        foreach (require $file as $row) {
            $project = Project::where('is_demo', false)->where('name', $row['project'])->where('city', 'Lahore')->first();
            if (! $project) {
                continue;
            }
            $source = $this->sources[$row['sro']] ??= $this->source(['name' => 'FBR — '.$row['sro'].' (Lahore immovable property valuation)', 'publisher' => 'Federal Board of Revenue', 'type' => 'official_record', 'level' => 1,
                'url' => $row['url'], 'method' => 'official_pdf', 'city' => 'Lahore', 'category' => 'tax_valuation', 'read_on' => '2026-11-05',
                'access' => 'Public notification on FBR\'s site. Tax values under section 68 of the Income Tax Ordinance, not market prices. Read once from the PDF\'s text.']);
            foreach (['residential' => 'residential_plot', 'commercial' => 'commercial_plot'] as $column => $type) {
                if (! $row[$column]) {
                    continue;
                }
                $observation = new MarketObservation(['kind' => 'sale', 'price_basis' => self::VALUATION_BASIS, 'country' => 'Pakistan', 'city' => 'Lahore', 'project_id' => $project->id,
                    'asset_class' => $column, 'property_type' => $type, 'size_value' => 1, 'size_unit' => 'marla', 'currency' => 'PKR', 'sample_size' => 1,
                    'average_amount' => $row[$column], 'observed_on' => Carbon::parse($row['date']), 'source_id' => $source->id,
                    'methodology' => 'Copied from '.$row['sro'].', row "'.$row['printed'].'". Open plot, per marla. A tax value, not a market price.',
                    'notes' => 'The row was matched to this project by its exact printed name only. Staff should confirm it is the same place.']);
                $observation->forceFill(['status' => 'submitted', 'is_demo' => false, 'submitted_at' => now()]);
                $observation->fingerprint = hash('sha256', implode('|', ['fbr', $project->id, $type, $row['sro'], $row[$column]]));
                if (MarketObservation::where('fingerprint', $observation->fingerprint)->exists()) {
                    continue;
                }
                $observation->saveQuietly();
                $count++;
            }
        }

        return $count;
    }
}
