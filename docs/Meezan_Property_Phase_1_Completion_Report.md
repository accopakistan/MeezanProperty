# Meezan Property — Phase 1 Completion Report

**Phase:** Property location system, interactive map pin picker and local application audit
**Date:** 30 Oct 2026 · **Environment:** local only (XAMPP, `C:\xampp\htdocs\MeezanProperty`, http://localhost:8000)
**Not done, as instructed:** no git, no GitHub, no deployment, no hosting or domain changes, no destructive database commands, `public/Refrence/` untouched.

---

## Section 1 — Executive summary

**Implemented**
- An interactive **location picker** inside the existing dealer listing form, for both create and edit:
  - click or tap to drop a pin, drag it to fine-tune
  - latitude/longitude fields that stay in sync with the pin both ways
  - Clear pin, Use my location, and a place search that only moves the map
  - an "exact location set / not set" indicator
  - a warning when the pin is far from the chosen area's centre
- **Server-side coordinate validation:**
  - coordinates must be a pair, numeric, and inside a Pakistan bounding box held in config
  - clear error messages
  - coordinates are rounded to the column's 7-decimal precision
  - clearing the pin saves NULL
  - requests that omit the fields leave saved coordinates unchanged
- **Place search endpoint** (`GET /dealer/location-search`): dealers only, rate-limited, server-side proxy to OpenStreetMap Nominatim, Pakistan-only, English names, short labels, results cached for 7 days. Failures are never cached and never surface as errors.
- **Location status labels** on the public listing page and the admin listing page: *Exact location* / *Approximate area* / *Location not provided*.
- **Admin review map:** the admin listing page now has a Location card with a map, so reviewers can check the dealer's pin against the stated area before approving.

**Improved**
- The old collapsed "Exact map location (optional)" box with two number fields was replaced by the picker. Its field names are unchanged, so the controller and model needed no changes.
- Validation messages for coordinates are clearer.
- Test helpers `listingPayload()` and `markersOn()` moved into `tests/Pest.php` so every test file can use them. Their contents are unchanged.

**Connected to the existing system (no redesign)**
- Pins saved by dealers flow straight into the existing public map: price pins, pop-ups that link to the listing, viewport search and drawn-area search.
- Listings without a pin keep using the existing approximate area grouping.

**Still incomplete:** see Sections 7 and 8. The main gaps are email/notifications, listing renewal, dealer verification documents, WhatsApp go-live and payments. External services (mail, WhatsApp, LLM) are **not** configured, and none is claimed to be live.

---

## Section 2 — Files changed

### Created
| File | Why |
|---|---|
| `resources/js/location-picker.js` | The picker: Leaflet map, a single draggable pin, two-way sync with the latitude/longitude inputs, Clear, Use my location, place search, distance warning. Initialises once per form (guard flag) and creates only one marker instance. Lazy-loaded only on pages that have a picker. |
| `resources/views/dealer/properties/partials/location-picker.blade.php` | Picker markup inside the existing Location card; passes config (tile server, Pakistan bounds, area centres, search URL) to the script. The latitude/longitude inputs still work without JavaScript. |
| `resources/views/properties/partials/location-status.blade.php` | The Exact / Approximate / Not provided badge, shared by the public and admin listing pages. |
| `app/Services/Geocoder.php` | Server-side Nominatim place search: limited to Pakistan, English names where available, labels shortened, duplicates removed, 7-day cache of successful look-ups only, failures logged. |
| `app/Http/Controllers/Dealer/LocationSearchController.php` | JSON endpoint for the picker's search; validates the query and drops any result outside the Pakistan bounds. |
| `tests/Feature/LocationPickerTest.php` | 14 tests (22 cases with datasets) covering create, edit, validation, authorisation, public map, viewport/draw search, location labels, admin view and place search. |
| `docs/Meezan_Property_Phase_1_Completion_Report.md` | This report. |

### Modified
| File | Change and reason |
|---|---|
| `config/meezan.php` | Added `map.pakistan_bounds` (south 23.5, north 37.2, west 60.5, east 78.0) and `map.area_warning_km` (6), so the bounds are defined in one place for validation, the endpoint and the script. |
| `app/Http/Requests/PropertyRequest.php` | Latitude/longitude rules now read the config bounds. Clearer messages for out-of-country, non-numeric and partial-pair input. `listingData()` rounds to 7 decimals and saves NULL when the pin is cleared, but only when the fields are present in the request. The `required_with` pair rule and `nullable` are kept. |
| `app/Http/Controllers/Dealer/PropertyController.php` | `areas()` now also selects `latitude, longitude` so the form can centre the map on the chosen area. Nothing else changed. |
| `app/Http/Controllers/Admin/PropertyController.php` | `show()` passes `MapMarkers::for([$property])` for the new admin location card. |
| `app/Providers/AppServiceProvider.php` | New `location-search` rate limiter: 15 requests a minute per user. |
| `routes/web.php` | New route `GET /dealer/location-search` → `dealer.location-search`, inside the existing `auth` + `role:dealer` group, with `throttle:location-search`. |
| `resources/views/dealer/properties/form.blade.php` | Replaced the collapsed coordinates `<details>` block with `@include('dealer.properties.partials.location-picker')`. |
| `resources/views/properties/show.blade.php` | Location heading now shows the status badge; exact pins carry the note "The pin was placed by the advertiser. Confirm the exact location when you visit." |
| `resources/views/admin/properties/show.blade.php` | Coordinates moved out of the details grid into a new Location card with the status badge, coordinates, map and a reviewer hint. |
| `resources/js/app.js` | Lazy-imports `location-picker.js` when a `[data-location-picker]` element exists. |
| `resources/css/app.css` | Styles for the picker pin (`.mp-picker-pin`). |
| `tests/Pest.php` | Now holds the shared helpers `listingPayload()` and `markersOn()`. |
| `tests/Feature/DealerPortalTest.php`, `tests/Feature/MapMarkersTest.php` | Their local helper functions moved to `tests/Pest.php`. No test was removed or weakened. |
| `docs/zillow-gap-report.md` | Added row 19 (location picker). |
| `public/build/*` | Regenerated by `npm run build`. Leaflet is now a shared chunk used by both the map search and the picker. |

Laravel Pint also normalised line endings in `routes/web.php`, `tests/Pest.php` and the two test files above; this is a formatting-only change.

### Deleted
None. A temporary debugging test (`tests/Feature/ZzDebugTest.php`) was created and removed during this phase while finding a test bug. It is not in the project.

---

## Section 3 — Database changes

- **New migrations:** none.
- **Existing columns reused:**
  - `properties.latitude` and `properties.longitude` are `decimal(10,7)`, nullable, created by `2026_10_29_000004_create_properties_table`, cast to `float` on the model.
  - `areas.latitude` and `areas.longitude` (from `2026_10_29_000013`) are read only, to position the map.
- **Schema changes applied:** none. `php artisan migrate:status` shows 16 of 16 migrations run and none pending.
- **Data changes made by the manual browser check:**
  - One draft listing was created through the dealer form to verify create, edit and clear end to end:
    - **MP-000017** (id 17), "QA test listing - location picker check (draft, safe to remove)"
    - owner: the demo dealer
    - status: `draft`, which is never public
    - pin: 31.4805123, 74.3239456
    - `is_demo = 0`, because the dealer form cannot set that flag
  - It was deliberately not deleted, following the "do not delete records" rule. You can remove it from *Dealer → My Listings → Edit → Remove listing* (this archives it with a soft delete).
  - Place-search results were cached in the `cache` table, and login sessions were written to `sessions`.
- **Data preservation:** no existing property, area, user or other record was modified or deleted. The 16 demo listings are unchanged and still have no coordinates.

---

## Section 4 — Location picker functionality

**Map interaction**
- Leaflet (already in use) with the configured tile server: the `MEEZAN_MAP_TILE_URL` setting, OpenStreetMap by default. No second map library, no API key, nothing secret sent to the browser.
- Zoom controls are shown. Scroll-wheel zoom turns on only after the user clicks the map, so the page still scrolls normally.

**Starting view (never a saved value)**
1. The saved pin, if there is one, at zoom 16.
2. Otherwise, the selected area's centre at zoom 14.
3. Otherwise, the city centre.

Changing the area re-centres the map when no pin is set and shows "Showing the centre of X. Click the exact property location to place the pin." **An area or society centre is never written to the coordinate fields.** This is covered by a test: a new listing starts empty even when the area has a centre.

**Marker placement**
- A click or tap places one draggable pin and fills both fields with 7 decimals.
- Clicking again moves the same pin.
- A second marker can never exist; this was checked in the browser.

**Marker dragging:** dragging updates the fields. A drag that ends outside Pakistan is refused with a message, and the fields are not changed.

**Coordinate sync**
- Typing a valid pair moves the pin and pans to it: debounced while typing, applied immediately on change.
- Invalid text, an incomplete pair or an out-of-country pair shows a message and does not move the pin.
- Emptying both fields removes the pin.

**Clear pin:** removes the pin, empties both fields and updates the status badge. On save, the coordinates become NULL.

**Use my location**
- An explicit action, meant for dealers who are at the property.
- It reports the GPS accuracy and asks the dealer to drag the pin if needed.
- The button is hidden when geolocation isn't available or the page isn't on a secure origin.

**Place search**
- Runs when the dealer presses Enter or Find. There is no search-as-you-type, in line with Nominatim's usage policy, and Enter doesn't submit the listing form.
- Choosing a result **only moves the map**; the dealer then places the pin.
- A rate-limit hit (HTTP 429) or a failure shows a friendly message.

**Distance warning:** when the pin is more than 6 km from the selected area's centre, an amber note asks the dealer to check the pin and the area. It is a warning only and does not block saving.

**Server validation** (`PropertyRequest`, which applies to every request, not just the form)
- `latitude`: nullable, `required_with:longitude`, numeric, between 23.5 and 37.2.
- `longitude`: nullable, `required_with:latitude`, numeric, between 60.5 and 78.0.
- Rejected: partial pairs, text, values like "31.48, 74.32" typed into one field, "74°E", 91, 40.1 N, Dubai and London (all tested).
- CSRF is unchanged. Authorisation is unchanged: `PropertyPolicy::update` means only the owning dealer can change a listing's pin. Another dealer or a buyer gets 403 (tested).

**Create workflow:** fill the form → place the pin → *Save as draft* or *Submit for review* → coordinates are stored.

**Edit workflow**
- The saved pin loads on the map and in the fields.
- The dealer can move, retype or clear it and save.
- Moving the pin on a **published** listing sends it back to review, under the existing rule for material edits (tested).

**Public map integration**
- No changes were needed in `MapMarkers` or `PropertySearch`. A published listing with saved coordinates appears as an exact price pin at that point, and its pop-up links to the listing (tested).
- Viewport ("search as I move the map") and drawn-area searches find it (tested).
- Listings without a pin stay in the approximate area groups and never get an exact marker (tested).
- Marker payloads contain listing facts only, with no dealer email or phone (tested).
- Public map searches only read coordinates and never write them, so moving the map cannot change a listing.

---

## Section 5 — Tests

**Commands**
```
php artisan test
php artisan test --compact tests/Feature/LocationPickerTest.php
npm run build
php artisan route:list --except-vendor
php artisan migrate:status
```

| Result | Value |
|---|---|
| Total tests | **174** (152 before this phase + 22 new cases) |
| Assertions | **628** |
| Passed | 174 |
| Failed | 0 |
| Skipped | 0 |
| New tests | `LocationPickerTest`: 14 tests, 22 cases |
| Build | `npm run build` succeeded (about 2 s). New chunk `location-picker` is 6.3 KB; Leaflet is a shared 149.6 KB chunk. No warnings. |
| Routes | **120** (119 + `dealer.location-search`) |
| Migrations | 16 run, 0 pending |
| Laravel log | No new errors or warnings from this phase. The 6 old `ERROR` lines are from development on 29 Oct and are already resolved. |
| Browser console | No errors on the create form, edit form, listing page or public map |

**One failure during development:** a new test first failed. The cause was **in the test itself**: the first update renamed the listing and changed its slug, so the second request used an old URL and got 404. The application was correct. The test now routes by the fresh slug. No application code was changed to make a test pass.

**Regression coverage:** all existing creation, editing, authorisation, public listing, admin, map, filter and draw-area tests still pass.

---

## Section 6 — Manual verification (browser)

Manual checks were done in the built-in browser against http://localhost:8000. They are separate from the automated tests above.

| Check | Width | Result |
|---|---|---|
| Create form renders the picker (map, search, Use my location, fields, status badge) | Desktop 1440 / 1024 | OK |
| Choosing "Model Town" re-centres the map, sets no pin, fields stay empty | Desktop | OK |
| Click places one pin; fields fill (e.g. 31.4720469, 74.3230677); status becomes "Exact location set"; Clear pin appears | Desktop | OK |
| Dragging the pin updates the fields; clicking elsewhere moves the same pin (1 marker) | Desktop | OK |
| Typing 31.4805123 / 74.3239456 moves the pin; Dubai coordinates give the "outside Pakistan" message; one empty field gives the "enter both" message | Desktop | OK |
| Clear pin empties the fields, removes the marker and resets the badge | Desktop | OK |
| Place search "Liberty Market Lahore" returns English, de-duplicated places; choosing one moves the map without placing a pin | Desktop | OK (live Nominatim call, made server-side) |
| Save as draft with a pin, reopen the edit page: pin and fields restored | Desktop | OK (MP-000017) |
| Edit: retype coordinates → save → restored; Clear → save → NULL in the database (checked with tinker) | Desktop | OK |
| Server error display: latitude without longitude → "Enter both latitude and longitude, or clear the pin." under the field | Desktop | OK |
| Owner preview of the listing page shows the "Exact location" badge, the pin and the advertiser note | Desktop | OK |
| Phone layout: no horizontal scroll (scrollWidth 375), map 273×288 px, tap places the pin | 375×812 | OK |
| Tablet layout: map 627×384 px, no overflow | 768×1024 | OK |
| Public `/map` still shows 12 area pins, Draw Area and "Search as I move the map"; no console errors | Desktop | OK |
| **Admin listing page** | — | **Not checked manually.** The demo admin's password no longer matches the seeded value (someone changed it), so I did not sign in. This is covered by the automated test "shows reviewers the pin on the admin listing page". |
| Real touch device / on-screen keyboard | — | **Not tested.** Browser emulation sends mouse events. The fields sit below the map, so the keyboard does not cover the map while typing. |

---

## Section 7 — Remaining development backlog

### Module review

Effort is in developer-days (d), roughly.

**A. Public website**
- **Done:** home, Buy/Rent/Plots/Commercial pages, search, listing pages (gallery, facts, price history, loan calculator, inquiry, location status), SEO meta, OG, JSON-LD, sitemap, robots.
- **Incomplete:**
  - Nearby Places (shown in the Listing Dashboard reference)
  - legal pages are template text
  - mockup crops are used as photos
  - canonical and OG URLs follow `APP_URL` (localhost)
- **Risks:** legal exposure without reviewed terms and privacy pages.
- **Priority / effort:** Nearby Places P2, 2–3 d · legal review P1 (needs a lawyer) · photography P1 (content work)

**B. Buyer portal**
- **Done:** register/login, saved properties, saved searches, in-app alerts, requirements with consent, inquiry history, notification preferences.
- **Incomplete:** email verification, phone/OTP verification, email alerts (blocked by mail).
- **Risks:** fake accounts.
- **Dependencies:** mail provider (or a local mail catcher).
- **Priority / effort:** email verification P1, 1 d · phone OTP P2, 2 d plus an SMS provider

**C. Dealer portal**
- **Done:** registration, listings with photos and the new location picker, leads, performance reports, profile.
- **Incomplete:**
  - verification document upload
  - listing renewal
  - photo reorder/cover choice (the cover is always the first photo)
  - image optimisation
  - featured listing options for dealers (admin-only toggle today)
  - subscriptions (placeholder page)
- **Bug:** the performance report tells dealers to "renew to stay visible", but **no renew action exists**. Published listings past `expires_at` are hidden, their status stays Published, and `submit()` accepts only Draft, Rejected or Expired, so the dealer cannot resubmit them.
- **Priority / effort:** renewal P1, 1–2 d · documents P1, 2–3 d · image processing P1, 1–2 d · photo ordering P2, 1 d

**D. Admin panel**
- **Done:** users, dealer verification, listing approval and moderation, duplicates, reports queue, leads and reassignment, conversations inbox, analytics (internal database figures), audit log, locations with coordinates, projects, read-only integration status, and now a location review map.
- **Incomplete:** subscriptions/payments (placeholder), lead-distribution rules and follow-up automation, bulk moderation, admin notifications for new pending listings.
- **Priority / effort:** admin notifications P1, 1 d (with notifications) · bulk actions P2, 1 d · billing P3 (needs a business decision)

**E. Search and maps**
- **Done:** filters, facets, sort, saved searches, price and area pins, viewport and draw search, **exact dealer pins**, place search in the picker.
- **Incomplete:**
  - days-on-market and price-reduced filters (the data exists)
  - public location autocomplete beyond the area list
  - 4 areas still have no centre (DHA Rahbar, EME Society, Etihad Town, Punjab Coop Housing Society)
- **Risks:** the public OSM tile and Nominatim services are for light use only.
- **Priority / effort:** filters P2, 1 d · area centres P1, 0.1 d (needs the values from you) · commercial tiles/geocoder P1 before launch (configuration)

**F. AI property finder**
- **Done:** requirement parsing (English, Roman Urdu, number words), budget, location and size matching, top 3 matches with reasons, consented lead creation, human handover (WhatsApp mode).
- **Incomplete:**
  - conversation history on the website chat (it doesn't remember earlier messages)
  - the LLM path has never run against a live API
- **Dependencies:** `OPENAI_API_KEY` (optional).
- **Priority / effort:** conversation memory P2, 2 d · live LLM test P2, 0.5 d plus a key

**G. WhatsApp**
- **Done:** webhook verification, HMAC signature check, idempotent inbound storage, bot reply with matches (only when sending is enabled), human handover, admin inbox.
- **Incomplete:**
  - **opt-in and opt-out (STOP) handling**
  - approved message templates
  - dealer and lead notifications over WhatsApp
  - retry and error handling for failed sends
- **Dependencies:** Meta Business verification, phone number, access token.
- **Priority / effort:** P2, 3–5 d once credentials exist

**H. Email and notifications**
- **Done:** password reset flow (written to the log); saved-search email alert command, scheduled daily at 08:00, sending only with a real mailer and opt-in.
- **Incomplete:**
  - mail provider
  - registration verification
  - dealer new-lead and new-inquiry notifications
  - admin notifications
  - **queued** notifications (nothing implements `ShouldQueue`; no worker)
  - delivery error handling
- **Dependencies:** a provider for production; local work can use the `log` driver or a mail catcher.
- **Priority / effort:** **P0 for the local MVP, 3–4 d**

**I. Security and performance**
- **Done:**
  - role middleware
  - policies
  - validation (now including coordinates)
  - rate limits (login, inquiries, registrations, AI, webhook, location search)
  - upload type, size and dimension checks
  - indexes on search columns
  - hashed IPs
  - immutable audit log
  - `preventLazyLoading`
- **Incomplete:**
  - uploads are not re-encoded or resized (EXIF/GPS metadata is kept)
  - `APP_DEBUG=true`
  - no config/route/view caching
  - no dependency audit (`composer audit`, `npm audit`)
  - session cookie flags for production (`SESSION_SECURE_COOKIE`)
- **Priority / effort:** image processing P1, 1–2 d · production settings P1 at deployment, 0.5 d · dependency audit P1, 0.5 d

### Prioritised backlog
- **P0 — critical for the local MVP**
  1. Notifications foundation: queued mail notifications for dealer new inquiries/leads, admin new pending listings and buyer saved-search alerts; a queue worker; checked with the `log` mailer locally.
  2. Email verification for buyers and owners. Depends on 1.
  3. Listing expiry and renewal: mark expired listings, a dealer "Renew" action, expiry reminders. This fixes the "renew" bug above.
- **P1 — important for the local MVP**
  1. Dealer verification document upload (private disk, admin-only viewing).
  2. Image processing: resize, WebP, thumbnails, strip EXIF.
  3. Centre coordinates for the 4 remaining areas (values from you).
  4. Legal page review and real photography (content).
  5. Dependency audit plus a production configuration checklist.
- **P2 — advanced marketplace features**
  1. Nearby Places from OSM Overpass, shown only for listings with exact pins.
  2. Days-on-market and price-reduced filters.
  3. WhatsApp go-live: templates, opt-in/opt-out, notifications.
  4. Photo ordering and cover choice; bulk moderation.
  5. AI chat memory; a live LLM test.
- **P3 — later enhancements**
  1. Subscriptions and payments (JazzCash/Easypaisa/card), paid featured listings.
  2. Dealer reviews.
  3. Urdu UI and further cities.
  4. Analytics with consent.
  5. A native app or public API.

---

## Section 8 — Known issues

1. **Listing renewal gap (bug).** Dealers are told to renew expiring listings, but no renew action exists, and expired published listings cannot be resubmitted. Fixing this is backlog P0-3.
2. **Bounding box, not a border.** The Pakistan box also contains slivers of Iran, Afghanistan and India (Kabul, for example, passes). The 6 km area-distance warning and admin review are the current safeguards. A polygon check could be added later.
3. **Keyboard users cannot drag the pin.** The labelled latitude/longitude fields are the accessible alternative, and the map's `aria-label` says so.
4. **Typing an out-of-country pair leaves the pin at its previous spot** while the fields show the new values. A message explains this and the server rejects the save, but the pin and fields briefly disagree.
5. **Brand colours: resolved, 30 Oct 2026.** The brief listed purple `#4B1592`, gold `#D6A13D` and dark purple `#21133A`. The project owner decided to **keep the current palette**, which follows the Brand Style Guide reference image: brand-600 `#4B2E83`, brand-500 `#6A3FB0`, gold `#D4AF37`, ink `#2E2E2E`. No change needed.
6. **Demo admin password changed.** The `admin@meezanproperty.test` account no longer uses the seeded password, so I didn't check the admin pages manually this phase.
7. **QA draft listing MP-000017** remains in the local database (Section 3).
8. **External dependencies, not configured and not live:**
   - mail (`MAIL_MAILER=log`)
   - WhatsApp Cloud API (`WHATSAPP_*`)
   - LLM (`MEEZAN_AI_DRIVER`, `OPENAI_API_KEY`)
   - SMS and payments (not implemented)
   - The public OpenStreetMap tile and Nominatim services allow light use only; configure a commercial or self-hosted provider before launch (`MEEZAN_MAP_TILE_URL`; the geocoder endpoint is in `app/Services/Geocoder.php`).
9. **Production settings to change at deployment:** `APP_DEBUG=true` and `APP_URL=http://localhost:8000` (so canonical URLs point to localhost).
10. **No version control**, as you instructed for this phase.

---

## Section 9 — Next recommended phase

**Phase 2: Notifications & listing lifecycle**, covering backlog P0-1 to P0-3:

- queued email notifications (dealer new inquiry/lead, admin pending listing, buyer alerts) with a queue worker
- email verification
- listing expiry and renewal

**Why this phase next:**
- Every marketplace loop currently ends in silence. Dealers, admins and buyers only learn about new activity by logging in, and response time is the main thing the new performance reports measure.
- It fixes a real bug: dealers are told to renew listings, but can't.
- It can be fully built and tested locally with the `log` mailer (or a local mail catcher) before any provider credentials exist. Going live later is just configuration, which fits the local-first plan.
- It unblocks email verification and gives WhatsApp notifications a channel to plug into later.
