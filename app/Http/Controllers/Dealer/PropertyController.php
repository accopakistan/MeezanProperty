<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PropertyRequest;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ListingSubmittedForReview;
use App\Services\AuditLogger;
use App\Services\Billing\Entitlements;
use App\Services\ImageProcessor;
use App\Services\TrustSignals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(PropertyStatus::class)]])['status'] ?? null;

        $counts = $request->user()->properties()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        $properties = $request->user()->properties()
            ->with(['coverImage', 'area:id,name'])
            ->withCount('inquiries')
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('dealer.properties.index', compact('properties', 'counts', 'status'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Property::class);

        return view('dealer.properties.form', [
            'property' => new Property(['city' => config('meezan.default_city'), 'purpose' => 'sale', 'area_unit' => 'marla']),
        ]);
    }

    public function store(PropertyRequest $request): RedirectResponse
    {
        $property = DB::transaction(function () use ($request) {
            $property = new Property($request->listingData());
            $property->user_id = $request->user()->id;
            $property->status = PropertyStatus::Draft;
            $this->recordAuthorization($request, $property);
            $property->save();

            $this->storeImages($request, $property);
            // Photos are added after the first save, so recount them for completeness.
            $property->refreshCompleteness();

            return $property;
        });

        AuditLogger::record('property.created', $property);

        if ($request->input('action') === 'submit') {
            return $this->submitForReview($property);
        }

        return redirect()->route('dealer.properties.edit', $property)->with('status', 'Draft saved. Submit it for review when you are ready.');
    }

    public function edit(Request $request, Property $property): View
    {
        Gate::authorize('update', $property);

        return view('dealer.properties.form', [
            'property' => $property->load('images'),
        ]);
    }

    public function update(PropertyRequest $request, Property $property): RedirectResponse
    {
        $wasPublished = $property->status === PropertyStatus::Published;

        DB::transaction(function () use ($request, $property, $wasPublished) {
            $property->fill($request->listingData());
            $this->recordAuthorization($request, $property);

            if ($ids = $request->input('remove_images')) {
                foreach ($property->images()->whereIn('id', $ids)->get() as $image) {
                    $image->deleteFiles();
                    $image->delete();
                }
            }

            $this->applyPhotoOrder($request, $property);
            $this->storeImages($request, $property);

            // Material edits to a live listing go back through moderation.
            if ($wasPublished && ($property->isDirty() || $request->hasFile('images') || $request->filled('remove_images'))) {
                $property->status = PropertyStatus::Pending;
                $property->submitted_at = now();
            }

            $property->save();
        });

        AuditLogger::record('property.updated', $property, ['status' => $property->status->value]);

        if ($request->input('action') === 'submit' && (in_array($property->status, [PropertyStatus::Draft, PropertyStatus::Rejected, PropertyStatus::ChangesRequested, PropertyStatus::Expired], true) || $property->hasLapsed())) {
            return $this->submitForReview($property);
        }

        $message = $wasPublished && $property->status === PropertyStatus::Pending
            ? 'Changes saved. The listing is back in review and will reappear once approved.'
            : 'Listing saved.';

        return redirect()->route('dealer.properties.edit', $property)->with('status', $message);
    }

    public function submit(Request $request, Property $property): RedirectResponse
    {
        Gate::authorize('update', $property);

        // A lapsed listing counts as expired even before the daily job marks it.
        abort_unless(in_array($property->status, [PropertyStatus::Draft, PropertyStatus::Rejected, PropertyStatus::ChangesRequested, PropertyStatus::Expired], true) || $property->hasLapsed(), 422, 'This listing cannot be submitted.');

        return $this->submitForReview($property);
    }

    /**
     * One-click renewal for an unchanged listing near or just past its end
     * date: another full listing period, no new review. Older expired
     * listings go through "Submit for review" instead.
     */
    public function renew(Request $request, Property $property): RedirectResponse
    {
        Gate::authorize('update', $property);

        $request->validate(['still_available' => ['accepted']], [
            'still_available.accepted' => 'Please confirm the property is still available before renewing.',
        ]);

        if (! $property->canBeRenewed()) {
            return back()->with('error', $property->needsReviewToRelist()
                ? $property->reference.' expired more than '.config('meezan.listing.renew_grace_days').' days ago. Check the details and submit it for review.'
                : $property->reference.' can be renewed in the last '.config('meezan.listing.renew_window_days').' days before it expires.');
        }

        $wasExpired = $property->hasLapsed();
        $error = DB::transaction(function () use ($property) {
            // An expired listing coming back uses an allowance again.
            if ($error = $this->listingLimitError($property)) {
                return $error;
            }
            $property->status = PropertyStatus::Published;
            $property->expires_at = now()->addDays((int) config('meezan.listing.lifetime_days'));
            $property->expiry_reminder_sent_at = null;
            $property->save();

            return null;
        });
        if ($error) {
            return back()->with('error', $error);
        }

        AuditLogger::record('property.renewed', $property, ['was_expired' => $wasExpired, 'expires_at' => $property->expires_at->toDateString()]);

        return back()->with('status', $property->reference.' renewed until '.$property->expires_at->format('j M Y').'.');
    }

    public function destroy(Request $request, Property $property): RedirectResponse
    {
        Gate::authorize('delete', $property);

        $property->status = PropertyStatus::Archived;
        $property->save();
        $property->delete();

        AuditLogger::record('property.archived_by_dealer', $property);

        return redirect()->route('dealer.properties.index')->with('status', 'Listing removed.');
    }

    /**
     * Dealer features or un-features one of their live listings, within the
     * package's featured allowance. Staff can still feature any listing.
     */
    public function feature(Request $request, Property $property, Entitlements $entitlements): RedirectResponse
    {
        Gate::authorize('update', $property);
        abort_unless($property->status === PropertyStatus::Published, 422, 'Only live listings can be featured.');

        if (! $property->is_featured) {
            $plan = $entitlements->plan($request->user());
            if (! $plan->hasFeature('featured_listings') || ! $entitlements->allows($request->user(), 'featured')) {
                return back()->with('error', $plan->hasFeature('featured_listings')
                    ? 'You have used all '.$plan->limit('featured').' featured listings in your package. Remove one from featured or upgrade your package.'
                    : 'Featured listings are not included in your package. Upgrade your package to feature listings.');
            }
        }

        $property->is_featured = ! $property->is_featured;
        $property->save();
        AuditLogger::record($property->is_featured ? 'property.featured_by_dealer' : 'property.unfeatured_by_dealer', $property);

        return back()->with('status', $property->reference.($property->is_featured ? ' is now featured.' : ' is no longer featured.'));
    }

    /**
     * The message to show when this listing would start using a listing
     * allowance and none is left; null when it may go ahead. Call inside a
     * transaction: the dealer row is locked so two submissions at once
     * cannot both take the last allowance.
     */
    protected function listingLimitError(Property $property): ?string
    {
        if (Entitlements::countsListing($property->status)) {
            return null; // Already counted (e.g. a live listing being edited or renewed).
        }

        $dealer = User::whereKey($property->user_id)->lockForUpdate()->firstOrFail();
        $entitlements = app(Entitlements::class);
        $entitlements->flush($dealer);
        if ($entitlements->allows($dealer, 'listings')) {
            return null;
        }

        $entitlements->notifyLimitReached($dealer, 'listings');

        return sprintf('You have reached your listing limit. Upgrade your package to add more listings. (%d of %d used)', $entitlements->usage($dealer)['listings'], $entitlements->plan($dealer)->limit('listings'));
    }

    protected function submitForReview(Property $property): RedirectResponse
    {
        $error = DB::transaction(function () use ($property, &$flags) {
            if ($error = $this->listingLimitError($property)) {
                return $error;
            }
            $property->status = PropertyStatus::Pending;
            $property->rejection_reason = null;
            $property->submitted_at = now();
            // Duplicate, burst and price-change checks run once here, not on every page view.
            $flags = app(TrustSignals::class)->atSubmission($property);
            $property->save();

            return null;
        });
        if ($error) {
            return redirect()->route('dealer.properties.edit', $property)->with('error', $error);
        }

        $duplicates = collect($flags['duplicates'] ?? []);
        AuditLogger::record('property.submitted', $property, array_filter([
            'possible_duplicates' => $duplicates->pluck('reference')->all() ?: null,
            'review_flags' => array_keys(array_diff_key($flags, ['duplicates' => 1])) ?: null,
            'completeness' => $property->completeness_score,
        ]));
        Notification::send(User::activeAdmins(), new ListingSubmittedForReview($property, $duplicates->pluck('reference')->all()));

        $redirect = redirect()->route('dealer.properties.index')->with('status', 'Submitted for review. We’ll publish it once it passes our checks.');

        return $duplicates->isNotEmpty()
            ? $redirect->with('error', 'This listing looks similar to '.$duplicates->map(fn ($d) => $d['reference'].' ('.implode(', ', $d['reasons']).')')->implode('; ').'. Our reviewers will check it isn’t a duplicate.')
            : $redirect;
    }

    protected function recordAuthorization(PropertyRequest $request, Property $property): void
    {
        if ($property->authorization_type !== $request->input('authorization_type') || ! $property->authorization_confirmed_at) {
            $property->authorization_type = $request->input('authorization_type');
            $property->authorization_confirmed_at = now();
        }
    }

    /**
     * New photos are resized, re-encoded as WebP (dropping EXIF/GPS) and get
     * a thumbnail. If any photo fails, files written by this request are
     * removed and the whole save is rolled back with a field error.
     */
    protected function storeImages(PropertyRequest $request, Property $property): void
    {
        $next = (int) $property->images()->max('sort_order') + 1;
        $processor = app(ImageProcessor::class);
        $written = [];

        foreach ($request->file('images', []) as $index => $file) {
            try {
                $stored = $processor->storeListingPhoto($file->getRealPath(), 'properties/'.$property->id);
            } catch (\RuntimeException $e) {
                Storage::disk('public')->delete($written);
                Log::info('Listing photo rejected during processing', ['error' => $e->getMessage()]);

                throw ValidationException::withMessages([
                    'images' => 'Photo '.($index + 1).' ('.$file->getClientOriginalName().') could not be processed. Save it as a JPG or PNG and try again.',
                ]);
            }
            array_push($written, $stored['path'], $stored['thumb_path']);

            $image = $property->images()->make($stored + ['sort_order' => $next++]);
            $image->save();
        }
    }

    /**
     * Photo order from the edit form (image_order[] = ids, first is the
     * cover; cover_image = id moved to the front). Ignores ids that do not
     * belong to this listing. Order alone does not send a listing to review.
     */
    protected function applyPhotoOrder(PropertyRequest $request, Property $property): void
    {
        $existing = $property->images()->pluck('id');
        if ($existing->isEmpty() || (! $request->filled('image_order') && ! $request->filled('cover_image'))) {
            return;
        }

        $order = collect($request->input('image_order', []))->map(fn ($id) => (int) $id)->intersect($existing)->values();
        $order = $order->concat($existing->diff($order))->values();

        if ($cover = (int) $request->input('cover_image')) {
            if ($existing->contains($cover)) {
                $order = $order->reject(fn ($id) => $id === $cover)->prepend($cover)->values();
            }
        }

        foreach ($order as $position => $id) {
            $property->images()->whereKey($id)->update(['sort_order' => $position + 1]);
        }
    }
}
