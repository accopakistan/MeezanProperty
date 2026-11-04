<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentMethodology;
use App\Models\Project;
use App\Services\AuditLogger;
use App\Services\Investment\RankingBrowser;
use App\Services\Investment\RankingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Scoring methodologies (versioned weights) and recalculation runs:
 * run, review, publish. Publishing never edits earlier snapshots.
 */
class InvestmentAdminController extends Controller
{
    public function methodologies(): View
    {
        return view('admin.market.methodologies', [
            'versions' => InvestmentMethodology::orderByDesc('version')->get(),
            'factors' => config('meezan.investment.factors'),
            'categories' => config('meezan.investment.categories'),
        ]);
    }

    /** Ranking categories (config meezan.investment.segments): what feeds each one, and how much data it has. */
    public function categories(): View
    {
        $calc = InvestmentCalculation::current();
        $rows = $calc ? $calc->rankings()->whereNotNull('segment')->get(['segment', 'status', 'city']) : collect();
        $projects = Project::query()->toBase()->selectRaw('category, is_published, count(*) as n')->groupBy('category', 'is_published')->get();

        return view('admin.market.categories', [
            'segments' => collect(RankingBrowser::segments())->map(fn ($seg, $slug) => $seg + [
                'rows' => $rows->where('segment', $slug)->count(),
                'ranked' => $rows->where('segment', $slug)->where('status', 'ranked')->count(),
                'cities' => $rows->where('segment', $slug)->pluck('city')->unique()->count(),
                'published_projects' => (int) $projects->whereIn('category', $seg['project_types'])->where('is_published', 1)->sum('n'),
                'hidden_projects' => (int) $projects->whereIn('category', $seg['project_types'])->where('is_published', 0)->sum('n'),
            ]),
            'uncategorised' => (int) $projects->whereNull('category')->sum('n'),
            'calc' => $calc,
        ]);
    }

    /** New draft version, copied from the active one. */
    public function createMethodology(Request $request): RedirectResponse
    {
        $active = InvestmentMethodology::active();
        $draft = new InvestmentMethodology([
            'name' => 'Draft based on v'.($active?->version ?? 0),
            'weights' => $active?->weights ?? config('meezan.investment.default_weights'),
            'parameters' => $active?->parameters ?? config('meezan.investment.default_parameters'),
        ]);
        $draft->forceFill(['version' => (int) InvestmentMethodology::max('version') + 1, 'status' => 'draft', 'created_by' => $request->user()->id])->save();
        AuditLogger::record('investment.methodology_created', $draft, ['from' => $active?->version]);

        return redirect()->route('admin.market.methodologies.edit', $draft)->with('status', 'Draft version '.$draft->version.' created.');
    }

    public function editMethodology(InvestmentMethodology $methodology): View
    {
        return view('admin.market.methodology-form', [
            'methodology' => $methodology,
            'factors' => config('meezan.investment.factors'),
            'categories' => config('meezan.investment.categories'),
        ]);
    }

    public function updateMethodology(Request $request, InvestmentMethodology $methodology): RedirectResponse
    {
        abort_unless($methodology->isEditable(), 409, 'Active and retired methodology versions cannot be changed.');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:2000'], 'weights' => ['required', 'array']]);
        $weights = InvestmentMethodology::validateWeights($data['weights']);
        $methodology->forceFill(['name' => $data['name'], 'notes' => $data['notes'] ?? null, 'weights' => $weights])->save();
        AuditLogger::record('investment.methodology_updated', $methodology, ['weights' => $weights]);

        return back()->with('status', 'Draft saved. Weights in every category add up to 100.');
    }

    public function activateMethodology(Request $request, InvestmentMethodology $methodology): RedirectResponse
    {
        abort_unless($methodology->isEditable(), 409);
        InvestmentMethodology::validateWeights($methodology->weights);
        DB::transaction(function () use ($methodology, $request) {
            InvestmentMethodology::where('status', 'active')->update(['status' => 'retired']);
            $methodology->forceFill(['status' => 'active', 'activated_by' => $request->user()->id, 'activated_at' => now()])->save();
        });
        AuditLogger::record('investment.methodology_activated', $methodology);

        return back()->with('status', 'Version '.$methodology->version.' is now active. Published rankings keep the version they were calculated with; run a new calculation to use it.');
    }

    public function calculations(): View
    {
        return view('admin.market.calculations', [
            'calculations' => InvestmentCalculation::with('triggeredBy:id,name')->latest('id')->paginate(20),
            'methodology' => InvestmentMethodology::active(),
        ]);
    }

    public function runCalculation(Request $request, RankingCalculator $calculator): RedirectResponse
    {
        $calc = $calculator->run($request->user(), 'manual');

        return redirect()->route('admin.market.calculations.show', $calc)
            ->with('status', $calc->status === 'completed' ? 'Calculation finished. Review the results, then publish.' : 'Calculation failed: '.$calc->error);
    }

    public function showCalculation(Request $request, InvestmentCalculation $calculation): View
    {
        $category = array_key_exists((string) $request->query('category'), config('meezan.investment.categories')) ? $request->query('category') : 'overall';

        return view('admin.market.calculation', [
            'calc' => $calculation->load('triggeredBy:id,name'),
            'category' => $category,
            'rows' => $calculation->rankings()->where('category', $category)
                ->orderByRaw('case when status = ? then 0 else 1 end', ['ranked'])->orderBy('asset_class')->orderBy('rank')->orderBy('entity_name')->paginate(50)->withQueryString(),
        ]);
    }

    public function publish(Request $request, InvestmentCalculation $calculation, RankingCalculator $calculator): RedirectResponse
    {
        if ($calculation->status !== 'completed') {
            return back()->withErrors(['publish' => 'Only a completed, unpublished calculation can be published.']);
        }
        $calculator->publish($calculation, $request->user());

        return back()->with('status', 'Published. The previous publication is kept as history.');
    }
}
