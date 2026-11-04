# Meezan Investment Index: Phase 1 Report
## Scoring engine for five development categories, from verified evidence only

Date: 4 November 2026. Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB 10.4).

Nothing was committed, pushed or deployed. No `migrate:fresh` or `db:wipe` was run, nothing was deleted, and `.env` was not changed.

**Backup before changes:** `C:\xampp\backups\MeezanProperty-before-investment-index-phase1-2026-11-04\meezanproperty.sql`, 66 of 66 tables, 181 MB.

---

## 1. What the inspection found

| Item | Finding |
|---|---|
| Existing ranking engine (`app/Services/Investment`, about 2,400 lines) | Ranks **areas and projects** in 11 "segments", including plots, houses, apartments, shops, offices and commercial plots, from market price records. An admin publishes each run by hand. |
| Real data in MySQL | **0 real projects, 0 real market records, 0 real sources.** All 420 projects and 7,187 observations are the illustrative demo dataset. |
| Published rankings | Demo calculation #7 only, shown publicly with a DEMO notice. |
| Project categories in the database | Residential society 77, apartment complex 63, shopping mall 63, business tower 63, commercial plaza 56, commercial land 56, mixed use 42 (all demo). |
| Official legal sources (web research) | LDA, RDA, CDA and SBCA publish lists of approved and illegal schemes, as web pages, PDFs and notices. The formats differ and change often ([LDA list report](https://www.zameen.com/news/list-of-lda-approved-housing-schemes-in-lahore-district.html), [RDA illegal societies](https://tribune.com.pk/story/2595293/rda-declares-294-housing-societies-illegal), [CDA list](https://propakistani.pk/2023/07/31/list-of-all-illegal-housing-societies-in-islamabad/), [SBCA illegal projects](https://propakistani.pk/proproperty/sbca-warns-karachiites-against-investing-in-these-illegal-projects/)). |
| Occupancy, rent rolls, footfall, tenant quality, structural safety, finances | **No official or licensed public source was found.** This decides what Phase 2 can automate (section 7). |

**Conclusion.** The old engine's model doesn't fit the new scope: price records for areas, a segment for each individual property type, and manual publishing. I built the Index as a **separate module**. The old engine and the marketplace are untouched apart from the demo default (section 4).

## 2. What Phase 1 built

```
verified evidence + legal records + verified transaction/valuation prices
        │   (each record: source, source URL, collection time; verified or not)
        ▼
 IndexEngine ── for every real, published project in the five categories
   ├─ LegalStatus       excluded | approved | partially approved | pending | not verified
   ├─ FactorScorer      one 0-100 score per weighted factor, or "missing" with the reason
   ├─ DataConfidence    separate 0-100 score: coverage, recency, authority, independence, corroboration
   ├─ RiskClassifier    low | moderate | elevated | high | excluded, with each finding
   ├─ ranks             five category leaderboards + the Overall Meezan Investment Index
   └─ ChangeHistory     why every project moved, entered or left
        ▼
 published automatically (the previous run is kept); "unchanged" when nothing moved
```

**The five categories and weights** are exactly as in the brief. They sum to 100 and are enforced by a test.

| Category | Project categories included |
|---|---|
| Housing Societies | residential society |
| Commercial Plazas | commercial plaza |
| Apartment Buildings | apartment complex |
| Shopping Malls | shopping mall |
| Business Towers | business tower, office building |

**Out of scope, never assessed:**
- individual plots, houses, shops, apartments and listings, which stay in the marketplace;
- commercial land and mixed-use projects;
- every demo record;
- unpublished projects.

**Weights are configurable.**
- **Where:** methodology versions in `investment_index_methodologies`. Version 1 is created from `config/investment_index.php`.
- **Versioning:** a change is a new version, never an edit, so every past run can be explained.
- **Validation:** whole numbers 0–100, summing to 100 per category. Legal approval can never be set to 0.

**How each factor is scored**, only from verified records of accepted source types:

| Factor | Basis | Accepted sources |
|---|---|---|
| Legal approval | Legal records (section 3) | Official records only |
| Development progress, occupancy | Verified percentage | Official record, site inspection, audited document (occupancy also licensed data) |
| Rental income | Verified gross yield, or a verified rent **lease** paired with a verified **transaction/valuation** price within 12 months, mapped from 2–10% onto 0–100 | Audited document, verified rental, licensed data, official |
| Historical market performance | Annualised change between verified **transaction or valuation** prices at least 9 months apart, mapped from −10% to +25% a year. **Asking prices are never used** | Verified transactions |
| Market demand, location, footfall, tenant quality or mix, maintenance, structural safety, operational performance, financial stability | Verified level (for example good, certified, strong), each with a fixed score table | Per factor, in config |
| Utilities | Average of each utility's latest status (electricity, gas, water, sewerage) | Official, site inspection |
| Developer track record | Verified delivery record of the developer's **other** projects (on time, late, delayed, stalled, abandoned) | Official, site inspection, audited |

**The score** is the weighted average of the verified factors. It's reduced by up to 20% for the share of weight without evidence, so thin evidence never beats full evidence.

## 3. Legal safety and status rules

A project's legal status comes only from **verified records from official sources**, read oldest to newest:

| Situation | Result |
|---|---|
| Cancellation, illegal-scheme listing, regulatory restriction, or any record of severity "serious", not cleared later by the same authority | **Excluded.** No score and no rank, shown as excluded with the authority, reference and date. |
| Verified "unsafe" structural finding | **Excluded.** |
| No current verified approval (none on file, unverified or expired) | **"Insufficient Verified Data — legal status has not been verified."** Never shown as approved. |
| Approval pending | Scored but **not ranked**, with a "pending approval" risk. |
| Approved or partially approved | Can be ranked. Each open dispute or warning deducts from the legal score (minor −10, material −30) and adds risk. |
| A later regularisation or approval from the same authority | Clears the earlier problem. |

**Project statuses:**
- **Ranked:** meets every requirement.
- **Not eligible:** approval pending.
- **Insufficient Verified Data:**
  - legal status not verified;
  - verified evidence below 60% of the weight, with the missing factors listed;
  - or a Data Confidence Score below 35.
- **Excluded:** a serious legal or safety finding, as above.

**Verification rules on every record.** A record can't be saved as verified unless it has all of the following:
- a real, active source of a type the factor accepts;
- a valid http(s) source URL;
- a collection time;
- a real (non-demo) project.

Demo sources and asking-price sources can never verify anything.

## 4. Overall Index, automation, history, and demo data

- **Overall Meezan Investment Index.** In each category with at least 3 ranked projects, a project's mid-rank percentile is its normalized score. The index is that score × (0.5 + 0.5 × confidence/100). Unlike assets are compared only by their standing in their own category, never by raw prices or yields. Smaller categories are still ranked, but stay out of the Overall Index until they have enough peers.
- **Automatic operation.**
  - **Triggers:** saving or unverifying verified evidence, a legal record, a verified project market record, or an in-scope project change.
  - **Job:** queues one delayed, de-duplicated `RecalculateInvestmentIndex` job that calculates and **publishes with no human step**.
  - **Safety net:** a daily 04:30 run catches anything missed and ages out old evidence.
  - **Unchanged runs:** a run whose results equal the published ones is recorded as "unchanged".
- **Change history.** Every entry, exit, move, rescoring or status change is recorded against the previous run. The reasons are specific, for example "Development progress: 50 (50%) → 100 (100%), verified record of 28 Oct 2026", a legal status change, a confidence change, or "other projects in the category changed".
- **No paid ranking.** The engine never reads featured flags, packages or subscriptions; a test proves that featuring a project doesn't change the order.
- **No AI-generated data.** The engine makes no AI calls. A missing factor stays missing.
- **Demo data.**
  - The Index never reads demo records.
  - Demo mode for the old rankings pages is now **off by default** (`INVESTMENT_DEMO_MODE` defaults to false). Those pages therefore show "No rankings are published yet" instead of the demo snapshot.
  - The 420 demo projects remain in the database, flagged, and admins can still switch the preview on.
  - `.env` doesn't set this value, so it wasn't touched.

## 5. Files

**New:**
- **Configuration:** `config/investment_index.php`, which holds the categories, weights, factor rubrics, accepted sources, legal rules, source authority and parameters.
- **Migration:** `database/migrations/2026_11_04_000051_create_investment_index_tables.php`. It adds six tables (evidence, legal records, methodologies, runs, scores, changes) and changes no existing table.
- **Models:** `app/Models/InvestmentEvidence.php`, `InvestmentLegalRecord.php`, `InvestmentIndexMethodology.php`, `InvestmentIndexRun.php`, `InvestmentIndexScore.php`, `InvestmentIndexChange.php`, plus `app/Models/Concerns/HasEvidenceProvenance.php`.
- **Services:** `app/Services/InvestmentIndex/` — `IndexEngine`, `ProjectAssessor`, `FactorScorer`, `LegalStatus`, `DataConfidence`, `RiskClassifier`, `ChangeHistory`.
- **Automation:**
  - `app/Jobs/RecalculateInvestmentIndex.php`;
  - `app/Providers/InvestmentIndexServiceProvider.php`;
  - the command `app/Console/Commands/InvestmentIndex.php`, run as `php artisan meezan:investment-index run|status`.
- **Tests:** `tests/Feature/InvestmentIndexEngineTest.php`.

**Changed:**
- `config/meezan.php`: demo mode off by default; three evidence source types (site inspection, audited document, licensed data).
- `.env.example`: the same default.
- `bootstrap/providers.php`, `routes/console.php`: daily run.
- `tests/Feature/InvestmentDemoDataTest.php`, `GlobalUiFixesTest.php`: these test the demo preview, so they now switch it on explicitly, as an admin would. Their assertions are unchanged.

## 6. Testing

| Check | Result |
|---|---|
| New Investment Index tests (15) | Passed. They cover the five categories and weights; scope (demo, unpublished, land and mixed-use never assessed); the factor breakdown; unverified legal never approved; exclusion and clearance; pending not ranked; Insufficient Verified Data; asking prices and unsupported yields; provenance guards; no paid placement; Overall Index; automatic publishing; change history; the unsafe exclusion and risk classes; demo off by default |
| Full suite | **805 passed, 4,486 assertions**, 0 failed |
| Pint | Passed |
| MariaDB migration | Applied. The first attempt failed partway right after MariaDB was restarted (see below); the second applied cleanly |
| MariaDB engine run, real data | Run #1 published: 0 projects assessed, which is correct because every project is demo |
| MariaDB engine run, fixtures (rolled back) | 3 societies ranked 1–3 with Overall Index ranks; price history "+10% a year" from verified transactions; developer record "2 projects on record"; 3 change records; second run "unchanged". After rollback: 420 projects, 0 evidence, unchanged |
| Existing data on MariaDB | 420 projects, 7,187 observations, 18 listings, 4 users, 1,238 areas, all unchanged |

**MariaDB note.** MariaDB stopped during this phase. Its log shows no crash or shutdown, so it was probably closed with XAMPP. I started it again with XAMPP's own settings. The first migration attempt then failed partway: MariaDB can't roll back created tables, so two of the new tables were left behind. I checked they were empty, removed only those two, and the migration then applied cleanly. I didn't capture the first error.

## 7. What Phase 1 does not do yet, and the decision Phase 2 needs

**Not yet built:**

| Phase | Work |
|---|---|
| **Phase 2** — authorized data collection | A source registry, where an admin approves each source's access once. Collectors run on a schedule, respect each site's terms and robots rules, and store the source URL, timestamp and content hash. Normalization and project matching, automatic verification rules, and an exceptions queue for legal, security or access problems only. First collectors: the LDA, RDA, CDA and SBCA approved and illegal scheme lists. |
| **Phase 3** — public site | The five leaderboards; the Overall Index; project profiles with every field in the brief, "Insufficient Verified Data" where evidence is missing, sources and change reasons; a methodology page and an admin weights editor. Old segment pages for out-of-scope types redirect. The advisor, AI agents and map move to the Index. |
| **Phase 4** — QA | QA on MariaDB, desktop and mobile; documentation. |

**What visitors will see.** No real project or verified evidence exists yet. Until Phase 2 collects legal records and other evidence, every leaderboard will correctly show "Insufficient Verified Data".

**Decision needed before Phase 2.** Official sources can automate the legal-status factor, about 20–25% of the weight. Occupancy, rental income, footfall, tenant quality, structural safety, maintenance and financial stability have no public authorized source in Pakistan. Without one, most projects stay below the 60% evidence threshold. There are two options:
- **A. Owner-submitted documents, checked by staff.** Developers or building managers upload rent rolls, audited accounts, safety certificates and occupancy statements, and staff confirm each document once. Ranking stays fully automatic: the human step is verifying the source, not calculating scores.
- **B. Licensed data providers only.** Fully automatic, but it needs commercial data agreements. Until then most factors stay empty.

## 8. Remaining issues

1. The old rankings pages, the AI advisor and the agents still use the old engine. With demo mode off they show no rankings. Phase 3 moves them to the Index.
2. Collecting from official sites depends on each authority's terms. A source is activated only after an admin confirms access is permitted (a source-access review, which the brief allows).
3. The minimum-evidence threshold (60% of the weight), the minimum confidence (35) and the Overall Index minimum of 3 peers are starting values. They're adjustable in a new methodology version.
4. **Palette:** kept as the Brand Style Guide colours (#4B2E83 / #D4AF37).
