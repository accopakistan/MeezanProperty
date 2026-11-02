# Meezan Property — Phase 9 Completion Report
## Dealer Subscriptions, Billing, Payments, Package Limits & Commercialisation

Date: 2 November 2026 · Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB) · No Git commits, no GitHub push, no Hostinger deployment in this phase.

Status key: **COMPLETE AND VERIFIED** · **COMPLETE BUT CONFIGURATION-BLOCKED** · **PARTIALLY IMPLEMENTED** · **NOT IMPLEMENTED**

---

## 1. Executive summary

Meezan Property now has a database-driven commercial foundation:

- **Packages:** admins create and edit them (price, currency, monthly or yearly billing, trial days, limits and feature flags). No package or limit is hard-coded. The three seeded packages are labelled **Draft** and none is the default plan.
- **Subscriptions:** a full lifecycle (awaiting payment, trial, active, overdue, cancelled, expired, suspended). History is never overwritten, and every subscription keeps a snapshot of what was bought.
- **Billing records:** invoices and payments that are never deleted, with refunds and adjustments in an append-only table.
- **Payment gateways:** one abstraction (`PaymentGateway`) with two drivers:
  - **bank transfer:** staff must confirm the money arrived before anything activates;
  - **test (mock) gateway:** local-only, clearly labelled, excluded from revenue.
- **Webhooks:** signed, validated, idempotent and audited.
- **Limits enforced:**
  - listing limit at submit and renew;
  - monthly lead limit in the AI/requirement lead router, where a lead that can't be placed is **held for staff, never lost**;
  - featured allowance, for dealer self-featuring.
- **Over-limit policy:** listings are never deleted or hidden; only new additions are blocked.
- **Screens:** dealer billing dashboard, public package comparison, admin package management, admin billing dashboard (real recorded revenue only), subscriptions, payments with transfer review, and invoices with refunds.

**Results:** 405 tests pass (1,957 assertions, 0 failures), including 39 new billing tests. Pint, the build, both audits and the route cache are clean. Existing data is intact (§23).

No real payment provider is connected. Taking real money needs bank details (for transfers) or a merchant account (for an online gateway); see §28.

---

## 2. Initial architecture audit

