# Meezan Property — Investment Rankings Demo Data Report

Date: 4 November 2026. Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB).

Nothing was committed, pushed or deployed, and no destructive database operation was run.

**Backup before seeding:** `C:\xampp\backups\MeezanProperty-before-investment-demo-data-2026-11-04\meezanproperty.sql`. All 66 of 66 tables were dumped.

---

## Summary

The Investment Rankings module is now fully populated with demonstration data in all seven cities and all eleven property types. Every page shows complete dashboards: tables, featured cards, maps, price-history charts, yields, MPIS scores, comparisons, growth scenarios, upcoming projects and the AI Investment Advisor.

**The demo data can never pass as real:**
- **Fictional projects:** all 420 projects are fictional, and every name ends in "(Demo)". They are placed in real localities, but no invented figure is attached to a real society or development.
- **Separate status:** every demo record is flagged `is_demo` and has its own status `demo`. It is never `verified` and never counted as verified.
- **Separate ranking:** demo data is ranked by the normal MPIS engine into a **separate demo snapshot**, which never replaces real rankings.
- **Disclosure everywhere:**
  - every demo-driven page shows the notice: *"DEMO DATA — Illustrative figures for platform preview only. Prices, rental yields, investment scores and forecasts are not verified market information."*;
  - every demo entry carries a **DEMO** tag;
  - demo prices are labelled "Illustrative demo price", never "Verified transaction".
- **Not indexed:** demo pages are `noindex` and kept out of the sitemap.
- **Off switch:** turning demo mode off removes it all from public view at once. Removing the dataset deletes demo records only.

## 1. Total demo records created

Seeded into your local MariaDB with `php artisan db:seed --class=InvestmentDemoSeeder` (52 seconds), then ranked.

| Record | Count |
|---|---:|
| Demo projects | **420** (60 per city) |
| Demo price and rent records (market observations) | **7,187** (6,725 sale, 462 rent; 4,682 transaction-type, 2,505 asking-type) |
| Demo market indicators | **3,524** (including 164 occupancy) |
| Demo market sources | **3** (transactions, asking prices, rental survey: each "illustrative, not real") |
| Demo ranking calculation | **#3**: 3,522 snapshot rows, of which 1,078 are property-type rows |

Featured projects: 98. Status mix: 302 completed, 97 under construction, 21 upcoming.

**Real data after seeding (unchanged):** 0 real projects, 0 verified records, 0 real sources. Users 4, dealer profiles 2, properties 18, property images 42, leads 2, conversations 12, subscriptions 1, payments 2, cities 7, areas 1,238.

## 2. City-wise record counts

| City | Demo projects | Price and rent records |
|---|---:|---:|
| Lahore | 60 | 1,010 |
| Karachi | 60 | 1,069 |
| Islamabad | 60 | 1,008 |
| Multan | 60 | 971 |
| Faisalabad | 60 | 976 |
| Quetta | 60 | 1,069 |
| Peshawar | 60 | 1,084 |

Each city has 10 residential societies, 8 apartment projects, 8 shopping malls, 8 commercial plazas, 8 business towers, 8 commercial land projects, 5 mixed-use developments and 5 upcoming or new projects.

**Placement:** 400 of 420 projects are placed at a real locality from your location directory (for example Allama Iqbal Town, Bedian Road, Blue Area, Hayatabad). The other 20 are placed around the city centre. All 420 have distinct map positions.

## 3. Category-wise record counts

**Ranking rows per property type, all cities:**

| Property type | Rows | Built from |
|---|---:|---|
| Residential Societies | 77 | 70 society projects + 7 upcoming |
| Residential Plots | 154 | 5 Marla, 10 Marla and 1 Kanal plots inside the societies |
| Houses | 63 | 5 and 10 Marla houses inside the societies |
| Apartments | 168 | 2- and 3-bedroom units in apartment and mixed-use projects |
| Shopping Malls | 63 | 56 malls + 7 upcoming |
| Commercial Plazas | 56 | Plazas |
| Business Towers | 63 | 56 towers + 7 upcoming |
| Office Spaces | 119 | Offices inside towers and plazas |
| Commercial Shops | 161 | Shops inside malls, plazas and mixed-use projects |
| Commercial Plots | 112 | 4 and 8 Marla plots in commercial land projects |
| Mixed-Use Developments | 42 | 35 + 7 upcoming |

The same project appears in more than one property type only where it really contains that type, for example the shops inside a mall.

