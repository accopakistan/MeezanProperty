<?php

namespace App\Console\Commands;

use App\Enums\AreaUnit;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Models\Property;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\LocationResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Imports listings that Meezan Property is authorised to publish: an agency's own
 * feed, licensed data or a data partnership, always for one named lister (the
 * dealer account the listings belong to). Nothing is published here: every new
 * listing goes to the moderation queue as "pending review", with no photos
 * (photos need their own permission and are added by the lister).
 *
 * A dry run by default. Rows without a source reference, with placeholder text,
 * an unknown area or an existing source reference are reported and skipped.
 */
class ListingsImport extends Command
{
    protected $signature = 'meezan:listings-import
        {file : CSV file with a header row}
        {--lister= : Email of the dealer account the listings belong to}
        {--basis= : authorized_feed, licence or partnership}
        {--source= : Name of the source, for example the agency or partner}
        {--commit : Write the listings; without it nothing is saved}';

    protected $description = 'Import listings from an authorised source into the moderation queue (dry run unless --commit)';

    /** Columns every row needs. Optional: address, bedrooms, bathrooms, furnishing, completion_status, amenities (separated by ;), authorization_type, checked_on. */
    public const REQUIRED = ['source_reference', 'source_url', 'title', 'purpose', 'type', 'city', 'area', 'area_size', 'area_unit', 'price', 'description'];

