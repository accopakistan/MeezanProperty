<?php

namespace App\Http\Controllers;

use App\Models\InvestmentEvidenceSubmission;
use App\Models\Project;
use App\Services\InvestmentIndex\EvidenceSubmissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Owners and managers of a development submit evidence with documents for
 * the Meezan Investment Index. Nothing counts until Meezan staff check the
 * documents; submitting never changes a ranking by itself.
 */
class InvestmentEvidenceController extends Controller
{
    public function create(Request $request, Project $project): View
    {
        abort_unless(EvidenceSubmissions::accepts($project), 404);

        return view('investment-index.submit', [
            'project' => $project,
            'factors' => EvidenceSubmissions::factorsFor($project),
            'mine' => InvestmentEvidenceSubmission::where('project_id', $project->id)->where('user_id', $request->user()->id)->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request, Project $project, EvidenceSubmissions $submissions): RedirectResponse
    {
        abort_unless(EvidenceSubmissions::accepts($project), 404);
        $submissions->store($project, $request->user(), $request->except(['files', '_token']), $request->file('files', []));

        return redirect()->route('investment-index.evidence.create', $project)
            ->with('status', 'Thank you. Your documents were received; Meezan staff will check them before they count in the Investment Index.');
    }
}