**Coverage:** every city × property type combination has scored entries; there is no empty section. Per city that is 11 societies, 22 plot rows, 9 houses, 24 apartment rows (21 scored), 9 malls (8 scored), 8 plazas, 9 towers, 17 office rows (16 scored), 23 shop rows (21 scored), 16 commercial-plot rows and 6 mixed-use.

**Variety, as real data would have:**

| Measure | Breakdown |
|---|---|
| Status | 1,029 ranked; 49 "Insufficient Data" (mostly early-stage projects with only recent asking prices and no rents) |
| Data confidence | 879 High, 150 Medium |
| Risk | 315 Low, 759 Medium, 4 High |

## 4. Images added or reused

- **Added:** `public/images/demo/investments/`, 44 SVG illustrations: 11 building types (society, plots, houses, apartments, mall, plaza, shops, office, tower, commercial land, mixed use) × 4 colour variants, 296 KB in total.
- **Labelled:** each image is stamped **"DEMO"** and **"Illustrative image · not a real project"**, and its accessible label says the same.
- **Why illustrations:** the only existing images are licensed photos of real city landmarks and three category placeholders. Using landmark photos as pictures of fictional developments would mislead, so they were not used.
- **Not repeated:** 28 different cover images are in use, and each project also gets a gallery of 2–3 related illustrations. Project pages call them illustrations.

## 5. Historical chart data generated

- **Full history (about 60% of projects):** a sale price every 6 months for 5 years (11 points per series), so the 1Y, 2Y, 5Y and All charts work.
- **Shorter histories (the rest):** 3 years for some, 18 months for others; upcoming projects have three recent asking prices only.
- **Consistency:** prices follow a per-project annual growth rate (category-specific ranges, for example plots 5–20%, offices 2–12%) with ±1.5% noise. So the 1-, 2- and 5-year changes the engine calculates agree with the chart.
- **Price levels:** city-adjusted (Islamabad highest, Quetta lowest).
- **Rents:** set from category yield ranges (residential 3–5.5%, apartments 4–7%, shops and malls 6–10%, offices 5.5–9%).
- **Result:** 1,078 property-type rows have a chart series; 875 have 2-year changes, 618 have 5-year changes, and 875 have growth scenarios.
- **Reproducible:** every figure is generated from a random sequence seeded with the project's name, so the same dataset is produced every time.

## 6. MPIS integration

- **Scoring:** no score is hard-coded. Demo records go through the existing Phase 14 engine (`RankingCalculator`, `InvestmentScorer`, methodology v1 weights), in a run flagged `includes_demo`.
- **Separate snapshots:**
  - publishing a demo run supersedes only the previous demo run, never the real snapshot (and the reverse);
  - real runs read only real verified records and never demo ones;
  - a demo run reads demo records plus any real verified ones, so real entries can sit beside demo ones and stay unflagged.
- **Flags:** every ranking row carries `is_demo`, which drives the DEMO tags.
- **Labels and insights:** demo prices are labelled "Illustrative demo price" or "… asking price". The insight text reads "illustrative demo data" instead of "verified data", and the score breakdown notes that the figures are demo data.

## 7. Map integration

- **Markers:** all seven cities have demo markers on the existing MapLibre map, at their real locality's centre plus a small offset, so no two share a spot.
- **Pop-ups:** each carries a "DEMO · illustrative" tag with the score, price and yield.
- **Unchanged behaviour:** clustering, map–list sync and "View performance" work as before. For example, the Lahore mall page shows 9 markers and the Peshawar apartments tab 24.

## 8. AI Advisor integration

The advisor is now **connected to the Central AI Brain:**
- **Path:** each question becomes a task for the **Investment Analyst** agent (`intent: advisor`), which uses a new registered read tool, `investment.advisor`. The tool was granted by migration and recorded in the agent's version history.
- **Controls:** permissions, budgets, audit (task and tool-call records) and grounded model wording all apply.
- **Chat:** investment questions in the website and WhatsApp chat use the same workflow.
- **Disclosure:** answers open with the demo notice, add "The projects named are fictional demo records", and tag each result DEMO. They are labelled "Answered by the Investment Analyst agent (Central AI Brain)".
- **Fallback:** if the agent is disabled or fails, the panel answers directly from the same data and says so.
- **Agent outputs:** the `investment.rankings` tool now reports `includes_demo`, so agent reports carry the demo notice instead of claiming "verified records".

