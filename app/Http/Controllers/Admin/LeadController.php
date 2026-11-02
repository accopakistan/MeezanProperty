<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Leads\LeadRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'source' => ['nullable', Rule::in(['website', 'account', 'ai_finder', 'whatsapp'])],
        ]);

        $leads = Lead::query()
            ->with(['inquiry:id,name', 'requirement:id,user_id,purpose,type', 'requirement.user:id,name', 'property:id,title,slug,reference,deleted_at', 'area:id,name', 'activeAssignments.dealer:id,name', 'activeAssignments.dealer.dealerProfile:id,user_id,agency_name'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['source'] ?? null, fn ($q, $s) => $q->where('source', $s))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = Lead::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return view('admin.leads.index', compact('leads', 'filters', 'counts'));
    }

    public function show(Lead $lead, LeadRouter $router): View
    {
        // CRM state per dealer (stage, priority, counts). Note text stays private to the dealer.
        $lead->load(['inquiry', 'requirement.user', 'property.area', 'area', 'assignments.dealer.dealerProfile', 'assignments.assigner:id,name']);
        $lead->assignments->loadCount([
            'notes',
            'followUps as pending_follow_ups' => fn ($q) => $q->pending(),
            'followUps as overdue_follow_ups' => fn ($q) => $q->overdue(),
            'appointments as scheduled_appointments' => fn ($q) => $q->where('status', 'scheduled'),
            'recommendations',
        ]);

        $dealers = User::where('role', Role::Dealer)->where('status', 'active')
            ->whereHas('dealerProfile', fn ($q) => $q->where('verification_status', DealerStatus::Verified))
            ->with('dealerProfile:id,user_id,agency_name')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.leads.show', [
            'lead' => $lead,
            'dealers' => $dealers,
            'suggested' => $lead->requirement ? $router->eligibleDealers($lead->requirement)->take(5) : collect(),
        ]);
    }

    public function reassign(Request $request, Lead $lead, LeadRouter $router): RedirectResponse
    {
        $data = $request->validate([
            'dealer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', Role::Dealer->value)->where('status', 'active')],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $dealer = User::with('dealerProfile')->findOrFail($data['dealer_id']);
        abort_unless($dealer->isVerifiedDealer(), 422, 'Leads can only be assigned to verified dealers.');

        $router->reassign($lead, $dealer, $request->user(), $data['reason']);

        return back()->with('status', 'Lead assigned to '.$dealer->dealerProfile->agency_name.'.');
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(LeadStatus::class)]]);

        $previous = $lead->status;
        $lead->status = $data['status'];
        $lead->save();

        AuditLogger::record('lead.status_changed', $lead, ['from' => $previous->value, 'to' => $data['status'], 'by' => 'admin']);

        return back()->with('status', 'Lead status updated.');
    }

    /** Add one more dealer to the lead (verification, coverage and per-lead maximum always apply). */
    public function addDealer(Request $request, Lead $lead, LeadRouter $router): RedirectResponse
    {
        $data = $request->validate([
            'dealer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', Role::Dealer->value)],
            'reason' => ['required', 'string', 'max:255'],
            'override_allowance' => ['nullable', 'boolean'],
        ]);
        $dealer = User::with('dealerProfile')->findOrFail($data['dealer_id']);

        try {
            $router->addDealer($lead, $dealer, $request->user(), $data['reason'], $request->boolean('override_allowance'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['dealer_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Lead also assigned to '.$dealer->dealerProfile->agency_name.'.');
    }

    /** End one dealer's assignment. Their CRM history is kept (ended), and they are told. */
    public function removeAssignment(Request $request, LeadAssignment $assignment, LeadRouter $router): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $router->removeAssignment($assignment, $request->user(), $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Assignment ended. The dealer’s CRM history is kept.');
    }

    /** Mark a lead as spam, a duplicate or invalid (it then uses no dealer's allowance), or undo that. */
    public function updateValidity(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate(['invalid_reason' => ['nullable', Rule::in(array_keys(Lead::INVALID_REASONS))]]);
        $reason = $data['invalid_reason'] ?? null;

        $lead->forceFill([
            'invalidated_at' => $reason ? now() : null,
            'invalid_reason' => $reason,
            'invalidated_by' => $reason ? $request->user()->id : null,
        ])->save();
        AuditLogger::record($reason ? 'lead.invalidated' : 'lead.validity_restored', $lead, ['reason' => $reason]);

        return back()->with('status', $reason ? 'Lead marked as '.strtolower(Lead::INVALID_REASONS[$reason]).'. It no longer counts against any dealer’s allowance.' : 'Lead counts as valid again.');
    }
}
