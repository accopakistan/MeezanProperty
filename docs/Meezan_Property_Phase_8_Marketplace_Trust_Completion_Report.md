# Meezan Property — Phase 8: Marketplace Trust — Completion Report

_Local development (XAMPP, Laravel 12, MariaDB), 31 October 2026. No Git, GitHub or Hostinger actions. No destructive database commands. `public/Refrence/` untouched._

**Status labels:**

- **COMPLETE AND VERIFIED** — built, covered by automated tests and checked where visible.
- **COMPLETE BUT CONFIGURATION-BLOCKED** — built, but needs external settings to run live.
- **PARTIALLY IMPLEMENTED**
- **NOT IMPLEMENTED**

---

## 1. Executive summary

Meezan Property now has a working trust foundation for onboarding real dealers and listings, mostly by extending what earlier phases built rather than replacing it:

- **Dealer verification lifecycle:** documents required → submitted → under review → verified, with rejected, suspended and expired states.
  - Per-document approve/reject, with reasons the dealer sees.
  - Replaced documents are kept as history.
  - Every decision is audited (who, from, to, reason) and the dealer is notified.
- **Private documents:** stored on the private disk and streamed only through authorised routes.
  - Image uploads must decode as real images.
  - Each file gets a content hash.
- **Location privacy:** listings can show the exact pin, an approximate point (about 500 m) or the area only.
  - Public maps, map search, drawn-area search and Nearby Places use only the public point, so a hidden pin is never revealed.
  - A location-quality state (none, approximate, exact, verified, flagged) flags pins far from the chosen city or area for review instead of rejecting them.
- **Listings:** a completeness score (labelled as information completeness, not a property rating), per-photo moderation, new statuses (changes requested, suspended), a reviewer checklist, and improved duplicate detection that explains its reasons.
- **Trust dashboard** with live counts.

**Results:** 361 tests passed, 1,605 assertions, 0 failures. That is all 339 tests from before this phase plus 22 new ones; one earlier test was updated for the strict verification rule with your approval (§3), keeping all its original assertions. The build, both audits, the formatting check and route caching all pass, and data counts before and after are identical.

## 2. Initial project audit

**Already in place (reused):**

| Area | What existed |
|---|---|
| Documents | `dealer_documents` on the private `local` disk, random names, MIME from content, size and type validation, authorised streaming with `no-store` and `nosniff`, admin views audited, removal blocked after verification |
| Verification | 4 states (pending, verified, rejected, suspended); admin decisions with reasons; audit log |
| Photos | GD pipeline: content-checked, EXIF-corrected and stripped (removes GPS), re-encoded to WebP at ≤1600 px plus a 640 px thumbnail; ordering, cover and removal scoped to the listing |
| Map | Exact-pin picker that never copies the area centre; Pakistan bounding-box validation; area-centre fallback markers |
| Duplicates | Same area + size ±2% + price ±10% |
| Platform | Rate limits, CSRF, policies, the notification base class |

**Gaps found:**

- No per-document review, submit step or dealer-facing status guidance.
- No location privacy: exact coordinates reached public maps, viewport and drawn-area search (precise enough to narrow down an exact spot), Nearby Places (distances could be used to triangulate) and the Google Maps link.
- No location-quality state, completeness score or image moderation.
- No "changes requested" or "suspended" listing states.
- Duplicate detection gave no reasons and ignored pins and addresses.
- No trust dashboard.

## 3. Dealer verification architecture

**COMPLETE AND VERIFIED.** The existing `DealerStatus` enum was extended rather than duplicated: `pending` (documents required), `submitted`, `under_review`, `verified`, `rejected`, `suspended`, `expired`.

All transitions go through `App\Services\DealerVerification`, which:

- checks the current state
- requires reasons where the dealer must act
- writes the audit log (`from`, `to`, `reason`, admin)
- notifies the dealer

Nothing verifies a dealer automatically.

Required documents are configurable per business type (`config/meezan.php`, `verification.required_documents`):

| Business type | Required |
|---|---|
| individual | CNIC front and back |
| agency | CNIC front and back, agency registration |
| owner | CNIC front and back |

