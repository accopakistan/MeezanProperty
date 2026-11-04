<?php

namespace App\Services\Investment;

use App\Models\Area;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Cities;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CSV import of projects (societies, malls, plazas, towers …) for Investment
 * Rankings. Every row is validated on its own; rejected rows are reported
 * with their reasons. Existing projects (same name in the same city) are
 * never overwritten, and imported projects stay hidden until staff publish them.
 */
class ProjectImport
{
    public const COLUMNS = ['name', 'developer', 'category', 'city', 'area_slug', 'latitude', 'longitude', 'status', 'launched_on',
        'min_price', 'max_price', 'description', 'website', 'approval_note', 'approval_status'];

    public const MAX_ROWS = 2000;

    /** Rules shared with the project form. */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'developer' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', Rule::in(array_keys(config('meezan.investment.project_categories')))],
            'city' => ['required', Rule::in(Cities::names())],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'latitude' => ['nullable', 'numeric', 'between:23.5,37.5', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:60.5,78', 'required_with:latitude'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'launched_on' => ['nullable', 'date', 'after:1990-01-01', 'before_or_equal:today'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', 'gte:min_price'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'website' => ['nullable', 'url', 'max:255'],
            'approval_note' => ['nullable', 'string', 'max:255'],
            'approval_status' => ['nullable', Rule::in(array_keys(Area::APPROVAL_STATUSES))],
        ];
    }

    /**
     * @return array{rows: int, imported: int, duplicates: int, errors: list<array{row: int, errors: list<string>}>}
     *
     * @throws ValidationException when the file itself is unusable
     */
    public static function import(UploadedFile $file, User $user): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        if ($missing = array_diff(['name', 'city', 'status', 'description'], $header)) {
            throw ValidationException::withMessages(['file' => 'Missing columns: '.implode(', ', $missing).'. Use the template.']);
        }
        if ($unknown = array_diff($header, self::COLUMNS)) {
            throw ValidationException::withMessages(['file' => 'Unknown columns: '.implode(', ', $unknown).'. Use the template.']);
        }

        [$rows, $imported, $duplicates, $errors, $seen] = [0, 0, 0, [], []];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }
            $rows++;
            if ($rows > self::MAX_ROWS) {
                $errors[] = ['row' => $rows + 1, 'errors' => ['Row limit of '.self::MAX_ROWS.' reached; the rest of the file was not read.']];
                break;
            }
            $input = array_map(fn ($v) => ($v = trim((string) $v)) === '' ? null : $v, array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), null)));

            if (! empty($input['area_slug'])) {
                $area = Area::where('slug', $input['area_slug'])->where('city', $input['city'] ?? '')->first();
                if (! $area) {
                    $errors[] = ['row' => $rows + 1, 'errors' => ['Area "'.$input['area_slug'].'" was not found in '.($input['city'] ?? 'that city').'.']];

                    continue;
                }
                $input['area_id'] = $area->id;
            }
            unset($input['area_slug']);

            $v = Validator::make($input, self::rules());
            if ($v->fails()) {
                $errors[] = ['row' => $rows + 1, 'errors' => $v->errors()->all()];

                continue;
            }
            $data = $v->validated();
            $key = mb_strtolower($data['name']).'|'.$data['city'];
            if (isset($seen[$key]) || Project::where('city', $data['city'])->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->exists()) {
                $duplicates++;

                continue;
            }
            $seen[$key] = true;

            $project = new Project($data);
            $project->is_published = false;
            $project->save();
            $imported++;
        }
        fclose($handle);

        AuditLogger::record('project.imported', null, ['file' => mb_substr($file->getClientOriginalName(), 0, 200), 'rows' => $rows, 'imported' => $imported, 'duplicates' => $duplicates, 'rejected' => count($errors)], $user->id);

        return compact('rows', 'imported', 'duplicates', 'errors');
    }
}
