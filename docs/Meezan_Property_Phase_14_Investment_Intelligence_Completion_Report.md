# Meezan Property — Phase 14 Completion Report
## Property & Project Investment Intelligence, Transparent Ranking & Market Analytics

Date: 3 November 2026 · Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB) · No Git commits, no GitHub push, no deployment, no destructive operations on existing data.

Status key: **COMPLETE AND VERIFIED** · **COMPLETE BUT CONFIGURATION-BLOCKED** · **PARTIALLY IMPLEMENTED** · **NOT IMPLEMENTED**

---

## 1. Executive summary

Meezan Property now has an evidence-based investment intelligence system for all seven launch cities. It has three parts.

**1. Market Intelligence & Data Centre (admin)**
- A registry of data sources.
- Sale-price and rent observations: asking, transaction and valuation prices kept apart, each with range, sample size, date, method and source.
- Market indicators: demand, supply, liquidity, development progress, infrastructure, legal verification, developer status and growth drivers.
- Manual entry and CSV import, with validation and duplicate detection.
- A review workflow (draft → submitted → reviewed → verified, with rejection and correction) and an audit trail.

**2. Calculation engine**
- Versioned, admin-managed weights for seven ranking categories (each sums to 100).
- Historical change formulas applied only to comparable series.
- Gross (and, when costs are known, net) rental yield.
- Missing-data handling: missing factors are never scored neutrally, and below 70% coverage there is no ranking.
- A separate data-confidence score with plain-language reasons.
- An evidence-based risk classification. Anything not fully legally verified is never shown as Low risk.
- Growth scenarios, only with at least two years of comparable history.
- Every run records its inputs and checksum. Runs are never published automatically. Published snapshots are immutable and kept as history.

**3. Public Investment Insights**
- A ranking page with goal, city, asset, level, type, risk, confidence, period, budget, yield and growth filters.
- A full analysis page.
- A comparison page that only names winners when the data is comparable.
- A public methodology page.
- A rental yield calculator.
- Grounded insight text.
- The required disclaimer on every page.

**No market data was invented.** The local database has 0 market records, so the public pages show "No published rankings yet". I verified everything with automated test fixtures, plus a throwaway QA database of clearly labelled fixture data, which has since been deleted.

**Results:** 526 tests pass (2,939 assertions, 0 failures) on the final run, including 21 new Phase 14 tests. Pint, the build, both audits and the three cache commands are clean. All existing data counts are unchanged.

## 2. Existing architecture audit

| Area | Found | Used for |
|---|---|---|
| Location hierarchy | `cities` (7, with country), `areas` (1,238; type, parent, authority approval status, approval source URL, checked date), `area_aliases` | Entities; legal-verification fallback from `areas.approval_status` |
| Projects | `projects` (name, developer, city, area, status upcoming / under construction / completed, approval note); 0 rows locally | Project rankings; emerging eligibility |
| Property pricing | `properties.price`, `property_price_changes` (0 rows): listing asking prices only | Platform listing counts (labelled as platform-only), never as market data |
| Investment concepts | Only a stored "investment" reason in AI matching; no rankings | — |
| AI | Rules parser (default) and an OpenAI-compatible client (`services.openai`), rules fallback | Insight generator follows the same pattern |
| Admin / auth | `auth` + `role:admin` route group, `AuditLogger`, dashboard layout | Data Centre, every write audited |
| Charts | `x-line-chart` SVG component | Historical price chart |
| Scheduler | `routes/console.php` schedules | Weekly recalculation |

**Missing before this phase:** price and rent history, market indicators, sources, scoring, rankings and any investment pages.

## 3. Database architecture — **COMPLETE AND VERIFIED**

Two additive migrations: `2026_11_03_000041_create_market_intelligence_tables` and `…000042_seed_investment_methodology_v1`.

