# Meezan Property — Phase 3 Completion Report

**Phase:** Trust & media: dealer verification documents, photo processing, photo ordering and cover choice
**Date:** 30 Oct 2026 · **Environment:** local only (XAMPP, http://localhost:8000)
**Not done, as instructed:**
- no git, GitHub or deployment
- no destructive database commands
- `public/Refrence/` untouched
- palette unchanged
- **no new packages** (image processing uses PHP's built-in GD extension)

---

## 1. Executive summary

**Dealer verification documents**
- **Upload:** a new **Verification** page in the dealer portal (`/dealer/verification`). It matches the "Document upload" step in the Dealer Platform reference.
  - **Document types:** CNIC (front and back), agency registration or licence, office proof, ownership document (for private owners), and "other".
  - **Files:** JPG, PNG, WebP or PDF, up to 5 MB each, up to 4 per upload, 10 per dealer.
  - **Checklist:** the page shows which suggested documents are already uploaded; the suggestions differ for agencies and private owners.
  - **Privacy note:** a plain-language note explains how documents are handled.
- **Storage:**
  - files are kept on the **private** disk (`storage/app/private/dealer-documents/{profile}/`) under random names
  - the file type is detected from the file's content, not trusted from the browser
  - nothing is ever placed in `public/`
- **Viewing:**
  - only through authorised routes, by the dealer who uploaded it or by an admin
  - files are sent with `nosniff`, `no-store` and `noindex` headers
  - **every admin view is written to the audit log**
- **Effects:**
  - admins get a queued email; the documents are never attached
  - uploading after a rejection puts the application **back into review**
- **Removal:** dealers can remove documents only until they're verified. After that, documents are kept as the record of the check, but verified dealers can still add new ones, for example a renewed licence.
- **Admin side:**
  - the dealer review page has a "Verification documents" card with an Open button for each file
  - the dealer list shows each dealer's document count
- **Dealer dashboard:** the "verification pending" banner now has an "Upload documents" button.
- **Navigation:** the sidebar has separate **Agency Profile** and **Verification** items; the old combined "Profile & Verification" item is gone.

**Photo processing (`App\Services\ImageProcessor`)**
- Every new listing photo is:
  - decoded
  - **turned upright** from its EXIF orientation (sideways phone photos)
  - scaled down to at most **1600 px** on the longest side, never scaled up
  - **re-encoded as WebP** (quality 82)
- **Location data:** re-encoding **removes all metadata, including EXIF GPS coordinates** that could reveal an owner's address.
- **Thumbnails:** a **640 px** thumbnail is also generated and used on cards, lists, dashboards, compare, AI results and map pin previews. The full image is used for the listing gallery.
- **Safety limits:**
  - photos larger than 8000 × 8000 px or 40 megapixels are rejected before decoding, so memory can't run out
  - if any photo fails, the save is rolled back, files already written are deleted, and the dealer sees which photo to fix
- **Older photos:** `php artisan meezan:optimize-images [--dry-run] [--delete-originals]` converts existing photos in the same way. It keeps the originals unless asked. **I did not run it on your data** (dry run only, see Section 3).

**Photo ordering and cover choice**
- On the edit form, each photo has:
  - a **Cover** choice
  - **← → arrows** to reorder (these need JavaScript; the Cover choice works without it)
  - **Remove photo**
- Reordering doesn't send a live listing back to review, because the photos themselves haven't changed. Photo ids that don't belong to the listing are ignored on the server.

---

## 2. Files changed

### Created
| File | Purpose |
|---|---|
| `database/migrations/2026_10_30_000015_add_dealer_documents_and_image_variants.php` | `dealer_documents` table; `property_images.thumb_path`, `width`, `height`. Additive only. |
| `app/Models/DealerDocument.php` | Types, limits, private-disk streaming with safe headers, file deletion. |
| `app/Http/Controllers/Dealer/VerificationController.php` | Verification page; upload, view and remove documents. |
| `app/Http/Controllers/Admin/DealerDocumentController.php` | Admin document view, written to the audit log. |
| `app/Notifications/DealerDocumentsSubmitted.php` | Queued email to admins when documents are uploaded. |
| `app/Services/ImageProcessor.php` | GD pipeline: orientation, resize, WebP, thumbnail, metadata removed. |
| `app/Console/Commands/OptimizeListingImages.php` | `meezan:optimize-images` for existing photos. |
| `resources/views/dealer/verification.blade.php` | Verification documents page. |
| `tests/Feature/DealerDocumentsTest.php` | 7 tests. |
| `tests/Feature/ListingPhotosTest.php` | 9 tests. |
| `docs/Meezan_Property_Phase_3_Completion_Report.md` | This report. |

### Modified
| File | Change |
|---|---|
| `app/Models/DealerProfile.php` | `documents()` relation. |
| `app/Models/PropertyImage.php` | Fillable `thumb_path`, `width`, `height`; `thumbUrl()` (falls back to the full image); `deleteFiles()`. |
| `app/Models/Property.php` | `coverThumbUrl()`. |
| `app/Http/Controllers/Dealer/PropertyController.php` | Photos go through `ImageProcessor`, with clean-up and rollback on failure. New `applyPhotoOrder()`. Removing a photo deletes both files. |
| `app/Http/Requests/PropertyRequest.php` | Photo limit of 8000 × 8000 px; `image_order[]` and `cover_image` rules (kept out of the listing data). |
| `app/Http/Controllers/Admin/DealerController.php` | Loads documents on the dealer page; document count on the list. |
| `config/meezan.php` | `verification.max_document_kb` (5120). |
| `routes/web.php` | Routes `dealer.verification`, `dealer.documents.store` (throttled 10 a minute), `dealer.documents.show`, `dealer.documents.destroy`, `admin.dealers.documents.show`. |
| `resources/views/dealer/properties/form.blade.php` | New photo grid (Cover, arrows, Remove photo) using thumbnails; note that photos are resized and saved without location data. |
| `resources/js/app.js` | Photo reorder arrows. |
| `resources/views/components/layouts/dashboard.blade.php` | Dealer nav: "Agency Profile" and "Verification". |
| `resources/views/dealer/dashboard.blade.php` | "Upload documents" button on the verification banner. |
| `resources/views/admin/dealers/show.blade.php`, `admin/dealers/index.blade.php` | Documents card; document count. |
| Thumbnail views (`components/property-card`, `account/dashboard`, `account/inquiries`, `account/saved`, `admin/dashboard`, `admin/properties/index`, `admin/properties/show`, `ai-finder/partials/results`, `compare`, `dealer/dashboard`, `dealer/performance`, `dealer/properties/index`, `properties/show` side photos) and `app/Support/MapMarkers.php` | Use the thumbnail instead of the full photo. |
| `docs/zillow-gap-report.md` | Row 21 added. |
| `public/build/*` | Rebuilt. |

### Deleted
None.

---

## 3. Database and data

- **Migration** `2026_10_30_000015_add_dealer_documents_and_image_variants`, run in batch 8:
  - **New table `dealer_documents`:**
    - `dealer_profile_id` → dealer_profiles (cascade on delete)
    - `uploaded_by` → users (set to NULL if the user is deleted)
    - `type`, `path`, `original_name`, `mime`, `size`, timestamps
    - index on (`dealer_profile_id`, `type`)
  - **New `property_images` columns:** `thumb_path`, `width` and `height`, all nullable. The 42 existing photos keep `thumb_path = NULL` and keep working, because they fall back to the full image.
- **Data preserved:** 4 users, 17 listings, 42 photos, 0 documents, 0 queued jobs. Nothing was changed or deleted.
- **`meezan:optimize-images --dry-run`:** would convert all 42 existing photos and skip none. The current demo photos total only **0.4 MB**, because they are small crops from the mockups, so converting them gains little. Run it, without `--dry-run`, once real photos exist.
- **Browser check:** I did **not** upload any document as the demo dealer. That dealer is verified, so a test upload couldn't be removed afterwards. Uploads are covered by the automated tests instead.

---

## 4. Security notes

- **Who can open a document:**
  - its own dealer
  - an admin, with each view written to the audit log
  - another dealer gets 404, a buyer gets 403, a guest is sent to sign in (all tested)
- **Rejected uploads:**
  - **Blocked file types:** HTML disguised with another name, executables, unknown document types, files over 5 MB (all tested).
  - **Accepted types:** images and PDFs only. SVG is not accepted, so no script-bearing image can be uploaded.
- **Headers** on document responses:
  - `X-Content-Type-Options: nosniff`
  - `Cache-Control: private, no-store, max-age=0`
  - `X-Robots-Tag: noindex`
  - inline display
- **Listing photos:** decoded and re-encoded, so a file that only looks like an image never reaches the public disk as uploaded. GPS and other metadata are removed (tested with a JPEG containing an EXIF block).
- **Private disk:** Laravel 12's `local` disk has `serve => true`. That only serves files through **temporary signed URLs**, which this app never creates, so document files can't be reached directly.

---

## 5. Tests

**Commands**
```
php artisan test
php artisan test --compact tests/Feature/ListingPhotosTest.php tests/Feature/DealerDocumentsTest.php
npm run build
php artisan route:list --except-vendor
php artisan migrate:status
php artisan meezan:optimize-images --dry-run
```

| Result | Value |
|---|---|
| Total tests | **215** (199 before + 16 new) |
| Assertions | **832** |
| Passed / Failed / Skipped | 215 / 0 / 0 |
| Build | Succeeded, no warnings |
| Routes | **129** (124 + 5) |
| Migrations | 18 run, 0 pending |
| Laravel log | 0 errors today |

**What the new tests cover**

*Photos*
- **Stored output:** WebP format, 1600 px and 640 px sizes.
- **No metadata kept:** a test JPEG containing an EXIF block and a marker string comes out with no "Exif" and no marker.
- **Orientation:** an EXIF Orientation 6 photo comes out portrait.
- **Small photos:** never scaled up.
- **Failure handling:** a failed photo rolls back the listing and leaves no files behind.
- **Size limits:** photos that are too small or too large are rejected.
- **Ordering:**
  - the chosen cover goes first
  - ids from another listing are ignored
  - a published listing stays published
- **Removing a photo:** deletes both files.
- **Cards:** use thumbnails, with a fallback for older photos; the detail page uses the full image.
- **Conversion command:** the dry run changes nothing; a real run converts and keeps the originals.

*Documents*
- **Upload:** files are stored privately with random names, nothing on the public disk; admins are emailed and the upload is audited.
- **Rejected uploads:** unsafe files, oversized files, unknown types and empty uploads.
- **Limits and status:** the 10-document cap is enforced; uploading after a rejection puts the application back in review.
- **Access:** viewing works only for the right users, with the private headers set; admin views are audited.
- **Removal:** allowed only until the dealer is verified.
- **Listing:** documents appear on the dealer's page, the admin dealer page and the admin list count.

---

## 6. Manual verification (browser)

| Check | Result |
|---|---|
| `/dealer/verification` as the demo dealer: page renders with the upload form, document type list, status, checklist and privacy note | OK |
| Network: the verification page and its assets all returned 200 | OK (the one 403 in the console was an earlier scripted sign-in request to an admin URL) |
| Edit form of demo listing MP-000001: 4 photos with Cover, arrows and Remove photo; clicking → moved photo 43 after photo 44 (hidden order became 44,43,45,46); cover stays as chosen | OK (**not saved**) |
| First layout clipped "Remove" and showed blank lazy-loaded tiles at narrow widths; fixed by moving the controls under each photo, using 2 / 3 / 4 columns, and loading thumbnails straight away | Fixed and re-checked |
| Phone width (375 px): photo grid in 2 columns, no horizontal scroll | OK |
| Actual upload of a photo or document in the browser | **Not done.** It would have added files and records to your local data, and a document on the verified demo dealer couldn't be removed. Covered by automated tests. |
| Admin dealer page with documents | **Not checked in the browser** (the demo admin's password has been changed). Covered by automated tests. |

---

## 7. Remaining backlog (updated)

- **P0:** none for the local MVP. **Before deployment:** set up a mail provider and a queue worker, turn on `MEEZAN_REQUIRE_EMAIL_VERIFICATION`, set production settings (`APP_DEBUG=false`, `APP_URL`), and use a commercial map tile service and geocoder.
- **P1**
  1. Centre coordinates for DHA Rahbar, EME Society, Etihad Town and Punjab Coop Housing Society (values from you).
  2. Legal review of the terms and privacy pages; real photography; then run `meezan:optimize-images`.
  3. Dependency audit (`composer audit`, `npm audit`) and a production settings checklist.
  4. A document retention rule, for example deleting documents of rejected or closed accounts after N months. **Needs a business or legal decision.**
- **P2**
  1. In-app notification centre (bell icon).
  2. WhatsApp templates, opt-in/opt-out and notifications.
  3. Nearby Places; days-on-market and price-reduced filters.
  4. Bulk moderation; AI chat memory; live LLM test.
  5. Drag-and-drop photo ordering (arrows today).
- **P3:** subscriptions and payments, dealer reviews, Urdu UI and more cities, analytics with consent, a native app or public API.

---

## 8. Known issues

1. **Existing photos are not converted yet.** The 42 demo photos are still the original JPEGs without thumbnails. They work, but run `meezan:optimize-images` once real photos exist.
2. **Photo reordering needs JavaScript** for the arrows. Without JavaScript, dealers can still choose the cover.
3. **Not checked in the browser:** real file uploads (avoided on purpose, Section 6) and the admin documents card (admin password changed). Covered by automated tests.
4. **Document retention is undefined.** Documents stay until the dealer removes them (while not yet verified) or an admin deletes them. There is no admin delete button yet. Needs a policy decision.
5. **Still from earlier phases:**
   - mail is `log` only and needs a queue worker
   - QA draft MP-000017 remains in the local database
   - WhatsApp, LLM, SMS and payments are not configured
   - public OpenStreetMap services are for light use only
   - `APP_DEBUG=true`
   - no version control (deferred by you)

---

## 9. Next recommended phase

**Phase 4: Discovery & engagement.** This covers:
- an in-app notification centre for dealers and admins, reusing the Phase 2 notifications on Laravel's database channel
- days-on-market and price-reduced search filters (the data already exists)
- **Nearby Places** on listing pages that have an exact pin, using OpenStreetMap Overpass data, cached

**Why:**
- The trust and lifecycle foundations are now in place.
- The biggest remaining gaps are on the buyer-facing discovery side, which the Zillow-style vision depends on, and on in-app awareness for dealers who don't read email.
- All of it can be built and tested locally, with no paid services.
