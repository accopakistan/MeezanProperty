<?php

namespace App\Console\Commands;

use App\Models\Area;
use Illuminate\Console\Command;

/**
 * Writes coordinates found by `meezan:geocode-areas` (or entered by admins)
 * back into the master dataset CSV, matched by research key, so the research
 * files stay the record of what is known. Only coordinate columns change.
 */
class ExportLocations extends Command
{
    protected $signature = 'meezan:export-locations {--file= : CSV path (default: docs/research/Meezan_Pakistan_Locations.csv)}';

    protected $description = 'Write area coordinates back into the location master dataset';

    public function handle(): int
    {
        $path = $this->option('file') ?: base_path('docs/research/Meezan_Pakistan_Locations.csv');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $areas = Area::whereNotNull('research_key')->get(['research_key', 'latitude', 'longitude', 'coordinates_source', 'coordinate_precision'])->keyBy('research_key');

        $in = fopen($path, 'r');
        $header = fgetcsv($in);
        $rows = [];
        $filled = 0;
        while (($data = fgetcsv($in)) !== false) {
            $row = array_combine($header, $data);
            $area = $areas[$row['key']] ?? null;
            if ($area?->hasCoordinates()) {
                $row['latitude'] = number_format($area->latitude, 7, '.', '');
                $row['longitude'] = number_format($area->longitude, 7, '.', '');
                $row['coordinate_source'] = (string) $area->coordinates_source;
                $row['coordinate_precision'] = (string) $area->coordinate_precision;
                $filled++;
            }
            $rows[] = $row;
        }
        fclose($in);

        $out = fopen($path, 'w');
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);

        $this->info("{$filled} of ".count($rows).' rows now carry coordinates.');

        return self::SUCCESS;
    }
}
