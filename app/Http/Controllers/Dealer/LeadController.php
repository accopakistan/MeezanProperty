<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\LeadAssignment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $dealer = $request->user();
        $tab = $request->query('tab') === 'requirements' ? 'requirements' : 'inquiries';

        $inquiries = $dealer->receivedInquiries()
            ->with('property:id,title,slug,reference,deleted_at')
            ->latest()
            ->paginate(15, ['*'], 'inquiries_page')
            ->withQueryString();

        // Requirement leads, routed by area. Contact details exist only because the buyer opted in.
        $assignments = $dealer->leadAssignments()
            ->whereHas('lead', fn ($q) => $q->whereNotNull('buyer_requirement_id')->where('consent_to_share', true))
            ->with(['lead.requirement.user:id,name,email,phone', 'lead.area:id,name'])
            ->latest('assigned_at')
            ->paginate(15, ['*'], 'leads_page')
            ->withQueryString();

        return view('dealer.leads', compact('inquiries', 'assignments', 'tab'));
    }

    public function updateInquiry(Request $request, Inquiry $inquiry): RedirectResponse
    {
        abort_unless($inquiry->recipient_id === $request->user()->id, 403);

        $data = $request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'closed'])]]);
        $inquiry->status = $data['status'];
        // First move away from "new" counts as the dealer's response.
        if ($data['status'] !== 'new' && ! $inquiry->responded_at) {
            $inquiry->responded_at = now();
        }
        $inquiry->save();

        if ($lead = $inquiry->lead) {
            $lead->status = ['new' => 'assigned', 'contacted' => 'contacted', 'closed' => 'closed'][$data['status']];
            $lead->save();
        }

        AuditLogger::record('inquiry.status_changed', $inquiry, ['status' => $data['status']]);

        return back()->with('status', 'Inquiry updated.');
    }

    public function updateAssignment(Request $request, LeadAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->dealer_id === $request->user()->id && $assignment->status === 'active', 403);

        $data = $request->validate(['status' => ['required', Rule::in(['contacted', 'qualified', 'closed', 'lost'])]]);
        if (! $assignment->responded_at) {
            $assignment->forceFill(['responded_at' => now()])->save();
        }
        $assignment->lead->status = $data['status'];
        $assignment->lead->save();

        AuditLogger::record('lead.status_changed', $assignment->lead, ['status' => $data['status'], 'by' => 'dealer']);

        return back()->with('status', 'Lead updated.');
    }
}
