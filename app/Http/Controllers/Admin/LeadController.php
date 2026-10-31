<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Lead;
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
        $lead->load(['inquiry', 'requirement.user', 'property.area', 'area', 'assignments.dealer.dealerProfile', 'assignments.assigner:id,name']);

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
}