| Area | Found | Reused / changed |
|---|---|---|
| Users / dealers | `users` (role) + `dealer_profiles` (verification, `lead_capacity`, `accepts_leads`) | Subscriptions hang off `users.id`, matching `properties.user_id` and `lead_assignments.dealer_id` |
| Dealer status | `DealerStatus` enum with `label()/tone()` | Same enum pattern for `SubscriptionStatus`, `InvoiceStatus`, `PaymentStatus` |
| Listing status | `PropertyStatus` (draft … suspended) | Counting rules use these values, configurable |
| Leads | `leads` + `lead_assignments`; `LeadRouter` (consent, verified, city/area, `lead_capacity`, ranking) | Extended in place; no second routing system |
| Notifications | `QueuedMailNotification` (in-app + optional mail, after commit) | `BillingUpdated` (dealer) and `BillingAdminAlert` (staff) |
| Audit | `AuditLogger` + immutable `AuditLog` | Every billing change is recorded with from/to/reason/who |
| Webhooks | `webhook_events` (provider + unique hash), WhatsApp controller pattern | Reused for payment idempotency (provider `pay-{gateway}`, hash of the provider's event id); no schema change |
| Private files | `local` disk (dealer documents) | Payment proofs use the same private disk |
| Billing | Only placeholder pages (`Admin\BillingController`, `dealer/subscription`) stating billing was not set up | Replaced with real pages |
| Featured | `properties.is_featured`, admin toggle | Kept; dealer self-featuring added within the package allowance |

---

## 3. Database changes — COMPLETE AND VERIFIED

Six additive migrations, run on 2 Nov 2026. No table was dropped or rewritten.

| Migration | Change |
|---|---|
| `2026_11_02_000027_create_packages_table` | name, slug, description, price, currency, billing_interval, trial_days; listing/lead/featured/team/AI limits (null = no limit); features JSON; is_active, is_default, is_recommended, is_demo, sort_order |
| `…000028_create_subscriptions_table` | user, package, previous_subscription_id, status, change_type, interval, amount, currency, **package_snapshot**, starts/trial/period dates, cancel_at_period_end, scheduled_package_id, cancelled/ended/end_reason, expiry reminder, is_test; indexes (user, status) and (status, period end) |
| `…000029_create_invoices_table` | unique number, user, subscription, package, kind, description, amount, currency, status, period, issued/due/paid dates, method, gateway reference, refunded_amount, **billing_snapshot**, is_test |
| `…000030_create_payments_table` | unique reference, invoice, user, gateway, status, amount, gateway reference (unique per gateway), payer reference, private proof path/mime/SHA-256, review fields, failure reason, is_test |
| `…000031_create_billing_adjustments_table` | refund/credit/debit, amount, reason, external reference, created_by (append-only) |
| `…000032_add_billing_fields_to_leads` | `hold_reason`, `invalidated_at`, `invalid_reason`, `invalidated_by` |

One fix was needed during the run: MariaDB rejected a NOT NULL `issued_at` timestamp without a default. It now uses `useCurrent()`. That migration had not been applied, so nothing was left half-done.

## 4. Package architecture — COMPLETE AND VERIFIED

- **Model and limits:**
  - `App\Models\Package` stores every attribute in §7 of the brief.
  - Limits are nullable: empty means no limit.
  - `snapshot()` captures price, interval, limits and features at the moment of purchase.
- **Feature flags** are configured in `config('meezan.billing.features')`:
  - priority leads, featured listings, advanced analytics, AI assistant, API access, priority support;
  - new flags are added there without schema changes.
- **Default plan:** one free package can be marked as the default for dealers without a subscription. Validation refuses a paid default plan.
- **Recommended:** at most one package is recommended (highlighted). Savings percentages are never shown.
- **No deletion:** packages are never deleted (no destroy route); they are hidden instead.
- **Draft packages:** `DraftPackagesSeeder` creates three for local testing (Free / Starter PKR 2,500 / Gold PKR 7,500).
  - Each is marked `is_demo` and shown as "Draft".
  - The seeder is idempotent (matched by slug, never overwrites) and refuses to run outside local/testing.
  - None is set as the default plan, so no dealer's limits changed.

## 5. Subscription lifecycle — COMPLETE AND VERIFIED

`SubscriptionManager` is the only code that changes subscriptions.

| Status | Meaning |
|---|---|
| pending_payment | Paid package chosen; invoice issued; **not active** |
| trial | First-time trial of a package with trial days; no payment taken |
| active | Paid (or free) and in its period |
| past_due | Period ended with the renewal unpaid; package kept for `grace_days` (3) |
| cancelled | Ended by dealer/staff, replaced by another package, or a closed checkout |
| expired | Renewal not paid after grace, or a free/trial period ended |
| suspended | Staff suspension; the default plan applies meanwhile |

How it works:
- **History:** a package change starts a **new row** linked by `previous_subscription_id`. The old row ends with a reason (for example "Replaced by Gold"). Each paid period is an invoice.
- **Daily command:** `meezan:billing-lifecycle` (01:30) handles:
  - renewal invoices 7 days before the period ends (one per period);
  - reminders for subscriptions that are cancelling or changing;
  - moving subscriptions to overdue, then expired;
  - applying scheduled downgrades and cancellations;
  - closing checkouts unpaid after 14 days (the invoice is kept as cancelled);
  - retrying held leads.

  It is idempotent and never charges anyone.

## 6. Usage tracking — COMPLETE AND VERIFIED

`App\Services\Billing\Entitlements` defines what counts (all configurable in `config/meezan.php` → `billing`):

- **Listings:** listings in `counted_listing_statuses`. The default is **published + pending review**. Drafts, rejected, changes requested, expired, archived and suspended listings do not count.
- **Leads:** lead assignments this **calendar month** from `counted_lead_sources` (default: buyer requirements only). Excluded:
  - enquiries on the dealer's own listings (always delivered, never counted);
  - leads staff marked spam, duplicate or invalid;
  - assignments moved to another dealer.
- **Featured:** the dealer's published listings marked featured.

Performance:
- Usage is computed with indexed count queries, only where needed (billing pages, listing submission, lead routing).
- Lead routing computes plans and usage for all candidate dealers in two grouped queries; there is no per-dealer query.

## 7. Listing limits — COMPLETE AND VERIFIED

- **Where it's checked:** when a listing would start counting, that is "Submit for review" (from draft, rejected, changes requested or expired) and renewing an expired listing.
- **No race:** the check runs in a transaction with the dealer row locked, so two submissions can't both take the last allowance.
- **Message:** "You have reached your listing limit. Upgrade your package to add more listings. (n of m used)". It also appears as a banner with current usage, the limit and an **Upgrade package** button, on My Listings and the Add Listing form. The dealer gets an in-app notice at most once a day.
- **What stays allowed:** drafts can still be saved, and live listings can still be edited (they already count).
- **Staff:** listing approval and moderation are not blocked by dealer limits.

## 8. Lead limits — COMPLETE AND VERIFIED

`LeadRouter::routeRequirement` keeps its existing checks (consent, active verified dealer, not an owner, accepts leads, city/area coverage, `lead_capacity`). It then:

1. Reads each candidate's plan and monthly usage.
2. Ranks candidates, adding a configurable boost (+15) for packages with **Priority leads**.
3. Assigns to the best candidates **with allowance left**.

The top-ranked dealers who missed out only because of their limit get a "lead limit reached — upgrade" notice (once a day).

If every suitable dealer is at their limit, the overflow policy `billing.lead_overflow` applies:
- **`queue`** (default): the lead is marked *Needs review* with `hold_reason = capacity`, and admins are notified. The admin lead page shows "Held for staff" and each suggested dealer's usage (for example "5/5 leads this month, at limit"). The daily job retries held leads, so they route once allowances reset on the 1st or a dealer upgrades. Staff can also assign manually.
- **`allow_over_limit`:** assigns anyway, with the reason recorded as "assigned over the monthly lead limit".

Leads are never dropped.

## 9. Upgrade workflow — COMPLETE AND VERIFIED

1. The dealer compares packages and reviews a checkout summary (price, when it starts, payment method).
2. Choosing a paid package creates a subscription (**awaiting payment**), an invoice and a payment. **Nothing activates on click.**
3. When the gateway confirms the payment (or staff confirm a bank transfer), the processor marks the invoice paid and activates the new subscription.
4. The old subscription ends ("Replaced by …"), the history links them, and the dealer is notified.

**Proration:** the new period starts on the payment date. Unused time on the old package is not credited automatically; the checkout says so. Staff can record a credit (§17).

## 10. Downgrade workflow — COMPLETE AND VERIFIED

- **Timing:** a downgrade from a running paid period is **scheduled for the end of that period**. The dealer keeps the current package until then and can undo it. On the end date the job ends the old subscription and starts the new one (free: immediately; paid: an invoice to pay).
- **Over-limit policy** (`billing.over_limit_policy`):
  - **`keep_live` (default, chosen as the safest):** nothing is deleted or hidden. A dealer over a smaller limit keeps every live listing, sees a clear over-limit notice, and can't submit or renew listings until under the limit (as listings expire or the dealer archives them).
  - **`require_within_limit`:** the downgrade is refused until the dealer has archived enough listings, with an exact message (for example "Small allows 1 active listings and you have 3. Archive 2 listing(s) first.").

## 11. Billing records — COMPLETE AND VERIFIED

- **Invoices:**
  - unique sequential numbers (`MP-2026-000001`, prefix configurable);
  - linked to dealer, subscription and package, with amount, currency, status (draft, unpaid, paid, failed, cancelled, refunded), issue/due/paid dates, payment method and gateway reference;
  - a snapshot of the dealer's and package's details as issued.
- **Payments:** unique references (`PAY-0000001`), one row per attempt.
- **Immutable:** the models refuse to delete invoices or payments, or to change an issued invoice's amount. A cancelled or refunded invoice keeps its number and amounts.

## 12. Invoice / receipt system — HTML: COMPLETE AND VERIFIED · PDF: NOT IMPLEMENTED

- **Pages:** one printable HTML page serves dealers and admins. It is titled "Invoice" until paid and "Payment receipt" after, with a Print button (no inline script, CSP-safe).
- **Contents:** number, dealer details, package details, amount, issue/due/paid dates, method, reference, refunds, and a "TEST RECORD" banner for mock payments.
- **Tax wording:** each invoice states it is **not a tax invoice**, because tax treatment has not been reviewed for Pakistan and no sales tax is charged or shown.
- **PDF:** not added. A PDF library would be new infrastructure; browser print-to-PDF covers it for now.

## 13. Payment gateway abstraction — COMPLETE AND VERIFIED

- **Interface:** `PaymentGateway` defines key, label, availability, test flag, checkout URL, webhook support, signature check, and parsing an event into a `GatewayEvent`.
- **Gateways never change records.** `PaymentProcessor` alone applies outcomes, with the payment row locked, so the same outcome twice changes nothing.
- **Selection is by configuration:** `GatewayManager` lists only gateways that are available.
- **Adding a provider:** write one class, register it in `GatewayManager::DRIVERS`, and add its config. No provider API was invented.

| Gateway | Status |
|---|---|
| Bank transfer (manual) | COMPLETE BUT CONFIGURATION-BLOCKED (needs bank details) |
| Test (mock) | COMPLETE AND VERIFIED (local only) |
| JazzCash / Easypaisa / licensed PSP / card gateway | NOT IMPLEMENTED (needs a merchant account and the provider's official API documentation) |

## 14. Mock gateway — COMPLETE AND VERIFIED

- **Labelling:** shown as "Test payment (no real money)", with a TEST MODE warning page offering "Simulate successful payment" and "Simulate failed payment".
- **Same path as real payments:** outcomes go through `PaymentProcessor` exactly like a real gateway's, and the amount and currency are checked.
- **Flagged:** every payment, invoice and subscription it touches gets `is_test`. These are excluded from all revenue figures and counted separately on the admin dashboard.
- **Availability** (hardened at the end of this phase): it works only with `MEEZAN_MOCK_PAYMENTS=true` **and** `APP_ENV` local or testing. A test proves it is off in production, in staging, and locally without the flag. Your local `.env` now has `MEEZAN_MOCK_PAYMENTS=true`. Do **not** copy that line to the live server.
- **Signed webhooks:** a signed webhook variant (HMAC-SHA256, `MEEZAN_MOCK_WEBHOOK_SECRET`) exercises the webhook path.

## 15. Manual payment workflow — COMPLETE BUT CONFIGURATION-BLOCKED

1. The dealer chooses **Bank transfer** and sees:
   - the amount due;
   - the configured account details;
   - the invoice number as the payment reference.
2. The dealer submits the bank's transaction reference and, optionally, a receipt (JPG/PNG/WebP/PDF, up to 5 MB). The payment becomes **Awaiting review**. **Nothing is activated.**
3. Admins are notified. The review page shows the reference, the proof (private disk, SHA-256 recorded) and a warning if the same transaction reference was used on another payment.
4. **Approve** requires ticking "I have confirmed the money arrived in the account". **Reject** requires a reason, which is sent to the dealer; the invoice stays payable.
5. The dealer can't start another package change while a transfer is awaiting review.

**Blocked on:** `MEEZAN_MANUAL_PAYMENTS=true` and the bank details (§28). Until then, bank transfer is not offered.

## 16. Webhook architecture — COMPLETE AND VERIFIED (mock); real providers NOT IMPLEMENTED

`POST /webhooks/payments/{gateway}` is CSRF-exempt and throttled to 120 requests a minute. Checks, in order:

1. The gateway is known and accepts webhooks (else 404).
2. The signature is valid (else 401).
3. The payload has the expected shape (else 422, recorded as rejected).
4. The provider's event id hasn't been seen before. A redelivery answers `duplicate`; a different event reporting the same outcome is a no-op.
5. `PaymentProcessor` applies it. Unknown payments get 422. A processing error gets 500, so the provider retries safely, and admins are alerted.

Also:
- An amount or currency mismatch **fails** the payment instead of activating, and alerts staff.
- An event conflicting with a final state is ignored and audited.
- Payloads and signatures are never logged; a test confirms nothing logged contains the secret or the signature.

## 17. Payment security — COMPLETE AND VERIFIED

- **Never stored:** card numbers, CVVs and banking passwords have no field anywhere. The proof form tells dealers not to include them.
- **Secrets:** read from `.env` only, never sent to the browser. Tests check the checkout and test pages don't contain the webhook secret.
- **Proof files:**
  - stored on the private `local` disk, outside the web root, under unguessable UUID names;
  - streamed only to the owning dealer or to admins, with `nosniff` and `no-store`;
  - `proof_path` is hidden from model serialisation.
- **Isolation:** every dealer billing lookup goes through the signed-in dealer's relations, so another dealer's invoice, payment, proof or test checkout returns 404. Buyers get 403 on dealer billing, and dealers get 403 on admin billing.
- **Who can activate a paid package:** only a confirmed payment. Staff have no "activate without payment" action.
- **Money arithmetic:** amounts are compared in paisa (integers), avoiding float rounding without adding a bcmath dependency.

## 18. Admin package management — COMPLETE AND VERIFIED (automated); browser QA not possible (see §26)

- **Packages screen:** list with price, limits, running subscribers and badges (default, recommended, draft, trial); create/edit form; "Hide" / "Make available" toggle.
- **Edits apply to new subscriptions only:** existing ones keep their snapshot, and the form says so.
- **Audit:** creation is logged with the full package snapshot; edits are logged with **before and after** values of the changed fields.
- **Warning:** a banner appears when no default plan is set, explaining the fallback limits in effect.

## 19. Admin billing dashboard — COMPLETE AND VERIFIED (automated)

Revenue figures, using recorded data only and grouped by currency:

| Figure | What it is |
|---|---|
| Paid this month (net) | Invoices paid this month, less refunds recorded this month |
| Pending | Unpaid invoices |
| Expected monthly | Running paid subscriptions that will renew (yearly ÷ 12), labelled as an expectation, not income |

Every figure shows "—" when there is no data. A test proves this.

Counts:
- active, trial, overdue and ending-in-7-days subscriptions;
- checkouts awaiting payment;
- pending, awaiting-review and failed (30-day) payments;
- dealers per package (including dealers on the default plan);
- payment method status (bank transfer, test gateway, "Online gateway: Not integrated");
- recent payments.

Test records are excluded and counted in a note.

Other screens:
- **Admin dashboard:** the old "Monthly Revenue — / Billing not integrated" card is now "Paid This Month" (real). There is a "Bank Transfers to Review" pending action, and the Payment Gateway status reflects real configuration.
- **Subscriptions:** list with status/package/search filters; detail with the purchased terms, current usage, invoices, the dealer's history and an activity log; actions suspend / reinstate / cancel at period end / end now, each requiring a reason.
- **Payments:** list with awaiting-review first; review page as in §15.
- **Invoices:** detail page with "Record a refund".

## 20. Dealer subscription dashboard — COMPLETE AND VERIFIED (browser QA at 375 / 768 / 1366 px)

"Packages & Payments" shows:
- the current package, status, price, and renewal or end date;
- scheduled changes, with "Keep current package";
- usage meters for listings, leads this month and featured;
- over-limit and near-limit notices;
- upgrade options and a "Compare packages" page;
- open-invoice banners, and "Cancel package" (explains the end date);
- billing history and payment history, each paginated (10 per page).

The design follows the existing brand components (purple/gold cards, status badges, stat-card style) as in `public/Refrence/` (not modified).

## 21. Notifications — in-app: COMPLETE AND VERIFIED · email: COMPLETE BUT CONFIGURATION-BLOCKED · WhatsApp: NOT IMPLEMENTED for billing

`BillingUpdated` sends to dealers, in-app and by email if the dealer opted in. Events:
- subscription activated, trial started;
- payment pending, payment received, payment failed, transfer not confirmed;
- renewal due, payment overdue;
- ending soon, expired, ended, cancellation scheduled, downgrade scheduled, suspended;
- refund recorded;
- listing limit reached, lead limit reached (both with an upgrade prompt).

`BillingAdminAlert` sends to admins: transfer to review, payment needs attention, lead held at capacity.

Email is queued but goes to the log until a mail provider is configured. WhatsApp delivery of billing messages was not built.

## 22. Audit logging — COMPLETE AND VERIFIED

Each entry records who, when, from/to state and the reason where relevant. Actions logged:

- **Packages:** created, updated (before/after), activated/deactivated.
- **Subscriptions:** checkout started, trial started, activated, replaced, cancellation scheduled, cancelled, checkout cancelled or expired, past due, expired, downgrade scheduled, resumed, suspended, reinstated.
- **Invoices:** renewal issued, paid, paid twice, cancelled, refund recorded.
- **Payments:** started, transfer submitted (flags a reused reference), transfer approved/rejected, succeeded, failed, amount mismatch, conflicting event.
- **Other:** billing adjustment recorded; lead held, invalidated, validity restored; listing featured or unfeatured by the dealer.

Refunds, credits and debits live in the append-only `billing_adjustments` table.

## 23. Existing data preservation — COMPLETE AND VERIFIED

| Record | Before (2 Nov, pre-migration) | After |
|---|---|---|
| Users | 4 | 4 |
| Dealer profiles | 2 (both verified) | 2 (both verified) |
| Properties | 17 (16 published, 1 draft) | 17 (16 published, 1 draft) |
| Property images | 42 | 42 |
| Leads / assignments | 0 / 0 | 0 / 0 |
| Buyer requirements | 0 | 0 |
| AI conversations / messages | 2 / 14 | 2 / 14 |
| Contact consents | 0 | 0 |
| Dealer documents | 0 | 0 |
| Areas / cities | 1,238 / 7 | 1,238 / 7 |
| Inquiries | 0 | 0 |
| Packages | — (table did not exist) | 3 (Draft, from `DraftPackagesSeeder`) |
| Subscriptions / invoices / payments | — | 1 / 1 / 2 — **all test records** from manual QA (§26) |
| Billing adjustments | — | 0 |

- **Unchanged:** verification statuses, the AI conversation data and the seven-city locations. All AI, assistant and location tests still pass.
- **Not touched:** `public/Refrence/`.
- **No destructive operations:** no `migrate:fresh`, no `db:wipe`, no table drops or deletions.

## 24. Files changed

**New**

| Area | Files |
|---|---|
| Migrations | the six in §3 |
| Enums | `SubscriptionStatus`, `InvoiceStatus`, `PaymentStatus` |
| Models | `Package`, `Subscription`, `Invoice`, `Payment`, `BillingAdjustment` |
| Services | `Billing/Entitlements`, `Billing/Plan`, `Billing/SubscriptionManager`, `Billing/PaymentProcessor`, `Billing/BillingReport` |
| Gateways | `Billing/Gateways/PaymentGateway`, `GatewayEvent`, `GatewayManager`, `ManualGateway`, `MockGateway` |
| Controllers | `Admin/PackageController`, `Dealer/BillingController`, `PricingController`, `Webhooks/PaymentWebhookController` |
| Notifications | `BillingUpdated`, `BillingAdminAlert` |
| Command | `BillingLifecycle` (`meezan:billing-lifecycle`) |
| Seeder | `DraftPackagesSeeder` |
| Views | `pricing`; `billing/invoice`; `dealer/billing/{packages, checkout, payment, mock}`; `admin/packages/{index, form}`; `admin/billing/{dashboard, subscriptions, subscription, payments, payment}`; components `usage-meter`, `package-card`, `listing-allowance` |
| Tests | `tests/Feature/BillingTest.php` (27), `tests/Feature/BillingLimitsTest.php` (12) |
| Report | this file |

**Changed**

| File | Change |
|---|---|
| `config/meezan.php` | `billing` section: counting rules, policies, features, gateways |
| `.env.example` | billing variables, empty |
| `.env` | added `MEEZAN_MOCK_PAYMENTS=true` (local only) |
| `routes/web.php` | 31 new routes |
| `routes/console.php` | schedule for the lifecycle command |
| `bootstrap/app.php` | CSRF exemption for `webhooks/payments/*` |
| `AppServiceProvider` | `GatewayManager` singleton |
| `Models/User.php` | subscriptions/invoices/payments relations |
| `Models/Lead.php` | hold/invalid fields |
| `Services/Leads/LeadRouter.php` | package-aware capacity, priority, overflow, `retryHeld()` |
| `Dealer/PropertyController` | listing limit at submit/renew; `feature()` |
| `Dealer/SubscriptionController` | rewritten |
| `Admin/BillingController` | rewritten |
| `Admin/DashboardController` | real revenue, transfers to review, gateway status |
| `Admin/LeadController` | `updateValidity()` |
| Views | `dealer/subscription` (rewritten), `dealer/properties/{index, form}`, `admin/leads/show`, `components/layouts/dashboard` (nav: Billing, Packages, Payments badge) |
| `resources/views/admin/billing.blade.php` | removed (placeholder replaced) |
| `resources/js/app.js` | print button handler |
| `resources/css/app.css` | `.table` is `relative`; this fixes hidden header cells widening pages on phones, including the existing My Listings page |
| `resources/views/dealer/dashboard.blade.php` | `grid-cols-1` so the existing dealer dashboard no longer overflows on phones |
| `tests/Feature/AdminTest.php` | see §25 |

## 25. Automated test results

Commands, final run:

| Command | Result |
|---|---|
| `php artisan test` | **405 passed, 1,957 assertions, 0 failed** (99.5 s) |
| `vendor/bin/pint --test` | passed |
| `npm run build` | OK |
| `composer audit` | no advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `php artisan route:cache` | cached successfully (then cleared for local development) |

Before Phase 9 there were 366 tests: 361 at the end of Phase 8, plus 5 asset-URL tests added since. **39 new tests** cover:

- **Packages:** create/edit with audit before/after; validation; free-only default; single default/recommended; active-only public page; hidden packages can't be chosen; no deletion; fixed invoice amounts.
- **Subscriptions:** default plan vs fallback; free start with history; no activation on click; test-payment success (flagged, excluded from revenue); failure and retry; paid upgrade replacing the old subscription; scheduled downgrade; cancel/undo/fallback; renewal issued once, extension, overdue and expiry; one trial only; closing unpaid checkouts.
- **Payments:** bank transfer only when configured; transfer review with private proof and the "money arrived" confirmation; rejection needs a reason; reused-reference flag; signed, idempotent webhooks (duplicates, unknown payments, bad payloads); amount mismatch; test gateway off in production, staging and without the flag; refunds capped and netted from revenue; credits.
- **Security:** dealers isolated from each other's billing; buyer and dealer access to admin pages; secrets never shown or logged.
- **Limits:** counting rules; submit blocked with the exact message and the draft kept; live listings still editable; renewal blocked; over-limit never removes listings; `require_within_limit`; lead limit skip/hold/notify/retry next month; over-limit assignment option; spam, duplicate, reassigned and enquiry leads not counted; priority ranking; featured allowance.
- **Data preservation:** dealers, listings, verification and leads unchanged by billing runs.

**One existing test changed:** `AdminTest > it does not invent billing data` asserted the old placeholder sentences ("No payment provider is connected", "Subscription plans are not set up"), which Phase 9 replaces.
- It now asserts the real empty states ("No payments found", "No subscriptions found").
- It also asserts the billing dashboard shows "—" for paid, pending and expected revenue.
- Its intent, no invented billing data, is kept and strengthened. No other existing test was touched.

## 26. Manual testing results

| Area | Check | Result |
|---|---|---|
| Public | `/pricing` shows the three DB packages labelled Draft; Gold highlighted only because it is marked recommended; no savings claims | Pass |
| Dealer (demo dealer from the seed file) | Packages & Payments with live data | Pass, after fixes below |
| Dealer | Starter (draft) checkout → test page → **simulated failure** (invoice "Payment failed", nothing activated) → retry → **simulated success** | Pass: receipt "TEST RECORD", period 2 Nov–2 Dec 2026, usage 16/25 listings, 0/20 leads; both attempts kept in history |
| Dealer | My Listings featured buttons; no console errors | Pass |
| Responsive | billing pages, pricing, My Listings, dealer dashboard at 375, 768, 1366 px | Pass (no horizontal overflow) after fixes |
| Logs | `storage/logs/laravel.log` | Only today's error is the `issued_at` migration failure, fixed before rerun |
| Admin | create/edit package, subscriptions, transfer review, billing dashboard | **Not done in the browser**: the admin password belongs to you and was not used. Covered by automated tests that render every admin billing page with data |

Fixes found by manual QA:
- **Featured listings chosen by staff** showed as a dealer "over the limit" breach. The breach banner now ignores featured, and the meter explains when self-featuring isn't included.
- **Wrong fallback wording:** "Packages are not set up" now reads "Launch access … until a default plan is set". The checkout's "Currently" shows the real plan name.
- **Phone overflow:**
  - hidden table header cells widened pages (also on the existing My Listings page);
  - the receipt table had no scroll wrapper;
  - the existing dealer dashboard grid overflowed.
- **Test gateway auto-enabled** whenever `APP_ENV=local`: it now needs an explicit flag (§14).

**Test records left in the local database:** the demo dealer now has a test Starter (draft) subscription:
- invoice MP-2026-000001 (paid, test);
- payments PAY-0000001 (failed, test) and PAY-0000002 (succeeded, test).

They are labelled and excluded from revenue. If the scheduler runs locally, a renewal invoice will be issued around 25 Nov and the subscription will expire if unpaid. Billing records can't be deleted by design.

## 27. Known limitations

1. **No default plan is set.** Dealers without a subscription (both existing dealers) keep **launch access**: listings and leads unlimited, no self-featuring (`fallback_limits`). Choosing the free plan's limits is a business decision; mark a package as default in Admin → Packages when agreed.
2. **No proration.** Upgrades start a fresh period from payment; staff can record a credit.
3. **No automatic charging.** No card or token is stored, so each renewal is an invoice the dealer pays. Auto-debit needs a tokenising gateway.
4. **Renewals use the price in the subscription's snapshot** (grandfathered). A new price applies to new subscriptions.
5. **Lead allowance runs per calendar month** (application timezone), not per billing period.
6. **Staff actions don't check dealer allowances.** Lead reassignment counts toward the allowance but isn't blocked; listing approval and reinstatement aren't blocked.
7. **Featuring isn't tracked by who did it.** Staff-featured listings count toward a dealer's featured usage, and dealers can unfeature their own staff-featured listings.
8. **Suspended subscriptions** fall back to the default plan; they don't block the account (dealer suspension does).
9. **Credits and debits are records only**; they don't reduce invoices.
10. **Feature flags** other than Priority leads and Featured listings, and the team-member and AI-request limits, are stored and shown but not enforced (no team or dealer-AI feature exists yet).
11. **Invoices** are HTML only (print to PDF) and are not tax invoices.
12. **Payment proofs** live in `storage/app/private` and must be included in server backups.
13. **Live-site warning:** keep `MEEZAN_MOCK_PAYMENTS` unset or false in the live `.env`. The code also refuses it unless `APP_ENV` is local or testing.

## 28. External credentials still required

| To enable | Needed | Where |
|---|---|---|
| Bank transfer | Company bank name, account title, account number and/or IBAN, and `MEEZAN_MANUAL_PAYMENTS=true` | `.env` (`MEEZAN_BANK_*`) |
| Online payments | A merchant account with a licensed Pakistani PSP (e.g. JazzCash or Easypaisa merchant, or a bank/aggregator gateway) or an international provider, its official API documentation, sandbox and live keys, and a webhook signing secret | New gateway class plus `.env`; not built, no API was assumed |
| Email notifications | A mail provider (SMTP / API) | `.env` `MAIL_*` |
| WhatsApp billing messages | Live WhatsApp Business API (see Phase 7) | — |
| Tax-compliant invoices | Review by a Pakistan tax adviser (FBR/provincial sales tax on services, NTN/STRN details) | — |

## 29. Recommended next phase

1. **Business decisions:** agree final packages, prices, limits and the **default free plan**; replace or hide the Draft packages.
2. **First real payment method:** enable bank transfer with company account details, then choose and contract a licensed online gateway and implement its driver against official documentation (sandbox first).
3. **Admin browser QA** of packages, transfer review and the billing dashboard, signed in with your admin account.
4. **Email delivery** for billing notices; optional WhatsApp templates.
5. **Proration or credits applied to invoices**, and a "featured by" field to separate staff featuring from dealer featuring.
6. **Tax and invoice compliance review** before issuing invoices to real dealers.

---

### Final figures

| Item | Value |
|---|---|
| Tests passed | **405** |
| Assertions | **1,957** |
| Failures | **0** |
| New database tables | **5** (`packages`, `subscriptions`, `invoices`, `payments`, `billing_adjustments`), plus 4 new columns on `leads` |
| New routes | **31** (`pricing`, `webhooks.payments`, `dealer.properties.feature`, 12 × `dealer.billing.*`, `admin.billing`, `admin.billing.adjustments`, 6 × `admin.packages.*`, 2 × `admin.subscriptions.*`, 3 × `admin.payments.*`, 2 × `admin.invoices.*`, `admin.leads.validity`) |
| New scheduled commands | **1** (`meezan:billing-lifecycle`, daily 01:30) |
| Existing data before / after | users 4/4 · dealers 2/2 (verified 2/2) · properties 17/17 · images 42/42 · leads 0/0 · requirements 0/0 · conversations 2/2 · areas 1,238/1,238 |
| External credentials still required | Bank account details (transfer); PSP merchant account and keys (online); mail provider; WhatsApp API; tax review |
