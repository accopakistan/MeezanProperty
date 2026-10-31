<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(array_keys(Project::STATUSES))]])['status'] ?? null;

        return view('projects.index', [
            'projects' => Project::published()->with('area:id,name')
                ->when($status, fn ($q, $s) => $q->where('status', $s))
                ->latest()->paginate(12)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(Project $project): View
    {
        abort_unless($project->is_published, 404);

        return view('projects.show', [
            'project' => $project->load('area'),
            'listings' => $project->area_id
                ? Property::published()->forCards()->where('area_id', $project->area_id)->latest('published_at')->take(4)->get()
                : collect(),
        ]);
    }
}