Office proof and other documents are optional.

**COMPLETE AND VERIFIED: strict rule.** The server refuses "verify" until every required document is approved, naming the documents still outstanding, and there is no override. The audit entry records which documents were approved.

This was changed after the first version of this report, with your approval. One existing test (`AdminTest`, "verifies and suspends dealers") verified a dealer with no documents. It was updated to approve the required documents first; all its original assertions are unchanged, and it now also checks that verification is refused without them.

The two dealers who were already verified keep their status: the rule applies to new verifications.

## 4. Database changes

Two additive migrations (generated SQL scanned for drops beforehand; none):

| Migration | Changes |
|---|---|
| `2026_10_31_000025_add_verification_workflow_to_dealers` | `dealer_profiles`: `verification_submitted_at`, `review_started_at`, `changes_requested_at`, `verification_expires_at`, `internal_note`. `dealer_documents`: `status` (default `pending`, indexed), `review_note`, `reviewed_by`, `reviewed_at`, `sha256` |
| `2026_10_31_000026_add_trust_fields_to_properties` | `properties`: `location_privacy` (default `exact`), `public_latitude`, `public_longitude`, `location_status` (default `none`), `location_flag_reason`, `location_verified_at`, `location_verified_by`, `completeness_score`, `review_flags` (JSON), `flagged_at`; indexes on location status, completeness and public coordinates. `property_images`: `status` (default `active`), `moderation_note`, `moderated_by`, `moderated_at`; index (`property_id`, `status`) |

- **Tables added:** 0.
- **Tables changed:** `dealer_profiles`, `dealer_documents`, `properties`, `property_images`.
- **New enum values:** stored in the existing `string(20)` status columns, so no column changes were needed.
- **Backfill:** `php artisan meezan:refresh-listing-trust --duplicates` filled the derived fields for all 17 listings without changing any dealer-entered value or `updated_at`.

## 5. Document upload system

**COMPLETE AND VERIFIED.**

- Up to 4 files per upload and 10 current documents per dealer; JPG, PNG, WebP or PDF; ≤ 5 MB each.
- Images must decode as real images and be ≤ 10,000 px per side.
- A minimum side length is available as an option (`MEEZAN_DOCUMENT_MIN_IMAGE_PX`), off by default.
- PDFs are recognised by content (the `%PDF-` signature) through the existing `mimes` rule, so a renamed file fails.
- File names are random and server-side; folders are per dealer; each file gets a SHA-256 hash.
- Uploading a document of a type that was rejected marks the rejected one `superseded` (kept as history).
- Uploading after a rejection or expiry returns the dealer to "documents required".

Tested: fake images, a broken PNG, an `.exe`, an HTML file renamed `.jpg`, oversized files, unknown types, cross-dealer access.

## 6. Document security

**COMPLETE AND VERIFIED.**

- Files live on the private `local` disk (`storage/app/private`); nothing goes to the public disk (tested), and `/storage/<path>` is not served (tested).
- Viewing goes through authorised routes only:
  - the dealer sees their own documents (another dealer gets 404, guests are sent to login)
  - admins see any document, and every view is audited
- Responses are `inline`, `no-store`, `nosniff` and `noindex`.
- Security relies on authorisation, not on hidden file names.

## 7. Admin review workflow

**COMPLETE AND VERIFIED by automated tests.** Not checked in the browser: your admin password is needed.

On a dealer's admin page, an admin can:

- approve or reject each document (rejecting needs a reason the dealer sees)
- see a required-document checklist
- start review, request more documents, verify (only offered, and only accepted, once every required document is approved), reject, suspend or lift a suspension, with reasons where required
- keep a staff-only internal note (tested: never shown to the dealer)

Review history comes from the existing audit log, which cannot be edited or deleted. Reviewed documents can no longer be deleted by the dealer (tested).

## 8. Dealer user experience

**COMPLETE AND VERIFIED** (the verification page checked in the browser for a verified dealer, at desktop and phone widths).

- **Status headline** with the next step:
  - Verification not started
  - Documents required
  - Ready to submit
  - Submitted for review
  - Under review
  - Additional documents required
  - Verified
  - Verification rejected
  - Account suspended
  - Verification expired
