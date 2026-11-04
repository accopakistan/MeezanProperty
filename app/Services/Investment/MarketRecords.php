<?php

namespace App\Services\Investment;

use App\Enums\AreaUnit;
use App\Enums\PropertyType;
use App\Models\Area;
use App\Models\MarketImportBatch;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Cities;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation and CSV import for market observations and indicators. Manual
 * entry and imports share the same rules; imported rows become drafts that
 * still go through review. Duplicates (same fingerprint) are skipped.
 */
class MarketRecords
{
    public const MAX_IMPORT_ROWS = 5000;

    public const OBSERVATION_COLUMNS = ['kind', 'price_basis', 'city', 'area_slug', 'project_slug', 'asset_class', 'property_type', 'size_value', 'size_unit',
        'currency', 'average_amount', 'min_amount', 'max_amount', 'observed_on', 'sample_size', 'source_id', 'methodology', 'notes',
        // Added later; last, so templates made before it keep their column order.
        'bedrooms'];

    public const INDICATOR_COLUMNS = ['city', 'area_slug', 'project_slug', 'asset_class', 'indicator', 'level', 'numeric_value', 'observed_on', 'source_id', 'methodology', 'notes'];

    /** @return array<string, mixed> validated, with area_id / project_id resolved */
    public static function validateObservation(array $input): array
    {
        $input = static::resolveLocation($input);
        $data = Validator::make($input, [
            'kind' => ['required', Rule::in(array_keys(MarketObservation::KINDS))],
            'price_basis' => ['required', Rule::in(array_keys(config('meezan.investment.price_bases')))],
            'city' => ['required', Rule::in(Cities::names())],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('city', $input['city'] ?? '')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('city', $input['city'] ?? '')],
            'asset_class' => ['required', Rule::in(['residential', 'commercial'])],
            'property_type' => ['required', Rule::enum(PropertyType::class)],
            'size_value' => ['nullable', 'numeric', 'min:0.25', 'max:1000000', 'required_with:size_unit'],
            'size_unit' => ['nullable', Rule::enum(AreaUnit::class), 'required_with:size_value'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:10'],
            'currency' => ['required', Rule::in(['PKR'])],
            'average_amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'min_amount' => ['nullable', 'integer', 'min:1', 'lte:average_amount'],
            'max_amount' => ['nullable', 'integer', 'gte:average_amount', 'max:999999999999'],
            'observed_on' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'sample_size' => ['required', 'integer', 'min:1', 'max:1000000'],
            'source_id' => ['required', 'integer', Rule::exists('market_sources', 'id')->where('is_active', true)],
            'methodology' => ['required', 'string', 'min:10', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'methodology.required' => 'Describe how the figure was collected or calculated.',
            'currency.in' => 'Pakistan markets record amounts in PKR.',
        ])->after(function ($v) use ($input) {
            $type = PropertyType::tryFrom((string) ($input['property_type'] ?? ''));
            $class = $input['asset_class'] ?? null;
            if ($type && $class && ($type->group() === 'commercial') !== ($class === 'commercial')) {
                $v->errors()->add('asset_class', 'The asset class does not match the property type.');
            }
            if (! empty($input['area_id']) && ! empty($input['project_id'])) {
                $v->errors()->add('project_id', 'Choose an area/society or a project, not both.');
            }
            $source = MarketSource::find($input['source_id'] ?? 0);
            if ($source && ($input['price_basis'] ?? null) === 'transaction' && $source->type === 'public_asking_prices') {
                $v->errors()->add('price_basis', 'An asking-price source cannot supply transaction prices.');
            }
        })->validate();

        return $data + ['country' => 'Pakistan'];
    }

    public static function validateIndicator(array $input): array
    {
        $input = static::resolveLocation($input);
        $definitions = config('meezan.investment.indicators');
        $definition = $definitions[$input['indicator'] ?? ''] ?? null;
        $numeric = ! empty($definition['numeric']);

        $data = Validator::make($input, [
            'city' => ['required', Rule::in(Cities::names())],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('city', $input['city'] ?? '')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('city', $input['city'] ?? '')],
            'asset_class' => ['nullable', Rule::in(['residential', 'commercial'])],
            'indicator' => ['required', Rule::in(array_keys($definitions))],
            'level' => [$numeric ? 'prohibited' : 'required', Rule::in(array_keys($definition['levels'] ?? []))],
            'numeric_value' => [$numeric ? 'required' : 'prohibited', 'numeric', 'between:0,100'],
            'observed_on' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'source_id' => ['required', 'integer', Rule::exists('market_sources', 'id')->where('is_active', true)],
            'methodology' => ['required', 'string', 'min:10', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'methodology.required' => 'Describe the evidence and how the level was decided.',
            'level.in' => 'Choose one of the levels defined for this indicator.',
        ])->after(function ($v) use ($input) {
            if (! empty($input['area_id']) && ! empty($input['project_id'])) {
                $v->errors()->add('project_id', 'Choose an area/society or a project, not both.');
            }
        })->validate();

        return $data + ['country' => 'Pakistan'];
    }

    /** Imports accept area_slug / project_slug; forms send ids. */
    protected static function resolveLocation(array $input): array
    {
        $input = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $input);
        $input = array_map(fn ($v) => $v === '' ? null : $v, $input);
        // A slug field that is present but blank clears the location (whole city).
        if (array_key_exists('area_slug', $input)) {
            $input['area_id'] = $input['area_slug'] ? (Area::where('slug', $input['area_slug'])->value('id') ?? -1) : null;
        }
        if (array_key_exists('project_slug', $input)) {
            $input['project_id'] = $input['project_slug'] ? (Project::where('slug', $input['project_slug'])->value('id') ?? -1) : null;
        }
        unset($input['area_slug'], $input['project_slug']);

        return $input;
    }