    public function handle(): int
    {
        $basis = (string) $this->option('basis');
        if (! in_array($basis, ['authorized_feed', 'licence', 'partnership'], true)) {
            $this->error('--basis must be authorized_feed, licence or partnership. An advert seen on another portal is not a basis for publishing.');

            return self::FAILURE;
        }
        $source = trim((string) $this->option('source'));
        if ($source === '') {
            $this->error('--source must name the agency or partner the listings come from.');

            return self::FAILURE;
        }
        $lister = User::where('email', (string) $this->option('lister'))->where('role', 'dealer')->where('status', 'active')->with('dealerProfile')->first();
        if (! $lister || ! $lister->dealerProfile || $lister->dealerProfile->is_demo) {
            $this->error('--lister must be the email of an active, real dealer account.');

            return self::FAILURE;
        }
        $rows = $this->rows((string) $this->argument('file'));
        if ($rows === null) {
            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');
        $results = [];
        $seen = [];
        foreach ($rows as $line => $row) {
            [$status, $detail, $data] = $this->check($row, $source, $seen);
            if ($status === 'ok' && $commit) {
                $property = $this->store($data, $lister, $basis, $source);
                $detail = 'Imported as '.$property->reference.', pending review';
            }
            $results[] = [$line, mb_strimwidth((string) ($row['title'] ?? ''), 0, 40, '…'), $status === 'ok' ? ($commit ? 'imported' : 'would import') : $status, $detail];
        }

        $this->table(['Line', 'Title', 'Result', 'Detail'], $results);
        $counts = collect($results)->countBy(fn ($r) => $r[2]);
        $this->line($counts->map(fn ($n, $k) => "$k: $n")->implode(' · '));
        if ($commit) {
            AuditLogger::record('listings.imported', $lister, ['source' => $source, 'basis' => $basis, 'counts' => $counts->all()]);
        } else {
            $this->info('Dry run: nothing was saved. Add --commit to import.');
        }

        return self::SUCCESS;
    }

    /** @return array<int, array<string, string>>|null rows keyed by their line number */
    protected function rows(string $file): ?array
    {
        if (! is_file($file) || ! ($handle = fopen($file, 'r'))) {
            $this->error("Cannot read {$file}.");

            return null;
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\u{FEFF}")), fgetcsv($handle) ?: []);
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing) {
            $this->error('Missing columns: '.implode(', ', $missing));
            fclose($handle);

            return null;
        }
        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if ($values === [null]) {
                continue;
            }
            $rows[$line] = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
        }
        fclose($handle);

        return $rows;
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>} */
    protected function check(array $row, string $source, array &$seen): array
    {
        $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
        $type = PropertyType::tryFrom($row['type'] ?? '');
        $validator = Validator::make($row, [
            'source_reference' => ['required', 'string', 'max:120'],
            'source_url' => ['required', 'url', 'max:2048'],
            'title' => ['required', 'string', 'min:10', 'max:160'],
            'purpose' => ['required', Rule::enum(Purpose::class)],
            'type' => ['required', Rule::enum(PropertyType::class)],
            'city' => ['required', Rule::in(config('meezan.cities'))],
            'area' => ['required', 'string', 'max:140'],
            'price' => ['required', 'integer', 'min:1000', 'max:50000000000'],
            'area_size' => ['required', 'numeric', 'min:0.25', 'max:1000000'],
            'area_unit' => ['required', Rule::enum(AreaUnit::class)],
            'bedrooms' => [$type?->hasRooms() ? 'required' : 'nullable', 'integer', 'between:0,30'],
            'bathrooms' => ['nullable', 'integer', 'between:0,30'],
            'furnishing' => ['nullable', Rule::in(['unfurnished', 'semi-furnished', 'furnished'])],
            'completion_status' => ['nullable', Rule::in(array_keys(Property::COMPLETION))],
            'authorization_type' => ['nullable', Rule::in(array_keys(Property::AUTHORIZATION))],
            'description' => ['required', 'string', 'min:50', 'max:5000', 'not_regex:/lorem\s+ipsum|dolor\s+sit\s+amet|placeholder\s+text/i'],
            'checked_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        if ($validator->fails()) {
            return ['invalid', implode(' ', $validator->errors()->all()), []];
        }

        $key = mb_strtolower($row['source_reference']);
        if (isset($seen[$key])) {
            return ['duplicate', 'Same source reference appears twice in this file', []];
        }
        $seen[$key] = true;
        $existing = Property::withTrashed()->where(fn ($q) => $q->where(fn ($r) => $r->where('source_name', $source)->where('source_reference', $row['source_reference']))
            ->orWhere('source_url', $row['source_url']))->first();
        if ($existing) {
            return ['duplicate', 'Already imported as '.$existing->reference, []];
        }

        $area = Area::where('city', $row['city'])->where('is_active', true)->whereRaw('lower(name) = ?', [mb_strtolower($row['area'])])->first()
            ?? LocationResolver::find($row['area'], $row['city'], 1)->first();
        if (! $area || $area->city !== $row['city']) {
            return ['invalid', "Area \"{$row['area']}\" was not found in {$row['city']}", []];
        }
        $amenities = array_values(array_intersect(array_filter(array_map('trim', explode(';', (string) ($row['amenities'] ?? '')))), array_keys(config('meezan.amenities'))));

        return ['ok', 'Area: '.$area->name, $row + ['area_id' => $area->id, 'amenity_list' => $amenities]];
    }

    protected function store(array $d, User $lister, string $basis, string $source): Property
    {
        return DB::transaction(function () use ($d, $lister, $basis, $source) {
            $property = new Property([
                'title' => $d['title'], 'purpose' => $d['purpose'], 'type' => $d['type'], 'city' => $d['city'], 'area_id' => $d['area_id'],
                'address' => ($d['address'] ?? '') ?: null, 'price' => (int) $d['price'], 'area_size' => $d['area_size'], 'area_unit' => $d['area_unit'],
                'bedrooms' => ($d['bedrooms'] ?? '') === '' ? null : (int) $d['bedrooms'], 'bathrooms' => ($d['bathrooms'] ?? '') === '' ? null : (int) $d['bathrooms'],
                'furnishing' => ($d['furnishing'] ?? '') ?: null, 'completion_status' => ($d['completion_status'] ?? '') ?: null,
                'description' => $d['description'], 'amenities' => $d['amenity_list'],
            ]);
            $property->forceFill([
                'user_id' => $lister->id, 'status' => PropertyStatus::Pending, 'submitted_at' => now(), 'is_demo' => false,
                'authorization_type' => ($d['authorization_type'] ?? '') ?: 'authorized_agent', 'authorization_confirmed_at' => now(),
                'publication_basis' => $basis, 'source_name' => $source, 'source_url' => $d['source_url'], 'source_reference' => $d['source_reference'],
                'source_verified_at' => ($d['checked_on'] ?? '') ? $d['checked_on'] : now(),
            ])->save();
            AuditLogger::record('property.imported', $property, ['source' => $source, 'basis' => $basis, 'reference' => $d['source_reference']]);

            return $property;
        });
    }
}