| Table | Purpose |
|---|---|
| `market_sources` | Source registry (type, URL, licence, reliability, coverage, publisher for independence, last checked, active) |
| `market_observations` | Sale or rent; price basis (asking / transaction / valuation); city / area / project; asset class; property type; size and normalised sq ft; PKR; average, min, max; observation date; sample size; source; method; notes; workflow fields; fingerprint |
| `market_indicators` | Indicator kind; level or %; date; source; method; workflow fields; fingerprint |
| `market_import_batches` | CSV import results (rows, imported, duplicates, failed, row errors) |
| `investment_methodologies` | Version, weights per category, parameters, status (draft / active / retired) |
| `investment_calculations` | Run status, trigger, methodology version, input ids and checksum, summary, error, publisher |
| `investment_rankings` | Snapshot rows: category, entity, asset class, status, rank, score, confidence, risk, factor breakdown, metrics |

**Design notes**
- **Country-aware:** each record carries `country` (Pakistan), and `config('meezan.investment.markets')` lists UAE, Saudi Arabia, Qatar and the UK with their currency, units and legal checks, marked **planned**, not active.
- **Indexes:** on status, city, kind, date and entity.
- **Foreign keys:** to `areas` and `projects` restrict deletion, so market data can't orphan locations.

## 4. Data Centre — **COMPLETE AND VERIFIED**

`/admin/market` (admin only):
- **Overview:** counts per status, verified records per city, active methodology, latest publication, recent imports.
- **Observations and indicators:** filter, manual entry with strict validation, correction (drafts and rejected records only), and workflow buttons. Rejection requires a reason.
- **CSV import:** template download (header only); UTF-8; at most 2 MB and 5,000 rows. Rows go through the same validation and become drafts. Duplicates are skipped (within the file and against existing records); row errors are reported.

**Validation rules (all tested):**
- An asking-price source cannot supply transaction prices.
- The asset class must match the property type.
- Areas and projects must belong to the chosen city.
- No future dates; min ≤ average ≤ max.
- PKR only for Pakistan.
- Indicator levels must be from the defined list.
- A method description is required.

## 5. Data-source registry — **COMPLETE AND VERIFIED**

- **Fields:** name, type (verified transactions, public asking prices, authorized report, official record, verified rental observations, Meezan research), URL, licence / permission, reliability, coverage, publisher, last checked, active.
- **Reliability and independence:** reliability is set by staff, never implied by public availability. Publisher groups sources for the independence measure.
- **Use on public pages:** source names, types, URLs and licences appear on every analysis page.

## 6. Historical price analysis — **COMPLETE AND VERIFIED**

- **Comparable series:** one series per entity, with the same kind, basis, currency, property type and size. Transactions are preferred over valuations, and valuations over asking prices. Asking and transaction prices are never mixed (tested).
- **One-year change:** against the observation closest to 12 months earlier, within 9–15 months. **Two-year change:** closest to 24 months, within 21–27 months.
- **Formula:** ((current − historical) ÷ historical) × 100 (tested: 25 m → 30 m = +20%).
- **No comparison available:** "Insufficient Historical Data" is shown and the chart is not drawn.
- **Shown on the analysis page:** the observations table with dates and sample sizes, and the chart.
- **Not done:** monthly series are stored and charted, but there are no inflation-adjusted or rolling-average views.

## 7. Rental yield calculator — **COMPLETE AND VERIFIED**

- **Gross yield** = monthly rent × 12 ÷ value × 100 (tested: 150,000 / 30,000,000 = 6%). In rankings, rent and sale observations must have the same type, size and currency and be at most 12 months apart. Dates and bases are shown.
- **Net yield** = (annual rent − annual costs) ÷ value × 100, only when the user enters costs (calculator). Market records have no expense fields, so rankings use gross yield only, clearly labelled.
- **Public calculator:** `/investment/rental-yield-calculator`.

## 8. Investment score methodology — **COMPLETE AND VERIFIED**

- **Version 1** holds the proposed weights from the brief (20 / 12 / 12 / 12 / 10 / 10 / 14 / 10), stored as data and described as "not permanent business rules".
- **Normalisation:** annualised change −10% … +25% → 0–100; gross yield 2% … 10% → 0–100. Indicator levels map to fixed scores (configurable), and development progress uses its percentage. Risk factor = 100 − risk score.
- **Missing data:** factors without data are excluded, never set to a neutral value. Score = weighted mean of the factors with data × (1 − 0.15 × missing weight share). Below 70% coverage, or without a factor the category requires, the result is **insufficient data**, with the reason (tested).
- **Versioning:** admins create a draft version (copied from the active one), edit the weights (validated as whole numbers summing to 100 per category) and activate it. Active and retired versions are locked (409).
- **Reproducibility:** every snapshot stores its methodology version, factor breakdown and basis text. Each calculation stores its input ids and a checksum.

