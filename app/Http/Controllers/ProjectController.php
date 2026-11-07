<?php

namespace App\Http\Controllers;

use App\Models\InvestmentCalculation;
use App\Models\InvestmentIndexScore;
use App\Models\Project;
use App\Models\Property;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Services\InvestmentIndex\IndexEngine;
use App\Support\Cities;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(protected IndexBrowser $index) {}

    public function index(Request $request): View
    {
        $status = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Project::STATUSES))],
            'category' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', Rule::in(Cities::names())],
            'legal' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $statusVal = $status['status'] ?? null;
        $categoryParam = $status['category'] ?? null;
        $cityVal = $status['city'] ?? null;
        $legalVal = $status['legal'] ?? null;
        $queryVal = trim((string) ($status['q'] ?? ''));

        // Map category slug to model category codes if slug provided
        $categoryCodes = null;
        $categoryConfigs = config('investment_index.categories');
        if ($categoryParam && isset($categoryConfigs[$categoryParam])) {
            $categoryCodes = $categoryConfigs[$categoryParam]['project_categories'];
        } elseif ($categoryParam && in_array($categoryParam, array_keys(config('meezan.investment.project_categories')), true)) {
            $categoryCodes = [$categoryParam];
        }

        $projects = Project::published()
            ->where('is_demo', false)
            ->with([
                'area:id,name,city,slug',
                'discoverySource:id,name,type,url',
                'legalRecords' => fn ($q) => $q->verified()->latest('issued_on'),
            ])
            ->when($statusVal, fn ($q, $s) => $q->where('status', $s))
            ->when($cityVal, fn ($q, $c) => $q->where('city', $c))
            ->when($categoryCodes, fn ($q, $codes) => $q->whereIn('category', $codes))
            ->when($legalVal, function ($q, $legal) {
                if ($legal === 'unverified') {
                    $q->where(fn ($sub) => $sub->whereNull('approval_status')->orWhere('approval_status', '')->orWhere('approval_status', 'unverified'));
                } else {
                    $q->where('approval_status', $legal);
                }
            })
            ->when($queryVal !== '', function ($q) use ($queryVal) {
                $term = '%'.addcslashes($queryVal, '%_').'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('developer', 'like', $term)
                        ->orWhereHas('area', fn ($a) => $a->where('name', 'like', $term));
                });
            })
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'status' => $statusVal,
            'selectedCategory' => $categoryParam,
            'selectedCity' => $cityVal,
            'selectedLegal' => $legalVal,
            'searchQuery' => $queryVal,
            'categories' => $categoryConfigs,
            'cities' => Cities::names(),
            'run' => $this->index->run(),
        ]);
    }

    public function show(Project $project): View
    {
        abort_unless($project->is_published, 404);
        abort_if($project->is_demo && ! InvestmentCalculation::demoVisible(), 404);

        $profile = $this->index->profile($project);
        $score = $profile['score'] ?? InvestmentIndexScore::where('project_id', $project->id)->latest('id')->first();
        $categoryKey = IndexEngine::categoryMap()[$project->category] ?? null;
        $categoryConfig = $categoryKey ? config("investment_index.categories.{$categoryKey}") : null;

        // Investment ranking eligibility diagnosis
        $rankingEligibility = [
            'is_eligible' => $score && $score->status === 'ranked',
            'status' => $score?->status ?? 'insufficient',
            'legal_status' => $project->approval_status ?: ($score?->legal_status ?? 'unverified'),
            'coverage' => $score?->coverage ?? 0.0,
            'missing_factors' => $score
                ? collect($score->factors ?? [])->reject(fn ($f) => $f['display_only'] ?? false)->filter(fn ($f) => ($f['score'] ?? null) === null)->pluck('label')->all()
                : ['Physical development progress', 'Occupancy percentage', 'Verified rental yield', 'Utility connections', 'Building safety certification'],
            'reasons' => $score?->reasons ?? [
                'Appearing on an official directory list establishes statutory existence and approval stage, but does NOT constitute an investment endorsement.',
                'The Meezan Investment Index requires verified evidence covering at least 60% of weighted factor categories before a score or ranking can be calculated.',
                'Legal registration accounts for 20% to 25% of factor weight; remaining operational, occupancy and yield evidence is currently missing.',
            ],
        ];

        return view('projects.show', [
            'project' => $project->load(['area:id,name,city,slug', 'aliases:id,project_id,alias', 'discoverySource:id,name,type,url,publisher', 'legalRecords.source']),
            'profile' => $profile,
            'score' => $score,
            'categoryConfig' => $categoryConfig,
            'rankingEligibility' => $rankingEligibility,
            'listings' => $project->area_id
                ? Property::published()->forCards()->where('area_id', $project->area_id)->latest('published_at')->take(4)->get()
                : collect(),
        ]);
    }
}

