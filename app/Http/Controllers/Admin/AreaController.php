<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaAlias;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Services\CityDirectory;
use App\Services\LocationMerger;
use App\Support\LocationName;
use App\Support\LocationResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Location management: the national directory of areas, societies, phases
 * and blocks, with approval metadata, aliases, coordinates and merging.
 */
class AreaController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'city' => ['nullable', Rule::in(config('meezan.cities'))],
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(Area::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(Area::APPROVAL_STATUSES))],
            'review' => ['nullable', 'boolean'],
            'no_coords' => ['nullable', 'boolean'],
            'inactive' => ['nullable', 'boolean'],
        ]);

        $areas = Area::query()
            ->with('parent:id,name')
            ->withCount('properties')
            ->when($filters['city'] ?? null, fn ($q, $v) => $q->where('city', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->matching($v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('approval_status', $v))
            ->when($filters['review'] ?? false, fn ($q) => $q->where('needs_review', true))
            ->when($filters['no_coords'] ?? false, fn ($q) => $q->whereNull('latitude'))
            ->where('is_active', ! ($filters['inactive'] ?? false))
            ->orderBy('city')->orderBy('depth')->ordered()
            ->paginate(50)->withQueryString();

        return view('admin.areas', [
            'areas' => $areas,
            'filters' => $filters,
            'totals' => [
                'active' => Area::where('is_active', true)->count(),
                'review' => Area::where('is_active', true)->where('needs_review', true)->count(),
                'noCoords' => Area::where('is_active', true)->whereNull('latitude')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $area = new Area($data + ['slug' => $this->slug($data['name'], $data['city'])]);
        $area->save();

        AuditLogger::record('area.created', $area);
        CityDirectory::flush();

        return back()->with('status', $area->name.' added.');
    }

    public function edit(Area $area): View
    {
        $area->load('aliases', 'parent');

        return view('admin.areas-edit', [
            'area' => $area,
            'parents' => Area::active()->where('city', $area->city)->where('id', '!=', $area->id)->where('depth', '<', 3)
                ->orderBy('depth')->orderBy('name')->get(['id', 'name', 'depth']),
            'history' => AuditLog::where('auditable_type', $area->getMorphClass())->where('auditable_id', $area->id)
                ->with('user:id,name')->latest('created_at')->take(20)->get(),
        ]);
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $before = $area->only(['name', 'city', 'parent_id', 'approval_status', 'latitude', 'longitude', 'is_active']);
        $area->update($this->validated($request, $area));

        if ($request->has('aliases')) {
            $this->syncAliases($area, (string) $request->input('aliases'));
        }

        AuditLogger::record('area.updated', $area, array_filter(['before' => array_diff_assoc(array_map('strval', $before), array_map('strval', $area->only(array_keys($before))))]));
        CityDirectory::flush();

        return back()->with('status', $area->name.' updated.');
    }

    public function merge(Request $request, Area $area, LocationMerger $merger): RedirectResponse
    {
        $data = $request->validate([
            'into_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('city', $area->city)->where('is_active', true)],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Tick the box to confirm the merge.']);

        $into = Area::findOrFail($data['into_id']);

        try {
            $moved = $merger->merge($area, $into, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['into_id' => $e->getMessage()]);
        }
        CityDirectory::flush();

        return redirect()->route('admin.areas.edit', $into)
            ->with('status', "{$area->name} merged into {$into->name}: {$moved['properties']} listing(s), {$moved['leads']} lead(s) and {$moved['dealers']} dealer area(s) moved.");
    }

    /**
     * Quick-edit rows send name/city/coordinates/order only; the full edit
     * page also sends hierarchy and verification fields ("sometimes").
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Area $area = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('areas')->where('city', $request->input('city'))->ignore($area?->id)],
            'city' => ['required', Rule::in(config('meezan.cities'))],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:23,37.5'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:60,78'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('areas', 'id')->where('city', $request->input('city')), Rule::notIn(array_filter([$area?->id]))],
            'type' => ['sometimes', Rule::in(array_keys(Area::TYPES))],
            'official_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'authority' => ['sometimes', 'nullable', 'string', 'max:120'],
            'approval_status' => ['sometimes', Rule::in(array_keys(Area::APPROVAL_STATUSES))],
            'approval_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'verification_source_url' => ['sometimes', 'nullable', 'url:https,http', 'max:255'],
            'verified_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'confidence' => ['sometimes', 'nullable', Rule::in(['high', 'medium', 'low'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        // A parent cannot be one of this location's own phases or blocks.
        if ($area && ! empty($data['parent_id']) && in_array((int) $data['parent_id'], LocationResolver::withDescendants([$area->id]), true)) {
            abort(back()->withErrors(['parent_id' => 'Choose a parent outside this location.'])->withInput());
        }

        $data += ['latitude' => null, 'longitude' => null];
        $moved = ! $area || (float) $area->latitude !== (float) $data['latitude'] || (float) $area->longitude !== (float) $data['longitude'];
        if ($moved) {
            $data['coordinates_source'] = $data['latitude'] === null ? null : 'manual';
            $data['coordinate_precision'] = $data['latitude'] === null ? null : 'manual';
        }
        if ($request->has('full_edit')) {
            $data['needs_review'] = $request->boolean('needs_review');
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data + ['is_popular' => $request->boolean('is_popular'), 'sort_order' => $data['sort_order'] ?? 100];
    }

    protected function syncAliases(Area $area, string $text): void
    {
        $wanted = collect(preg_split('/\r\n|\r|\n/', $text))->map(fn ($a) => trim($a))->filter()->unique(fn ($a) => LocationName::normalize($a))
            ->reject(fn ($a) => LocationName::normalize($a) === $area->normalized_name)->take(30);

        $area->aliases()->whereNotIn('normalized', $wanted->map(fn ($a) => LocationName::normalize($a)))->delete();
        foreach ($wanted as $alias) {
            AreaAlias::firstOrCreate(['area_id' => $area->id, 'normalized' => LocationName::normalize($alias)], ['alias' => Str::limit($alias, 160, ''), 'city_id' => $area->city_id]);
        }
    }

    protected function slug(string $name, string $city): string
    {
        $base = Str::slug(str_contains(strtolower($name), strtolower($city)) ? $name : $name.' '.$city);
        $slug = $base;
        $i = 2;
        while (Area::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
