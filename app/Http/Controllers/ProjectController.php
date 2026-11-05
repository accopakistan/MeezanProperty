<?php

namespace App\Http\Controllers;

use App\Models\InvestmentCalculation;
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
            // Demo projects (Investment Rankings preview) are fictional: never listed as real developments.
            'projects' => Project::published()->where('is_demo', false)->with('area:id,name')
                ->when($status, fn ($q, $s) => $q->where('status', $s))
                ->latest()->paginate(12)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(Project $project): View
    {
        abort_unless($project->is_published, 404);
        // A demo project's page exists only while demo mode shows the demo rankings that link to it.
        abort_if($project->is_demo && ! InvestmentCalculation::demoVisible(), 404);

        return view('projects.show', [
            'project' => $project->load('area'),
            'listings' => $project->area_id
                ? Property::published()->forCards()->where('area_id', $project->area_id)->latest('published_at')->take(4)->get()
                : collect(),
        ]);
    }
}
