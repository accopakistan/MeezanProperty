<?php

namespace App\Http\Controllers;

use App\Models\ListingReport;
use App\Models\Property;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListingReportController extends Controller
{
    public function store(Request $request, Property $property): RedirectResponse
    {
        abort_unless($property->isPublic(), 404);

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ListingReport::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
            'reporter_email' => ['nullable', 'email:rfc', 'max:190'],
            'website' => ['prohibited'],
        ]);

        $ipHash = hash('sha256', $request->ip().config('app.key'));

        // One open report per reporter per listing.
        $exists = ListingReport::where('property_id', $property->id)->where('status', 'open')
            ->where(fn ($q) => $request->user() ? $q->where('user_id', $request->user()->id) : $q->where('ip_hash', $ipHash))
            ->exists();

        if (! $exists) {
            $report = new ListingReport([
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'reporter_email' => $request->user() ? null : ($data['reporter_email'] ?? null),
            ]);
            $report->property_id = $property->id;
            $report->user_id = $request->user()?->id;
            $report->ip_hash = $ipHash;
            $report->save();

            AuditLogger::record('listing.reported', $property, ['reason' => $data['reason']]);
        }

        return back()->with('status', 'Thank you. Our moderation team will review this listing.');
    }
}
