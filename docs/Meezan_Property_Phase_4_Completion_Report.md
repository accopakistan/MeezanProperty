# Meezan Property — Phase 4 Completion Report

**Phase:** Discovery & engagement: in-app notification centre, listed-within and price-reduced filters, Nearby Places
**Date:** 30 Oct 2026 · **Environment:** local only (XAMPP, http://localhost:8000)
**Not done, as instructed:**
- no git, GitHub or deployment
- no destructive database commands
- `public/Refrence/` untouched
- palette unchanged
- no new packages

---

## 1. Executive summary

**In-app notification centre (all roles)**
- **Where it appears:**
  - every service notification from Phases 2–3 now also creates an entry in a **Notifications** page (`/notifications`)
  - a **bell with an unread count** shows in the admin top bar and the dealer/buyer mobile bar
  - a "Notifications" item with the count is in every sidebar
- **Notifications covered:**
  - new inquiry
  - new lead
  - listing submitted, approved, rejected or unpublished
  - expiring or expired listing
  - contact form message (admins)
  - verification documents uploaded (admins)
- **Using it:**
  - Opening an entry marks it read and follows its link.
  - **Only links on this site are followed**; a stored external link sends the user to the list instead.
  - "Mark all as read" and an All/Unread filter are available.
- **Preferences:**
  - The in-app copy follows the existing `notify_in_app` preference (on by default).
  - **It is independent of email**, so a dealer who switches emails off still sees new inquiries. Suspended users get nothing.
- **Clean-up:** a daily job (03:00) deletes read entries older than 90 days and any entry older than 180 days.
- **Admin bell change:** it previously counted pending listings and dealers; it now opens the notification centre. Pending counts still show in the Properties and Dealers menu items and on the dashboard.

**New search filters**
- **Listed within:** last 24 hours, 3, 7, 14 or 30 days (`?listed=`).
- **Price reduced:** the asking price was lowered **to today's price** within the last 90 days (`?reduced=1`). A cut that was later reversed, or one older than 90 days, doesn't count.
- **Where they work:**
  - filter chips with removal links
  - included in saved searches and alerts
  - the sidebar filter on the search and map pages
- **On cards:**
  - **"Price reduced"** badge with "↓ from 6 Cr", only from recorded asking-price changes
  - otherwise a **"New"** badge for listings published in the last 7 days, the same rule as the "New" map pins
- **Listing page:** a "Days on Meezan Property" fact.

**Nearby Places (from the Listing Dashboard reference)**
- **What it shows:** schools and colleges, hospitals and clinics, mosques, shopping and parks within **1.5 km** of a listing's **exact pin**. Up to 3 per category, each with its straight-line distance.
- **Source:** OpenStreetMap via the **Overpass API**, called **server-side**.
  - cached for 30 days per location
  - a failure is cached for 10 minutes so an outage isn't retried on every page view
- **Loading:**
  - the page loads it **after** the listing, only when the section scrolls near view, so a slow service never delays the listing
  - only named places are shown and nothing is invented
  - shown only for listings with an exact pin, and only to visitors who can see the listing
- **Settings:** `MEEZAN_NEARBY_ENABLED`, and `MEEZAN_OVERPASS_URL` to point at a self-hosted or commercial Overpass server.

---

## 2. Files changed

### Created
| File | Purpose |
|---|---|
| `database/migrations/2026_10_30_000016_create_notifications_table.php` | Laravel's standard `notifications` table, plus a (notifiable, read_at) index |
| `app/Http/Controllers/NotificationController.php` | Notification list, open (mark read and follow internal link), mark all read |
| `resources/views/notifications/index.blade.php` | Notification centre page |
| `app/Services/NearbyPlaces.php` | Overpass query, categorise, distances, caching |
| `tests/Feature/NotificationCentreTest.php` | 7 tests |
| `tests/Feature/DiscoveryTest.php` | 9 tests (filters, badges, days listed, saved searches, Nearby Places) |
| `docs/Meezan_Property_Phase_4_Completion_Report.md` | This report |

### Modified
| File | Change |
|---|---|
| `app/Notifications/QueuedMailNotification.php` | Adds the `database` channel when the notification has an in-app entry and the user is active with in-app notifications on; `inApp()` and `toArray()` hooks. |
| `app/Notifications/NewInquiryReceived.php`, `LeadAssigned.php`, `ListingSubmittedForReview.php`, `ListingReviewed.php`, `ListingExpiring.php`, `ContactMessageReceived.php`, `DealerDocumentsSubmitted.php` | `inApp()` entries: title, short body, link, icon. No buyer phone or email stored. |
| `routes/web.php` | Routes `notifications.index`, `notifications.read-all`, `notifications.open` (UUID only), `properties.nearby` (throttled 30 a minute). |
| `routes/console.php` | Daily `prune-notifications` at 03:00. |
| `resources/views/components/layouts/dashboard.blade.php` | Bell with unread count (admin header, dealer/buyer mobile bar); "Notifications" nav item for all roles. |
| `app/Models/Property.php` | `NEW_DAYS`, `REDUCED_DAYS`; `latestPriceChange()`; `scopeRecentlyReduced()`; `recentReduction()` (only from the eager-loaded relation); `isNewListing()`; `daysListed()`; `forCards` eager-loads the latest price change. |
| `app/Support/MapMarkers.php` | Uses `Property::NEW_DAYS`. |
| `app/Services/PropertySearch.php` | `LISTED` options; `listed` and `reduced` validation, query and chips. |
| `app/Http/Controllers/Account/SavedSearchController.php` | Saved searches keep `listed` and `reduced`. |
| `resources/views/properties/partials/filters.blade.php` | "Price reduced" checkbox and "Listed within" select. |
| `resources/views/components/property-card.blade.php` | "Price reduced" / "New" badges; "↓ from …" next to the price. |
| `app/Http/Controllers/PropertyController.php` | `nearby()` JSON endpoint, with the same visibility rule as the listing page. |
| `resources/views/properties/show.blade.php` | "Days on Meezan Property"; Nearby Places section and tab (exact pin only). |
| `resources/js/app.js` | Nearby Places loader (fetched when near view, escaped output, failure and empty messages). |
| `config/meezan.php`, `.env.example` | `nearby` settings. |
| `tests/Feature/NotificationsTest.php` | Two assertions updated for the intended change, explained in Section 5. |
| `docs/zillow-gap-report.md` | Row 22 added. |
| `public/build/*` | Rebuilt. |

### Deleted
None.

---

## 3. Database and data

- **Migration** `2026_10_30_000016_create_notifications_table`, run in batch 9. New table only.
- **Data preserved:** 4 users, 17 listings; 0 notifications (none generated locally yet); 0 queued jobs.
- **Cache:** one failed Nearby Places look-up for the QA draft's location is cached for 10 minutes, so it won't be retried before then.

---

## 4. Tests

**Commands**
```
php artisan test
php artisan test --compact tests/Feature/NotificationCentreTest.php tests/Feature/DiscoveryTest.php tests/Feature/NotificationsTest.php
npm run build
php artisan route:list --except-vendor
php artisan migrate:status
php artisan schedule:list
```

| Result | Value |
|---|---|
| Total tests | **231** (215 before + 16 new) |
| Assertions | **917** |
| Passed / Failed / Skipped | 231 / 0 / 0 |
| Build | Succeeded |
| Routes | **133** (129 + 4) |
| Migrations | 19 run, 0 pending |
| Schedule | `prune-notifications` 03:00, `meezan:listing-lifecycle` 07:00, `meezan:send-search-alerts` 08:00 |
| Laravel log | 0 errors. One expected warning: the live Overpass request was refused (Section 5). |

**Two existing tests changed, intentionally:**
- **Inquiry email test:** now expects the channels `['database', 'mail']` instead of `['mail']`. It still checks there are no buyer contact details, the subject and the link.
- **Email opt-out test:** before, a dealer with emails off received nothing; now they get exactly `['database']`. The test still checks that a suspended dealer gets nothing.

**Failures during development, all in the test code:**
- the saved-search endpoint expects its filters as a JSON string
- stacked `Http::fake()` calls let the first fake win (replaced with a response sequence)
- one PHP syntax error in new code (an unparenthesised ternary), caught by the suite and fixed

**What the new tests cover**
- **Notification centre:**
  - entries are stored without buyer phone numbers
  - the unread count shows in the dealer, admin and buyer portals
  - opening an entry marks it read; external links are refused
  - entries are private to each user; guests are sent to sign in
  - mark all read and the unread filter work
  - the in-app preference is respected
  - the clean-up job keeps exactly the right entries
- **Filters:**
  - "listed within" works, and invalid values are ignored
  - "price reduced" excludes cuts that were later reversed or are older than 90 days
  - card badges appear only when earned
  - days listed shows on the listing page
  - the new filters are kept in saved searches
- **Nearby Places:**
  - categorising: a church isn't counted as a mosque, an unnamed park is skipped, English names are preferred
  - distances, and the 30-day cache
  - a failure returns 503, is cached for only 10 minutes, then recovers
  - nothing for listings without a pin, or drafts the visitor can't see; available to the owner in preview
  - no requests at all when switched off

---

## 5. Manual verification (browser)

| Check | Result |
|---|---|
| `/properties?listed=30&reduced=1`: chips "Listed: last 30 days" and "Price reduced", controls pre-filled, 0 results (no demo listing has a recorded price cut) | OK |
| `/properties`: 12 cards; 4 "New" badges matching the published dates; no "Price reduced" badges (correct, no cuts recorded); no horizontal overflow | OK |
| `/notifications` as the demo dealer: empty state; bell in the dealer top bar; nav item | OK (no entries exist locally yet) |
| QA draft MP-000017 (has a pin), owner preview: Nearby Places section with loading placeholders, then the **"not available right now"** fallback | **Fallback OK. The live data could not be checked.** The public `overpass-api.de` server returns **HTTP 406 to every request from this machine**, including its status page and a trivial query with any User-Agent. Three other public mirrors didn't respond. This is a network or server block, not the application. Categorising and display are covered by the automated tests. |
| Console | Only the expected 503 from the Nearby fallback, and the earlier unrelated 403 from my scripted sign-in |
| Admin bell and notification list | **Not checked in the browser** (the demo admin's password has been changed). Covered by automated tests. |

---

## 6. Remaining backlog (updated)

- **Before deployment (P0):**
  - mail provider and queue worker
  - `MEEZAN_REQUIRE_EMAIL_VERIFICATION=true`
  - production settings (`APP_DEBUG=false`, `APP_URL`)
  - commercial or self-hosted map tiles, geocoder and **Overpass** (`MEEZAN_OVERPASS_URL`)
- **P1**
  1. Coordinates for the 4 areas without a centre (values from you).
  2. Legal review of the terms and privacy pages; real photography; `meezan:optimize-images`.
  3. Dependency audit and a production settings checklist.
  4. Document retention policy (decision needed).
  5. **Get a working Overpass endpoint and check Nearby Places with real data.**
- **P2**
  1. WhatsApp templates, opt-in/opt-out and notifications (they would plug into the same notification classes).
  2. Bulk moderation; AI chat memory; live LLM test.
  3. Drag-and-drop photo ordering.
  4. Show Nearby Places on the listing map, and let buyers filter by distance to a school or mosque.
- **P3:** subscriptions and payments, dealer reviews, Urdu UI and more cities, analytics with consent, a native app or public API.

---

## 7. Known issues

1. **Nearby Places can't reach its data source from this machine** (Overpass returns 406 here). The page shows a polite fallback. Configure `MEEZAN_OVERPASS_URL` for a reachable instance, or test from another network.
2. **No demo listing has a recorded price cut or an exact pin,** so "Price reduced" and Nearby Places can't be shown on the demo data. Both are covered by automated tests.
3. **The admin bell now opens the notification centre** rather than counting pending items. The pending counts moved to the menu badges and the dashboard.
4. **Not checked in the browser:** admin pages (the admin password has been changed).
5. **Still from earlier phases:**
   - mail is `log` only and needs a queue worker
   - QA draft MP-000017 remains
   - WhatsApp, LLM, SMS and payments are not configured
   - `APP_DEBUG=true`
   - no version control (deferred by you)

---

## 8. Next recommended phase

**Phase 5: Production readiness (still local).** This covers:
- a production `.env` checklist, with config, route and view caching tested locally
- dependency audits (`composer audit`, `npm audit`)
- security headers (CSP, HSTS-ready, frame options)
- error pages
- `APP_DEBUG=false` checks
- a separate log channel for tests
- performance passes: N+1 checks with lazy-loading guards, query counts on the main pages, image lazy-loading
- a deployment runbook for Hostinger: queue worker, scheduler cron, storage link, provider settings

**Why:** the feature set for a local MVP is now broad. The highest risk before GitHub and Hostinger is operational, not missing features. Doing this locally first keeps the eventual deployment predictable.
