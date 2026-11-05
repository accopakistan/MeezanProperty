<?php

namespace App\Services\InvestmentIndex;

use App\Models\Area;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\ProjectAlias;
use App\Services\AuditLogger;
use App\Services\InvestmentIndex\Collection\OfficialStatus;
use App\Services\InvestmentIndex\Collection\Registry;
use App\Services\InvestmentIndex\Collection\SchemeNames;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds the real project database from original sources without inventing
 * anything. Each project keeps the source it was found in; a different spelling
 * of a known project becomes an alias, not a second project. Official illegal
 * lists never create projects: they stay in the registry and count only if a
 * project matches them. Status is "not verified" unless a source states it.
 * Re-running changes nothing that is already there.
 */
class ProjectDiscovery
{
    /** @var array<string, array<string, int>> city => normalized area name => area id */
    protected array $areas = [];

    public function __construct(protected Registry $registry) {}

    /** The hand-read sources in database/data, into the source registry and the official registry. */
    public function importFile(?string $path = null): array
    {
        $totals = ['sources' => 0, 'entries' => 0, 'references' => 0];
        foreach (require ($path ?? database_path('data/investment/real_world_sources.php')) as $def) {
            $source = $this->source($def);
            $totals['sources']++;
            if ($def['list_type'] === 'reference') {
                foreach ($def['cities'] as $city => $items) {
                    foreach ($items as $item) {
                        [$name, $website, $status, $note] = array_pad(explode('|', $item), 4, null);
                        $this->ensure(['name' => $name, 'city' => $city, 'category' => $def['category'], 'website' => $website ?: null, 'status' => $status ?: null,
                            'category_basis' => $def['category_basis'], 'url' => $def['url'], 'read_on' => $def['read_on'],
                            'description' => trim("{$name} is reported as a development in {$city} by {$def['publisher']}, a secondary public reference. ".($note ? "{$note} " : '').'Meezan Property has not verified it.')], $source);
                        $totals['references']++;
                    }
                }

                continue;
            }
            foreach (preg_split('/\R/', trim($def['lines'])) as $line) {
                $this->entry($line, $def, $source);
                $totals['entries']++;
            }
        }

        return $totals;
    }

    /** Projects for the approved and authority entries that have none yet. @return array{created: int, aliased: int} */
    public function fromRegistry(): array
    {
        $totals = ['created' => 0, 'aliased' => 0];
        InvestmentRegistryEntry::with('source')->whereIn('list_type', ['approved', 'authority_scheme'])->whereNull('removed_at')
            ->whereIn('match_status', ['unmatched', 'ambiguous'])->orderBy('id')->chunkById(200, function ($entries) use (&$totals) {
                foreach ($entries as $entry) {
                    $config = $entry->source?->collectorPreset() ?? [];
                    if (($config['discover'] ?? true) === false) {
                        continue;
                    }
                    $category = $entry->raw['_category'] ?? $config['category'] ?? 'residential_society';
                    $result = $this->ensure(['name' => $entry->scheme_name, 'city' => $entry->city, 'category' => $category, 'developer' => $entry->raw['_developer'] ?? null,
                        'category_basis' => $entry->raw['_category_basis'] ?? 'official', 'url' => $entry->source_url, 'read_on' => $entry->first_seen_at,
                        'description' => $this->officialDescription($entry), 'location' => $entry->location], $entry->source);
                    $totals[$result['created'] ? 'created' : 'aliased']++;
                    $this->registry->match($entry->fresh());
                }
            });

        return $totals;
    }

    /**
     * The project with this name in this city and category, created if there is none.
     *
     * @return array{project: Project, created: bool}
     */
    public function ensure(array $a, ?MarketSource $source): array
    {
        $name = static::displayName((string) $a['name']);
        if ($existing = $this->findExisting($name, $a['city'], $a['category'])) {
            $this->alias($existing, $name, $source);
            // A source naming the developer fills a blank. It never overwrites one already recorded.
            if (blank($existing->developer) && filled($a['developer'] ?? null)) {
                Project::whereKey($existing->id)->update(['developer' => $a['developer']]);
            }

            return ['project' => $existing, 'created' => false];
        }
        $project = new Project(['name' => $name, 'city' => $a['city'], 'category' => $a['category'], 'status' => $a['status'] ?? 'unverified', 'developer' => $a['developer'] ?? null,
            'website' => $a['website'] ?? null, 'description' => $a['description'], 'area_id' => $this->areaFor($name, $a['city'])]);
        $project->forceFill(['is_published' => true, 'is_demo' => false, 'discovery_source_id' => $source?->id, 'discovery_url' => $a['url'] ?? null,
            'discovered_at' => $a['read_on'] ?? now(), 'category_basis' => $a['category_basis'] ?? null])->save();
        AuditLogger::record('investment_index.project_discovered', $project, ['source' => $source?->name, 'url' => $a['url'] ?? null]);

        return ['project' => $project, 'created' => true];
    }

    /** Readable casing for names a list writes in capitals. PS and roman numerals stay as they are; so does a short one-word name such as PHAF. */
    public static function displayName(string $raw): string
    {
        $name = SchemeNames::clean($raw);
        if ($name !== mb_strtoupper($name) || (! str_contains($name, ' ') && mb_strlen($name) <= 5)) {
            return $name;
        }

        return preg_replace_callback('/\p{L}+/u', function ($m) {
            $w = $m[0];

            return preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/', $w) || ! preg_match('/[AEIOU]/', $w) ? $w : Str::title(mb_strtolower($w));
        }, $name);
    }

