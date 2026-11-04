<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Project;
use App\Services\AuditLogger;
use App\Services\Investment\ProjectImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function index(): View
    {
        $origin = in_array(request('origin'), ['demo', 'real'], true) ? request('origin') : null;

        return view('admin.projects.index', ['origin' => $origin, 'projects' => Project::with('area:id,name')
            ->when($origin, fn ($q) => $q->where('is_demo', $origin === 'demo'))->latest()->paginate(25)->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.projects.form', ['project' => new Project(['city' => config('meezan.default_city'), 'status' => 'under_construction']), 'areas' => Area::ordered()->get(['id', 'name'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $project = new Project;
        $this->save($request, $project);
        AuditLogger::record('project.created', $project);

        return redirect()->route('admin.projects.index')->with('status', 'Project saved.');
    }

    public function edit(Project $project): View
    {
        return view('admin.projects.form', ['project' => $project, 'areas' => Area::ordered()->get(['id', 'name'])]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->save($request, $project);
        AuditLogger::record('project.updated', $project);

        return redirect()->route('admin.projects.index')->with('status', 'Project updated.');
    }

    protected function save(Request $request, Project $project): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'developer' => ['nullable', 'string', 'max:160'],
            'city' => ['required', Rule::in(config('meezan.cities'))],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'website' => ['nullable', 'url', 'max:255'],
            'approval_note' => ['nullable', 'string', 'max:255'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_published' => ['boolean'],
            // Investment Rankings: what the project is, where it is, and its documents.
            'category' => ProjectImport::rules()['category'],
            'latitude' => ProjectImport::rules()['latitude'],
            'longitude' => ProjectImport::rules()['longitude'],
            'approval_status' => ProjectImport::rules()['approval_status'],
            'launched_on' => ProjectImport::rules()['launched_on'],
            'specifications' => ['nullable', 'string', 'max:3000'],
            'gallery' => ['nullable', 'array', 'max:8'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_gallery' => ['nullable', 'array'],
            'remove_gallery.*' => ['string'],
        ]);

        $project->fill(collect($data)->except(['cover', 'is_published', 'specifications', 'gallery', 'remove_gallery'])->all());
        $project->is_published = $request->boolean('is_published');
        $project->specifications = $this->specifications($data['specifications'] ?? null);

        // Gallery: remove ticked photos (only this project's own), add new ones, at most 8.
        $gallery = collect($project->gallery ?? []);
        $remove = $gallery->intersect($data['remove_gallery'] ?? []);
        $remove->each(fn ($path) => Storage::disk('public')->delete($path));
        $gallery = $gallery->diff($remove)->values();
        foreach ($request->file('gallery', []) as $file) {
            if ($gallery->count() < 8) {
                $gallery->push($file->store('projects/gallery', 'public'));
            }
        }
        $project->gallery = $gallery->all() ?: null;

        if ($request->hasFile('cover')) {
            if ($project->cover_path) {
                Storage::disk('public')->delete($project->cover_path);
            }
            $project->cover_path = $request->file('cover')->store('projects', 'public');
        }

        $project->save();
    }

    /** "Label: value" lines → [label => value] (at most 30 lines). */
    protected function specifications(?string $text): ?array
    {
        $specs = collect(preg_split('/\R/', (string) $text))->map(fn ($line) => array_map('trim', explode(':', $line, 2)))
            ->filter(fn ($pair) => count($pair) === 2 && $pair[0] !== '' && $pair[1] !== '')->take(30)
            ->mapWithKeys(fn ($pair) => [mb_substr($pair[0], 0, 60) => mb_substr($pair[1], 0, 200)]);

        return $specs->isEmpty() ? null : $specs->all();
    }

    public function importForm(): View
    {
        return view('admin.projects.import', ['columns' => ProjectImport::COLUMNS]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $result = ProjectImport::import($request->file('file'), $request->user());

        return redirect()->route('admin.projects.import')->with('import', $result)
            ->with('status', "{$result['imported']} imported (hidden until published), {$result['duplicates']} already existed, ".count($result['errors']).' rejected.');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ProjectImport::COLUMNS);
            fputcsv($out, ['Example Mall (replace)', 'Example Developers', 'shopping_mall', 'Lahore', '', '31.5204', '74.3587', 'under_construction', '', '', '', 'Describe the project in at least thirty characters, as verified with the developer.', '', '', '']);
            fclose($out);
        }, 'projects-template.csv', ['Content-Type' => 'text/csv']);
    }
}