## 9. Ranking categories — **COMPLETE AND VERIFIED**

There are seven categories, each with its own weights:
- Overall
- Capital Appreciation (requires price history)
- Rental (requires yield)
- Low-Risk (requires a risk assessment)
- Emerging
- Commercial
- Residential

**Emerging** covers only projects that are upcoming or under construction, or entities with development progress below 100% or a developer status of on track / delayed. Established areas are excluded (tested). It weights development, infrastructure and growth more heavily, and price history less.

**Separation:** rankings are separated by asset class (residential / commercial) and can be filtered by level (city / area / project).

## 10. Data confidence — **COMPLETE AND VERIFIED**

- **Components (0–100):** observations (20), recency (20), source reliability (20), source independence (15), consistency from price ranges or repeat observations (10), and factor coverage (15).
- **Levels:** High ≥ 75, Medium ≥ 50, Limited ≥ 25, otherwise Insufficient.
- **Reasons:** every component is explained in plain words, for example "1 independent source" or "Prices are asking prices, not completed transactions".
- **Separate from the score:** a test confirms that a high score from one low-reliability asking-price source gets Limited or Medium confidence, not High.

## 11. Demand and supply — **COMPLETE AND VERIFIED**

- **Demand:** Very High / High / Medium / Low.
- **Supply:** Limited / Balanced / High.
- **Missing:** shown as **Insufficient Data**.
- **Evidence:** values come only from verified indicators that have a method and a source.
- **Listings:** Meezan Property listing counts appear separately as "platform figure only, not total market supply" and are never used as demand.

## 12. Liquidity — **COMPLETE AND VERIFIED**

High / Medium / Low / Insufficient Data, from verified indicators. It feeds both the liquidity factor and the risk assessment.

## 13. Risk assessment — **COMPLETE AND VERIFIED**

**Components:**
- volatility (standard deviation of annualised period changes);
- development or developer status;
- legal-verification uncertainty;
- oversupply;
- liquidity;
- infrastructure dependency.

**Classification:**
- At least three components are needed; otherwise **Insufficient Data** (tested).
- Low < 35, Medium < 60, otherwise High.
- **Anything not fully legally verified is never Low** (tested).

**Legal status** is reported separately on every analysis page. It comes from a verified legal indicator or, for areas, the authority approval status already on the location record. Anything not verified shows a **verification warning**.

## 14. Growth scenarios — **COMPLETE AND VERIFIED**

- **Requirements:** at least 24 months and three comparable observations, with at least two periods of six months or longer. Otherwise no numbers are shown, only the reason (tested).
- **Rates:** Conservative is the weakest historical annualised rate, Expected the whole-period average annual rate, and High growth the strongest rate.
- **Horizon:** three years from the latest price.
- **Shown with:** the scenario period, assumptions, method and data confidence.
- **Labelling:** "forward-looking, not a forecast", kept apart from historical figures.

## 15. AI insights — **COMPLETE AND VERIFIED** (rules) · **COMPLETE BUT CONFIGURATION-BLOCKED** (LLM wording)

`InvestmentInsights` builds five labelled sections from the snapshot alone:
- **Verified data** (with dates and sources);
- **Calculated metrics**;
- **Interpretation**, labelled "Automated", or "AI-generated" when the LLM is used;
- **Forward-looking scenarios**;
- **Missing information**.

**Optional LLM wording:** with `MEEZAN_INVESTMENT_AI=llm` and an OpenAI-compatible key, the model rephrases the interpretation from the same structured data.
- **Grounding check:** a reply containing any number not present in that evidence is rejected, and the rules text is used instead (tested with a faked API).
- **Privacy:** no user data is sent (tested).

## 16. Comparison — **COMPLETE AND VERIFIED**

