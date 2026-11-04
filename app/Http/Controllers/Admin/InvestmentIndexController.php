<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CollectInvestmentSource;
use App\Jobs\RecalculateInvestmentIndex;
use App\Models\InvestmentCollectionRun;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentEvidenceSubmission;
use App\Models\InvestmentException;
use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentLegalRecord;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketSource;
use App\Models\Project;
use App\Services\AuditLogger;
use App\Services\InvestmentIndex\Collection\Registry;
use App\Services\InvestmentIndex\EvidenceSubmissions;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin side of the Meezan Investment Index. Routine work (collecting,
 * matching, verifying official records, scoring, publishing) is automatic;
 * staff here authorize sources once, check owner documents, and decide
 * the exceptions the pipeline will not guess.
 */
class InvestmentIndexController extends Controller
{
    public function overview(): View
    {
        return view('admin.investment-index.overview', [
            'run' => InvestmentIndexRun::latestPublished(),
            'lastRun' => InvestmentIndexRun::latest('id')->first(),
            'counts' => [
                'evidence' => InvestmentEvidence::verified()->count(), 'legal' => InvestmentLegalRecord::verified()->count(),
                'registry' => InvestmentRegistryEntry::whereNull('removed_at')->count(), 'matched' => InvestmentRegistryEntry::whereIn('match_status', ['matched', 'manual'])->count(),
                'exceptions' => InvestmentException::where('status', 'open')->count(), 'submissions' => InvestmentEvidenceSubmission::where('status', 'pending')->count(),
                'sources' => MarketSource::whereNotNull('collector')->count(), 'collectable' => MarketSource::whereNotNull('collector')->get()->filter->isCollectable()->count(),
                'real_projects' => Project::where('is_demo', false)->count(),
            ],
            'collections' => InvestmentCollectionRun::with('source:id,name')->latest('id')->limit(8)->get(),
        ]);
    }

    public function sources(): View
    {
        return view('admin.investment-index.sources', [
            'sources' => MarketSource::whereNotNull('collector')->orderBy('name')->get(),
            'presets' => collect(config('investment_index.collectors'))->reject(fn ($p) => $p['configurable'] ?? false),
            'missingPresets' => collect(config('investment_index.collectors'))->reject(fn ($p, $k) => ($p['configurable'] ?? false) || MarketSource::where('collector', $k)->exists())->count(),
            'runs' => InvestmentCollectionRun::with('source:id,name')->latest('id')->limit(20)->get(),
        ]);
    }

    public function setupSources(): RedirectResponse
    {
        Artisan::call('meezan:investment-collect', ['--setup' => true]);

        return back()->with('status', 'Built-in official sources added. They are inactive until you record their access basis.');
    }

