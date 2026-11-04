# Meezan Investment Index: Phase 2 Report
## Collecting official records automatically, and owner documents checked by staff

Date: 4 November 2026. Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB 10.4).

Nothing was committed, pushed or deployed. No `migrate:fresh` or `db:wipe` was run, nothing was deleted, and `.env` was not changed. Your decision after Phase 1 was **owner documents checked by staff**: owner-submitted documents count only after a staff check, and ranking stays automatic.

**Backup before changes:** `C:\xampp\backups\MeezanProperty-before-investment-index-phase2-2026-11-04\meezanproperty.sql`, 72 of 72 tables, 181 MB.

---

## 1. What was inspected

**LDA site.** `lda.gop.pk` serves its lists as data embedded in each page (Inertia). robots.txt sets no restrictions. Checked on 4 Nov 2026:

| List | URL | Entries | Fields used |
|---|---|---:|---|
| Approved private housing schemes | `/approved-schemes` | 401 (one page) | name, location, approval stage (free text), approval date |
| Illegal private housing schemes | `/illegal-schemes` | 382 (32 pages) | name, location, status "active", directorate, listing date |
| Schemes developed by LDA | `/regular-schemes` | 19 (2 pages) | name |

**The approved list's stages are free text.** There are about 40 spellings, for example "Finally Appoved", "Technical Approval Granted dated 13-06-2020 Final Approval is pending", "Approved from TMA". Five entries are blank, and one holds an address instead of a stage.

**Other authorities.** RDA, CDA and SBCA publish similar lists, but their formats weren't verified in this phase. A configurable HTML-table collector is ready for them once each format is checked.

**Existing patterns reused:**
- the private-disk document upload used for dealer verification (random file names, type read from the content, no caching, `nosniff`);
- the admin layout, the audit log, and the existing `market_sources` table.

## 2. What Phase 2 built

```
official list (authorized source) ──► ListReader ──► registry entries (first/last seen, row hash)
        │ robots.txt, bot name, 1 s between pages, size limit                 │
        │                                                     match to a real project in the same city
        │                                          (one near-exact name → automatic; less certain → staff)
        ▼                                                                      ▼
exceptions queue ◄── access/format problems, unreadable stages,   verified legal record (official source,
                     uncertain matches, removals, conflicts        row URL, collection time, row as excerpt)
                                                                               │
owner documents ──► staff check once ──► verified evidence / legal record ─────┤
                                                                               ▼
                                                Investment Index recalculates and publishes (Phase 1)
```

### Automatic collection

**Authorization comes first.**
- **Sources are inactive when added.** "Add built-in sources", or `php artisan meezan:investment-collect --setup`, adds the three LDA lists inactive.
- **The review is done once.** An admin records the access basis (public record, licence, agreement), the terms URL, and what was checked, and confirms. Only then is the source read.
- **Switching off:** each source has a switch-off control.

**Polite reading:**
- requests identify themselves as `MeezanPropertyBot/1.0 (+https://meezanproperty.com/contact)`;
- robots.txt is obeyed, and an unreadable robots.txt (server error) means "don't read";
- requests wait 1 second between pages, with a 30-second timeout and an 8 MB limit.

**Schedule:**
- daily at 03:45, before the 04:30 Index run;
- from the admin, "Collect now" queues a job, because the illegal list takes about 40 seconds;
- `--dry-run` reads a source and stores nothing.

**Reading stages conservatively:**
- "Final…" or "Approved…" counts as **approved**.
- "Technical…", or anything "pending" or "not issued", counts as **partially approved**.
- Wording it can't read is **not recorded**. Staff are asked instead, so a stage is never assumed to be an approval.
- Dates are read in several formats. A bare year isn't treated as a date.

**Matching:**
- **Name comparison:** names are normalized, which repairs garbled characters, drops words such as "Housing Scheme" or "Cooperative Society", and reads "Phase-II" or "Ph-II" as "phase 2".
- **Phases must agree:** a Phase II listing never matches a Phase I project.
- **Automatic match:** made only for **one** near-exact name in the same city.
- **Uncertain cases:** go to staff with the candidate projects.
- **Later projects:** a project added or renamed later is matched against entries already collected.
- **Staff decisions:** never overwritten.

**Legal records from matches:**
- **Record types:**
  - Approved list: an approval record, with the authority's date.
  - Illegal list: an `illegal_listing` record of severity serious. Under the Phase 1 rules the project is **excluded** from recommendations.
  - LDA-developed schemes: an approval record.