`/investment/compare` compares up to four entries in one category and asset class on:
- score and data confidence;
- one- and two-year change;
- gross yield;
- demand, supply and liquidity;
- risk, development progress and legal verification.

**Winners:** Highest score, Highest growth, Best yield and Lowest risk are highlighted only when every entry has the metric and none has Insufficient confidence. In the throwaway QA database, Gulberg against DHA Phase 6 showed all four winners; Gulberg against Johar Town (insufficient data) showed none.

## 17. User-facing pages — **COMPLETE AND VERIFIED**

| Page | Content |
|---|---|
| `/investment` | Goal tabs (seven categories); filters for city (all seven), asset category, level, property type, risk preference, data confidence, period (1 or 2 years), budget, minimum yield and minimum growth. Cards show rank, name, score, confidence, risk, 1y/2y change or "Insufficient Historical Data", yield, latest price (asking labelled) and a compare checkbox, plus the published date and methodology version |
| `/investment/{city\|area\|project}/{id}` | Overview, score breakdown (weights, factor scores, basis), historical chart and table, rental yield, scenarios, insight, market indicators, legal verification, risk, data confidence, sources, published history, methodology link |
| `/investment/compare` | See §16 |
| `/investment/methodology` | Data rules, formulas, live weight table, markets (active / planned), label glossary |
| `/investment/rental-yield-calculator` | Gross and net yield |

**Design:** brand purple, gold and white, using the Brand Style Guide palette (`#4B2E83` / `#D4AF37`) as decided earlier, not the brief's hex values.

**Other details:**
- Linked from the header "More" menu and the footer.
- Pages without published data are noindex.
- The disclaimer is on every page.

## 18. Admin review workflow — **COMPLETE AND VERIFIED**

**Records:** Draft → Submitted → Reviewed → Verified, with Reject (reason required) back to correction and Reopen (verified → draft).
- Steps can't be skipped; verified records are locked.
- Every action is written to the audit log with the user.
- Only verified records are used in calculations (tested).

**Calculations:** Run → Completed → (admin) Published. The previous publication becomes Superseded and its rows are kept.
- Published or superseded snapshot rows throw on update (tested).
- Superseded runs can't be published again.
- **Not built:** a separate "used in calculation" status per record. Use is recorded through the calculation's input ids instead.

## 19. Scheduled recalculation — **COMPLETE AND VERIFIED**

- **Command:** `php artisan meezan:investment-recalculate`, scheduled weekly (Mondays 04:00, `withoutOverlapping`). It records timestamp, methodology version, input ids, checksum, summary and status.
- **Never publishes** (tested: the scheduled run stays "completed", and the public page still shows no rankings).
- **Manual runs** from the Calculations screen are throttled.
- **Production needs** the scheduler cron.

## 20. Security — **COMPLETE AND VERIFIED**

