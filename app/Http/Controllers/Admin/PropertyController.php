<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Notifications\ListingReviewed;
use App\Services\AuditLogger;
use App\Services\DuplicateDetector;
use App\Services\ListingCompleteness;
use App\Services\TrustSignals;
use App\Support\MapMarkers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PropertyStatus::class)],
            'group' => ['nullable', Rule::in(['homes', 'plots', 'commercial'])],
            'area' => ['nullable', 'integer'],
            'dealer' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'flag' => ['nullable', Rule::in(['review', 'no_pin', 'location', 'incomplete'])],
        ]);

        $status = $filters['status'] ?? (empty($filters) ? PropertyStatus::Pending->value : null);

        $properties = Property::withTrashed()
            ->with(['dealer:id,name', 'dealer.dealerProfile:id,user_id,agency_name,verification_status', 'area:id,name', 'coverImage'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['group'] ?? null, fn ($q, $g) => $q->inGroup($g))
            ->when($filters['area'] ?? null, fn ($q, $a) => $q->where('area_id', $a))
            ->when($filters['dealer'] ?? null, fn ($q, $d) => $q->where('user_id', $d))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('title', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")))
            ->when($filters['flag'] ?? null, fn ($q, $f) => match ($f) {
                'review' => $q->whereNotNull('flagged_at'),
                'no_pin' => $q->whereNull('latitude'),
                'location' => $q->where('location_status', 'flagged'),
                'incomplete' => $q->where('completeness_score', '<', ListingCompleteness::READY_AT),
            })
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderBy('submitted_at')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $counts = Property::withTrashed()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return view('admin.properties.index', [
            'properties' => $properties,
            'filters' => $filters,
            'status' => $status,
            'counts' => $counts,
            'areas' => Area::ordered()->get(['id', 'name']),
        ]);
    }

    public function show(Property $property): View
    {
        $property->load(['images', 'area', 'dealer.dealerProfile']);

        return view('admin.properties.show', [
            'property' => $property,
            'duplicates' => app(DuplicateDetector::class)->candidates($property),
            'signals' => app(TrustSignals::class)->for($property),
            'completeness' => ListingCompleteness::for($property),
            'reports' => $property->reports()->with('user:id,name')->latest()->get(),
            'history' => AuditLog::where('auditable_type', $property->getMorphClass())->where('auditable_id', $property->id)->with('user:id,name')->latest('created_at')->get(),
            // Lets reviewers check that the dealer's pin sits in the stated area.
            'markers' => MapMarkers::for([$property]),
        ]);
    }

    public function moderate(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'request_changes', 'suspend', 'reinstate', 'archive', 'unpublish'])],
            'reason' => ['nullable', 'required_if:decision,reject,request_changes,suspend', 'string', 'max:255'],
        ], ['reason.required_if' => 'Please tell the dealer why.']);

        $previous = $property->status;

        match ($data['decision']) {
            'approve' => $this->approve($property, $previous),
            'reject' => $property->forceFill(['status' => PropertyStatus::Rejected, 'rejection_reason' => $data['reason']]),
            'request_changes' => $this->expectStatus($previous, [PropertyStatus::Pending, PropertyStatus::Published])
                ?? $property->forceFill(['status' => PropertyStatus::ChangesRequested, 'rejection_reason' => $data['reason']]),
            'suspend' => $this->expectStatus($previous, [PropertyStatus::Published, PropertyStatus::Pending])
                ?? $property->forceFill(['status' => PropertyStatus::Suspended, 'rejection_reason' => $data['reason']]),
            'reinstate' => $this->expectStatus($previous, [PropertyStatus::Suspended])
                ?? $property->forceFill(['status' => $property->published_at ? PropertyStatus::Published : PropertyStatus::Pending, 'rejection_reason' => null]),
            'unpublish' => $property->forceFill(['status' => PropertyStatus::Draft, 'rejection_reason' => $data['reason'] ?? null]),
            'archive' => $property->forceFill(['status' => PropertyStatus::Archived]),
        };
        $property->save();

        $action = ['approve' => 'approved', 'reject' => 'rejected', 'request_changes' => 'changes_requested', 'suspend' => 'suspended', 'reinstate' => 'reinstated', 'archive' => 'archived', 'unpublish' => 'unpublished'][$data['decision']];

        AuditLogger::record('property.'.$action, $property, [
            'from' => $previous->value,
            'to' => $property->status->value,
            'reason' => $data['reason'] ?? null,
        ]);

        // Archiving and reinstating are internal housekeeping; the other decisions are the dealer's to know.
        if (! in_array($data['decision'], ['archive', 'reinstate'], true)) {
            $property->loadMissing('dealer')->dealer?->notify(new ListingReviewed($property, $data['decision'], $data['reason'] ?? null));
        }

        return back()->with('status', $property->reference.' is now '.strtolower($property->status->label()).'.');
    }

    /** Pending listings that resemble another live or pending listing. */
    public function duplicates(DuplicateDetector $detector): View
    {
        $flagged = Property::where('status', PropertyStatus::Pending)
            ->with(['area:id,name', 'dealer.dealerProfile:id,user_id,agency_name', 'coverImage'])
            ->oldest('submitted_at')->limit(100)->get()
            ->map(fn (Property $p) => ['property' => $p, 'candidates' => $detector->candidates($p)])
            ->filter(fn ($row) => $row['candidates']->isNotEmpty())
            ->values();

        return view('admin.duplicates', ['flagged' => $flagged]);
    }

    public function feature(Request $request, Property $property): RedirectResponse
    {
        abort_unless($property->status === PropertyStatus::Published, 422, 'Only published listings can be featured.');

        $property->is_featured = ! $property->is_featured;
        $property->save();

        AuditLogger::record($property->is_featured ? 'property.featured' : 'property.unfeatured', $property);

        return back()->with('status', $property->is_featured ? 'Listing featured.' : 'Listing no longer featured.');
    }

    /** @param  list<PropertyStatus>  $allowed */
    protected function expectStatus(PropertyStatus $current, array $allowed): null
    {
        abort_unless(in_array($current, $allowed, true), 422, 'Not possible while the listing is '.strtolower($current->label()).'.');

        return null;
    }

    /**
     * Internal review marker ("requires review"), a reviewer's check of the
     * map pin, or flagging the pin. Never shown to buyers as an accusation.
     */
    public function review(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['flag', 'clear_flag', 'verify_location', 'flag_location'])],
            'reason' => ['nullable', 'required_if:action,flag,flag_location', 'string', 'max:255'],
        ], ['reason.required_if' => 'Record what needs checking.']);
        $flags = $property->review_flags ?? [];

        match ($data['action']) {
            'flag' => $property->forceFill(['flagged_at' => now(), 'review_flags' => $flags + ['manual' => $data['reason']]]),
            'clear_flag' => $property->forceFill(['flagged_at' => null, 'review_flags' => array_diff_key($flags, ['manual' => 1, 'rapid_submissions' => 1, 'price_change' => 1]) ?: null]),
            'verify_location' => abort_unless($property->hasCoordinates(), 422, 'There is no pin to verify.')
                ?? $property->forceFill(['location_status' => 'verified', 'location_verified_at' => now(), 'location_verified_by' => $request->user()->id, 'location_flag_reason' => null]),
            'flag_location' => $property->forceFill(['location_status' => 'flagged', 'location_flag_reason' => $data['reason'], 'location_verified_at' => null, 'location_verified_by' => null]),
        };
        $property->save();

        AuditLogger::record('property.review_'.$data['action'], $property, array_filter(['reason' => $data['reason'] ?? null]));

        return back()->with('status', ['flag' => 'Marked as requiring review.', 'clear_flag' => 'Review marker cleared.', 'verify_location' => 'Location marked as verified.', 'flag_location' => 'Location flagged.'][$data['action']]);
    }

    /** Hide or restore one photo. Files are kept so the decision can be reversed. */
    public function moderateImage(Request $request, PropertyImage $image): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'flagged', 'rejected', 'removed'])],
            'note' => ['nullable', 'required_if:status,rejected,removed', 'string', 'max:255'],
        ], ['note.required_if' => 'Record why this photo is hidden.']);

        $from = $image->status;
        $image->forceFill(['status' => $data['status'], 'moderation_note' => $data['note'] ?? null, 'moderated_by' => $request->user()->id, 'moderated_at' => now()])->save();
        $image->property->refreshCompleteness();

        AuditLogger::record('property.image_'.$data['status'], $image->property, ['image_id' => $image->id, 'from' => $from, 'to' => $data['status'], 'reason' => $data['note'] ?? null]);

        return back()->with('status', 'Photo is now '.$data['status'].'.');
    }

    protected function approve(Property $property, PropertyStatus $previous): void
    {
        abort_unless(array_key_exists((string) $property->publication_basis, Property::PUBLICATION_BASES), 422, 'This listing has no publication basis (a direct submission, authorised feed, licence or partnership), so it cannot be published.');
        abort_unless(in_array($previous, [PropertyStatus::Pending, PropertyStatus::Rejected, PropertyStatus::Draft, PropertyStatus::ChangesRequested], true), 422, 'Only pending listings can be approved.');

        $property->status = PropertyStatus::Published;
        $property->rejection_reason = null;
        $property->published_at ??= now();
        $property->expires_at = now()->addDays((int) config('meezan.listing.lifetime_days'));
        $property->expiry_reminder_sent_at = null;
    }
}