    /** One-time access review: the basis on which automated reading is permitted, or switching the source off. */
    public function authorizeSource(Request $request, MarketSource $source): RedirectResponse
    {
        abort_unless($source->collector, 404);
        if ($request->input('action') === 'deactivate') {
            $source->forceFill(['is_active' => false])->save();
            AuditLogger::record('investment_index.source_deactivated', $source);

            return back()->with('status', "{$source->name} will not be collected.");
        }
        $data = $request->validate([
            'access_basis' => ['required', Rule::in(array_keys(config('investment_index.collection.access_bases')))],
            'terms_url' => ['nullable', 'url:http,https', 'max:2048'],
            'access_note' => ['required', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Confirm that automated reading of this source is permitted.',
            'access_note.required' => 'Note what you checked (terms of use, robots.txt, licence or agreement).']);
        unset($data['confirm']);
        $source->forceFill($data + ['authorized_at' => now(), 'authorized_by' => $request->user()->id, 'is_active' => true])->save();
        AuditLogger::record('investment_index.source_authorized', $source, ['access_basis' => $data['access_basis']]);

        return back()->with('status', "{$source->name} is authorized and will be collected daily.");
    }

    public function collect(MarketSource $source): RedirectResponse
    {
        abort_unless($source->collector, 404);
        if (! $source->isCollectable()) {
            return back()->with('error', 'Authorize and activate this source first.');
        }
        CollectInvestmentSource::dispatch($source->id, 'manual');

        return back()->with('status', "Collection of {$source->name} has been queued.");
    }

    public function registry(Request $request): View
    {
        $filters = $request->validate(['match' => ['nullable', Rule::in(array_keys(InvestmentRegistryEntry::MATCH_STATUSES))],
            'list' => ['nullable', Rule::in(array_keys(InvestmentRegistryEntry::LIST_TYPES))], 'q' => ['nullable', 'string', 'max:100']]);

        return view('admin.investment-index.registry', [
            'filters' => $filters,
            'entries' => InvestmentRegistryEntry::with(['source:id,name', 'project:id,name,slug'])
                ->when($filters['match'] ?? null, fn ($q, $m) => $q->where('match_status', $m))
                ->when($filters['list'] ?? null, fn ($q, $l) => $q->where('list_type', $l))
                ->when($filters['q'] ?? null, fn ($q, $s) => $q->where('scheme_name', 'like', '%'.$s.'%'))
                ->orderByRaw("case match_status when 'ambiguous' then 0 when 'matched' then 1 when 'manual' then 2 else 3 end")->orderBy('scheme_name')
                ->paginate(50)->withQueryString(),
            'projects' => Project::where('is_demo', false)->whereIn('category', Registry::SCHEME_CATEGORIES)->orderBy('name')->get(['id', 'name', 'city']),
        ]);
    }

    public function match(Request $request, InvestmentRegistryEntry $entry, Registry $registry): RedirectResponse
    {
        $data = $request->validate(['project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('is_demo', 0)]]);
        $project = isset($data['project_id']) ? Project::find($data['project_id']) : null;
        $registry->decide($entry, $project, $request->user()->id);
        AuditLogger::record('investment_index.registry_matched', $entry, ['project' => $project?->id]);

        return back()->with('status', $project ? "Matched to {$project->name}; its official record now counts." : 'Marked as not a project on Meezan Property.');
    }

    public function exceptions(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'resolved', 'dismissed'], true) ? $request->query('status') : 'open';

        return view('admin.investment-index.exceptions', [
            'status' => $status,
            'exceptions' => InvestmentException::with(['source:id,name', 'project:id,name,slug', 'registryEntry'])->where('status', $status)->latest('updated_at')->paginate(30)->withQueryString(),
        ]);
    }

    public function resolve(Request $request, InvestmentException $exception): RedirectResponse
    {
        $data = $request->validate(['outcome' => ['required', Rule::in(['resolved', 'dismissed'])], 'resolution' => ['required', 'string', 'max:2000'],
            'withdraw_record' => ['nullable', 'boolean']]);
        // A scheme removed from an approved list: staff may withdraw the legal record it produced.
        if (($data['withdraw_record'] ?? false) && $exception->registry_entry_id) {
            InvestmentLegalRecord::verified()->where('meta->registry_entry_id', $exception->registry_entry_id)->get()
                ->each(fn ($r) => $r->forceFill(['verification_status' => 'superseded', 'rejection_reason' => 'Withdrawn by staff: '.$data['resolution']])->save());
        }
        $exception->forceFill(['status' => $data['outcome'], 'resolution' => $data['resolution'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()])->save();
        AuditLogger::record('investment_index.exception_'.$data['outcome'], $exception, ['type' => $exception->type, 'withdrawn' => (bool) ($data['withdraw_record'] ?? false)]);

        return back()->with('status', 'Exception '.$data['outcome'].'.');
    }

    public function submissions(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'verified', 'rejected'], true) ? $request->query('status') : 'pending';

        return view('admin.investment-index.submissions', [
            'status' => $status,
            'submissions' => InvestmentEvidenceSubmission::with(['project:id,name,slug,category', 'user:id,name,email'])->where('status', $status)->oldest()->paginate(30)->withQueryString(),
        ]);
    }

