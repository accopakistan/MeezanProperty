<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('admin.projects.index', ['projects' => Project::with('area:id,name')->latest()->paginate(25)]);
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
        ]);

        $project->fill(collect($data)->except(['cover', 'is_published'])->all());
        $project->is_published = $request->boolean('is_published');

        if ($request->hasFile('cover')) {
            if ($project->cover_path) {
                Storage::disk('public')->delete($project->cover_path);
            }
            $project->cover_path = $request->file('cover')->store('projects', 'public');
        }

        $project->save();
    }
}