Tested in the browser on your data. "Compare 5 Marla plots in Islamabad" returned four demo societies with MPIS, confidence, illustrative prices and 1-year changes, with the disclosure first.

## 9. Forecast data

- **Scenarios:** Conservative, Moderate and Optimistic, from the existing `GrowthScenarios`: past annual rates repeated for 3 years, available for 875 rows.
- **Shown with:** the period, data basis ("illustrative demo prices, hypothetical, not market data"), confidence, assumptions, key risks, and a highlighted line: *"DEMO: a hypothetical illustration built from invented demo prices. Not a market forecast and not an expected return."*
- **Unavailable cases:** rows without two years of history show "Forecast unavailable — insufficient verified data".

## 10. Admin demo management

**Admin → Market Data Centre → Demo Data** (`/admin/market/demo`):
- **Totals:** demo records, and real data shown separately (real projects, verified records, real sources); the published demo and real calculations.
- **Breakdowns:** demo projects and records by city, projects by category, ranking rows by property type.
- **Actions:** load demo data (import, idempotent), regenerate demo records (update), and recalculate demo rankings.
- **Demo mode:** on/off, or back to the `.env` setting.
- **Removal:** "Remove all demo data" requires typing `REMOVE DEMO DATA`. It deletes demo projects, records, indicators, sources and calculations only. A demo project or source that a real record refers to is kept and reported.
- **Filters:** Records list (Data: real only / demo only) and Projects list (`?origin=demo|real`).
- **Labels:** demo projects are tagged DEMO; demo calculations are tagged DEMO, and their detail page reads "observations (including illustrative demo records)".
- **Reports:** the admin "verified" figures exclude demo, because demo records are never `verified`.

**Command line:** `php artisan meezan:investment-demo seed | refresh | recalculate | remove [--force] | status | mode --on|--off`.

**Seeder:** `database/seeders/InvestmentDemoSeeder.php` is idempotent. Running it again created no duplicates (tested), and a real project with the same name and city is never changed (tested).

## 11. Demo mode configuration

- **Setting:** `INVESTMENT_DEMO_MODE` (default `true`), documented in `.env.example`. Your `.env` was not changed; the default applies.
- **Admin override:** the switch on the Demo Data screen is stored as the `investment.demo_mode` app setting.

| Setting | Public dashboards |
|---|---|
| **On** | The demo snapshot, with notices and DEMO tags. Demo project pages are reachable, with a "fictional demo project" notice |
| **Off** | Real verified data only. Demo projects disappear and demo project pages return 404. With no verified data, "No rankings are published yet" |

Tested on your MariaDB: off showed 0 demo names and no notice, and a demo project page returned 404; on brought them back.

**Always, whatever the mode:**
- Demo projects never appear on the general Projects page or in area guides.
- Demo-driven pages are `noindex`, and the sitemap lists only real rankings.
- No ItemList structured data is produced for demo pages.

## 12. Tests executed and results

**New tests:** `tests/Feature/InvestmentDemoDataTest.php`, 11 tests, all passing. They cover:
- flagging, the demo status, and nothing verified;
- idempotency;
- not touching a real project with the same name;
- separate demo and real snapshots, and choosing between them by mode;
- every property type scored for a city (full Lahore dataset);
- no "Verified transaction" labels;
- notices and DEMO tags on all pages, noindex, and sitemap exclusion;
- demo mode off;
- the advisor through the Investment Analyst agent (task, intent, tool call, disclosure, JSON), and its fallback when the agent is disabled;
- chat routing to the advisor workflow;
- safe removal (real records kept, a referenced demo project kept);
- the admin screen, mode switch, recalculation, guarded removal, and access control;
- the command.

**Related suites:** Investment Rankings (17), Phase 14 Investment Intelligence, Agents (64) and AI Assistant all pass. One expectation was updated on purpose: the advisor JSON now reports `rules-agent`, because it answers through the agent.

**Full suite (`php artisan test`), after all changes:** **762 passed, 4,259 assertions, 0 failed** (751 before this work, plus 11 new).

**On your MariaDB:**
- 147 page requests (7 cities × 11 property types and group pages, plus 4 special tabs per city, compare, `/investment` pages and the advisor): **all 200**.
- Browser checks at 1440 px and 375 px, including the in-browser advisor, the map, the chart and the forecast.

