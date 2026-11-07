<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\InvestmentCollectionRun;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\ProjectAlias;
use App\Models\StaffTask;
use App\Services\AuditLogger;
use App\Services\InvestmentIndex\Collection\SchemeNames;
use App\Services\InvestmentIndex\Coverage;
use App\Services\InvestmentIndex\DataValidator;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Services\InvestmentIndex\IndexEngine;
use App\Support\Cities;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The real-world data centre: what is covered, which sources feed it, and the
 * records waiting for a person. Whatever staff add or import starts unverified:
 * a person verifies it here, and the Investment Index recalculates by itself.
 */
class InvestmentCoverageController extends Controller
{
    public function coverage(Coverage $coverage): View
    {
        return view('admin.investment-index.coverage', ['summary' => $coverage->summary(), 'sources' => $coverage->sources(), 'pending' => $coverage->pending(), 'duplicates' => $coverage->possibleDuplicates()]);
    }

    public function verification(Request $request): View
    {
        $only = $request->integer('project') ?: null;
        $onlyProject = $only ? Project::where('is_demo', false)->find($only, ['id', 'name', 'slug', 'city']) : null;

        return view('admin.investment-index.verification', [
            'onlyProject' => $onlyProject,
            'legal' => InvestmentLegalRecord::where('verification_status', 'unverified')->when($onlyProject, fn ($q, $p) => $q->where('project_id', $p->id))->with(['project:id,name,slug,city', 'source:id,name,type'])->latest('id')->paginate(25, ['*'], 'legal_page'),
            'evidence' => InvestmentEvidence::where('verification_status', 'unverified')->when($onlyProject, fn ($q, $p) => $q->where('project_id', $p->id))->with(['project:id,name,slug,city', 'source:id,name,type'])->latest('id')->paginate(25, ['*'], 'evidence_page'),
            'recentlyRejected' => InvestmentLegalRecord::where('verification_status', 'rejected')->with('project:id,name,slug')->latest('updated_at')->limit(10)->get(),
            'recordTypes' => config('investment_index.legal.record_types'),
            'statuses' => config('investment_index.legal.statuses'),
            'severities' => config('investment_index.legal.severities'),
            'factors' => InvestmentEvidence::kinds(),
            'sourceTypes' => array_diff_key(config('meezan.investment.source_types'), ['demo' => 1]),
            'importResult' => session('import_result'),
            'bySource' => MarketSource::whereIn('id', InvestmentLegalRecord::where('verification_status', 'unverified')->select('source_id'))
                ->withCount(['legalRecords as pending_legal' => fn ($q) => $q->where('verification_status', 'unverified')])->get(['id', 'name', 'url']),
        ]);
    }

    /**
     * Verify every waiting legal record from one source, after a person has
     * checked that source's list. Always a person's action, and audited.
     */
    public function reviewSource(Request $request, int $source): RedirectResponse
    {
        $request->validate(['checked' => ['accepted']], ['checked.accepted' => 'Confirm that you checked the list against the source.']);
        $count = 0;
        foreach (InvestmentLegalRecord::where('verification_status', 'unverified')->where('source_id', $source)->get() as $record) {
            try {
                $record->forceFill(['verification_status' => 'verified', 'verification_method' => 'staff_review', 'verified_by' => $request->user()->id, 'verified_at' => now()])->save();
                $count++;
            } catch (DomainException $e) {
                return back()->with('error', $e->getMessage());
            }
        }
        AuditLogger::record('investment_index.source_records_reviewed', MarketSource::findOrFail($source), ['verified' => $count]);

        return back()->with('status', $count.' legal '.str('record')->plural($count).' verified. The Investment Index recalculates automatically.');
    }

