<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaBoundary;
use App\Models\AuditLog;
use App\Models\City;
use App\Services\AuditLogger;
use App\Support\BoundaryGeometry;
use App\Support\Cities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Society / area boundaries (admin only; the route group requires the admin
 * role). Every outline belongs to an existing location; geometry is
 * validated by BoundaryGeometry before it is stored, and nothing is public
 * until an admin has verified and then published it.
 */
class BoundaryController extends Controller
{
    public function index(Request $request): View
    {
        $city = in_array($request->query('city'), Cities::names(), true) ? $request->query('city') : null;
        $status = array_key_exists((string) $request->query('status'), AreaBoundary::STATUSES) ? $request->query('status') : null;
        $term = trim((string) $request->query('q'));

        $boundaries = AreaBoundary::query()
            ->with(['area:id,name,city,slug', 'creator:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'archived'))
            ->when($city, fn ($q) => $q->whereHas('area', fn ($a) => $a->where('city', $city)))
            ->when($term !== '', fn ($q) => $q->whereHas('area', fn ($a) => $a->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%')))
            ->latest('updated_at')->paginate(25)->withQueryString();

        // Locations to start a new outline for (search by name).
        $candidates = $term === '' ? collect() : Area::active()->whereIn('city', Cities::names())
            ->when($city, fn ($q) => $q->where('city', $city))
            ->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%')
            ->withCount(['boundaries as published_count' => fn ($q) => $q->published()])
            ->ordered()->limit(15)->get(['id', 'name', 'city', 'type']);

        return view('admin.boundaries.index', [
            'boundaries' => $boundaries,
            'candidates' => $candidates,
            'counts' => AreaBoundary::query()->toBase()->groupBy('status')->pluck(DB::raw('count(*)'), 'status')->map(fn ($n) => (int) $n)->all(),
            'filters' => ['city' => $city, 'status' => $status, 'q' => $term],
        ]);
    }

    public function create(Request $request): View
    {
        $area = Area::active()->whereIn('city', Cities::names())->findOrFail((int) $request->query('area'));

        return $this->editor(new AreaBoundary(['source' => 'admin_drawn']), $area);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $area = Area::active()->whereIn('city', Cities::names())->findOrFail($data['area_id']);

        $boundary = new AreaBoundary(Arr::only($data, ['source', 'source_reference', 'notes']));
        $boundary->area()->associate($area);
        $boundary->setGeometry($data['shape'])->forceFill(['status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();

        AuditLogger::record('boundary.created', $boundary, ['area' => $area->name, 'vertices' => $boundary->vertex_count, 'source' => $boundary->source]);

        return redirect()->route('admin.boundaries.edit', $boundary)->with('status', 'Draft boundary saved for '.$area->name.'. Check it, then mark it verified.');
    }

    public function edit(AreaBoundary $boundary): View
    {
        return $this->editor($boundary, $boundary->load('area')->area);
    }

    public function update(Request $request, AreaBoundary $boundary): RedirectResponse
    {
        abort_unless($boundary->isEditable(), 409, 'Only drafts can be edited. Create a new draft from this boundary instead.');
        $data = $this->validated($request, $boundary);

        $before = $boundary->vertex_count;
        $boundary->fill(Arr::only($data, ['source', 'source_reference', 'notes']))->setGeometry($data['shape'])->forceFill(['updated_by' => $request->user()->id])->save();
        AuditLogger::record('boundary.updated', $boundary, ['vertices' => ['before' => $before, 'after' => $boundary->vertex_count]]);

        return back()->with('status', 'Draft saved.');
    }

    /** verify · reopen · publish · unpublish · archive */
    public function transition(Request $request, AreaBoundary $boundary): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(array_keys($boundary->nextActions()))]])['action'];
        $user = $request->user()->id;

        DB::transaction(function () use ($boundary, $action, $user) {
            match ($action) {
                'verify' => $boundary->forceFill(['status' => 'verified', 'verified_by' => $user, 'verified_at' => now()]),
                'reopen' => $boundary->forceFill(['status' => 'draft', 'verified_by' => null, 'verified_at' => null]),
                'unpublish' => $boundary->forceFill(['status' => 'verified', 'published_at' => null, 'published_by' => null]),
                'archive' => $boundary->forceFill(['status' => 'archived', 'archived_at' => now()]),
                'publish' => $boundary->forceFill(['status' => 'published', 'published_by' => $user, 'published_at' => now()]),
            };

            // One published outline per location: publishing replaces the previous one.
            if ($action === 'publish') {
                AreaBoundary::where('area_id', $boundary->area_id)->whereKeyNot($boundary->id)->published()->get()
                    ->each(function (AreaBoundary $old) {
                        $old->forceFill(['status' => 'archived', 'archived_at' => now()])->save();
                        AuditLogger::record('boundary.archived', $old, ['reason' => 'replaced by a newer published boundary']);
                    });
            }

            $boundary->forceFill(['updated_by' => $user])->save();
            AuditLogger::record('boundary.'.['verify' => 'verified', 'reopen' => 'reopened', 'publish' => 'published', 'unpublish' => 'unpublished', 'archive' => 'archived'][$action], $boundary);
        });

        return back()->with('status', 'Boundary '.strtolower($boundary->statusLabel()).'.');
    }

    /** Start a new draft from an existing outline (verified and published ones are never edited in place). */
    public function duplicate(Request $request, AreaBoundary $boundary): RedirectResponse
    {
        $copy = $boundary->replicate(['status', 'verified_by', 'verified_at', 'published_by', 'published_at', 'archived_at', 'created_by', 'updated_by']);
        $copy->forceFill(['status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();
        AuditLogger::record('boundary.created', $copy, ['copied_from' => $boundary->id]);

        return redirect()->route('admin.boundaries.edit', $copy)->with('status', 'New draft created from boundary #'.$boundary->id.'.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?AreaBoundary $boundary = null): array
    {
        $data = $request->validate([
            'area_id' => [$boundary ? 'prohibited' : 'required', 'integer'],
            'geometry' => ['required', 'string', 'max:2000000'],
            'source' => ['required', Rule::in(array_keys(AreaBoundary::SOURCES))],
            'source_reference' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->input('source') !== 'admin_drawn')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'source_reference.required' => 'Say where imported data came from and under which licence.',
        ]);

        $data['shape'] = BoundaryGeometry::parse($data['geometry']);
        unset($data['geometry']);

        return $data;
    }

    protected function editor(AreaBoundary $boundary, Area $area): View
    {
        $shape = $boundary->exists ? $boundary->shape() : null;

        // Other published outlines nearby, for reference while drawing.
        $centre = $area->hasCoordinates() ? [(float) $area->latitude, (float) $area->longitude] : City::centre($area->city);
        $neighbours = $centre ? AreaBoundary::published()->where('area_id', '!=', $area->id)
            ->intersecting($centre[0] - 0.15, $centre[1] - 0.15, $centre[0] + 0.15, $centre[1] + 0.15)
            ->with('area:id,name')->limit(100)->get() : collect();

        return view('admin.boundaries.edit', [
            'boundary' => $boundary,
            'area' => $area,
            'centre' => $centre,
            'neighbours' => $neighbours,
            // A warning, not a rule: the recorded centre may itself be approximate.
            'centreOutside' => $shape && $area->hasCoordinates() && ! $shape->contains((float) $area->latitude, (float) $area->longitude),
            'versions' => $area->boundaries()->with('creator:id,name')->latest('id')->get(),
            'history' => $boundary->exists ? AuditLog::with('user:id,name')->where('auditable_type', $boundary->getMorphClass())->where('auditable_id', $boundary->id)->latest('id')->limit(20)->get() : collect(),
        ]);
    }
}