- **Authorization:** all Data Centre routes are behind `auth` + `role:admin`. Guests are redirected; buyers and dealers get 403 on every action tested (view, run, transition, create).
- **Validation:** strict rules on every write. Mass assignment is limited to `$fillable`; workflow and user fields are set explicitly.
- **CSV import:** MIME and extension checks, 2 MB and 5,000-row limits. Unknown columns are rejected; rows become drafts.
- **Public pages:** they read only published snapshot rows. They expose no user, dealer, buyer, CRM or contact data, and no drafts (tested: a draft observation's figure never appears).
- **Audit log:** source, record, import, methodology and calculation actions are all logged.
- **Performance:** a ranking list is one indexed query per calculation, category and asset class; filtering and pagination happen in memory over that set, which is bounded by the number of ranked entities. Snapshots are immutable, so caching is safe by calculation id, but no cache layer was added.

## 21. Data preservation — **COMPLETE AND VERIFIED**

| Table | Before | After |
|---|---|---|
| users | 4 | 4 |
| dealer_profiles | 2 | 2 |
| properties | 18 | 18 |
| leads / lead_assignments | 2 / 2 | 2 / 2 |
| cities / areas / projects | 7 / 1,238 / 0 | 7 / 1,238 / 0 |
| conversations / messages | 11 / 48 | 11 / 48 |
| subscriptions / payments / invoices | 1 / 2 / 1 | 1 / 2 / 1 |
| New: market_sources / observations / indicators | — | 0 / 0 / 0 |
| New: investment_methodologies / calculations / rankings | — | 1 (v1) / 0 / 0 |

**Note on the first migration attempt:** it failed because one index name was over MySQL's 64-character limit, and MySQL doesn't roll back table creation. The seven tables it had just created were all **empty** and were removed before the migration was fixed and rerun. No pre-existing table was touched.

## 22. Files changed

**Added**
- *Models:* `app/Models/MarketSource.php`, `MarketObservation.php`, `MarketIndicator.php`, `MarketImportBatch.php`, `InvestmentMethodology.php`, `InvestmentCalculation.php`, `InvestmentRanking.php`, `Concerns/HasReviewWorkflow.php`
- *Services:* `app/Services/Investment/MarketMetrics.php`, `RiskAssessor.php`, `ConfidenceAssessor.php`, `GrowthScenarios.php`, `InvestmentScorer.php`, `RankingCalculator.php`, `InvestmentInsights.php`, `MarketRecords.php`
- *Controllers:* `app/Http/Controllers/InvestmentController.php`, `Admin/MarketController.php`, `Admin/MarketSourceController.php`, `Admin/MarketRecordController.php`, `Admin/InvestmentAdminController.php`
- *Command:* `app/Console/Commands/InvestmentRecalculate.php`
- *Migrations:* `database/migrations/2026_11_03_000041_…`, `…000042_…`
- *Public views:* `resources/views/investment/` (index, show, compare, methodology, yield, partials/disclaimer)
- *Admin views:* `resources/views/admin/market/` (index, sources, records, methodologies, methodology-form, calculations, calculation, partials/nav)
- *Component:* `resources/views/components/investment-badge.blade.php`
- *Tests:* `tests/Feature/InvestmentIntelligenceTest.php`
- This report.

**Modified**
- `config/meezan.php`: `investment` section.
- `routes/web.php`: 5 public and 24 admin routes.
- `routes/console.php`: weekly schedule.
- Site header, footer and admin nav: links.
- `admin/boundaries/index` (Phase 13): the table box is now positioned, so screen-reader labels can't widen the page on phones.

## 23. Database changes

- **Seven new tables** (§3).
- **One inserted row:** methodology v1.
- **No existing tables or columns changed.**

## 24. Routes

**Public:**
- `investment.index`
- `investment.show` (`/investment/{city|area|project}/{id}`)
- `investment.compare`
- `investment.methodology`
- `investment.yield`

**Admin** (`/admin/market…`, 24 routes):
- `admin.market.index`
- `admin.market.sources.*` (index, create, store, edit, update)
- `admin.market.records.*` (index, create, store, edit, update, transition, import, import.store, template) for `observations` and `indicators`
- `admin.market.methodologies.*` (index, store, edit, update, activate)
- `admin.market.calculations.*` (index, store, show, publish)

## 25. Automated test results

| Command | Result |
|---|---|
| `php artisan test` | **526 passed, 2,939 assertions, 0 failures** (final run, 64.8 s) |
| New `InvestmentIntelligenceTest` | 21 tests, 185 assertions |
| `vendor/bin/pint --test` | passed |
| `npm run build` | built |
| `composer audit` | no advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `route:cache` / `config:cache` / `view:cache` | succeeded, then cleared for local development |

**What the new tests cover:**
- methodology seeding, validation and versioning;
- change and yield formulas, comparable-series rules, asking vs transaction separation;
- full ranking from verified data;
- missing data never scored neutrally; confidence kept separate from score;
- legal floor on risk and the insufficient-risk rule;
- scenarios refused without history;
- emerging eligibility; verified-only calculation inputs;
- no auto-publish, immutable snapshots, superseded history;
- the record workflow and audit; strict validation and seven-city acceptance;
- CSV import (drafts, duplicates, row errors, bad columns and file types);
- admin-only access; public privacy;
- comparison winners only when comparable;
- grounded insights and rejection of LLM text with invented numbers;
- the disclaimer.

**Intermittent existing test:** `WhatsAppIntegrationTest › handles a repeated webhook delivery once` (Phase 10, unchanged here) failed in 2 of 5 full-suite runs during this phase (one parallel, one serial). It passed in the other runs and in every isolated run. It relies on a "within a minute" duplicate window, so it looks timing-sensitive. Not investigated further in this phase.

## 26. Manual QA

**Your local data** (`localhost:8000`): `/investment` shows "No published rankings yet", because no market data exists, which is correct.

**Throwaway QA database** (separate SQLite in the session scratchpad, served on `127.0.0.1:8010`, all figures labelled "QA FIXTURE … (not real)", deleted afterwards). The fixtures covered three Lahore areas and one under-construction project; the calculation gave 11 ranked rows and 10 insufficient-data rows.
- **Ranking page:** Gulberg #1 (70.2, High confidence, Low risk, +11.11%, 6% yield) and DHA Phase 6 #2. Johar Town was not ranked (insufficient data).
- **Analysis page:** all sections present, consistent with the formulas, with sources and fixture labels.
- **Emerging ranking:** only the project, at High risk (unverified legal status, delayed developer) and Limited Data.
- **Comparison:** winners for Gulberg against DHA Phase 6; none with Johar Town.
- **Admin pages:** all 19 Data Centre and public URLs load (200) with no errors.
- **Mobile (375 px):** all 11 investment and Data Centre pages, plus the Phase 13 boundaries page, have no horizontal scroll. QA found and fixed two overflow issues: grid columns that could not shrink, and screen-reader-only labels escaping their table's scrolling box.
- **Console:** no errors. **Laravel log:** only the two errors from the failed first migration attempt (§21).
- **Screenshots:** not taken for the QA copy (the browser pane was hidden). QA was done through page text and DOM checks.

## 27. Known limitations

1. **No real market data yet**, so no published rankings. Data must come from sources you are permitted to use (§28).
2. **Net yield** is only in the calculator; market records have no expense fields.
3. **Rankings for a specific property** (comparing a listing with verified comparables) are **NOT IMPLEMENTED**.
4. **"Used in calculation" status:** not a separate status per record; it is traced through calculation inputs instead.
5. **Independent review:** the same admin can submit, review and verify. There is no rule requiring a second person.
6. **International markets:** configured as planned only (currency, units, legal checks). No non-Pakistan data entry is enabled; PKR only.
7. **Category weights:** emerging and other category weights are a first proposal and should be reviewed with real data.
8. **Ranking display:** "rank" is stored per category and asset class nationally; the page shows the position within the current filter.
9. **Caching:** no cache layer was added (queries are indexed and bounded; snapshots are cache-safe).
10. **Intermittent WhatsApp test:** see §25.

## 28. External data sources and credentials required

- **Market data:** none is bundled. You need licensed or authorized sources, for example:
  - verified transaction records or registry data you have permission to use;
  - authorized market reports;
  - official authority approval lists (LDA, CDA, KDA, RDA, DHA and others);
  - your own documented research and rental surveys.

  Record each source's licence or permission in the registry. Don't scrape portals or bypass access controls. Portal asking prices may only be entered as **asking** prices, from a source typed "public asking prices".
- **Optional AI wording:** `MEEZAN_INVESTMENT_AI=llm` plus `OPENAI_API_KEY` (and optionally `OPENAI_BASE_URL` / `OPENAI_MODEL`). Without them the rules-based insight is used.
- **Production scheduler:** the cron for `schedule:run` (weekly recalculation).

## 29. Recommended next phase

1. **Data onboarding:** register real sources and enter or import verified observations and indicators for priority societies in each city, then run, review and publish the first calculation.
2. **Review roles:** separate data-entry and verification roles, and require a second reviewer before verification.
3. **Listing comparables:** a "market context" box on property pages comparing a listing's price per sq ft with verified comparables, labelled by basis.
4. **Expense data and net yield** in market records.
5. **Methodology review:** calibrate the weights and normalisation ranges with real data, publish v2, and keep v1 snapshots for comparison.
6. **Saved investment alerts** when a published ranking or score changes.
