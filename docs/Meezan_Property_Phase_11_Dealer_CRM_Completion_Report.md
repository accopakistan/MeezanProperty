# Meezan Property — Phase 11 Completion Report
## Dealer CRM, Advanced Lead Management, Follow-ups, Appointments & Sales Pipeline

Date: 2 November 2026 · Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB) · No Git commits, no GitHub push, no deployment in this phase.

Status key: **COMPLETE AND VERIFIED** · **COMPLETE BUT CONFIGURATION-BLOCKED** · **PARTIALLY IMPLEMENTED** · **NOT IMPLEMENTED**

> The Phase 11 brief arrived cut off after "REGRESSION". This report follows the structure of the earlier phase reports and covers every requirement the brief stated.

---

## 1. Executive summary

Dealers now have a CRM built on the existing lead system:
- **Overview** of what needs doing today.
- **Kanban pipeline:** drag-and-drop, plus a "Move to…" menu on every card.
- **Filtered lead list.**
- **Lead workspace:** requirement, real matching listings, recommendations, private notes, follow-ups, viewings and an activity timeline.
- **Agenda** of follow-ups and appointments.

The central design point:
- **Each dealer has their own pipeline per lead.** The CRM state lives on the existing per-dealer `lead_assignments` row, so when a lead is shared with up to three dealers, one dealer's stage, notes and follow-ups never affect another's.
- **No second "CRM lead" model was created.**
- **A bug was fixed along the way.** The earlier dealer status dropdown wrote the *shared* lead status, so one dealer could overwrite it for everyone; it now moves only that dealer's own stage.

Staff get:
- lead oversight (counts per dealer, never claiming a lead is "won" because one dealer won it);
- per-dealer CRM state on each lead page (counts only, never note text);
- add or remove a single dealer, with eligibility checks;
- an operational dealer activity table.

Two pre-existing database problems surfaced during QA and were fixed (§19):
- **Silent timestamp overwrites:** MariaDB was overwriting four timestamp columns on every row update, including when a lead was assigned (since Phase 3) and when a consent was granted (since Phase 7). All original values were recovered.
- **Times in the wrong zone:** CRM times typed in Pakistan time were being stored as UTC, so reminders would have fired 5 hours late.

**Results:** 455 tests pass (2,419 assertions, 0 failures), including 17 new CRM tests. Pint, the build, both audits and the route cache are clean.

## 2. CRM architecture audit (before changes)

| Question | Finding |
|---|---|
| Lead statuses | Global `LeadStatus` on `leads`: new, assigned, contacted, qualified, closed, lost, unrouted. Listing inquiries also have new / contacted / closed |
| Reusable fields | `lead_assignments` already had one row per lead and dealer (unique pair): `status` (active/reassigned), `assigned_at`, `responded_at`, score and reason. `leads` holds purpose, type, area, source, hold and invalid fields |
| Multiple dealers | Yes: up to `max_dealers_per_requirement` (3) per lead |
| Buyer contact protection | Contact shown to dealers only on consented requirement leads; admin masks WhatsApp numbers and audits reveals |
| WhatsApp ↔ leads | `conversations.lead_id` and `buyer_requirement_id`; `buyer_requirements.source = whatsapp` |
| Lead allowance counting | This month's requirement-lead assignments, excluding moved-away ones and staff-invalidated leads |
| Conflict found | The dealer leads page wrote the **global** lead status from a dealer's dropdown |

## 3. Data architecture — COMPLETE AND VERIFIED

**Extended (additive):** `lead_assignments` gains:
- `pipeline_stage` and `priority`;
- `stage_changed_at` and `last_activity_at`;
- milestones `first_contact_at`, `qualified_at`, `closed_at`;
- `outcome_reason` and `outcome_note`;
- dealer-reported won details (`won_transaction_type`, `won_property_id`, `won_value`);
- `stale_flagged_at` and `end_reason`;
- indexes (dealer, status, stage) and (dealer, last activity).

Existing assignments were given a starting stage from their lead's old status (contacted → contacted, qualified → qualified, closed → won, lost → lost).

