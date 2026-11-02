<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\PipelineStage;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Http\Controllers\Controller;
use App\Models\LeadActivity;
use App\Models\LeadAppointment;
use App\Models\LeadAssignment;
use App\Models\LeadFollowUp;
use App\Models\LeadRecommendation;
use App\Models\Property;
use App\Services\Crm\CrmStats;
use App\Services\Crm\DealerCrm;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\PropertyMatcher;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The dealer's CRM: dashboard, Kanban pipeline, filtered lead list, lead
 * workspace and agenda. Every query starts from the signed-in dealer's own
 * active assignments; every change goes through DealerCrm.
 */
class CrmController extends Controller
{
    public function __construct(protected DealerCrm $crm) {}

    public function dashboard(Request $request, CrmStats $stats): View
    {
        $dealer = $request->user();
        $mine = fn () => LeadFollowUp::where('dealer_id', $dealer->id)->whereHas('assignment', fn ($q) => $q->active());
        $today = LocalTime::today(); // the dealer's day in Pakistan time

        return view('dealer.crm.dashboard', [
            'stageCounts' => $stats->stageCounts($dealer->id),
            'newLeads' => $this->assignments($request)->where('pipeline_stage', 'new')->count(),
            'dueToday' => $mine()->pending()->whereBetween('due_at', [now(), $today[1]])->count(),
            'overdue' => $mine()->overdue()->count(),
            'stale' => $this->assignments($request)->whereNotNull('stale_flagged_at')->count(),
            'viewingsToday' => LeadAppointment::where('dealer_id', $dealer->id)->where('type', 'viewing')->where('status', 'scheduled')
                ->whereBetween('starts_at', $today)->count(),
            'upcomingFollowUps' => $mine()->pending()->with('assignment.lead.area:id,name', 'assignment.lead.requirement:id,city,type,purpose')
                ->orderBy('due_at')->take(8)->get(),
            'todayAppointments' => LeadAppointment::where('dealer_id', $dealer->id)->where('status', 'scheduled')
                ->whereBetween('starts_at', $today)->with('assignment.lead.area:id,name', 'property:id,title,reference')
                ->orderBy('starts_at')->get(),
            'activity' => LeadActivity::whereHas('assignment', fn ($q) => $q->where('dealer_id', $dealer->id))
                ->with('actor:id,name')->latest('id')->take(10)->get(),
            'performance' => $stats->performance([$dealer->id])[$dealer->id],
        ]);
    }

    public function pipeline(Request $request): View
    {
        $filters = $this->filters($request);
        $perColumn = (int) config('meezan.crm.kanban_per_column');
        $columns = [];

        foreach (PipelineStage::columns() as $key => $column) {
            $stages = collect(PipelineStage::cases())->filter(fn (PipelineStage $s) => $s->column() === $key)->map->value->values()->all();
            $query = $this->filtered($request, $filters)->whereIn('pipeline_stage', $stages);
            $columns[$key] = $column + [
                'count' => (clone $query)->count(),
                'cards' => $query->with($this->cardRelations())->withCount(['followUps as overdue_count' => fn ($q) => $q->overdue()])
                    ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
                    ->latest('last_activity_at')->take($perColumn)->get(),
            ];
        }

        return view('dealer.crm.pipeline', ['columns' => $columns, 'filters' => $filters, 'perColumn' => $perColumn] + $this->filterOptions($request));
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('dealer.crm.index', [
            'assignments' => $this->filtered($request, $filters)->with($this->cardRelations())
                ->withCount(['followUps as overdue_count' => fn ($q) => $q->overdue()])
                ->with(['followUps' => fn ($q) => $q->pending()->orderBy('due_at')])
                ->latest('assigned_at')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ] + $this->filterOptions($request));
    }

    public function show(LeadAssignment $assignment, PropertyMatcher $matcher): View
    {
        Gate::authorize('view', $assignment);
        $assignment->load([
            'lead.requirement.user:id,name,email,phone', 'lead.area:id,name,city', 'lead.property', 'lead.inquiry',
            'notes.author:id,name', 'recommendations.property.coverImage', 'wonProperty:id,title,reference',
            'followUps' => fn ($q) => $q->orderByRaw("case status when 'pending' then 0 else 1 end")->orderBy('due_at'),
            'appointments' => fn ($q) => $q->with('property:id,title,reference,slug')->orderByDesc('starts_at'),
        ]);
        $lead = $assignment->lead;
        $criteria = $lead->requirement ? MatchCriteria::fromRequirement($lead->requirement) : ($lead->property ? new MatchCriteria(
            purpose: $lead->property->purpose, type: $lead->property->type, city: $lead->property->city, areaIds: array_filter([$lead->property->area_id]),
            maxPrice: (int) round($lead->property->price * 1.15),
        ) : null);
        $recommended = $assignment->recommendations->pluck('property_id');

        return view('dealer.crm.show', [
            'assignment' => $assignment,
            'lead' => $lead,
            'matches' => $criteria ? $matcher->match($criteria)->matches->reject(fn ($m) => $recommended->contains($m->property->id) || $m->property->id === $lead->property_id) : collect(),
            'timeline' => $assignment->activities()->with('actor:id,name')->take(50)->get(),
            'stages' => $assignment->pipeline_stage->allowedNext(),
        ]);
    }

