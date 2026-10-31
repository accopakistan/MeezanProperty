<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\AreaAlias;
use App\Models\City;
use App\Services\AuditLogger;
use App\Support\LocationName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports the researched location master dataset
 * (docs/research/Meezan_Pakistan_Locations.csv) into `areas` + `area_aliases`.
 *
 * Idempotent: rows are matched by research key, then by (city, name), and
 * updated in place. Existing names, slugs, coordinates and popularity are
 * never overwritten, and nothing is deleted. Rows for non-launch cities
 * (Rawalpindi) are rejected, not silently re-homed.
 */
class ImportLocations extends Command
{
    protected $signature = 'meezan:import-locations
        {--file= : CSV path (default: docs/research/Meezan_Pakistan_Locations.csv)}
        {--dry-run : Validate and report without writing}';

    protected $description = 'Import the Pakistan location master dataset (idempotent)';

    protected array $counts = ['read' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'rejected' => 0, 'flagged' => 0, 'aliases_added' => 0, 'parents_linked' => 0, 'possible_duplicates' => 0];

    protected array $log = [];

    /** @var array<string, string> research key => the other record(s) with the same normalised name */
    protected array $duplicates = [];

    /**
     * Rows of one city whose names normalise to the same text ("Al Zia H/S",
     * "Al Zia Housing Scheme") are flagged, never merged automatically.
     *
     * @param  array<int, array<string, string>>  $rows
     */
    protected function findDuplicates(array $rows): void
    {
        $groups = [];
        foreach ($rows as $row) {
            if (($row['type'] ?? '') !== 'city' && ($row['name'] ?? '') !== '') {
                $groups[$row['city'].'|'.LocationName::normalize($row['name'])][] = $row;
            }
        }
        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $row) {
                $others = array_map(fn ($o) => '"'.$o['name'].'" ('.$o['approval_status'].')', array_filter($group, fn ($o) => $o['key'] !== $row['key']));
                $this->duplicates[$row['key']] = implode(', ', $others);
            }
        }
    }

    public function handle(): int
    {
        $path = $this->option('file') ?: base_path('docs/research/Meezan_Pakistan_Locations.csv');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->read($path);
        $this->findDuplicates($rows);
        $dry = (bool) $this->option('dry-run');
        $cities = City::pluck('id', 'name');
        $launch = config('meezan.cities');

        $apply = function () use ($rows, $dry, $cities, $launch) {
            $byKey = [];
            foreach ($rows as $line => $row) {
                $this->counts['read']++;
                $error = $this->validate($row, $launch, $cities);
                if ($error) {
                    $this->counts['rejected']++;
                    $this->note('REJECTED', $line, $row, $error);

                    continue;
                }
                if ($row['type'] === 'city') {
                    $this->counts['skipped']++;

                    continue;
                }

                if (isset($this->duplicates[$row['key']])) {
                    $row['needs_review'] = '1';
                    $row['notes'] = trim($row['notes'].' Possible duplicate of '.$this->duplicates[$row['key']].' (same normalised name); check and merge in Admin → Locations if it is the same place.');
                    $this->counts['possible_duplicates']++;
                }
                $byKey[$row['key']] = $this->upsert($row, $dry);
                if ($row['needs_review'] === '1') {
                    $this->counts['flagged']++;
                }
            }

            // Second pass: parents (they may appear later in the file).
            foreach ($rows as $line => $row) {
                $area = $byKey[$row['key']] ?? null;
                if (! $area || $row['parent_key'] === '') {
                    continue;
                }
                $parent = $byKey[$row['parent_key']] ?? Area::where('research_key', $row['parent_key'])->first();
                if (! $parent || $parent->city !== $area->city) {
                    $this->note('PARENT', $line, $row, 'parent not found in the same city: '.$row['parent_key']);

                    continue;
                }
                if ($area->parent_id !== $parent->id && ! $dry) {
                    $area->parent_id = $parent->id;
                    $area->save();
                    $this->counts['parents_linked']++;
                }
            }
        };

        $dry ? $apply() : DB::transaction($apply);

        $this->table(array_keys($this->counts), [array_values($this->counts)]);
        $logFile = storage_path('logs/location-import-'.now()->format('Ymd-His').($dry ? '-dry-run' : '').'.log');
        file_put_contents($logFile, implode("\n", array_merge(
            ['Location import '.now()->toDateTimeString().' from '.$path.($dry ? ' (dry run)' : ''), json_encode($this->counts)],
            $this->log,
        ))."\n");
        $this->info('Log written to '.$logFile);

        if (! $dry) {
            AuditLogger::record('locations.imported', null, $this->counts + ['file' => basename($path)]);
        }

        return self::SUCCESS;
    }

    /** @return array<int, array<string, string>> keyed by CSV line number */
    protected function read(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $rows = [];
        $line = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            if (count($data) !== count($header)) {
                $this->note('REJECTED', $line, [], 'column count mismatch');
                $this->counts['rejected']++;

                continue;
            }
            $rows[$line] = array_map('trim', array_combine($header, $data));
        }
        fclose($handle);

        return $rows;
    }

    protected function validate(array $row, array $launch, $cities): ?string
    {
        return match (true) {
            ($row['name'] ?? '') === '' || ($row['key'] ?? '') === '' => 'missing name or key',
            ! in_array($row['city'], $launch, true) => "{$row['city']} is not a launch city",
            ! isset($cities[$row['city']]) => "no cities row for {$row['city']}",
            $row['type'] !== 'city' && ! isset(Area::TYPES[$row['type']]) => "unknown type {$row['type']}",
            ! isset(Area::APPROVAL_STATUSES[$row['approval_status']]) => "unknown approval status {$row['approval_status']}",
            mb_strlen($row['name']) > 120 => 'name longer than 120 characters',
            default => null,
        };
    }

    protected function upsert(array $row, bool $dry): Area
    {
        $area = Area::where('research_key', $row['key'])->first()
            ?? Area::where('city', $row['city'])->where('name', $row['name'])->first()
            ?? Area::where('city', $row['city'])->where('normalized_name', LocationName::normalize($row['name']))->whereNull('research_key')->first();

        $meta = [
            'research_key' => $row['key'],
            'type' => $row['type'],
            'official_name' => $row['official_name'] ?: null,
            'authority' => $row['authority'] ?: null,
            'approval_status' => $row['approval_status'],
            'approval_reference' => Str::limit($row['approval_reference'], 250, '') ?: null,
            'verification_source_url' => $row['source_url'] ?: null,
            'source_ids' => $row['source_ids'] ?: null,
            'verified_on' => $row['research_date'] ?: null,
            'confidence' => $row['confidence'] ?: null,
            'needs_review' => $row['needs_review'] === '1',
            'is_active' => $row['active'] !== '0',
            'notes' => $row['notes'] ?: null,
        ];

        if ($area) {
            $area->fill($meta);
            // Keeps the search key current when the normaliser improves.
            $area->normalized_name = LocationName::normalize($area->name);
            // Coordinates only fill gaps; existing points are never replaced.
            if (! $area->hasCoordinates() && $row['latitude'] !== '' && $row['longitude'] !== '') {
                $area->fill(['latitude' => $row['latitude'], 'longitude' => $row['longitude'], 'coordinates_source' => $row['coordinate_source'] ?: 'dataset', 'coordinate_precision' => $row['coordinate_precision'] ?: null]);
            }
            $dirty = $area->isDirty();
            if ($dirty && ! $dry) {
                $area->save();
            }
            $this->counts[$dirty ? 'updated' : 'unchanged']++;
        } else {
            $area = new Area($meta + [
                'city' => $row['city'],
                'name' => $row['name'],
                'slug' => $this->uniqueSlug($row['name'], $row['city']),
                'is_popular' => $row['popular'] === '1',
                'sort_order' => $row['popular'] === '1' ? 50 : 100,
                'latitude' => $row['latitude'] !== '' ? $row['latitude'] : null,
                'longitude' => $row['longitude'] !== '' ? $row['longitude'] : null,
                'coordinates_source' => $row['latitude'] !== '' ? ($row['coordinate_source'] ?: 'dataset') : null,
                'coordinate_precision' => $row['coordinate_precision'] ?: null,
            ]);
            if (! $dry) {
                $area->save();
            }
            $this->counts['created']++;
        }

        if (! $dry && $row['aliases'] !== '') {
            $this->syncAliases($area, explode('|', $row['aliases']));
        }

        return $area;
    }

    protected function syncAliases(Area $area, array $aliases): void
    {
        $existing = $area->aliases()->pluck('normalized')->all();
        $existing[] = $area->normalized_name;
        foreach (array_unique(array_filter(array_map('trim', $aliases))) as $alias) {
            $norm = LocationName::normalize($alias);
            if ($norm === '' || in_array($norm, $existing, true)) {
                continue;
            }
            AreaAlias::create(['area_id' => $area->id, 'city_id' => $area->city_id, 'alias' => Str::limit($alias, 160, '')]);
            $existing[] = $norm;
            $this->counts['aliases_added']++;
        }
    }

    protected function uniqueSlug(string $name, string $city): string
    {
        $base = Str::slug(str_contains(strtolower($name), strtolower($city)) ? $name : $name.' '.$city);
        $slug = $base;
        for ($i = 2; Area::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    protected function note(string $kind, int $line, array $row, string $message): void
    {
        $this->log[] = sprintf('%s line %d %s: %s', $kind, $line, $row['key'] ?? '', $message);
    }
}