    /** Every real project with what is on file and what is missing, for staff to work through. */
    public function research(Request $request, IndexBrowser $index): View
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(IndexEngine::categoryMap()))],
            'city' => ['nullable', Rule::in(Cities::names())],
            'gap' => ['nullable', Rule::in(['legal', 'evidence', 'market', 'developer', 'awaiting'])],
            'q' => ['nullable', 'string', 'max:80'],
        ]);
        $search = addcslashes(trim($filters['q'] ?? ''), '%_\\');
        $query = Project::where('is_demo', false)
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($filters['city'] ?? null, fn ($q, $c) => $q->where('city', $c))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when(($filters['gap'] ?? null) === 'legal', fn ($q) => $q->whereNotIn('id', InvestmentLegalRecord::whereIn('verification_status', ['verified', 'unverified'])->select('project_id')))
            ->when(($filters['gap'] ?? null) === 'evidence', fn ($q) => $q->whereNotIn('id', InvestmentEvidence::whereIn('verification_status', ['verified', 'unverified'])->select('project_id')))
            ->when(($filters['gap'] ?? null) === 'market', fn ($q) => $q->whereNotIn('id', MarketObservation::where('is_demo', false)->whereNotNull('project_id')->select('project_id')))
            ->when(($filters['gap'] ?? null) === 'developer', fn ($q) => $q->whereNull('developer'))
            ->when(($filters['gap'] ?? null) === 'awaiting', fn ($q) => $q->where(fn ($w) => $w->whereIn('id', InvestmentLegalRecord::where('verification_status', 'unverified')->select('project_id'))
                ->orWhereIn('id', InvestmentEvidence::where('verification_status', 'unverified')->select('project_id'))))
            ->withCount([
                'legalRecords as legal_verified' => fn ($q) => $q->where('verification_status', 'verified'),
                'legalRecords as legal_waiting' => fn ($q) => $q->where('verification_status', 'unverified'),
                'evidence as evidence_verified' => fn ($q) => $q->where('verification_status', 'verified'),
                'evidence as evidence_waiting' => fn ($q) => $q->where('verification_status', 'unverified'),
            ])->orderBy('city')->orderBy('name');
        $run = $index->run();
        $real = fn () => Project::where('is_demo', false);
        $byCity = $real()->groupBy('city')->selectRaw('city, count(*) as n')->pluck('n', 'city');
        $byCategory = $real()->groupBy('category')->selectRaw('category, count(*) as n')->pluck('n', 'category');
        $notRejected = ['verified', 'unverified'];

        return view('admin.investment-index.research', [
            'filters' => $filters,
            'projects' => $query->paginate(40)->withQueryString(),
            'totals' => $index->researchTotals(),
            'byCity' => $byCity,
            'byCategory' => $byCategory,
            'gaps' => [
                'Verified legal record' => $real()->whereIn('id', InvestmentLegalRecord::where('verification_status', 'verified')->select('project_id'))->count(),
                'Legal record waiting for a person' => $real()->whereIn('id', InvestmentLegalRecord::where('verification_status', 'unverified')->select('project_id'))->count(),
                'No legal record at all' => $real()->whereNotIn('id', InvestmentLegalRecord::whereIn('verification_status', $notRejected)->select('project_id'))->count(),
                'Other evidence on file' => $real()->whereIn('id', InvestmentEvidence::whereIn('verification_status', $notRejected)->select('project_id'))->count(),
                'Asking or tax figures on file' => $real()->whereIn('id', MarketObservation::where('is_demo', false)->whereNotNull('project_id')->select('project_id'))->count(),
                'Verified market data' => $real()->whereIn('id', MarketObservation::where('is_demo', false)->where('status', 'verified')->whereNotNull('project_id')->select('project_id'))->count(),
                'No developer recorded' => $real()->whereNull('developer')->count(),
            ],
            'sourceCount' => MarketSource::where('is_demo', false)->where('is_active', true)->count(),
            'latestCollections' => InvestmentCollectionRun::with('source:id,name')->latest('id')->limit(5)->get(),
            'latestRuns' => InvestmentIndexRun::latest('id')->limit(5)->get(['id', 'status', 'trigger', 'published_at', 'created_at']),
            'provisionalPublic' => IndexBrowser::provisionalPublic(),
            'provisional' => collect(array_keys(IndexBrowser::categories()))->mapWithKeys(fn ($c) => [$c => $index->provisional($c)]),
            'run' => $run,
        ]);
    }

    /** A note for staff that a project needs more research. Changes no public data. */
    public function requestResearch(Request $request, int $project): RedirectResponse
    {
        $record = Project::where('is_demo', false)->findOrFail($project);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        StaffTask::create(['title' => mb_substr('Research needed: '.$record->name, 0, 200), 'description' => $data['note'], 'category' => 'investment_research', 'related_type' => 'project', 'related_id' => $record->id, 'status' => 'open']);
        AuditLogger::record('investment_index.research_requested', $record);

        return back()->with('status', 'Research request added for '.$record->name.'.');
    }

    /** The owner's choice to show the Provisional Research Ranking to visitors. */
    public function provisionalSwitch(Request $request): RedirectResponse
    {
        $data = $request->validate(['on' => ['required', 'boolean'], 'confirmed' => ['required_if:on,1', 'accepted_if:on,1']]);
        $on = (bool) $data['on'];
        AppSetting::put('investment_index.provisional_public', $on, $request->user()->id);
        AuditLogger::record('investment_index.provisional_switched', $request->user(), ['on' => $on]);

        return back()->with('status', $on ? 'Visitors now see the Provisional Research Ranking, labelled as provisional.' : 'The Provisional Research Ranking is hidden from visitors.');
    }

    public function validation(DataValidator $validator): View
    {
        return view('admin.investment-index.validation', ['report' => $validator->report()]);
    }

    /** Another name for a project, so a list using it matches instead of creating a duplicate. */
    public function storeAlias(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('is_demo', 0)],
            'alias' => ['required', 'string', 'max:200'],
        ]);
        $normalized = SchemeNames::normalize($data['alias']);
        $project = Project::findOrFail($data['project_id']);
        if ($normalized === '' || ProjectAlias::where('project_id', $project->id)->where('normalized', $normalized)->exists()) {
            return back()->with('error', 'That name is empty or already recorded for this project.');
        }
        $alias = new ProjectAlias(['project_id' => $project->id, 'alias' => $data['alias'], 'normalized' => $normalized]);
        $alias->save();
        AuditLogger::record('investment_index.alias_added', $project, ['alias' => $data['alias']]);

        return back()->with('status', 'Alias added to '.$project->name.'.');
    }

    /** One legal record typed by staff from an official document. It starts unverified. */
    public function storeLegal(Request $request): RedirectResponse
    {
        $data = $request->validate($this->legalRules());
        $this->createLegal($data, $request->user()->id);

        return back()->with('status', 'Legal record added as unverified. Verify it below once it has been checked against the original.');
    }

    /** Check one record against its source: verified records count in the Index, rejected ones never do. */
    public function review(Request $request, string $kind, int $id): RedirectResponse
    {
        $class = $kind === 'legal' ? InvestmentLegalRecord::class : InvestmentEvidence::class;
        $record = $class::where('verification_status', 'unverified')->findOrFail($id);
        $data = $request->validate(['decision' => ['required', Rule::in(['verify', 'reject'])], 'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:500']]);
        try {
            $record->forceFill($data['decision'] === 'verify'
                ? ['verification_status' => 'verified', 'verification_method' => 'staff_review', 'verified_by' => $request->user()->id, 'verified_at' => now()]
                : ['verification_status' => 'rejected', 'rejection_reason' => $data['reason']])->save();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditLogger::record('investment_index.record_reviewed', $record, ['decision' => $data['decision'], 'kind' => $kind]);

        return back()->with('status', $data['decision'] === 'verify' ? 'Verified. The Investment Index recalculates automatically.' : 'Rejected. The record never counts.');
    }

    /**
     * CSV of legal records or evidence. Every row is validated; a row that is
     * already on file is skipped, nothing verified is overwritten, and every
     * accepted row waits here for a person.
     */
    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['legal', 'evidence'])], 'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($handle) ?: []);
        $result = ['added' => 0, 'duplicates' => 0, 'errors' => []];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false && $line < 2001) {
            $line++;
            if (count($row) !== count($header)) {
                $result['errors'][] = ['row' => $line, 'errors' => ['The number of columns does not match the header.']];

                continue;
            }
            $values = array_map(fn ($v) => trim((string) $v) === '' ? null : trim((string) $v), array_combine($header, $row));
            $v = Validator::make($values, $data['kind'] === 'legal' ? $this->legalRules() : $this->evidenceRules());
            if ($v->fails()) {
                $result['errors'][] = ['row' => $line, 'errors' => $v->errors()->all()];

                continue;
            }
            $created = $data['kind'] === 'legal' ? $this->createLegal($v->validated(), $request->user()->id) : $this->createEvidence($v->validated(), $request->user()->id);
            $result[$created ? 'added' : 'duplicates']++;
        }
        fclose($handle);
        AuditLogger::record('investment_index.records_imported', null, ['kind' => $data['kind']] + array_diff_key($result, ['errors' => 1]) + ['rejected' => count($result['errors'])]);

        return back()->with('status', "{$result['added']} added as unverified, {$result['duplicates']} already on file, ".count($result['errors']).' rejected.')->with('import_result', $result);
    }

    protected function legalRules(): array
    {
        return ['project_slug' => ['required', Rule::exists('projects', 'slug')->where('is_demo', 0)], 'authority' => ['required', 'string', 'max:80'],
            'record_type' => ['required', Rule::in(array_keys(config('investment_index.legal.record_types')))], 'status' => ['required', Rule::in(array_keys(config('investment_index.legal.statuses')))],
            'severity' => ['nullable', Rule::in(array_keys(config('investment_index.legal.severities')))], 'reference' => ['nullable', 'string', 'max:160'], 'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'source_url' => ['required', 'url:http,https', 'max:2048'], 'excerpt' => ['nullable', 'string', 'max:2000']];
    }

    protected function evidenceRules(): array
    {
        return ['project_slug' => ['required', Rule::exists('projects', 'slug')->where('is_demo', 0)], 'factor' => ['required', Rule::in(InvestmentEvidence::kinds())], 'subject' => ['nullable', 'string', 'max:60'],
            'value_numeric' => ['nullable', 'required_without:value_level', 'numeric'], 'value_level' => ['nullable', 'required_without:value_numeric', 'string', 'max:40'], 'unit' => ['nullable', 'string', 'max:20'],
            'observed_on' => ['required', 'date', 'before_or_equal:today'], 'source_name' => ['required', 'string', 'max:160'],
            'source_type' => ['required', Rule::in(array_keys(array_diff_key(config('meezan.investment.source_types'), ['demo' => 1, 'public_asking_prices' => 1])))], 'source_url' => ['required', 'url:http,https', 'max:2048']];
    }

    /** @return bool false when the same record is already on file */
    protected function createLegal(array $d, int $userId): bool
    {
        $project = Project::where('slug', $d['project_slug'])->firstOrFail();
        $source = $this->source($d['authority'].' — official record', 'official_record', $d['source_url'], $d['authority'], $userId);
        $record = new InvestmentLegalRecord(['project_id' => $project->id, 'authority' => $d['authority'], 'record_type' => $d['record_type'], 'status' => $d['status'], 'severity' => $d['severity'] ?? 'none',
            'reference' => $d['reference'] ?? null, 'issued_on' => $d['issued_on'] ?? null, 'source_id' => $source->id, 'source_url' => $d['source_url'], 'collected_at' => now(), 'excerpt' => $d['excerpt'] ?? null, 'verification_method' => 'staff_entry']);
        $record->forceFill(['verification_status' => 'unverified', 'created_by' => $userId]);
        if (InvestmentLegalRecord::where('fingerprint', (fn () => $this->fingerprintFor())->call($record))->exists()) {
            return false;
        }
        $record->save();

        return true;
    }

    /** @return bool false when the same record is already on file */
    protected function createEvidence(array $d, int $userId): bool
    {
        $project = Project::where('slug', $d['project_slug'])->firstOrFail();
        $source = $this->source($d['source_name'], $d['source_type'], $d['source_url'], $d['source_name'], $userId);
        $record = new InvestmentEvidence(['project_id' => $project->id, 'factor' => $d['factor'], 'subject' => $d['subject'] ?? null, 'value_numeric' => $d['value_numeric'] ?? null, 'value_level' => $d['value_level'] ?? null,
            'unit' => $d['unit'] ?? null, 'observed_on' => $d['observed_on'], 'source_id' => $source->id, 'source_url' => $d['source_url'], 'collected_at' => now(), 'verification_method' => 'staff_entry']);
        $record->forceFill(['verification_status' => 'unverified', 'created_by' => $userId]);
        if (InvestmentEvidence::where('fingerprint', (fn () => $this->fingerprintFor())->call($record))->exists()) {
            return false;
        }
        $record->save();

        return true;
    }

    protected function source(string $name, string $type, string $url, string $publisher, int $userId): MarketSource
    {
        $source = MarketSource::firstOrNew(['name' => mb_substr($name, 0, 160), 'type' => $type]);
        if (! $source->exists) {
            $source->fill(['url' => $url, 'publisher' => mb_substr($publisher, 0, 160), 'reliability' => $type === 'official_record' ? 'high' : 'medium']);
            $source->forceFill(['is_active' => true, 'access_method' => 'staff_entry', 'created_by' => $userId])->save();
        }

        return $source;
    }

    /**
     * Admin Evidence Gap Analysis: identifies missing evaluation factors for each project
     * and recommends verified source types required to bridge the gap to 60% ranking eligibility.
     */
    public function evidenceGaps(Request $request): View
    {
        $categoryKey = $request->query('category', 'housing-societies');
        $city = $request->query('city');
        $categories = config('investment_index.categories');
        $category = $categories[$categoryKey] ?? $categories['housing-societies'];
        $factors = $category['factors'] ?? [];

        $run = InvestmentIndexRun::latestPublished();
        $query = $run ? $run->scores()->where('category', $categoryKey) : InvestmentIndexScore::where('category', $categoryKey);

        if ($city && in_array($city, Cities::names(), true)) {
            $query->whereHas('project', fn ($q) => $q->where('city', $city));
        }

        $scores = $query->with('project:id,name,slug,city,developer,approval_status')
            ->orderBy('coverage', 'asc')
            ->paginate(30)
            ->withQueryString();

        // Build factor source recommendations
        $sourceRecommendations = [];
        foreach ($factors as $factorKey => $fConfig) {
            $acceptedTypes = config("investment_index.factors.{$factorKey}.sources") ?? [];
            $sourceRecommendations[$factorKey] = [
                'label' => $fConfig['label'],
                'weight' => $fConfig['weight'],
                'sources' => $acceptedTypes,
                'action' => match ($factorKey) {
                    'legal_approval' => 'Official authority gazettes and web portals (LDA, CDA, FDA, RDA, NAB)',
                    'development_progress' => 'Certified site engineering survey, audited developer construction milestone statement',
                    'occupancy' => 'Physical door-to-door census, resident association registers, utility meter activation counts',
                    'rental_income' => 'Audited REIT financial distributions, registered excise rental tax deeds',
                    'utilities' => 'WAPDA/LESCO demand notices, SNGPL gas meters, WASA sewerage connectivity audit',
                    'structural_safety' => 'SBCA / LDA vetted structural drawings, Pakistan Engineering Council (PEC) certified audit',
                    'market_demand' => 'Verified transaction deed registrations, authorized valuer liquidity surveys',
                    'developer_track_record' => 'Portfolio completion records across developer previous schemes',
                    default => 'Official or audited documentation',
                },
            ];
        }

        return view('admin.investment-index.evidence-gaps', [
            'categoryKey' => $categoryKey,
            'category' => $category,
            'categories' => $categories,
            'city' => $city,
            'cities' => Cities::names(),
            'scores' => $scores,
            'factors' => $factors,
            'sourceRecommendations' => $sourceRecommendations,
            'threshold' => (float) config('investment_index.parameters.min_coverage', 0.6),
        ]);
    }
}