**New tables, each hanging off one assignment** (so it's private to that dealer):

| Table | Purpose |
|---|---|
| `lead_notes` | Private notes; author-only editing; `edited_at` |
| `lead_follow_ups` | Call / WhatsApp / meeting / reminder; priority, due time, status (pending, completed, cancelled). Overdue = pending and past due, derived, never deleted |
| `lead_appointments` | Viewing / office / phone / other; time, duration, optional live listing, location, notes, status (scheduled, completed, cancelled, no show) |
| `lead_recommendations` | Real listing per lead (unique); status recommended / viewed / interested / not interested |
| `lead_activities` | Append-only timeline (the model refuses updates and deletes); ids, stages and short labels only, never note text |

Indexes cover the common queries: dealer + status + due date, dealer + status + start time, and assignment + id.

## 4. Lead pipeline and transitions — COMPLETE AND VERIFIED

**Stages** (`PipelineStage` enum): New, Contact attempted, Contacted, Qualified, Viewing scheduled, Negotiating, Won, Lost, No response, Invalid, Duplicate.

**Allowed moves** are explicit. For example New can't jump to Qualified or Won, and closed stages can be reopened only as a correction. **Reasons:**
- Lost requires a reason (configurable list: price mismatch, location mismatch, changed mind, no response, bought elsewhere, invalid requirement, other).
- Invalid and Duplicate require a note.
- No response is never turned into Lost automatically.

**Milestones:** first contact and qualified are set the first time they're reached and kept (for analytics); `closed_at` is cleared on reopen. **Logging:** each change is written to the timeline and the audit log (`lead.pipeline_changed`, with from, to and reason).

**Listing enquiries** (one dealer) keep their simple inquiry status in step with the dealer's stage.

**Older quick-update form** (Leads & Inquiries page): it now changes only that dealer's stage, and the page shows the dealer's own stage plus "Open in CRM".

## 5. Lead ownership and privacy — COMPLETE AND VERIFIED

- **`LeadAssignmentPolicy`:** a dealer can view and change only their **own, active** assignment.
- **Child records** (notes, follow-ups, appointments, recommendations) are always fetched through that assignment, so another dealer's ids return 403 or 404.
- **Buyers** get 403 on every dealer CRM page.
- **Admins** can't open the dealer CRM pages. Their lead page shows each dealer's stage, priority, last activity and counts (notes, follow-ups, appointments, recommendations), never note text.
- **Removed or reassigned dealers** lose access to the lead.
- **The marketplace lead status** is never changed by a dealer.

## 6. Kanban pipeline — COMPLETE AND VERIFIED

**Columns:** New, Contacted, Qualified, Viewing, Negotiating, Won, Lost / closed, with real counts. Each column shows up to 30 leads, urgent and recently active first; extra leads are linked to the list view.

**Drag-and-drop** (progressive, CSP-safe JavaScript):
- the server re-checks ownership and the transition (JSON 422 with a message if not allowed);
- disallowed drops are refused client-side with a message;
- columns that need a reason open the lead page;
- the board reloads after a successful move.

**Without dragging:** every card has a "Move to…" menu, which covers keyboard, touch and accessibility. On phones the columns stack and can be collapsed.

## 7. Lead workspace — COMPLETE AND VERIFIED

| Section | Contents |
|---|---|
| Summary | Stage; editable priority; assigned and last activity; source (listing enquiry, requirement or WhatsApp assistant); buyer contact shared with consent (call, WhatsApp link, email) |
| Move this lead | Allowed next stages only, reason or note fields, optional won details |
| Requirement | Intent, city, areas, type, size, budget (approx. flag), bedrooms and bathrooms; or the enquiry and the listing it was about |
| Matching listings | From the same `PropertyMatcher` the buyer's assistant uses: live listings with match labels, "Recommend" per listing, or recommend by reference. Nothing invented |
| Recommendations | History, plus the buyer's response as recorded by the dealer (defaults to "Recommended"; interest is never assumed) |
| Private notes | Add; edit (author only) |
| Follow-ups | Schedule; done; cancel |
| Viewings and appointments | Schedule (optional listing from the recommendations), reschedule, completed, no show, cancel. A scheduled viewing moves a Contacted or Qualified lead to "Viewing scheduled" |
| Activity | The timeline with real times and "You", "Meezan Property staff" or "Automatic" |

WhatsApp: the lead shows that it came from the WhatsApp assistant and offers a WhatsApp link to the buyer (who consented). The buyer's conversation transcript is **not** shown to dealers.

## 8. Follow-ups and reminders — COMPLETE AND VERIFIED (in-app) · email: CONFIGURATION-BLOCKED

`meezan:crm-reminders` runs every 5 minutes. Each reminder is sent once:
- **Follow-up due** at its due time.
- **Follow-up overdue** 60 minutes later.
- **Appointment upcoming** 60 minutes before it starts.
- **Needs attention:** an open lead with no dealer activity for 3 days (`MEEZAN_CRM_STALE_DAYS`). The flag clears on the next activity, and the lead is **never closed automatically**.

Notifications use the existing in-app channel through a new `CrmReminder`. Email works through the existing mail channel once a mail provider is configured. WhatsApp is not used for CRM reminders.

## 9. Appointments — COMPLETE AND VERIFIED

Create, reschedule (which resets the reminder), and mark completed, no show or cancelled. Only a scheduled appointment can change. A listing must be live. Every change is audited (`lead.appointment_*`).

## 10. Property recommendations — COMPLETE AND VERIFIED

- Only **live** listings can be recommended (drafts, unknown references and duplicates are refused).
- Details always come from the database.
- History is recorded on the timeline.
- Buyer responses are only what the dealer records.

## 11. Won and lost leads — COMPLETE AND VERIFIED

- **Won** optionally records the transaction type, the property and a value. These are labelled **dealer-reported, not verified, never Meezan Property revenue**, and nothing is required.
- **Lost** requires a reason.
- Admin wording is "leads at least one dealer marked won"; a lead is never globally won.

## 12. Priority — COMPLETE AND VERIFIED

Each dealer sets their own priority (Low, Normal, High, Urgent) on their assignment, separate from marketplace ranking and lead scores.

## 13. Search and filtering — COMPLETE AND VERIFIED

Filters run in the database with pagination (20 per page):
- stage, outcome (open, won, lost), priority, follow-ups (due today, overdue, none);
- city (via area, requirement or listing), area, property type, buy/rent;
- assigned date range, and "needs attention".

## 14. Dealer CRM dashboard and analytics — COMPLETE AND VERIFIED

**Overview cards:** new leads, follow-ups due today, overdue, viewings today, needs attention.

**Panels:** pipeline by column, today's appointments, next follow-ups, recent activity.

**Last 30 days:** received, contacted, qualified, viewings, won and lost. Definitions are stated on the page and in `CrmStats`:
- "contacted" and "qualified" mean the stage was reached at least once;
- follow-up completion = completed ÷ (completed + overdue).

Rates are shown only from 5 leads or follow-ups; below that the page says "not enough … yet".

## 15. Admin oversight and dealer performance — COMPLETE AND VERIFIED (automated) · browser QA not possible

**Admin → Lead Oversight:**
- **Lead counts:** total leads, with a dealer, without a dealer, held at lead limit, at least one dealer marked won, staff-invalidated, dealer-reported invalid or duplicate, overdue follow-ups.
- **Breakdowns:** leads by city; dealer pipeline stages, labelled as counting assignments.
- **Dealer activity table** for 7, 30 or 90 days: received, contacted (rate), qualified, viewings, won, lost, follow-ups done vs overdue (rate), needs attention, last activity.

The table is labelled as an operational insight: not a ranking, never public, and not used to penalise anyone. Figures come from a fixed number of grouped queries, not one per dealer.

## 16. Lead reassignment — COMPLETE AND VERIFIED

| Action | Behaviour |
|---|---|
| Existing **Reassign** (replace all) | Kept. Ended dealers now get a timeline entry and a "lead moved" notice |
| New: **Add a dealer** | Must be an active verified dealer who accepts leads and covers the lead's city and area; per-lead maximum of 3. A dealer with no lead allowance left can only be added with an explicit, audited override (`allowance_override`) |
| New: **Remove a dealer** | Reason required. The assignment is ended as `removed`, with its CRM history kept. The dealer is notified and loses access. If no dealer remains, the lead returns to *Needs review* |

Removed and reassigned assignments no longer count against the dealer's monthly lead allowance. Everything is audited (`lead.dealer_added`, `lead.assignment_removed`, `lead.reassigned`).

## 17. Notifications

| Event | Channel |
|---|---|
| New lead / staff assignment | Existing `LeadAssigned` (in-app, email, and WhatsApp for opted-in dealers from Phase 10) |
| Follow-up due, follow-up overdue, appointment upcoming, needs attention, lead moved away | `CrmReminder` (in-app; email when configured) |

No buyer contact details or note text are included in either.

## 18. Audit logging — COMPLETE AND VERIFIED

**Audit log:**
- pipeline changes (with won and lost);
- appointments scheduled, updated, completed, cancelled or no-show;
- staff add, remove and reassign;
- the allowance override.

**CRM timeline:** every dealer action, including notes (as "note added", no text), follow-ups, priority and recommendations.

Notes aren't audited separately, to avoid duplicate entries and to keep their text out of logs.

## 19. Database fixes found during QA — COMPLETE AND VERIFIED

**1. Timestamp columns overwritten by MariaDB.** With `explicit_defaults_for_timestamp=OFF` (your local MariaDB, and likely the live server), the first NOT NULL `TIMESTAMP` column of a table silently gets `ON UPDATE CURRENT_TIMESTAMP`. The database overwrote it, with its own Pakistan-time clock, whenever the row changed:

| Column | Affected since | Effect |
|---|---|---|
| `lead_assignments.assigned_at` | Phase 3 | Reset on every assignment update (skewed response times and lead-allowance months) |
| `contact_consents.granted_at` | Phase 7 | Reset when a consent was revoked (STOP) |
| `lead_follow_ups.due_at`, `lead_appointments.starts_at` | Phase 11 | Reset when a follow-up or appointment was completed or changed |

Migration `2026_11_02_000038_stop_auto_updating_timestamps`:
- **Columns and rows:** keeps every column and row, and gives these four an explicit default (no automatic update). It runs on MySQL/MariaDB only.
- **Restored values:**
  - `granted_at` and `assigned_at` from the row's creation time or its latest "assigned" timeline entry;
  - due and start times from the times recorded in the CRM timeline;
  - `last_activity_at` recomputed.
- **Verified on MariaDB:** updating the rows no longer changes these values.
- **Recurrence check:** `meezan:production-check` now reports any auto-updating timestamp column, so the live server will show it too.

The tests use SQLite, which never behaves this way, so this needed the real database to find.

**2. Times typed in Pakistan.** The application stores times in UTC. A dealer typing "11:00" means 11:00 in Pakistan, but it was stored as 11:00 UTC, so reminders would have fired 5 hours late and "today" was a UTC day.
- **Fix:** CRM times are now read and shown in `MEEZAN_TIMEZONE` (default `Asia/Karachi`) and stored in UTC.
- **Code:** a `LocalTime` helper and a `->localTime()` display helper; "today" is the Pakistan day.
- **Tested:** 11:00 typed → 06:00 UTC stored; the reminder fires at 06:00 UTC, not 05:59.
- **Local data:** the two QA records I had created were corrected.

**Not changed (pre-existing):** other pages (admin, billing, notifications) still show times in UTC, 5 hours behind Pakistan. Dates are unaffected. See §25.

## 20. Data preservation

| Record | Before Phase 11 | After |
|---|---|---|
| Users | 4 | 4 |
| Dealers (verified) | 2 (2) | 2 (2) |
| Properties (published) | 17 (16) | 17 (16) |
| Property images | 42 | 42 |
| Leads | 2 | 2 |
| Lead assignments | 2 | 2 (stages: 1 new, 1 viewing scheduled after QA) |
| Buyer requirements | 2 | 2 |
| Conversations / messages | 10 / 46 | 10 / 46 |
| Outbound WhatsApp messages | 18 | 18 |
| Subscriptions / invoices / payments | 1 / 1 / 2 | 1 / 1 / 2 |
| Areas / cities | 1,238 / 7 | 1,238 / 7 |
| Contact consents | 3 | 3 |
| New CRM records | — | 1 note, 1 follow-up (completed), 1 viewing, 1 recommendation, 8 timeline entries (QA on the demo dealer's lead) |

Nothing was deleted.
- **Corrected data:** only the timestamp values in §19.
- **Existing assignments:** given a starting stage.
- **Regression tests:** the AI assistant, WhatsApp, subscriptions and limits, payments, search, map and seven-city tests all still pass.

## 21. Files changed

**New**

| Area | Files |
|---|---|
| Migrations | `2026_11_02_000036_add_crm_fields_to_lead_assignments`, `…000037_create_crm_tables`, `…000038_stop_auto_updating_timestamps` |
| Enum | `PipelineStage` |
| Models | `LeadNote`, `LeadFollowUp`, `LeadAppointment`, `LeadRecommendation`, `LeadActivity` |
| Services | `Crm/DealerCrm`, `Crm/CrmStats` |
| Policy | `LeadAssignmentPolicy` |
| Notification | `CrmReminder` |
| Command | `CrmReminders` |
| Support | `LocalTime` |
| Controllers | `Dealer/CrmController`, `Admin/CrmOversightController` |
| Views | `dealer/crm/{dashboard, pipeline, index, show, agenda, _nav, _filters, _card, _agenda-follow-up}`, `admin/crm` |
| Tests | `tests/Feature/DealerCrmTest.php` (17) |
| Report | this file |

**Changed**

| File | Change |
|---|---|
| `Models/LeadAssignment.php` | CRM fields, relations, scopes, `headline()`, `buyerName()` |
| `Services/Leads/LeadRouter.php` | Eligibility, `addDealer`, `removeAssignment`, history on reassign, `last_activity_at` |
| `Services/Billing/Entitlements.php` | Removed assignments don't count |
| `Dealer/LeadController` | Quick form moves the dealer's own stage |
| `Admin/LeadController` | Add/remove dealer; per-dealer CRM counts |
| Views | `admin/leads/show`, `dealer/leads`, `components/layouts/dashboard` (nav: Lead CRM with overdue badge; Lead Oversight) |
| `resources/js/app.js` | Kanban drag-and-drop |
| `config/meezan.php` | `crm` section, `timezone` |
| `AppServiceProvider` | `localTime` macro |
| `ProductionCheck` | Auto-updating column check |
| `routes/web.php` | 18 routes |
| `routes/console.php` | Reminders every 5 minutes |

## 22. Routes added (18)

| Area | Routes |
|---|---|
| Dealer pages | `dealer.crm`, `dealer.crm.pipeline`, `dealer.crm.leads`, `dealer.crm.agenda`, `dealer.crm.leads.show` |
| Dealer actions | `dealer.crm.leads.stage`, `dealer.crm.leads.priority`, `dealer.crm.notes.store`/`update`, `dealer.crm.follow-ups.store`/`update`, `dealer.crm.appointments.store`/`update`, `dealer.crm.recommendations.store`/`update` |
| Admin | `admin.crm`, `admin.leads.assignments.store`, `admin.leads.assignments.destroy` |

## 23. Automated test results

| Command | Result |
|---|---|
| `php artisan test` | **455 passed, 2,419 assertions, 0 failed** |
| `vendor/bin/pint --test` | passed |
| `npm run build` | OK |
| `composer audit` | no advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `php artisan route:cache` | cached (then cleared) |

**New tests (17):**
- **Multi-dealer:** three dealers on one lead keep independent stages, notes and follow-ups; the lead status is untouched; B can't open, change or read A's records (mandatory test).
- **Pipeline:** transitions, required reasons, reopen, milestones and audit; won details; the quick form moves only the dealer's own stage; drag-and-drop JSON and refusals.
- **Records:** note privacy and author-only edit (dealer, buyer and admin blocked; admin sees counts only); follow-up lifecycle with due and overdue reminders sent once and isolation; appointments create, reschedule, complete and cancel with authorization and a reminder; recommendations (live only, once each, unauthorised blocked).
- **Automation and figures:** stale flagging without closing; analytics per dealer on shared leads; admin "at least one dealer won".
- **Staff and access:** add or remove a dealer with eligibility, override, maximum, history and lost access; guests, buyers and non-admins blocked.
- **Pages and times:** all pages render and filters (city, stage, overdue) work in the database; listing enquiry status sync; Pakistan-time entry stored as UTC and reminded on time; assigned, due and start times unchanged by later updates.

**Existing tests:** none weakened or edited.

## 24. Manual test results (demo dealer, local database)

| Check | Result |
|---|---|
| CRM overview | Real counts (2 new leads; nothing invented); no console errors |
| Pipeline | 2 cards in New. **Dragged** lead 1 to Contacted (saved, board refreshed); dragging Contacted → Won refused with a message |
| Workspace | Added a private note; created a follow-up and completed it; recommended a real listing from the matches (MP-000016); scheduled a viewing for it, which moved the lead to *Viewing scheduled*; set priority to Urgent. All appeared in the timeline |
| Times | After the §19 fixes: assigned 11:32 am, actions 12:14 pm, follow-up 3 Nov 11:00 am, viewing 4:00 pm (all Pakistan time, consistent) |
| Responsive | Overview, pipeline, list, agenda, lead page and the leads page at 375, 768 and 1366 px: no horizontal overflow, no errors |
| Laravel log | No new errors |

Not checked by hand:
- **Multi-dealer independence:** that would mean creating extra dealer accounts in your database; it's covered by the automated test.
- **Admin oversight and add/remove dealer:** these need your admin sign-in; automated tests render them with data.

## 25. Known limitations

1. **Times elsewhere:** outside the CRM, pages show times in UTC (5 hours behind Pakistan); dates are correct. A site-wide fix needs a decision: either show all times in Pakistan time (display-only, recommended), or change `APP_TIMEZONE` along with a data migration.
2. **Single-user agencies:** notes are private to the dealer account. There are no team members or shared agency notes yet (the Phase 9 team limit is stored but not used).
3. **No calendar sync:** no iCal or Google Calendar export for appointments.
4. **No CRM messaging to buyers:** "WhatsApp follow-up" is a task type and a link; no automated WhatsApp to buyers comes from the CRM, by design.
5. **Invalid/duplicate only flags for staff:** a dealer's own "Invalid"/"Duplicate" is shown to staff for review. It doesn't free the dealer's lead allowance; only staff invalidation does, to prevent abuse.
6. **Board size and scale:** the Kanban shows 30 cards per column, with the rest in the list. Analytics are computed on request with grouped queries (fine at current scale; cache if dealer numbers grow large).
7. **Reminders need the scheduler** (`schedule:run` via cron) and a queue worker for delivery.

## 26. Recommended next phase

1. **Site-wide local time display** (Pakistan time) for admin, billing and notifications.
2. **Agency teams:** multiple users per agency, with shared or assigned leads and notes.
3. **Email delivery and optional WhatsApp delivery** of CRM reminders to dealers (approved template).
4. **Calendar export (.ics) for viewings.**
5. **Admin browser QA** of Lead Oversight and add/remove dealer with your admin account.

---

### Final figures

| Item | Value |
|---|---|
| Tests passed | **455** |
| Assertions | **2,419** |
| Failures | **0** |
| New database tables | **5** (`lead_notes`, `lead_follow_ups`, `lead_appointments`, `lead_recommendations`, `lead_activities`) + 15 columns on `lead_assignments` |
| Schema fix | 4 timestamp columns no longer auto-update (values restored) |
| New routes | **18** |
| New scheduled commands | **1** (`meezan:crm-reminders`, every 5 minutes) |
| Existing data before → after | users 4→4 · dealers 2→2 · properties 17→17 · leads 2→2 · assignments 2→2 · conversations 10→10 · messages 46→46 · subscriptions 1→1 · payments 2→2 · areas 1,238→1,238 |