- **What each record keeps:** the stable row URL (for example `https://lda.gop.pk/approved-schemes#58`), the collection time, a hash of the row, and the row itself.
- **History:** a change on the list creates a new record, so history is kept.
- **Recalculation:** the Index recalculates automatically after new records.

**Safeguards:**

| Situation | What happens |
|---|---|
| A run reads under 50% of the previous count, or nothing | The run is marked partial or failed. Nothing is marked removed, and staff are told. |
| The page format changes | The run fails, and a "format changed" exception opens. |
| A matched scheme leaves a list | Staff are asked. Leaving a list is never treated as a clearance or a cancellation, and staff can withdraw the record. |
| A project appears on both the approved and the illegal list | A "conflicting official records" exception opens. The project stays excluded until an official clearance is on file. |

### Owner documents, checked by staff

**Who submits, and where.** Owners, developers and managers sign in and use "Submit evidence" on a published development's page (`/projects/{project}/evidence`). They state one fact and attach up to 3 PDF or image files of 5 MB each. Files go to the private disk, and only staff can open them.

**What they can submit:**
- **Facts:** development progress, occupancy, rental income, utilities, footfall, tenant quality or mix, maintenance, operational performance, financial stability, delivery status, operational status;
- **Approvals:** approval or regularisation letters.
- **Rental income:** the submitter enters the annual rent and the value of the space. The yield is calculated from those two figures, never typed in.
- **Structural safety:** needs a certificate from an independent issuer.
- **Location:** can't be self-certified by an owner.

**Nothing counts until checked.** A staff member opens the documents and verifies or rejects; a rejection needs a reason, which the submitter sees.

**On verification**, the fact becomes a verified record:
- **Source:** the submitting organisation, recorded as an owner-document source, or the named independent issuer, recorded as an audited-document source. An approval letter is recorded under its authority's name.
- **Weight:** owner statements count for less (authority 0.6) than independent or official records.
- **Traceability:** the record links to the submission's staff page.

**Legal precedence.** An owner document can't outweigh a serious finding on an official list. It can only clear one if it's an approval from the same authority dated after the listing.

### Admin: Investment Index

The new screens are in the admin menu, with a badge counting open exceptions plus documents awaiting a check:

| Screen | Purpose |
|---|---|
| Overview | Published run, verified evidence, registry and match counts, what's waiting for staff, recent collections |
| Official sources | The access review, collect now, switch off, collection history |
| Official registry | Every entry with its list, status, dates and match; staff match or reject an entry |
| Exceptions | Resolve or dismiss with a reason; option to withdraw a legal record |
| Owner documents | Pending, verified and rejected submissions; the check page with documents and figures |

Every action is written to the audit log, including viewing a document.

## 3. Problem found and fixed: database timestamps

**The problem.** MariaDB and MySQL running without `explicit_defaults_for_timestamp`, which is this server's setting, give the first non-null `TIMESTAMP` column in a table an automatic `ON UPDATE CURRENT_TIMESTAMP`. Phase 1's `collected_at` had that. Verifying or rejecting a record would have **overwritten when it was collected**, silently corrupting provenance. SQLite doesn't do this, so tests couldn't have caught it.

**The fix:**
- `collected_at`, `first_seen_at` and `last_seen_at` are now `DATETIME`.
- The Phase 2 migration converts the existing columns.
- No column in the whole database has `ON UPDATE` now.
- A test confirms the collection time stays fixed when a record is updated.

**The same issue caused this phase's first migration failure.** The failure left a partial table and new columns behind. I checked they were empty and removed only those, and the migration then applied cleanly. Phase 1's first migration failure was probably the same issue: it also stopped partway through, after the first of these timestamp tables, though I didn't capture that error.

## 4. Files

**New:**
- **Migration:** `database/migrations/2026_11_04_000052_add_investment_index_collection.php`. It adds 9 nullable columns on `market_sources` and four tables: collection runs, registry entries, evidence submissions, exceptions.
- **Models:** `app/Models/InvestmentCollectionRun.php`, `InvestmentRegistryEntry.php`, `InvestmentException.php`, `InvestmentEvidenceSubmission.php`.
- **Services:**
  - `app/Services/InvestmentIndex/Collection/` — `Fetcher` (robots.txt, politeness), `ListReader` (Inertia and HTML-table lists), `OfficialStatus` (stages, dates), `SchemeNames` (normalizing and matching), `Registry` (matching and legal records), `CollectionService`, `CollectionError`;
  - `app/Services/InvestmentIndex/EvidenceSubmissions.php`.