- **The reviewer's reason** and per-document status and notes.
- **Required-document checklist.**
- **Submit for review,** enabled once the required documents are uploaded.
- **Removal** only for unreviewed documents while not in review.
- **The dashboard banner** uses the same headline.

## 9. Trust badges

**COMPLETE AND VERIFIED.**

- The verified badge appears only for `verified` dealers (`isVerified()`), on the directory, dealer profile, cards, listing pages and compare.
- Any dealer who isn't verified is shown publicly as "Verification pending". The in-progress review states ("Submitted for review" and so on) are never shown publicly (tested).
- No CNIC data, document names, notes or internal flags appear publicly (tested).
- Expired and suspended dealers lose the badge.

## 10. Exact property map picker

**COMPLETE AND VERIFIED.** This is the existing Phase 1 picker plus the Phase 7 city → area → phase → block selects:

- The map centres on the chosen location's centre, or the city's.
- The dealer places or drags the pin.
- The area centre is never saved as the pin (tested: no pin stays no pin).

## 11. Location privacy

**COMPLETE AND VERIFIED** (tested; visually checked for exact and area-centre listings).

| Option | Public point |
|---|---|
| Exact | The pin |
| Approximate | The centre of a 0.005° grid cell (about 550 m), shown as a dashed marker with a shaded circle |
| Area only | None; the listing appears with its area marker |

- The dealer's pin is always kept internally, for the dealer and reviewers.
- Public maps, map markers, viewport search, drawn-area search, Nearby Places and the Google Maps link use only the public point.
- Tested: a drawn shape around a hidden pin finds nothing, and the hidden pin never appears in the page HTML.
- Existing listings default to "exact", which is how they were shown before.
- The approximate marker's appearance was not checked in the browser, because no published listing uses it and changing one would modify real data.

## 12. Map validation

**COMPLETE AND VERIFIED, with one limitation.**

- Coordinates must be numeric, given as a pair, and inside the Pakistan bounding box (existing behaviour); Dubai is rejected (tested).
- New checks flag rather than reject:
  - a pin more than 40 km from the chosen city's centre (65 km for Karachi)
  - a pin more than 10 km from the chosen area's centre

  The listing's location state becomes "flagged" with the distance as the reason, for review (tested: a Karachi pin on a Lahore listing).
- **Limitation:** the bounding box is a rectangle, so border slivers of India, Afghanistan and Iran pass it (e.g. Delhi). The city-distance check flags those for review. A true border check would need an official boundary polygon, which I didn't have and didn't invent.

## 13. Listing location quality

**COMPLETE AND VERIFIED.** `location_status` takes these values:

| Value | Meaning |
|---|---|
| `none` | No pin and no area centre |
| `approximate` | No pin; the area centre is used |
| `exact` | Pin supplied, not yet checked by a reviewer |
| `verified` | Reviewer marked the pin as checked; shown publicly as "Location checked" |
| `flagged` | Automatic distance flag, or a reviewer's flag with a reason |

Moving the pin or changing the area clears a reviewer's verification (tested). A dealer-supplied pin is never called "verified".

## 14. Image pipeline

**COMPLETE AND VERIFIED.**

- **Validation:** type, ≤ 5 MB, 400×300 to 8000×8000 px, image-content check, a 40-megapixel decode cap.
- **Processing:** decoded, EXIF-orientated, re-encoded (strips all metadata including GPS).
- **Errors:** a failure rolls back the whole save with a clear error (tested with a fake image).
- **Moderation:** per photo — active, flagged, rejected or removed. Hidden photos stay on file and are excluded from the public gallery and cover; a reason is required; the action is audited (tested).
- **No automatic image analysis** is used or claimed.

## 15. Image optimisation

**COMPLETE AND VERIFIED.** Two variants per photo:

- a full display image (≤ 1600 px WebP)
- a card/thumbnail image (≤ 640 px WebP)

These match the actual UI: the gallery uses the full image, cards and maps use the thumbnail.

**Original uploads are not kept**, deliberately: they carry EXIF and GPS, and storing them would double the storage.

