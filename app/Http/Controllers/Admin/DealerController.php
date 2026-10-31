<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DealerStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DealerDocument;
use App\Models\DealerProfile;
use App\Services\AuditLogger;
use App\Services\DealerPerformance;
use App\Services\DealerVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class DealerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(DealerStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $dealers = DealerProfile::query()
            ->with(['user:id,name,email,phone,status', 'areas:id,name'])
            ->withCount(['properties as listings_count', 'documents'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('verification_status', $s))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('agency_name', 'like', "%{$term}%")->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$term}%"))))
            ->orderByRaw("case when verification_status in ('submitted', 'under_review') then 0 when verification_status = 'pending' then 1 else 2 end")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = DealerProfile::select('verification_status', DB::raw('count(*) as total'))->groupBy('verification_status')->pluck('total', 'verification_status');

        return view('admin.dealers.index', compact('dealers', 'filters', 'counts'));
    }

    public function show(DealerProfile $dealer, DealerPerformance $performance): View
    {
        $dealer->load(['user', 'areas', 'verifier:id,name', 'documents']);

        $listingCounts = $dealer->user->properties()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return view('admin.dealers.show', [
            'dealer' => $dealer,
            'listingCounts' => $listingCounts,
            'listings' => $dealer->user->properties()->with('area:id,name')->latest('updated_at')->take(10)->get(),
            'performance' => $performance->for($dealer->user, 30),
            'history' => AuditLog::where('auditable_type', $dealer->getMorphClass())->where('auditable_id', $dealer->id)->with('user:id,name')->latest('created_at')->get(),
        ]);
    }

    public function updateCapacity(Request $request, DealerProfile $dealer): RedirectResponse
    {
        $data = $request->validate(['lead_capacity' => ['nullable', 'integer', 'between:1,500']]);
        $dealer->lead_capacity = $data['lead_capacity'] ?? null;
        $dealer->save();

        AuditLogger::record('dealer.capacity_changed', $dealer, ['lead_capacity' => $dealer->lead_capacity]);

        return back()->with('status', 'Lead capacity updated.');
    }

    /**
     * Verification decisions, all through DealerVerification (state checks,
     * audit with from/to/reason, dealer notification). A reason is required
     * wherever the dealer must act on it.
     */
    public function updateVerification(Request $request, DealerProfile $dealer, DealerVerification $verification): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['start_review', 'request_documents', 'verify', 'reject', 'suspend', 'reinstate'])],
            'reason' => ['nullable', 'required_if:decision,reject,suspend,request_documents', 'string', 'max:255'],
        ], ['reason.required_if' => 'Please record a reason for this decision.']);
        $admin = $request->user();

        try {
            match ($data['decision']) {
                'start_review' => $verification->startReview($dealer, $admin),
                'request_documents' => $verification->requestDocuments($dealer, $data['reason'], $admin),
                'verify' => $verification->verify($dealer, $admin, $data['reason'] ?? null),
                'reject' => $verification->reject($dealer, $data['reason'], $admin),
                'suspend' => $verification->suspend($dealer, $data['reason'], $admin),
                'reinstate' => $verification->reinstate($dealer, $admin),
            };
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('status', $dealer->agency_name.' is now '.strtolower($dealer->fresh()->verification_status->label()).'.');
    }

    /** Approve or reject one document; rejecting needs a reason the dealer will see. */
    public function reviewDocument(Request $request, DealerDocument $document, DealerVerification $verification): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:255'],
        ], ['note.required_if' => 'Tell the dealer why this document cannot be accepted.']);

        try {
            $verification->reviewDocument($document, $data['decision'], $data['note'] ?? null, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['note' => $e->getMessage()]);
        }

        return back()->with('status', $document->typeLabel().' '.($data['decision'] === 'approved' ? 'approved.' : 'marked for replacement.'));
    }

    /** Staff-only note, never shown to the dealer. */
    public function updateNote(Request $request, DealerProfile $dealer, DealerVerification $verification): RedirectResponse
    {
        $data = $request->validate(['internal_note' => ['nullable', 'string', 'max:2000']]);
        $verification->note($dealer, $data['internal_note'] ?? null, $request->user());

        return back()->with('status', 'Internal note saved.');
    }
}