    public function submission(InvestmentEvidenceSubmission $submission): View
    {
        return view('admin.investment-index.submission', ['s' => $submission->load(['project', 'user', 'reviewer', 'evidence', 'legalRecord'])]);
    }

    public function file(InvestmentEvidenceSubmission $submission, int $index): StreamedResponse
    {
        AuditLogger::record('investment_index.document_viewed', $submission, ['file' => $index]);

        return $submission->streamFile($index);
    }

    /* Methodology: weights change only through a new version ------------------------------- */

    public function methodology(): View
    {
        InvestmentIndexMethodology::current();

        return view('admin.investment-index.methodology', [
            'versions' => InvestmentIndexMethodology::orderByDesc('version')->get(),
            'active' => InvestmentIndexMethodology::active(),
            'draft' => InvestmentIndexMethodology::where('status', 'draft')->latest('version')->first(),
            'categories' => config('investment_index.categories'),
        ]);
    }

    public function createDraft(Request $request): RedirectResponse
    {
        if (InvestmentIndexMethodology::where('status', 'draft')->exists()) {
            return back()->with('error', 'A draft already exists: edit or activate it first.');
        }
        $active = InvestmentIndexMethodology::current();
        $draft = new InvestmentIndexMethodology(['name' => 'Meezan Investment Index v'.($active->version + 1), 'weights' => $active->weights,
            'parameters' => $active->parameters, 'notes' => null]);
        $draft->forceFill(['version' => (int) InvestmentIndexMethodology::max('version') + 1, 'status' => 'draft', 'created_by' => $request->user()->id])->save();
        AuditLogger::record('investment_index.methodology_drafted', $draft, ['from' => $active->version]);

        return back()->with('status', "Draft version {$draft->version} created from version {$active->version}.");
    }

    public function updateDraft(Request $request, InvestmentIndexMethodology $methodology): RedirectResponse
    {
        abort_unless($methodology->status === 'draft', 403, 'Only a draft methodology can be edited.');
        $data = $request->validate(['notes' => ['required', 'string', 'max:2000'], 'weights' => ['required', 'array']],
            ['notes.required' => 'Say why the weights change; this is published with the version.']);
        $methodology->fill(['weights' => InvestmentIndexMethodology::validateWeights($data['weights']), 'notes' => $data['notes']])->save();
        AuditLogger::record('investment_index.methodology_updated', $methodology);

        return back()->with('status', "Draft version {$methodology->version} saved.");
    }

    public function activate(Request $request, InvestmentIndexMethodology $methodology): RedirectResponse
    {
        abort_unless($methodology->status === 'draft', 403, 'Only a draft methodology can be activated.');
        InvestmentIndexMethodology::validateWeights($methodology->weights ?? []);
        DB::transaction(function () use ($methodology, $request) {
            InvestmentIndexMethodology::where('status', 'active')->update(['status' => 'retired']);
            $methodology->forceFill(['status' => 'active', 'activated_by' => $request->user()->id, 'activated_at' => now()])->save();
        });
        AuditLogger::record('investment_index.methodology_activated', $methodology);
        RecalculateInvestmentIndex::schedule('methodology');

        return back()->with('status', "Version {$methodology->version} is active. The Index is being recalculated with it; every change is recorded with the reason \"methodology changed\".");
    }

    public function review(Request $request, InvestmentEvidenceSubmission $submission, EvidenceSubmissions $submissions): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['verify', 'reject'])], 'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject']],
            ['note.required_if' => 'Say why the documents are not accepted; the submitter sees this.']);
        try {
            $data['decision'] === 'verify'
                ? $submissions->verify($submission, $request->user(), $data['note'] ?? null)
                : $submissions->reject($submission, $request->user(), $data['note']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.investment-index.submissions')->with('status', $data['decision'] === 'verify'
            ? 'Verified. The evidence now counts and the Index recalculates automatically.' : 'Rejected; the submitter can see your note.');
    }
}