**One real bug found and fixed:** the first demo calculation on MariaDB failed with "MySQL server has gone away". Ranking rows were inserted 200 at a time, and with full price series a batch exceeded MariaDB's 1 MB `max_allowed_packet`. This would also have hit real data at scale. Inserts are now batched by size (under about 512 KB). SQLite tests could not show it.

| Command | Result |
|---|---|
| `npm run build` | Built |
| `composer audit` | No advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `vendor/bin/pint --test` | Passed |

## 13. Missing or incomplete components

1. **Real project names:** not used. The brief allowed recognisable names "where appropriate", but invented prices against real developments would misrepresent real businesses. Real **localities** are used instead.
2. **Images are illustrations,** not photographs. Licensed project photos are a content task.
3. **No demo CSV import.** The demo dataset is generated by the seeder; CSV import is for real data (projects, prices, rents, indicators).
4. **Few Provisional entries:** confidence varies between High and Medium, so the "Provisional" label rarely appears in the demo data.
5. **Mixed counts on city cards:** the society counts come from your real location directory, while the commercial-project counts include demo projects. Both are real counts of what is on show.
6. **Regenerating is slow on SQLite:** it rewrites every record and takes several minutes there (about a minute on MariaDB). The normal load is about a minute.
7. **One failed demo calculation (#2) stays in the history** from before the batch-size fix. It is marked failed, and "Remove all demo data" deletes it.

## 14. Instructions for switching to verified real data

1. **Enter real data** in the Market Data Centre: register licensed sources; add or import real projects (Projects → Import CSV); import or enter real price, rent and indicator records; review and verify them.
2. **Calculate and publish** a real ranking (Market Data Centre → Calculations). This is a separate snapshot, and the demo snapshot is not affected.
3. **Turn demo mode off** (Market Data Centre → Demo Data, or `INVESTMENT_DEMO_MODE=false` in `.env`, then `php artisan optimize`). The public dashboards then show only the real snapshot; with too little verified data they say so instead of inventing results.
4. **Remove the demo dataset** when you no longer need it: Demo Data → type `REMOVE DEMO DATA`, or `php artisan meezan:investment-demo remove`. Real records are never touched.
5. **Nothing else changes:** pages, tables, maps, charts and the advisor need no redesign; they read whichever snapshot is on show.

---

## Files created and modified

**Created:**
- `database/migrations/2026_11_04_000048_add_investment_demo_data_flags.php`
- `database/seeders/InvestmentDemoSeeder.php`
- `app/Services/Investment/DemoDataManager.php`, `AdvisorGateway.php`
- `app/Console/Commands/InvestmentDemo.php`
- `app/Http/Controllers/Admin/InvestmentDemoController.php`
- `resources/views/admin/market/demo.blade.php`, `resources/views/investment/partials/demo-notice.blade.php`
- `public/images/demo/investments/*.svg` (44 files)
- `tests/Feature/InvestmentDemoDataTest.php`
- This report

**Modified:**
- `config/meezan.php`: demo mode and notice, `demo` source type, the "Commercial land" project category for commercial plots
- `.env.example`
- **Models:** `Project` (demo, featured, bundled image paths), `MarketObservation`, `MarketIndicator`, `MarketSource`, `InvestmentRanking`, `InvestmentCalculation` (demo-aware current snapshot), `Concerns/HasReviewWorkflow` (the `demo` status)
- **Investment services:** `RankingCalculator` (demo runs, separate publishing, row flags, demo price labels, size-capped inserts), `RankingBrowser`, `InvestmentAdvisor`, `InvestmentInsights`
- **Agents:** `ToolRegistry` (the `investment.advisor` tool, demo flags in investment tools), `Workers/InvestmentAnalystAgent` (advisor workflow, demo disclosure), `AgentChatRouter`, `AgentCatalog`
- **Controllers:** `InvestmentRankingsController`, `ProjectController`, `AreaGuideController`, `SitemapController`, `Admin/ProjectController`, `Admin/MarketRecordController`
- **Views:** the rankings pages and partials (notice, DEMO tags, demo wording, noindex); `investment/index`, `investment/show`, `investment/compare`; `projects/show`; the admin market navigation, records, calculations and projects lists
- `resources/js/investment-rankings.js`, `resources/js/investment-map.js`
- `routes/web.php`
- `tests/Feature/InvestmentRankingsTest.php` (the one advisor expectation)
- `public/build/` (rebuilt)