    /** A real project of this name (or a known alias) here. A different phase is a different project. */
    public function findExisting(string $name, string $city, string $category): ?Project
    {
        $auto = (float) config('investment_index.collection.match_auto');
        $normalized = SchemeNames::normalize($name);
        $alias = ProjectAlias::where('normalized', $normalized)->whereHas('project', fn ($q) => $q->where('is_demo', false)->where('city', $city)->where('category', $category))->first();
        if ($alias) {
            return $alias->project;
        }

        return Project::where('is_demo', false)->where('city', $city)->where('category', $category)->get(['id', 'name', 'city', 'category', 'slug'])
            ->map(fn (Project $p) => ['project' => $p, 'score' => SchemeNames::similarity($name, $p->name)])
            ->filter(fn ($c) => $c['score'] >= $auto)->sortByDesc('score')->first()['project'] ?? null;
    }

    public function alias(Project $project, string $name, ?MarketSource $source): void
    {
        $normalized = SchemeNames::normalize($name);
        if ($normalized !== '' && $name !== $project->name) {
            ProjectAlias::firstOrCreate(['project_id' => $project->id, 'normalized' => $normalized], ['alias' => mb_substr($name, 0, 250), 'source_id' => $source?->id]);
        }
    }

    protected function source(array $def): MarketSource
    {
        $source = MarketSource::firstOrNew(['name' => $def['name']]);
        $source->fill(['type' => $def['type'], 'url' => $def['url'], 'publisher' => $def['publisher'], 'reliability' => $def['type'] === 'official_record' ? 'high' : 'low',
            'coverage' => $def['city'] ?? implode(', ', array_keys($def['cities'] ?? [])), 'last_checked_on' => $def['read_on']]);
        $source->forceFill(['is_active' => true, 'is_demo' => false, 'authority_level' => $def['level'], 'access_method' => $def['method'], 'access_note' => $def['access'],
            'access_basis' => $def['type'] === 'official_record' ? 'public_record' : null, 'data_categories' => [$def['category']], 'last_success_at' => $def['read_on'], 'last_collected_at' => $def['read_on']])->save();

        return $source;
    }

    protected function entry(string $line, array $def, MarketSource $source): InvestmentRegistryEntry
    {
        [$name, $location, $date, $developer, $reference] = array_pad(explode('|', $line), 5, null);
        if ($def['split_location'] ?? false) {
            [$name, $location] = $this->splitChak((string) $name);
        }
        $name = SchemeNames::clean($name);
        $key = Str::slug($name.'-'.$location) ?: hash('sha256', $line);
        $raw = ['line' => $line, '_category' => $def['category'], '_category_basis' => $def['category_basis'], '_developer' => $developer ?: null, '_review' => $def['review'] ?? false];
        $entry = InvestmentRegistryEntry::firstOrNew(['source_id' => $source->id, 'external_key' => mb_substr($key, 0, 120)]);
        $readOn = Carbon::parse($def['read_on']);
        $entry->forceFill(['authority' => $def['authority'], 'list_type' => $def['list_type'], 'scheme_name' => mb_substr($name, 0, 250), 'normalized_name' => SchemeNames::normalize($name),
            'city' => $def['city'], 'location' => $location ?: null, 'raw_status' => $def['raw_status'], 'status' => OfficialStatus::for($def['list_type'], $def['raw_status'], $def['stages'] ?? null),
            'issued_on' => OfficialStatus::date($date), 'reference' => $reference ?: null, 'source_url' => $def['url'].'#'.rawurlencode($key), 'content_hash' => hash('sha256', $line), 'raw' => $raw,
            'first_seen_at' => $entry->first_seen_at ?? $readOn, 'last_seen_at' => $readOn, 'match_status' => $entry->match_status ?? 'unmatched'])->save();

        return $entry;
    }

    /** "Vista Homes Chak No. 121/JB" => ["Vista Homes", "Chak No. 121/JB"]. The authority's full wording stays in the entry. */
    protected function splitChak(string $text): array
    {
        $text = trim(html_entity_decode($text));
        if (preg_match('/^(.*?)[,\s]+((?:Chak\b|\d+\s*(?:&\s*\d+\s*)?\/?\s*[RGJ][BbGg]\b).*)$/iu', $text, $m) && trim($m[1]) !== '') {
            return [rtrim(trim($m[1]), ',.'), rtrim(trim($m[2]), '.')];
        }

        return [rtrim($text, '.'), null];
    }

    protected function officialDescription(InvestmentRegistryEntry $e): string
    {
        $where = $e->location ? " at {$e->location}" : '';
        $list = InvestmentRegistryEntry::LIST_TYPES[$e->list_type] ?? 'official';
        $stage = $e->raw_status ? ' The authority shows: "'.mb_substr($e->raw_status, 0, 200).'".' : '';

        return mb_substr("{$e->scheme_name}{$where} appears on {$e->authority}'s published list ({$list}).{$stage} This record copies the list. Meezan Property has not inspected the project, and development and operational status are not verified.", 0, 1000);
    }

    /** The location directory's entry with exactly this name in this city, for an approximate map position. */
    protected function areaFor(string $name, string $city): ?int
    {
        $this->areas[$city] ??= Area::where('city', $city)->get(['id', 'name'])->mapWithKeys(fn ($a) => [SchemeNames::normalize($a->name) => $a->id])->all();

        return $this->areas[$city][SchemeNames::normalize($name)] ?? null;
    }
}