**The 42 existing photos** predate processing (no thumbnails) and keep working unchanged. `php artisan meezan:optimize-images` (Phase 3) can process them; it was not run here, so no existing image was changed.

## 16. Image ordering

**COMPLETE AND VERIFIED** (Phase 3 feature, re-tested):

- Set the cover, reorder, remove the listing's own photos, add more up to 15.
- Another listing's photos can't be removed (tested: 403 on someone else's listing, and IDs from another listing are ignored on your own).
- The cover is marked on the admin page.

## 17. Listing completeness scoring

**COMPLETE AND VERIFIED.** Scored out of 100:

| Check | Points |
|---|---|
| Title of at least 20 characters | 10 |
| Description of at least 60 words | 15 |
| Price, type and purpose | 10 |
| City and area | 10 |
| Size | 10 |
| Bedrooms and bathrooms (where relevant) | 10 |
| At least 5 photos | 15 |
| Photos at least 1000 px wide | 5 |
| Exact pin (any privacy level) | 10 |
| Street/block or features | 5 |

- Shown to the dealer with a progress bar and the missing items, labelled as "how much information buyers get, not a rating of the property".
- Stored for reviewer filters. Never shown to buyers.
- Tested: a bare listing scores under 60 with the right hints; a complete one scores 100.

## 18. Listing workflow

**COMPLETE AND VERIFIED.** `PropertyStatus` was extended with `changes_requested` and `suspended`. Existing values and labels are unchanged.

| Brief's state | Implementation |
|---|---|
| Draft | `draft` |
| Ready for submission | A draft at ≥ 60% completeness (computed, not a status) |
| Pending review | `pending` |
| Changes requested | `changes_requested`: reason required; the dealer sees it and can resubmit |
| Approved / Published | `published` |
| Rejected | `rejected`: reason required |
| Suspended | `suspended`: reason required; hidden from the public; can be reinstated |
| Expired / Archived | `expired` / `archived` |

- **Reviewer actions:** approve, reject, request changes, suspend, reinstate, unpublish, archive.
- **Internal markers:** mark or clear "requires review", verify or flag the location, moderate photos.
- **Reviewer checklist:** dealer verification, location, completeness, duplicate reasons, burst submissions, large price change, open reports, flagged photos, changes requested.
- All decisions are audited, and the dealer is notified of every decision except archive and reinstate.

## 19. Duplicate detection improvements

**COMPLETE AND VERIFIED.**

- **Signals:**
  - same area
  - size ±2%
  - price ±10%
  - pins within 150 m
  - same address (normalised)
  - very similar title (≥ 85%)
  - same dealer
- **Pre-filters:** same purpose, type and city.
- **When it counts as a candidate:**
  - the old rule (area + size + price)
  - a nearby pin or the same address plus one more signal
  - any four signals
  - a similar title plus size and price
- **When it runs:** once at submission (stored in `review_flags` with reasons), not on every page.
- **Who sees it:** the dealer gets the reasons ("…similar to MP-000123 (map pins within 150 m, same address)"); reviewers see them on the listing.
- **Nothing is deleted or hidden** (tested).
- **The admin duplicates page** still checks live (an existing test relies on that), capped at 100 pending listings.

## 20. Fraud/trust signals

**COMPLETE AND VERIFIED.** Stored at submission:

- duplicates
- more than 15 submissions by the dealer in 24 hours
- a price change of more than 30% since the last published price
- a location flag (sets `flagged_at`)

Wording is neutral throughout: "Duplicate suspected", "Requires review", "Dealer verification required". There are no accusations, and none of it is public.

## 21. Admin trust dashboard

**COMPLETE AND VERIFIED by automated tests** (counts tested; not opened in the browser: admin sign-in needed).

`/admin/trust` ("Marketplace Trust" in the admin menu) shows live database counts, each linking to the filtered list:

- dealers awaiting verification, documents awaiting review, verified dealers, suspended dealers
- listings awaiting review, changes requested, suspended listings, marked as requiring review
- suspected duplicates, live listings without an exact pin, flagged locations, low completeness, flagged or hidden photos

The admin listings list gained a matching `flag` filter.

## 22. Notifications

**COMPLETE AND VERIFIED (in-app). Email: COMPLETE BUT CONFIGURATION-BLOCKED (`MAIL_MAILER=log`).**

**New `DealerVerificationUpdated`:**

- document approved
- document rejected
- more documents requested
- verified
- rejected
- suspended
- expired

**`ListingReviewed` extended:** changes requested, suspended (approve and reject existed).

Admins are told when a dealer submits. All of these use the existing queued in-app + mail base; adding WhatsApp later means adding a channel to that base. **WhatsApp delivery: NOT IMPLEMENTED.**

## 23. Public profile integration

**COMPLETE AND VERIFIED.** See §9.

- Dealer directory, profile, cards, listing pages and compare show "Verified" only when it is true.
- The public location badge shows "Exact location", "Location checked", "Approximate location", "Area only" or "Approximate area"; internal flags never appear.

## 24. Existing data preservation results

Recorded before the migrations and again after all work:

| Measure | Before | After |
|---|---|---|
| Users | 4 | 4 |
| Dealer profiles (status) | 2 (verified 2) | 2 (verified 2) |
| Properties incl. deleted | 17 (published 16, draft 1) | 17 (published 16, draft 1) |
| With exact pin / with area | 1 / 17 | 1 / 17 |
| Property images / files missing | 42 / 0 | 42 (all active) / 0 |
| Dealer documents | 0 | 0 |
| Areas, leads, requirements, conversations | 1,238 / 0 / 0 / 2 | 1,238 / 0 / 0 / 2 |

- The listing with a pin keeps "exact" privacy, so its public point equals its pin, as before.
- No passwords, users, properties or images were changed or deleted.
- No data was reassigned.

## 25. Files changed

**New files:**

- **Migrations:** `database/migrations/2026_10_31_000025_add_verification_workflow_to_dealers.php`, `000026_add_trust_fields_to_properties.php`
- **Services:** `app/Services/DealerVerification.php`, `ListingCompleteness.php`, `TrustSignals.php`
- **Support:** `app/Support/Geo.php`
- **Notifications:** `app/Notifications/DealerVerificationUpdated.php`
- **Commands:** `app/Console/Commands/RefreshListingTrust.php`, `ExpireDealerVerifications.php`
- **Controller and view:** `app/Http/Controllers/Admin/TrustDashboardController.php`, `resources/views/admin/trust.blade.php`
- **Tests:** `tests/Feature/MarketplaceTrustTest.php`
- **Report:** this file

**Modified files:**

- **Enums:** `app/Enums/DealerStatus.php`, `PropertyStatus.php`
- **Models:** `app/Models/DealerProfile.php`, `DealerDocument.php`, `Property.php`
- **Services and support:** `app/Services/DuplicateDetector.php`, `NearbyPlaces.php`, `PropertySearch.php`, `app/Support/MapMarkers.php`
- **Notifications:** `app/Notifications/ListingReviewed.php`
- **Controllers:**
  - `app/Http/Controllers/Dealer/VerificationController.php`
  - `Dealer/PropertyController.php`
  - `Admin/DealerController.php`
  - `Admin/PropertyController.php`
  - `Admin/DashboardController.php`
  - `PropertyController.php`
- **Request:** `app/Http/Requests/PropertyRequest.php`
- **Config and routes:** `config/meezan.php`, `routes/web.php`, `routes/console.php`
- **JavaScript:** `resources/js/map.js`
- **Views:**
  - `resources/views/dealer/verification.blade.php`
  - `dealer/dashboard.blade.php`
  - `dealer/properties/form.blade.php`
  - `dealer/properties/index.blade.php`
  - `dealer/properties/partials/location-picker.blade.php`
  - `admin/dealers/show.blade.php`
  - `admin/properties/show.blade.php`
  - `components/layouts/dashboard.blade.php`
  - `properties/show.blade.php`
  - `properties/partials/location-status.blade.php`
  - `properties/partials/map-panel.blade.php`

**Routes added (6):**

| Method | Route |
|---|---|
| POST | `/dealer/verification/submit` |
| PATCH | `/admin/dealer-documents/{document}/review` |
| PATCH | `/admin/dealers/{dealer}/note` |
| PATCH | `/admin/properties/{property}/review` |
| PATCH | `/admin/property-images/{image}/moderate` |
| GET | `/admin/trust` |

**Scheduled:** `meezan:expire-verifications` daily. It does nothing unless `MEEZAN_VERIFICATION_VALID_MONTHS` is set.

## 26. Automated test results

| Command | Result |
|---|---|
| `php artisan test` | **361 passed, 1,605 assertions, 0 failed** (339 from before + 22 new in `MarketplaceTrustTest.php`; one earlier test updated for the strict verification rule with your approval, §3) |
| `npm run build` | Success |
| `composer audit` | No advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `vendor/bin/pint --test` | Passed |
| `php artisan route:cache` | Routes cached successfully (then cleared) |

Two new rules were adjusted so that existing tests still pass unchanged. Neither weakens the brief's requirements:

- **Image minimum size** became optional; existing tests upload tiny fake images.
- **The PDF header check** relies on the `mimes` rule's content detection; an existing test uses a fake PDF.

## 27. Manual test results

In the in-app browser:

| Check | Result |
|---|---|
| Dealer verification page (verified demo dealer) | Status "Verified", accurate checklist (no documents were ever uploaded) |
| Listing edit form | Privacy choice shown with "exact" selected; completeness 60% with 3 concrete hints |
| Public listing without a pin | "Approximate area" badge; only the area-centre marker in the page data |
| Search results | 12 cards with genuine verified badges |
| Browser console | No errors |
| Phone width (375 px) | Listing form and verification page fit with no horizontal scroll |
| Laravel log | No errors except two from the moment the trust route existed before its controller (resolved) |

**Not checked manually:**

- all admin pages (your admin sign-in is needed)
- a real document upload and resubmission in the browser (tests cover them; skipped to avoid adding records to the demo account)
- creating and submitting a new listing, photo upload and reordering in the browser (tests cover them)
- the appearance of the approximate-privacy marker
- a public page for a listing with "area only" privacy

## 28. Known limitations

1. The two dealers verified before this phase have no documents on file; they keep their status, but would need approved documents to be verified again (for example after an expiry).
2. The Pakistan check is a bounding box; border slivers are flagged by the city check rather than rejected (§12).
3. The admin duplicates page computes candidates live (capped at 100 listings).
4. The 42 existing photos have no thumbnails; `meezan:optimize-images` can process them.
5. No automatic image analysis, no WhatsApp notifications, and email isn't live.
6. Suspending a dealer doesn't hide their listings. Listings are suspended individually.
7. The approximate-privacy marker hasn't been visually checked in the browser.

## 29. External services required

| Feature | Requirement |
|---|---|
| Email notifications | A real `MAIL_MAILER` and `MAIL_FROM_ADDRESS` |
| WhatsApp notifications | WhatsApp Cloud API credentials and approved message templates |
| Verification expiry (optional) | `MEEZAN_VERIFICATION_VALID_MONTHS` |
| Minimum document image size (optional) | `MEEZAN_DOCUMENT_MIN_IMAGE_PX` |
| Precise border validation (optional) | An official Pakistan boundary dataset |

## 30. Recommended next phase

1. **Admin QA by you:** the dealer review panel, the listing checklist and photo moderation, and the trust dashboard.
2. **Collect documents from the two already-verified dealers,** so their verification has evidence on file.
3. **Run the photo backfill:** `php artisan meezan:optimize-images` so older photos get thumbnails.
4. **Optionally hide a suspended dealer's listings automatically,** with a reversible record of their previous status.
5. **Connect a mail provider,** then add a WhatsApp notification channel for dealers who opt in.

---

## Totals

| Measure | Value |
|---|---|
| Tests passed | **361** |
| Assertions | **1,605** |
| Failures | **0** |
| Tables added | 0 |
| Tables changed | 4 (`dealer_profiles`, `dealer_documents`, `properties`, `property_images`) |
| Routes added | 6 |
| Existing data before → after | Users 4 → 4 · properties 17 → 17 · images 42 → 42 · dealers 2 → 2 · areas 1,238 → 1,238 |
