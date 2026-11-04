# Meezan Property — Investment Rankings Implementation Report
## City-wise rankings and property-type investment intelligence

Date: 3 November 2026. Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB).

Safety:
- Nothing was deployed, nothing was committed or pushed, and Git was not touched.
- No `migrate:fresh` or `db:wipe` was run, and no existing data was deleted.
- One additive migration was added.
- `.env` was not changed.

**Backup before any change:** `C:\xampp\backups\MeezanProperty-before-investment-rankings-2026-11-03\meezanproperty.sql`. All 66 of 66 tables were dumped.

---

## Summary

The Investment Rankings module is built and working. The user journey is: choose a city, then a group (Residential, Commercial, Mixed Use), then a property type such as Shopping Malls or 5 Marla Plots. Each property type opens a dashboard laid out like the reference designs, with:
- a ranking table;
- featured cards;
- the investment map;
- a performance panel with a price-history chart;
- growth scenarios;
- comparison;
- the AI Investment Advisor;
- upcoming projects.

**It is data-ready, but has no market data yet.** Your local database has 0 verified market observations, 0 indicators, 0 market sources and 0 projects. So on your real data every page truthfully shows:
- live counts, such as 390 societies in Lahore and 1 real listing for sale in Lahore;
- "No rankings are published yet";
- "Insufficient Data" for anything tracked.

No price, yield, score or forecast from the reference images was copied into the database or the pages.

**How it was tested with data:**
1. In a throwaway SQLite copy, with fixtures all named "QA Fixture …".
2. On your real MariaDB, inside a transaction that was rolled back. Row counts were identical before and after.

| Area | State |
|---|---|
| City → group → property-type pages, filters, sorting, search, in-place updates | Working, tested |
| Property-type rankings (societies, plots by size, houses by size, apartments by bedrooms, malls, plazas, towers, offices, shops, commercial plots, mixed use) | Working, tested with fixtures; **needs verified market data** to show anything on your site |
| Map, price chart, scenarios, comparison | Working, tested with fixtures; **need verified data** |
| AI Investment Advisor | Working in rules mode; the model wording path is tested only with mocks (no provider credit) |
| Admin: project categories, map position, gallery, specifications, project CSV import, Categories page | Working, tested |
| Category-specific background images (malls, towers) | **Not done**: the city photos are used; no licensed category images exist in the project |

---

## 1. Existing functionality discovered

| Existing piece | What it does | Used for |
|---|---|---|
| Phase 14 `RankingCalculator`, `InvestmentScorer`, `MarketMetrics`, `RiskAssessor`, `ConfidenceAssessor`, `GrowthScenarios` | Scores city / area / project × residential / commercial from verified records; snapshots; publish workflow | **Extended**, not duplicated |
| Phase 14 methodology v1 (weights per ranking category, admin-approved, versioned) | MPIS weights | The same weights for every property type |
| Phase 14 `InvestmentInsights`, `Grounding` | Rules text and grounded model text | Performance panel, advisor |
| Phase 14 Market Data Centre (sources, observations, indicators, review workflow, CSV import, calculations) | Admin data entry and verification | Kept; extended with bedrooms, occupancy and a Categories page |
| Phase 13 MapLibre set-up (`resources/js/geo-map/common.js`) | Style with raster fallback | Reused by the new investment map |
| Projects (admin "New Projects") | Name, city, area, status, price range, cover | Extended with investment fields |
| Areas (1,238; 390 society-type areas in Lahore) | Location directory | Societies are areas; nothing duplicated |
| Seven cities, `place_photos` (licensed city photos) | City pages | City cards and hero backgrounds |

**Confirmed methodology.** Methodology v1 from Phase 14 is active, with the brief's weights for "overall": price performance 20, demand and supply 12, rental yield 12, location and infrastructure 12, development progress 10, liquidity 10, risk 14, growth 10. No competing score was created.

**One bug was found and fixed.** In `RankingCalculator`, the per-city listing count used `pluck(DB::raw('count(*)'))`, which cannot read the count back. The `platform_listings` metric was therefore always empty. This is the same defect I fixed in the agent tools in Phase 15.

## 2. Existing functionality reused

