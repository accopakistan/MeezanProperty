<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\ListingReport;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ListingReportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['open', 'resolved', 'dismissed'])]])['status'] ?? 'open';

        return view('admin.reports', [
            'reports' => ListingReport::with(['property:id,title,slug,reference,status,user_id,deleted_at', 'property.dealer.dealerProfile:id,user_id,agency_name', 'user:id,name', 'resolver:id,name'])
                ->where('status', $status)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'counts' => ListingReport::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function resolve(Request $request, ListingReport $report): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['dismiss', 'resolve', 'unpublish'])],
            'note' => ['nullable', 'required_if:decision,unpublish', 'string', 'max:255'],
        ], ['note.required_if' => 'Record why the listing is being unpublished.']);

        if ($data['decision'] === 'unpublish' && $report->property && ! $report->property->trashed()) {
            $report->property->forceFill(['status' => PropertyStatus::Draft, 'rejection_reason' => $data['note']])->save();
            AuditLogger::record('property.unpublished', $report->property, ['reason' => $data['note'], 'report_id' => $report->id]);
        }

        $report->status = $data['decision'] === 'dismiss' ? 'dismissed' : 'resolved';
        $report->resolution_note = $data['note'] ?? null;
        $report->resolved_by = $request->user()->id;
        $report->resolved_at = now();
        $report->save();

        AuditLogger::record('report.'.$report->status, $report, ['decision' => $data['decision']]);

        return back()->with('status', 'Report '.$report->status.'.');
    }
}