    /**
     * Import a CSV of observations or indicators as drafts.
     *
     * @throws ValidationException when the file itself is unusable
     */
    public static function import(string $type, UploadedFile $file, User $user): MarketImportBatch
    {
        $columns = $type === 'observations' ? self::OBSERVATION_COLUMNS : self::INDICATOR_COLUMNS;
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        if ($missing = array_diff(['city', 'observed_on', 'source_id', 'methodology'], $header)) {
            throw ValidationException::withMessages(['file' => 'Missing columns: '.implode(', ', $missing).'. Use the template.']);
        }
        if ($unknown = array_diff($header, $columns)) {
            throw ValidationException::withMessages(['file' => 'Unknown columns: '.implode(', ', $unknown).'. Use the template.']);
        }

        $batch = new MarketImportBatch(['type' => $type, 'file_name' => mb_substr($file->getClientOriginalName(), 0, 200)]);
        $batch->created_by = $user->id;
        $batch->save();

        $model = $type === 'observations' ? MarketObservation::class : MarketIndicator::class;
        $seen = [];
        $errors = [];
        [$rows, $imported, $duplicates] = [0, 0, 0];

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }
            $rows++;
            if ($rows > self::MAX_IMPORT_ROWS) {
                $errors[] = ['row' => $rows + 1, 'errors' => ['Row limit of '.self::MAX_IMPORT_ROWS.' reached; the rest of the file was not read.']];
                break;
            }
            $input = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), null));
            try {
                $data = $type === 'observations' ? static::validateObservation($input) : static::validateIndicator($input);
            } catch (ValidationException $e) {
                $errors[] = ['row' => $rows + 1, 'errors' => array_slice(collect($e->errors())->flatten()->all(), 0, 5)];

                continue;
            }

            $record = new $model($data);
            $fingerprint = $model::fingerprintFor($record->getAttributes());
            if (isset($seen[$fingerprint]) || $model::where('fingerprint', $fingerprint)->where('status', '!=', 'rejected')->exists()) {
                $duplicates++;

                continue;
            }
            $seen[$fingerprint] = true;
            $record->forceFill(['status' => 'draft', 'created_by' => $user->id, 'import_batch_id' => $batch->id])->save();
            $imported++;
        }
        fclose($handle);

        $batch->forceFill(['rows' => $rows, 'imported' => $imported, 'duplicates' => $duplicates, 'failed' => count($errors), 'errors' => array_slice($errors, 0, 200)])->save();
        AuditLogger::record('market.imported', $batch, ['type' => $type, 'rows' => $rows, 'imported' => $imported, 'duplicates' => $duplicates, 'failed' => count($errors)], $user->id);

        return $batch;
    }
}