- **Scoring:** segment rows are produced by the same `analyse()` and `InvestmentScorer::score()` as Phase 14. Each property type uses the weights of an existing methodology category, so admins still change weights in one place (Methodology):
  - Shopping Malls, Apartments, Office Spaces and Commercial Shops use **Best Rental Investment**;
  - Plots and Commercial Plots use **Best Capital Appreciation**;
  - Societies and Houses use **Best Residential Investment**;
  - Plazas and Towers use **Best Commercial Investment**;
  - Mixed Use uses **Best Overall Investment**.
- **Publishing:** snapshots, history and immutability are unchanged. Segment rows belong to the same calculation and are published with it.
- **Map:** `createMap()` from Phase 13, with no second map system.
- **Design:** the layout component, brand tokens, breadcrumbs (which already emit BreadcrumbList JSON-LD), the field components and the disclaimer.
- **Palette:** the Brand Style Guide palette (#4B2E83 / #D4AF37) was kept. The brief again quotes #4B1592 / #D6A13D / #21133A; you chose the style-guide colours on 30 October 2026 (Phase 1 §8).

## 3. New pages created

| URL | Page |
|---|---|
| `/investment-rankings` | Landing: hero ("Smart Real Estate Investments for a Better Tomorrow"), search and filters, seven city cards, category chooser, top entries per property type, AI advisor, MPIS weights, overview, upcoming projects |
| `/investment-rankings/{city}` | City dashboard with 14 category tabs that update in place (All, Residential, Commercial, Plots, Houses, Apartments, Shopping Malls, Commercial Plazas, Business Towers, Office Spaces, Mixed-Use, Highest Rental Yield, Fastest Growing, New & Upcoming), map, advisor, overview, upcoming |
| `/investment-rankings/{city}/residential`, `/commercial` | Group page: one card per property type with its top three |
| `/investment-rankings/{city}/{group}/{type}` | Property-type dashboard, e.g. `/lahore/commercial/shopping-malls`, `/lahore/residential/plots` |
| `/investment-rankings/{city}/mixed-use` | Mixed use (one property type, so the group opens its dashboard) |
| `…/{type}/compare?items[]=…` | Comparison, up to four |
| `/investment-rankings/advisor?question=…` | JSON for the advisor (throttled to 30 a minute) |
| `/investment-rankings/pakistan/…` | The same pages for all cities |

"Investment Rankings" was added to the main header and the footer. The existing `/investment` pages remain as the detailed analysis of one entry.

## 4. City-wise ranking implementation

- **City cards:** real counts per city (society-type areas, published commercial projects, tracked entries). On a property-type page they show the matching count, such as "0 Malls tracked".
- **Overview:** live counts of:
  - published real listings (demo listings excluded), plots, and houses and apartments;
  - societies;
  - commercial projects, malls, plazas, and towers and offices;
  - new and upcoming projects;
  - verified market records.

  It also lists the highest verified yields and the fastest growth, but only when verified data exists. Otherwise it says what is needed.
- **Tabs:** they change results without a page reload (`X-Rankings-Partial`); the URL follows and the links work without JavaScript.
- **New cities:** a city added to `config/meezan.php` (cities, photos) gets its pages and routes automatically.

## 5. Property-type-wise ranking implementation

Eleven property types (`config/meezan.php → investment.segments`). Each has its own:
- entities (areas, projects or both);
- project categories;
- property types used;
- comparison grouping;
- weights;
- table columns.

| Group | Type | Compared by | Columns |
|---|---|---|---|
| Residential | Societies | Whole society (area or project) | Price, 1-yr, 2-yr, yield, MPIS, risk |
| | Plots | **Size** (3/5/7/10 Marla, 1/2 Kanal, plus any other recorded size) | Size, price, 1-yr, 2-yr, MPIS, development |
| | Houses | Size | Size, price, 1-yr, yield, MPIS, risk |
| | Apartments | **Bedrooms** (studio to 4) | Size, price, 1-yr, yield, MPIS, status |
| Commercial | Shopping Malls | Whole mall (project) | Price per sq ft, yield, MPIS, **occupancy**, status |
| | Commercial Plazas, Business Towers | Whole building | Price per sq ft, 1-yr, yield, MPIS, occupancy, status |
| | Office Spaces, Commercial Shops | Size | Size, price, 1-yr, yield, MPIS, risk |
| | Commercial Plots | Size | Size, price, 1-yr, 2-yr, MPIS, development |
| Mixed Use | Mixed-Use Developments | Whole development | Price per sq ft, 1-yr, yield, MPIS, occupancy, status |

**Like-for-like comparison:**
- Prices are never mixed across sizes or bedroom counts: each size or bedroom group is its own row.
- Plot observations never reach the shop or apartment views.
- A mall is never treated as a society.

**Display status** is a label on the snapshot row, not a new score:
- **Ranked:** scored with medium or high confidence.
- **Provisional:** scored on limited data, shown with a dashed outline.
- **Under Review:** records are submitted but not yet verified.
- **Insufficient Data:** published projects with no verified data are listed, never scored.

**Table features:** sorting (score, price, yield, change, name), search, filters (size, status, risk, data confidence, budget, minimum yield), pagination, and cards on phones.

## 6. Database changes

One additive migration, `2026_11_03_000047_add_investment_segments`:

| Table | Added |
|---|---|
| `projects` | `category`, `latitude`, `longitude`, `approval_status`, `launched_on`, `specifications` (json), `gallery` (json) |
| `market_observations` | `bedrooms` (nullable; included in the duplicate fingerprint only when set, so existing fingerprints are unchanged) |
| `investment_rankings` | `segment`, `size_key`, plus an index |

Configuration (no migration):
- an `occupancy` indicator (numeric %, shown only, not a scoring factor);
- `five_year_window` for the 5-year change;
- project categories, groups and segments.

Rows were not touched: your data counts are unchanged (§14).

## 7. MPIS integration

- **One methodology:** the same version for every comparable entry, and a score breakdown per factor (weight, score, basis), shown under Details.
- **Separate confidence:** the data-confidence level is always shown separately from the score.
- **No fabricated scores:** an entry without enough weighted data is not scored. For example, a mall with no verified rent is not scored under the rental-weighted malls ranking. This is deliberate, and the reason is shown.
- **Methodology changes:** proposed and approved in the existing Methodology screen, and snapshot history is kept.
- **Not a guarantee:** the disclaimer is on every page, and the advisor repeats that scores are not guarantees.

## 8. Map integration

- **Map:** a new `resources/js/investment-map.js` on the Phase 13 `createMap()`, using a clustered GeoJSON source.
- **Markers:** coloured by status (ranked, provisional, under review, tracked). Clicking a cluster zooms in; clicking a marker opens a pop-up with the score, price and yield, and "View performance" loads that entry's panel.
- **Map–list sync:** choosing an entry in the list centres the map on it.
- **Privacy:** projects show at their own position. Areas show at their centre, marked as approximate. No individual listing pins are shown.
- **Fallbacks:** entries without a position are listed but not mapped. Without JavaScript the table carries everything.

## 9. AI Investment Advisor integration

- **Service:** `App\Services\Investment\InvestmentAdvisor`. It reads the city, property type, size ("5 marla") and intent (best, compare, rental, risk, forecast) from the question. The tabs (Ask AI, Compare, Rental Analysis, Forecast) choose the intent.
- **Answers:** listed entries with their MPIS, data confidence, verified price and date, yield and change. It also gives sources, the methodology version and the publication date, and says plainly when data is missing.
- **Model wording:** optional (`MEEZAN_INVESTMENT_AI=llm`). The wording is kept only if every figure in it is in the data (Grounding). This is tested with mocks only: neither AI provider has API credit (Phase 15 §8).
- **Without JavaScript:** it works as a normal form (`?ask=`). With JavaScript it answers in place.
- **Scope:** it does not go through the Central AI Brain's agents. The existing chat routing still sends investment questions to the Investment Analyst agent.

## 10. Historical data and forecast implementation

**Price trend chart:**
- server-side SVG (`App\Support\PriceChart`) of the one comparable verified series, with periods 1Y, 2Y, 5Y and All;
- the price basis (transaction, asking or valuation) is shown and never mixed;
- with fewer than two points in the period there is no chart, only a message.

**Changes:** 1-, 2- and 5-year changes. The 5-year window is new; the others are from Phase 14.

**Future Growth Forecast:**
- Conservative, Moderate and Optimistic, from Phase 14 `GrowthScenarios`;
- shown with the period, assumptions, data basis, confidence and key risks;
- with too little data: "Forecast unavailable — insufficient verified data."

## 11. Admin data centre

- **Projects** (Admin → New Projects):
  - investment category, latitude and longitude (checked to be inside Pakistan);
  - verified approval status, launch date;
  - specifications ("Label: value" lines);
  - a gallery of up to 8 photos, which can be removed;
  - a Category column in the list.
- **Project CSV import** (`/admin/projects/import`, with a template):
  - each row is validated on its own;
  - rejected rows are listed with their reasons;
  - existing projects (same name and city) are **never overwritten**;
  - imported projects stay **hidden until published**;
  - each import is audit-logged.
- **Market Data Centre → Categories:** each property type, what feeds it, its weights, published and hidden projects, and snapshot rows. It warns when projects have no category.
- **Market Data Centre → Projects:** a new link.
- **Observations:** a bedrooms field in the form and CSV. The column was added at the **end** of the template so older files keep their order.
- **Occupancy:** entered as a market indicator.
- **Unchanged:** verification (source, dates, status, reviewer), CSV import of prices, rents and indicators, and ranking controls (calculate, then publish).

## 12. SEO implementation

- **Per page:** a unique title and description per city and property type, a canonical URL, and breadcrumbs with BreadcrumbList JSON-LD.
- **Structured data:** an ItemList of the scored entries on property-type pages.
- **Thin pages:** pages with no scored entry are `noindex, follow` and left out of the sitemap. Pages with scored entries are indexable and added to `sitemap pages`. The compare pages are always noindex.
- **URLs:** clean, as suggested in the brief (for example `/investment-rankings/lahore/commercial/shopping-malls`).

## 13. Responsive testing

Checked in the browser pane on the QA copy (fixtures) and on your MariaDB (empty state):

| Width | Result |
|---|---|
| 1440 | Three-column dashboard matching the reference (table and map, advisor); performance and forecast below |
| 1280 | The dashboard uses two columns (the first version cramped the table: fixed). The header nav had wrapped "Investment Rankings" onto two lines: fixed |
| 375 (phone) | No sideways scrolling; city cards and category chips scroll on their own; filters fold away; the table becomes cards; map and advisor usable |

**In-place behaviour checked in the browser:**
- choosing an entry swaps the performance panel and updates the URL;
- sorting swaps the table;
- the compare tray collects entries and builds the compare link;
- the advisor answers in place;
- the chart tabs work.

## 14. Automated test results

**New tests:** `tests/Feature/InvestmentRankingsTest.php`, **17 tests, 151 assertions, all passing**. They cover:
- empty states with no invented figures;
- real counts;
- 404s for unknown cities and types;
- per-type ranking with the existing scorer;
- plots by size, apartments by bedrooms;
- status labels;
- filters, sort and the in-place partial;
- the chart only from verified points;
- comparison;
- advisor answers, intents and "not enough data";
- grounded model wording;
- the hero search redirect;
- map markers;
- SEO and sitemap;
- admin project fields and validation;
- the CSV import (no overwrite, rejected rows, hidden);
- bedroom fingerprints.

**Full suite:**
- First run: 750 passed and 1 failed, out of 751.
- The failure was `InvestmentIntelligenceTest › imports CSV rows as drafts`. I caused it by inserting `bedrooms` into the middle of the CSV column list. It was fixed by moving the column to the end; the test was not changed.
- After the fix, the Phase 14 and new tests passed 38 of 38, three times in a row.
- One run also failed `grounds insights … invented numbers` once. It did not recur in 8 isolated runs and 3 combined runs, and this work does not touch that code path. I'm recording it as intermittent.
- **Final full run, after all changes: 751 passed, 4,125 assertions, 0 failed.**

| Command | Result |
|---|---|
| `vendor/bin/pint --test` | Passed (after formatting six new or changed files) |
| `composer audit` | No advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `npm run build` | Built (only the known MapLibre chunk-size warning) |

**On your real MariaDB, inside a rolled-back transaction**, I ran:
- the calculation with segments;
- browsing (items, cities, overview, upcoming);
- the advisor;
- the segment page render;
- the sitemap and the Categories page.

All worked. Row counts before and after were identical. The real database still has 0 calculations, and the sitemap has no fixture URL.

**Data preserved** (before = after): users 4, dealer profiles 2, properties 18, images 42, leads 2, assignments 2, conversations 12, messages 56, subscriptions 1, payments 2, invoices 1, cities 7, areas 1,238, projects 0, observations 0, indicators 0, rankings 0.

## 15. Known limitations

1. **Nothing to rank yet.** Until verified records are entered and a calculation is published, every ranking is empty. This is expected and honest.
2. **No category-specific hero images.** They use the city photos. Adding licensed mall or tower photos is a content task.
3. **Categories are configuration.** They are defined in `config/meezan.php`, not editable in the admin. The admin page shows them and their data.
4. **No CSV import for societies.** Societies are areas, managed in the location directory. Projects (including societies run as projects), prices, rents and indicators all have CSV import.
5. **Advisor model wording not live.** It is tested only with mocks (no provider credit), and the advisor is not connected to the agent workforce.
6. **Occupancy is not scored.** It is shown but is not an MPIS factor; adding it would need a new methodology version.
7. **Mixed use is narrow.** It ranks only projects with the "Mixed-use development" category.
8. **Some stats not shown.** The reference's "100% Transparent Data" and similar marketing figures were replaced by real counts.
9. **Compare tray memory.** It remembers selections per page in session storage, so it is empty in a new tab.

## 16. Data still required from the administrator

1. **Market sources** (Market Data Centre → Sources): licensed or authorised sources only.
2. **Projects:** malls, plazas, towers and societies, with category, position, status and photos (Admin → New Projects, or CSV import), then published.
3. **Verified observations:** sale prices with size (bedrooms for apartments) and dates, at least two comparable points; four or more for a trend and scenarios.
4. **Rents:** matching type and size within 12 months, needed for yields, and for any score in the rental-weighted types (malls, apartments, offices, shops).
5. **Indicators:** demand, supply, liquidity, infrastructure, development progress, legal verification, growth drivers, and **occupancy** for commercial buildings.
6. **Review, then calculate, then publish** (Market Data Centre → Calculations).

## 17. Files created and modified

**Created**
- `app/Http/Controllers/InvestmentRankingsController.php`
- `app/Services/Investment/RankingBrowser.php`, `InvestmentAdvisor.php`, `ProjectImport.php`
- `app/Support/PriceChart.php`
- `database/migrations/2026_11_03_000047_add_investment_segments.php`
- `resources/js/investment-rankings.js`, `resources/js/investment-map.js`
- `resources/views/rankings/`:
  - pages: `index`, `city`, `group`, `segment`, `compare`;
  - partials: `hero`, `city-cards`, `category-nav`, `badges`, `segment-results`, `city-results`, `performance`, `forecast`, `advisor`, `map`, `featured`, `upcoming`, `overview`
- `resources/views/admin/projects/import.blade.php`, `resources/views/admin/market/categories.blade.php`
- `tests/Feature/InvestmentRankingsTest.php`
- This report

**Modified**
- `config/meezan.php`: segments, project categories, groups, occupancy, five-year window
- `app/Services/Investment/RankingCalculator.php`: segment rows, 5-year change, price per sq ft and bedrooms in metrics, the count fix
- `app/Services/Investment/MarketRecords.php`, `app/Models/MarketObservation.php`: bedrooms
- `app/Models/Project.php`: new fields, `categoryLabel()`, `hasCoordinates()`
- `app/Http/Controllers/Admin/ProjectController.php`: fields, gallery, specifications, CSV import
- `app/Http/Controllers/Admin/InvestmentAdminController.php`: Categories page
- `app/Http/Controllers/InvestmentController.php`: excludes segment rows from the entity page
- `app/Http/Controllers/SitemapController.php`: scored ranking pages
- `routes/web.php`; `resources/js/app.js`
- Header and footer components; the admin project form and list; the Market Data Centre nav; the observation form
- `.claude/launch.json`: added a `meezan-8020` preview, because port 8000 now serves a different application on this machine
- `public/build/`: rebuilt assets (in Git since the last commit)

**Prototype or data-dependent features** (working code, but they show nothing on your site until verified data exists):
- rankings and scores;
- yields, changes and price charts;
- scenarios;
- highest yield and fastest growing;
- map markers for scored entries;
- the advisor's recommendations.

UI-only, no data needed: the layouts, filters, navigation, comparison mechanics and admin tools.