- **Jobs and command:** `app/Jobs/CollectInvestmentSource.php`, `app/Console/Commands/InvestmentCollect.php`.
- **Controllers:** `app/Http/Controllers/InvestmentEvidenceController.php`, `app/Http/Controllers/Admin/InvestmentIndexController.php`.
- **Views:** `resources/views/investment-index/submit.blade.php`, and `resources/views/admin/investment-index/` (overview, sources, registry, exceptions, submissions, submission, partials).
- **Tests:** `tests/Feature/InvestmentIndexCollectionTest.php`.

**Changed:**
- `config/investment_index.php`: collection settings, LDA presets, and the owner-document source type for the factors an owner can document.
- `config/meezan.php`: source type "Owner-submitted document (staff-verified)".
- `app/Models/MarketSource.php`: `isCollectable()`, `collectorPreset()`.
- Phase 1 migration 000051: `collected_at` is now `DATETIME`.
- `app/Providers/InvestmentIndexServiceProvider.php`: new or renamed projects are matched.
- `routes/web.php`, `routes/console.php`: daily collection at 03:45.
- `resources/views/components/layouts/dashboard.blade.php`: admin menu entry.
- `resources/views/projects/show.blade.php`: an "Own or manage this development? Submit evidence" panel on eligible developments.

## 5. Testing

| Check | Result |
|---|---|
| New Phase 2 tests (13) | Passed. They cover: conservative stage reading and dates; phase-safe name matching and character repair; collection into verified legal records and the Index; reading every page of the illegal list and excluding the project; the conflict exception; no reading without authorization, when robots.txt forbids, or when robots.txt is unreadable; a shrunken list or changed page treated as an exception, never removals; a scheme leaving a list goes to staff, who can withdraw the record; uncertain matches and unreadable stages go to staff; later projects match automatically; owner documents count only after a staff check (private files, computed yield, owner-document source); limits (independent safety certificate, no self-certified location, the declaration, demo projects refused, rejection with a reason); the admin screens and the access review; collection times fixed |
| Phase 1 and Phase 2 tests together | 28 passed, 226 assertions |
| Full suite | **818 passed, 4,621 assertions**, 0 failed; Pint passed on the whole project |
| Live dry run of the three LDA lists | 401, 382 (all 32 pages) and 19 entries read; 6 unrecognised stages found |
| Live collection on MariaDB (in a transaction, rolled back) | 802 entries stored; 6 exceptions for unreadable stages; a real project named like an approved scheme was matched on creation, with a verified LDA approval dated 31 Mar 2011 linked to its row; the Index showed its legal status as approved and "Insufficient Verified Data" (25% of the weight); counts after rollback unchanged |
| Admin screens on MariaDB (in a transaction, rolled back) | All 5 rendered; the JSON lookup for withdrawing a record works |
| Browser | Not checked visually: the admin screens need your admin sign-in, which I don't use, and the public form needs a real published project, which doesn't exist yet. Both render in the automated tests |

## 6. What you need to do

1. **Authorize the LDA sources.** Open Admin → Investment Index → Official sources, click "Add built-in sources", and complete the access review for each LDA list. The authorization is your decision. I found no terms of use prohibiting automated reading, and robots.txt sets no restrictions. Please confirm on the LDA site yourself before you authorize.
2. **Add real projects.** All 420 current projects are demo data and are never matched or ranked. Real developments added in the admin are matched against the LDA lists automatically.
3. **Check documents.** Pending documents appear in the menu badge.

## 7. Remaining issues

1. Rankings will still mostly show "Insufficient Verified Data" until real projects have evidence beyond legal status. Legal records cover 20–25% of the weight, and the threshold is 60%.
2. Only LDA presets exist. RDA, CDA and SBCA formats need checking before a preset is added. The HTML-table collector can be configured per source.
3. Matching covers housing societies only. Official scheme lists describe housing schemes; approvals for plazas, malls and towers come through owner-submitted approval letters for now.
4. Public leaderboards, project investment profiles, a methodology page and retiring the old segment pages are Phase 3.
5. **Palette:** kept as the Brand Style Guide colours (#4B2E83 / #D4AF37).