    public function agenda(Request $request): View
    {
        $dealer = $request->user();
        $base = LeadFollowUp::where('dealer_id', $dealer->id)->whereHas('assignment', fn ($q) => $q->active())
            ->with('assignment.lead.area:id,name', 'assignment.lead.requirement:id,city,type,purpose', 'assignment.lead.property:id,title,reference');

        return view('dealer.crm.agenda', [
            'overdue' => (clone $base)->overdue()->orderBy('due_at')->get(),
            'upcoming' => (clone $base)->pending()->where('due_at', '>=', now())->orderBy('due_at')->paginate(20, ['*'], 'followups')->withQueryString(),
            'appointments' => LeadAppointment::where('dealer_id', $dealer->id)->where('status', 'scheduled')->where('starts_at', '>=', LocalTime::today()[0])
                ->whereHas('assignment', fn ($q) => $q->active())
                ->with('assignment.lead.area:id,name', 'property:id,title,reference,slug')->orderBy('starts_at')->paginate(20, ['*'], 'appointments')->withQueryString(),
        ]);
    }

    /* Changes ------------------------------------------------------------- */

    public function updateStage(Request $request, LeadAssignment $assignment): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $assignment);
        $data = $request->validate([
            'stage' => ['required', Rule::enum(PipelineStage::class)],
            'reason' => ['nullable', Rule::in(array_keys(config('meezan.crm.lost_reasons')))],
            'note' => ['nullable', 'string', 'max:500'],
            'transaction_type' => ['nullable', Rule::in(['sale', 'rent'])],
            'property_id' => ['nullable', 'integer'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        return $this->attempt($request, fn () => $this->crm->changeStage($assignment, PipelineStage::from($data['stage']), $request->user(), $data),
            'Moved to '.PipelineStage::from($data['stage'])->label().'.', 'stage');
    }

    public function updatePriority(Request $request, LeadAssignment $assignment): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $assignment);
        $data = $request->validate(['priority' => ['required', Rule::in(array_keys(config('meezan.crm.priorities')))]]);

        return $this->attempt($request, fn () => $this->crm->setPriority($assignment, $data['priority'], $request->user()), 'Priority updated.', 'priority');
    }

    public function storeNote(Request $request, LeadAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        return $this->attempt($request, fn () => $this->crm->addNote($assignment, $data['body'], $request->user()), 'Note added.', 'body');
    }

    public function updateNote(Request $request, LeadAssignment $assignment, int $note): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $note = $assignment->notes()->findOrFail($note);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        return $this->attempt($request, fn () => $this->crm->updateNote($note, $data['body'], $request->user()), 'Note updated.', 'body');
    }

    public function storeFollowUp(Request $request, LeadAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('meezan.crm.follow_up_types')))],
            'due_at' => ['required', 'date', 'after:-1 day'],
            'priority' => ['nullable', Rule::in(array_keys(config('meezan.crm.priorities')))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->attempt($request, fn () => $this->crm->createFollowUp($assignment, $data, $request->user()), 'Follow-up scheduled.', 'due_at');
    }

    public function finishFollowUp(Request $request, LeadAssignment $assignment, int $followUp): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $followUp = $assignment->followUps()->findOrFail($followUp);
        $data = $request->validate(['outcome' => ['required', Rule::in(['completed', 'cancelled'])]]);

        return $this->attempt($request, fn () => $this->crm->finishFollowUp($followUp, $data['outcome'], $request->user()), 'Follow-up '.$data['outcome'].'.', 'outcome');
    }

    public function storeAppointment(Request $request, LeadAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $data = $this->appointmentData($request);

        return $this->attempt($request, fn () => $this->crm->scheduleAppointment($assignment, $data, $request->user()), 'Appointment scheduled.', 'starts_at');
    }

    public function updateAppointment(Request $request, LeadAssignment $assignment, int $appointment): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $appointment = $assignment->appointments()->findOrFail($appointment);

        if ($request->filled('status')) {
            $data = $request->validate(['status' => ['required', Rule::in(['completed', 'cancelled', 'no_show'])], 'status_reason' => ['nullable', 'string', 'max:255']]);

            return $this->attempt($request, fn () => $this->crm->setAppointmentStatus($appointment, $data['status'], $data['status_reason'] ?? null, $request->user()),
                'Appointment marked '.LeadAppointment::STATUSES[$data['status']].'.', 'status');
        }

        $data = $this->appointmentData($request);

        return $this->attempt($request, fn () => $this->crm->updateAppointment($appointment, $data, $request->user()), 'Appointment updated.', 'starts_at');
    }

    public function storeRecommendation(Request $request, LeadAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $data = $request->validate([
            'property_id' => ['nullable', 'integer', 'required_without:reference'],
            'reference' => ['nullable', 'string', 'max:30', 'required_without:property_id'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['reference.required_without' => 'Choose a listing or enter its reference.']);
        $propertyId = $data['property_id'] ?? Property::published()->where('reference', strtoupper(trim($data['reference'])))->value('id') ?? 0;

        return $this->attempt($request, fn () => $this->crm->recommend($assignment, (int) $propertyId, $data['note'] ?? null, $request->user()), 'Property recommended.', 'reference');
    }

    public function updateRecommendation(Request $request, LeadAssignment $assignment, int $recommendation): RedirectResponse
    {
        Gate::authorize('update', $assignment);
        $recommendation = $assignment->recommendations()->findOrFail($recommendation);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(LeadRecommendation::STATUSES))]]);

        return $this->attempt($request, fn () => $this->crm->updateRecommendation($recommendation, $data['status'], $request->user()), 'Recommendation updated.', 'status');
    }

    /* Helpers ------------------------------------------------------------- */

    /** Run a CRM change; business-rule errors become a validation error (or JSON 422 for the Kanban). */
    protected function attempt(Request $request, callable $change, string $message, string $field): RedirectResponse|JsonResponse
    {
        try {
            $change();
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }

    protected function appointmentData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(config('meezan.crm.appointment_types')))],
            'starts_at' => ['required', 'date', 'after:-1 day'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,480'],
            'property_id' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    protected function assignments(Request $request): Builder
    {
        return LeadAssignment::query()->where('dealer_id', $request->user()->id)->active();
    }

    /** @return array<string, string|null> */
    protected function filters(Request $request): array
    {
        return $request->validate([
            'stage' => ['nullable', Rule::enum(PipelineStage::class)],
            'city' => ['nullable', Rule::in(config('meezan.cities'))],
            'area' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(PropertyType::class)],
            'purpose' => ['nullable', Rule::enum(Purpose::class)],
            'priority' => ['nullable', Rule::in(array_keys(config('meezan.crm.priorities')))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'follow_up' => ['nullable', Rule::in(['due_today', 'overdue', 'none'])],
            'outcome' => ['nullable', Rule::in(['open', 'won', 'lost'])],
            'stale' => ['nullable', 'boolean'],
        ]);
    }

    protected function filtered(Request $request, array $f): Builder
    {
        return $this->assignments($request)
            ->when($f['stage'] ?? null, fn ($q, $s) => $q->where('pipeline_stage', $s))
            ->when($f['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('assigned_at', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('assigned_at', '<', now()->parse($d)->addDay()))
            ->when($f['outcome'] ?? null, fn ($q, $o) => $o === 'open' ? $q->open() : $q->where('pipeline_stage', $o))
            ->when($f['stale'] ?? null, fn ($q) => $q->whereNotNull('stale_flagged_at'))
            ->when(($f['type'] ?? null) || ($f['purpose'] ?? null) || ($f['area'] ?? null) || ($f['city'] ?? null), fn ($q) => $q->whereHas('lead', fn ($l) => $l
                ->when($f['type'] ?? null, fn ($w, $t) => $w->where('type', $t))
                ->when($f['purpose'] ?? null, fn ($w, $p) => $w->where('purpose', $p))
                ->when($f['area'] ?? null, fn ($w, $a) => $w->where('area_id', $a))
                ->when($f['city'] ?? null, fn ($w, $c) => $w->where(fn ($any) => $any
                    ->whereHas('area', fn ($a) => $a->where('city', $c))
                    ->orWhereHas('requirement', fn ($r) => $r->where('city', $c))
                    ->orWhereHas('property', fn ($p) => $p->where('city', $c))))))
            ->when($f['follow_up'] ?? null, fn ($q, $fu) => match ($fu) {
                'due_today' => $q->whereHas('followUps', fn ($x) => $x->pending()->whereBetween('due_at', LocalTime::today())),
                'overdue' => $q->whereHas('followUps', fn ($x) => $x->overdue()),
                'none' => $q->whereDoesntHave('followUps', fn ($x) => $x->pending()),
            });
    }

    /** @return array<string, mixed> */
    protected function filterOptions(Request $request): array
    {
        return [
            'areaOptions' => LeadAssignment::where('dealer_id', $request->user()->id)->active()
                ->join('leads', 'leads.id', '=', 'lead_assignments.lead_id')->join('areas', 'areas.id', '=', 'leads.area_id')
                ->distinct()->orderBy('areas.name')->pluck('areas.name', 'areas.id'),
        ];
    }

    protected function cardRelations(): array
    {
        return ['lead:id,purpose,type,area_id,buyer_requirement_id,property_id,inquiry_id,source', 'lead.area:id,name,city',
            'lead.requirement:id,city,type,purpose,max_price,min_area_sqft,bedrooms,contact_name,user_id,source', 'lead.requirement.user:id,name',
            'lead.property:id,title,reference,city', 'lead.inquiry:id,name'];
    }
}
