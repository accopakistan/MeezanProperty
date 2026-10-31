# Meezan Property — Phase 2 Completion Report

**Phase:** Notifications & listing lifecycle
**Date:** 30 Oct 2026 · **Environment:** local only (XAMPP, http://localhost:8000)
**Not done, as instructed:** no git, no GitHub, no deployment, no destructive database commands, `public/Refrence/` untouched, palette unchanged (Brand Style Guide colours, as you decided).

---

## 1. Executive summary

**Implemented**
- **Queued service emails** for every marketplace event that previously went unnoticed:

| Email | Recipient | Trigger |
|---|---|---|
| New inquiry | Listing dealer | Buyer submits an inquiry |
| New buyer lead | Dealer | Requirement lead routed to them, or admin reassigns a lead |
| Listing to review | Active admins | Dealer submits a listing (possible duplicates included) |
| Listing approved / changes needed / unpublished | Dealer | Admin moderation decision (archiving sends nothing) |
| Listing expires soon / listing expired | Dealer | Daily lifecycle job |
| Contact form message | `MEEZAN_CONTACT_EMAIL`, or admins if it is not set | Contact form submission (reply-to is the sender) |
| Confirm your email address | New buyer, owner or dealer | Registration, or the "send link" button |
| Saved-search matches (existing) | Opted-in buyers | Daily alert job; now queued, and sent only to **confirmed** addresses |

- **Listing expiry and renewal.** This fixes the Phase 1 bug where dealers were told to "renew" but had no way to do it.
  - A daily job, `meezan:listing-lifecycle` (07:00), does two things:
    - marks lapsed listings **Expired**
    - sends **one** reminder 7 days before expiry
  - **Renew** button on My Listings and in the performance report:
    - available in the last 14 days before expiry, or up to 30 days after it
    - gives another 90 days with no new review
    - the dealer must confirm the property is still available
  - Listings that expired more than 30 days ago must be **submitted for review** again.
- **Email verification**
  - **The flow:**
    - signed confirmation links
    - a resend button, throttled
    - a confirmation page
    - a reminder banner on buyer and dealer dashboards
  - **Enforcement:**
    - optional, via `MEEZAN_REQUIRE_EMAIL_VERIFICATION`, which is **off by default** so nobody is locked out while mail isn't configured
    - when on, an unconfirmed user can't create or submit listings, renew them, or share requirements with dealers
    - admins are never blocked
- **Email preferences:** dealers can switch account emails off in their profile. A new `notify_account_email` setting, on by default, controls this. Suspended accounts never receive email.
- **Admin visibility:** Admin → Settings now shows queued emails waiting, failed emails, and whether email confirmation is required.
- **Branded emails:** Laravel's mail theme recoloured to the brand palette (purple `#4B2E83`, page `#F9F7FB`, ink `#2E2E2E`).

**Design choices**
- **No buyer contact details in emails.** Inquiry and lead emails summarise the request and link to the dashboard; the buyer's phone and email stay in the dashboard.
- **Emails only after commit.** Every email is queued and sent only after its database transaction commits. It is retried 3 times (after 1 and 5 minutes); a final failure is logged and lands in `failed_jobs`.
- **Renewing keeps the original `published_at`,** so "New" badges and days-on-market stay honest.
- **No confirmation emails to guest inquirers,** so the public form can't be used to send mail to arbitrary addresses.

**Not done in this phase:**
- a real mail provider
- WhatsApp or SMS notifications
- in-app notification centre for dealers
- dealer verification documents
- image processing

---

## 2. Files changed

### Created
| File | Purpose |
|---|---|
| `database/migrations/2026_10_30_000014_add_notification_and_expiry_fields.php` | Adds `users.notify_account_email` (default true) and `properties.expiry_reminder_sent_at` (nullable). Additive only. |
| `app/Notifications/QueuedMailNotification.php` | Shared base for service emails: queued, sent after commit, 3 tries with back-off, failures logged, respects account-email preference and suspension. |
| `app/Notifications/NewInquiryReceived.php` | Dealer: new inquiry (no buyer phone or email in the email). |
| `app/Notifications/LeadAssigned.php` | Dealer: requirement lead or reassigned lead, with a summary of type, purpose, area, bedrooms and budget. |
| `app/Notifications/ListingSubmittedForReview.php` | Admins: listing to review, pin status, possible duplicates. |
| `app/Notifications/ListingReviewed.php` | Dealer: approved, rejected (with the reviewer note) or unpublished. |
| `app/Notifications/ListingExpiring.php` | Dealer: expires soon, or has expired. |
| `app/Notifications/ContactMessageReceived.php` | Contact form message to the company address or admins, with reply-to set to the sender. |
| `app/Notifications/VerifyEmailAddress.php` | Queued, branded version of Laravel's verification email. |
| `app/Console/Commands/ListingLifecycle.php` | `meezan:listing-lifecycle [--dry-run]`: expire lapsed listings and send reminders. |
| `app/Http/Controllers/Auth/EmailVerificationController.php` | Notice page, signed verify link, resend. |
| `app/Http/Middleware/EnsureEmailIsVerifiedWhenRequired.php` | `verified.required`: enforces confirmation only when `MEEZAN_REQUIRE_EMAIL_VERIFICATION=true`; never blocks admins. |
| `resources/views/auth/verify-email.blade.php` | "Confirm your email address" page. |
| `resources/views/vendor/mail/html/themes/default.css` | Brand-coloured copy of Laravel's mail theme. This is the standard override location, so no config change was needed. |
| `tests/Feature/NotificationsTest.php` | 9 tests. |
| `tests/Feature/ListingLifecycleTest.php` | 9 tests. |
| `tests/Feature/EmailVerificationTest.php` | 7 tests. |
| `docs/Meezan_Property_Phase_2_Completion_Report.md` | This report. |

### Modified
| File | Change |
|---|---|
| `app/Models/User.php` | Implements `MustVerifyEmail`, with a queued verification email. Adds `notify_account_email` (fillable and cast), `wantsAccountEmail()` and `activeAdmins()`. |
| `app/Models/Property.php` | `expiry_reminder_sent_at` cast; `hasLapsed()`, `canBeRenewed()`, `needsReviewToRelist()`. |
| `app/Http/Controllers/InquiryController.php` | Notifies the listing dealer after the inquiry is routed. |
| `app/Services/Leads/LeadRouter.php` | Notifies each dealer assigned a requirement lead, and the new dealer on reassignment. |
| `app/Http/Controllers/Dealer/PropertyController.php` | Notifies admins on submit. New `renew()` action. `submit()` and submit-on-save also accept lapsed listings. |
| `app/Http/Controllers/Admin/PropertyController.php` | Notifies the dealer on approve, reject and unpublish; approval resets the reminder flag. |
| `app/Http/Controllers/PageController.php` | Contact form emails the company address or admins. |
| `app/Http/Controllers/Dealer/ProfileController.php` | Saves the account-email preference. A form without the field keeps the current value. |
| `app/Http/Controllers/Admin/SettingsController.php` + `resources/views/admin/settings.blade.php` | Queue and verification status rows; optional custom status label. |
| `app/Notifications/SavedSearchMatches.php` | Now queued (3 tries; skipped if the search was deleted). |
| `app/Console/Commands/SendSearchAlerts.php` | Only emails buyers with a confirmed address. |
| `app/Services/DealerPerformance.php` + `resources/views/dealer/performance.blade.php` | The expiry suggestion uses the renewal rules; expired listings say how to relist; Renew button added. |
| `resources/views/dealer/properties/index.blade.php` | "Live until …" / "Expired … · hidden from buyers" under the status; Renew or Submit as appropriate. |
| `resources/views/dealer/properties/form.blade.php` | Expired listings can be submitted for review; the status panel explains expiry and renewal. |
| `resources/views/dealer/profile.blade.php` | "Email notifications" switch. |
| `resources/views/components/layouts/dashboard.blade.php` | Confirm-your-email banner with a resend button (not shown to admins). |
| `routes/web.php` | Routes `verification.notice`, `verification.verify` (signed, throttled), `verification.send` (throttled), `dealer.properties.renew`. `verified.required` applied to listing store, update, submit and renew, and to requirement store and update. |
| `routes/console.php` | Schedules `meezan:listing-lifecycle` daily at 07:00 (without overlapping). |
| `bootstrap/app.php` | Middleware alias `verified.required`. |
| `config/meezan.php` | `listing.reminder_days` (7), `listing.renew_window_days` (14), `listing.renew_grace_days` (30), `require_verified_email` (env, default false). |
| `.env.example` | Documents `MEEZAN_REQUIRE_EMAIL_VERIFICATION` and the need for a queue worker. |
| `docs/zillow-gap-report.md` | Row 20 added. |
| `public/build/*` | Rebuilt. |

**Housekeeping:** running Laravel Pint over the whole `app` folder also reformatted the generated files `bootstrap/cache/packages.php` and `services.php`. I regenerated both straight away with `php artisan clear-compiled` and `package:discover`. No source file was affected.

### Deleted
None.

---

## 3. Database changes

- **New migration:** `2026_10_30_000014_add_notification_and_expiry_fields`, run in batch 7.
  - `users.notify_account_email`: boolean, default `true`. The 4 existing users get `true`, so they keep receiving account emails.
  - `properties.expiry_reminder_sent_at`: timestamp, nullable.
- **Reused:** `users.email_verified_at`, `properties.expires_at`, `properties.status` (the existing `expired` value), the `jobs` and `failed_jobs` tables.
- **Data preserved:**
  - no existing record was changed or deleted
  - counts after the phase: 4 users, 17 listings (16 demo plus the Phase 1 QA draft)
  - `meezan:listing-lifecycle --dry-run` on the local database reports 0 listings to expire and 0 to remind; every demo listing runs until 27 Jan 2027
- **Side effect of the manual check:** one verification email was queued to the demo buyer's `.test` address and processed by the worker into `storage/logs/laravel.log`. The `jobs` and `failed_jobs` tables are empty again.

---

## 4. How it works

**Sending flow**

action → notification queued (database queue, after commit) → `php artisan queue:work` sends it → mailer

- **Mailer:** currently `log`, so emails are written to `storage/logs/laravel.log`.
- **Without a running worker:** emails wait in the `jobs` table, and Admin → Settings shows how many.
- **Local commands:** `composer run dev` already starts a queue listener; `php artisan queue:work` starts one on its own.

**Who gets what**
- **Admins:** active admins only.
- **Dealers:** service emails unless they switched them off; suspended users get nothing.
- **Buyers:** saved-search emails only with the existing opt-in (`notify_email`) **and** a confirmed address.

**Expiry and renewal timeline (defaults)**

| When | What happens |
|---|---|
| Approved | Live for 90 days; reminder flag reset |
| 7 days before expiry | One reminder email |
| Last 14 days before expiry | Renew available |
| Expiry date | Hidden from buyers immediately (existing behaviour). The daily job marks it Expired and emails the dealer. |
| Up to 30 days after expiry | Renew puts it straight back online for 90 days |
| More than 30 days after expiry | Must be submitted for review |

**Renew rules**
- Requires a POST with `still_available=1`; the button asks the dealer to confirm in a dialog.
- Only the listing's own dealer can renew (`PropertyPolicy::update`).
- When `MEEZAN_REQUIRE_EMAIL_VERIFICATION` is on, the dealer's email must be confirmed first.

**Email verification**
- Standard Laravel signed links, which expire after 60 minutes. Tampered or foreign links return 403 (tested).
- The banner and the resend button are rate-limited to 6 a minute.
- **Before turning on `MEEZAN_REQUIRE_EMAIL_VERIFICATION=true`:**
  - make sure outgoing mail works
  - note that the one dealer who registered through the live form has **not** confirmed their email yet; they would be unable to add listings until they do

---

## 5. Tests

**Commands**
```
php artisan test
php artisan test --compact tests/Feature/NotificationsTest.php tests/Feature/ListingLifecycleTest.php tests/Feature/EmailVerificationTest.php
npm run build
php artisan route:list --except-vendor
php artisan migrate:status
php artisan schedule:list
php artisan meezan:listing-lifecycle --dry-run
```

| Result | Value |
|---|---|
| Total tests | **199** (174 before + 25 new) |
| Assertions | **738** |
| Passed / Failed / Skipped | 199 / 0 / 0 |
| New tests | Notifications 9, Listing lifecycle 9, Email verification 7 |
| Build | Succeeded, no warnings |
| Routes | **124** (120 + 4) |
| Migrations | 17 run, 0 pending |
| Schedule | `meezan:listing-lifecycle` daily 07:00; `meezan:send-search-alerts` daily 08:00 |
| Laravel log | 0 errors today. The warnings present ("Geocoder returned an error 503", "LLM requirement parsing failed") come from tests that simulate those failures. The test suite writes to the same log file. |

**What the tests cover**
- **Inquiry emails:** sent to the right dealer, with no buyer phone or email in the body.
- **Preferences:** the opt-out and suspension are respected.
- **Lead emails:** requirement-lead and reassignment emails go to the right dealer, with the right summary.
- **Moderation emails:**
  - admins are notified; suspended admins are not
  - approve and reject emails go to the dealer, including the reviewer note
  - archiving sends nothing
- **Contact form:** goes to the company address, or to admins when none is set, with reply-to set to the sender.
- **Queueing:** every service email is queued, sent after commit, with 3 tries.
- **Profile switch:** can be turned off and on; an older form without the field keeps the current choice.
- **Alert emails:** go only to confirmed addresses.
- **Lifecycle job:** expires and reminds once each, with no repeat on a second run; a dry run changes nothing.
- **Renewing:**
  - near expiry, recently expired, and lapsed before the job ran: all accepted
  - too early, too late, unconfirmed, or another dealer's listing: all refused
  - long-expired listings go back through review
  - My Listings shows dates and the right button
  - approval resets the reminder
- **Email verification:**
  - registration sends the queued verification email
  - a signed link confirms the address once; tampered or foreign links get 403
  - the banner and resend button appear only for unconfirmed users
  - with the switch off, unconfirmed users are not blocked; with it on, listings and requirements are blocked
  - admins are never blocked

---

## 6. Manual verification

The automated tests are listed in Section 5; these are the manual checks.

| Check | Result |
|---|---|
| Real queue run: one verification email queued to the demo buyer → `php artisan queue:work --stop-when-empty` → job DONE in 156 ms → email written to `laravel.log` with subject "Confirm your email address" and the brand-coloured button CSS; 0 jobs left, 0 failed | OK |
| Email HTML opened from the log in the preview pane | Opened, but the pane can't screenshot local files, so the **rendered email was not visually checked**. Only the HTML content was checked. |
| `meezan:listing-lifecycle --dry-run` on the real local database | OK: 0 to expire, 0 to remind |
| Dealer My Listings: published demo listings show "Live until 27 Jan 2027" with no Renew (outside the 14-day window); the QA draft shows Submit | OK |
| Dealer profile: "Email notifications" switch shown and on by default; no confirm-email banner (the demo dealer is confirmed) | OK |
| Confirm-email banner, the notice page, and the Renew button on an expiring listing | **Not checked in the browser.** No local account or listing is in that state, and I didn't change records to create one. Covered by automated tests. |
| Admin settings queue rows | **Not checked in the browser.** The demo admin's password no longer matches the seed. Renders in the automated admin page test. |

---

## 7. Remaining backlog (updated)

- **P0 — critical for the local MVP**
  - None left from the Phase 1 list.
  - Before deployment: choose a mail provider and set `MAIL_*`, run a queue worker under a process manager, then turn on `MEEZAN_REQUIRE_EMAIL_VERIFICATION`.
- **P1 — important for the local MVP**
  1. Dealer verification documents (CNIC or agency registration) on a private disk, viewable by admins only.
  2. Image processing: resize, WebP, thumbnails, strip EXIF/GPS. Also photo ordering and cover choice.
  3. Centre coordinates for DHA Rahbar, EME Society, Etihad Town and Punjab Coop Housing Society (values from you).
  4. Legal review of the terms and privacy pages; real photography.
  5. Dependency audit (`composer audit`, `npm audit`) and a production settings checklist.
- **P2 — advanced marketplace features**
  1. In-app notification centre (a bell icon) for dealers and admins, reusing these notifications on Laravel's database channel.
  2. WhatsApp templates, opt-in/opt-out and notifications.
  3. Nearby Places; days-on-market and price-reduced filters.
  4. Bulk moderation; AI chat memory; live LLM test.
- **P3 — later**
  - subscriptions and payments, paid featured listings
  - dealer reviews
  - Urdu UI and more cities
  - analytics with consent
  - a native app or public API

---

## 8. Known issues

1. **Emails need a running worker.** Without `php artisan queue:work` (or `composer run dev`), emails wait in `jobs`. Admin → Settings shows the count.
2. **Mail is `log` only.** No email leaves this machine yet.
3. **Verification is not enforced yet** (`MEEZAN_REQUIRE_EMAIL_VERIFICATION=false`). Turning it on before mail works would block the unconfirmed real dealer from adding listings.
4. **The rendered email layout was not visually checked.** Only the HTML content was.
5. **Not checked in the browser:** the banner and Renew-on-expiring states (no local data in those states) and the admin settings rows (the admin password was changed). All are covered by automated tests.
6. **Tests write their simulated warnings into `storage/logs/laravel.log`.** Harmless, but it can confuse anyone reading the log. A separate log channel for tests would fix it.
7. **Still from earlier phases:**
   - QA draft MP-000017 remains in the local database
   - WhatsApp, LLM, SMS and payments are not configured
   - public OpenStreetMap services are for light use only
   - `APP_DEBUG=true`
   - no version control (deferred by you)

---

## 9. Next recommended phase

**Phase 3: Trust & media**, covering dealer verification documents, image processing, and photo ordering and cover choice.

**Why:**
- Admins currently verify dealers with no evidence attached. Private document upload with admin-only viewing closes the biggest remaining trust gap.
- Photos are uploaded and served at full size with their EXIF/GPS data intact. That slows every page and can reveal where an owner lives.
- Both can be built and tested fully locally, with no external credentials.
