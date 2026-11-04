# Meezan Property — Global UI Fixes Report
## Clean entity names and one four-tab search everywhere

Date: 4 November 2026. Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB).

Nothing was committed, pushed or deployed; no `migrate:fresh` or `db:wipe` was run, and no record was deleted.

**Backup before renaming:** `C:\xampp\backups\MeezanProperty-before-global-ui-fixes-2026-11-04\meezanproperty.sql`. All 66 of 66 tables were dumped (40 MB).

---

## 1. Files inspected

**Search:**
- the shared `components/search-bar` (home hero, results bar, category bars);
- the page heroes: `cities/show` (its own Buy/Rent-only form), `areas/show` (society pages), `locations/listings`, `projects/index`, `projects/show`, `properties/index`, `properties/map` (its own tabs, "Plot"), `categories/show`;
- the Investment Rankings hero (`rankings/partials/hero`: Buy / Rent / Invest / Compare / AI Search);
- `app/Services/PropertySearch.php` (how `tab`, `purpose`, `group` and `type` combine), `app/Support/Lookup.php`, `components/area-combobox`, and `routes/web.php`.

**Names:**
- `app/Services/Investment/DemoDataManager.php`, which added " (Demo)" to every investment demo project;
- `database/seeders/DemoContentSeeder.php` (demo dealer "Imran Dealer (Demo)", agency "Demo Realty (Sample Data)");
- every rankings view and partial;
- `projects/show`, `investment-map.js`, `investment-rankings.js`, `InvestmentAdvisor`, `InvestmentInsights` and `RankingCalculator` (price labels);
- the bundled illustrations (`public/images/demo/investments/*.svg`, stamped "DEMO");
- the database: project names, user names, agency names, listing titles and area names.

## 2. Files modified

**Change 1 (names):**
- `app/Services/Investment/DemoDataManager.php`:
  - names without " (Demo)";
  - records from the first dataset version are recognised and **renamed in place**;
  - figures are generated from a stable key, so renaming never changes a project's figures, status or position;
  - fictional developer names and source names without "Demo".
- `app/Services/Investment/RankingCalculator.php`: price labels "Illustrative price" / "Illustrative asking price".
- `app/Services/Investment/InvestmentInsights.php`, `InvestmentAdvisor.php`: illustrative wording.
- `config/meezan.php`: source-type label "Illustrative data (not real)".
- `database/seeders/DemoContentSeeder.php`, plus the new `database/migrations/2026_11_04_000049_tidy_demo_display_names.php`.
- **Views:**
  - `rankings/index`, `city`, `group`, `segment`, `compare`;
  - partials `badges`, `hero`, `featured`, `upcoming`, `overview`, `performance`, `forecast`, `segment-results`, `city-results`, `advisor`;
  - `investment/partials/demo-notice`, `projects/show`.
- `resources/js/investment-map.js`, `resources/js/investment-rankings.js`.
- `public/images/demo/investments/*.svg`: regenerated without the "DEMO" box.

**Change 2 (search):**
- **New:**
  - `resources/views/components/search-tabs.blade.php`: the four tabs, used everywhere;
  - `app/Http/Controllers/SearchController.php` with the route `GET /search` (named `search`).
- **Rewritten:** `resources/views/components/search-bar.blade.php`, the one shared search.
- **Using the shared search now:**
  - `cities/show` (replaced its own form), `areas/show`, `locations/listings`, `projects/index`, `projects/show` (added);
  - `properties/index` (passes category and size), `properties/map` (shared tabs as links), `rankings/partials/hero` (investment mode).
- `resources/js/app.js` (tab switching), `routes/web.php`.

**Tests:**
- new `tests/Feature/GlobalUiFixesTest.php`;
- updated `tests/Feature/InvestmentDemoDataTest.php`: its name and label expectations now follow the new requirement, and the demo-flag assertions are kept.

## 3. Number of entity names corrected

| Entity | Before | After | Count |
|---|---|---|---:|
| Investment demo projects (societies, apartments, malls, plazas, towers, commercial land, mixed use, upcoming) | "Iqbal Town Fashion Mall (Demo)" | "Iqbal Town Fashion Mall" | **420** |
| Their fictional developers | "Indus Demo Builders (fictional)" | "Indus Builders (fictional)" | 420 projects, 8 names |
| Demo market sources (shown under Details and in advisor answers) | "Meezan Demo Dataset — …" | "Meezan Illustrative Dataset — … (not real)" | 3 |
| Public demo dealer | "Imran Dealer (Demo)" | "Imran Dealer" | 1 |
| Demo agency | "Demo Realty (Sample Data)" | "Sample Realty" | 1 |
| Published ranking rows (entity names) | — | Recalculated (demo calculation #7) | 3,522 rows, 0 with "Demo" |

**How the projects were renamed:**
- **Same records:** renamed in place, so no project was duplicated or deleted (420 before, 420 after).
- **Unchanged:** ids, records, map positions and figures. Slugs were regenerated from the new names.
- **Real projects:** none exist, so none were touched. The renamer skips any demo record whose new name would match a real project in the same city.
- **Kept on purpose:** the admin and buyer sample accounts still carry "(Demo)". They are never shown publicly, and the admin account is yours.

**Checked on the rendered pages:**
- Visible text (HTML stripped) of the Investment Rankings landing, city, group and property-type pages, compare, the `/investment` detail page, a project page, and the advisor JSON.
- **Zero** "Demo" mentions outside the page-level disclosure notice.
- A 120-page sweep on MariaDB found no "(Demo)" anywhere.

## 4. Confirmation that internal demo flags remain intact

On MariaDB after the change:

| Item | Count |
|---|---:|
| Projects with `is_demo` | 420 |
| Market observations with `is_demo` and status `demo` | 7,187 |
| Demo indicators | 3,524 |
| Demo sources | 3 |
| Published demo calculation | #7 (`includes_demo`) |
| Demo listings | 16 |

**Still in place:**
- the demo and verified separation (demo records are never `verified`);
- demo mode, `INVESTMENT_DEMO_MODE`, and the admin switch;
- Admin → Market Data Centre → Demo Data (counts, load, recalculate, guarded removal);
- the Demo/Real filters in the admin records and projects lists, and the DEMO tags **in the admin**;
- `noindex` and sitemap exclusion for demo pages.

All of these are covered by tests.

**Disclosure kept, as requested:**
- **Page notice:** every demo-driven page still shows *"DEMO DATA — Illustrative figures for platform preview only. … The projects shown are fictional examples."*
- **Project pages:** "This is a fictional example project".
- **Detail panel:** a neutral "Illustrative data" marker.
- **Illustrations:** still read "Illustrative image · not a real project".
- **Advisor:** answers still open with the notice.

The repetitive per-entity tag next to every name is gone.

## 5. Pages updated with four search tabs

One component, `x-search-bar` with `x-search-tabs`. Every page shows **Buy | Rent | Plots | Commercial** in that order, with the tab that matches the page selected:

| Page | Selected tab |
|---|---|
| Homepage hero | Buy |
| City pages (`/lahore`, …) | Buy (previously only a Buy/Rent dropdown) |
| Society / area pages (`/areas/…`) | Buy, pre-filled with the society (new) |
| City and area listing pages (`/lahore/plots-for-sale`, …) | Matches the page |
| Category pages `/buy`, `/rent`, `/plots`, `/commercial` | Matches the page |
| Search results `/properties` | Kept from the URL |
| Map search `/map` | As links; "Plot" is now "Plots" |
| Projects list and project pages | Project pages open on Commercial for commercial projects |
| Investment Rankings (landing, city, residential, commercial, every property type: societies, plots, houses, apartments, malls, plazas, towers, offices, shops, commercial plots, mixed use) | Plot pages → Plots; commercial pages → Commercial; yield-sorted → Rent; otherwise Buy |

**Look:**
- white tabs; the selected tab is purple with a gold underline (it now keeps its colours on hover);
- the same padding, type and radius on every page;
- a compact size inside bars and narrow panels.

## 6. Search functionality tested

**Property search** (submits to `/search`, which redirects to the results and keeps the tab in the URL):

| Tab | Searches | Filters |
|---|---|---|
| **Buy** | Listings for sale (homes, plots, commercial) | Location or society, type, sale budget, beds |
| **Rent** | Listings for rent (homes and commercial) | Type, **monthly rent** ranges, beds |
| **Plots** | Residential and commercial plots | Plot type, size (3 Marla – 2 Kanal), budget; no bedroom filter |
| **Commercial** | Shops, offices, warehouses, buildings and commercial plots | Type, budget, size, **investment category** (malls, plazas, towers, offices, shops, commercial plots, mixed use) and **development status**. With a category it opens that category's Investment Rankings with the budget and status |

**Investment search** (Investment Rankings pages) uses the same tabs:

| Tab | Opens |
|---|---|
| **Buy** | Residential investments (societies, plots, houses, apartments) |
| **Rent** | Rental-income investments, **ranked by yield** (expected return filter) |
| **Plots** | Residential and commercial plot rankings (plot size) |
| **Commercial** | Commercial investments (category, yield, development status) |

**Field handling:**
- **Swapping:** fields belong to tabs. Fields of other tabs are hidden **and disabled**, so they are never sent. The page script swaps them when the tab changes; without JavaScript the starting tab's fields work.
- **Clean-up:** the server drops a type that doesn't belong to the tab (for example a house left over after switching to Plots), and drops beds from Plots and Commercial searches.

**Checked on MariaDB:**
- 13 tab, filter and redirect combinations;
- result pages per tab: Buy 12 for sale, Rent 5, Plots 4, Commercial 3, commercial plots 1, each with the right tab still selected;
- in the browser: switching to Commercial on the Lahore page showed and sent only the commercial fields.

## 7. Desktop and mobile testing

- **Desktop (1366 px):**
  - the city page shows the four tabs and a one-row panel;
  - field switching works;
  - the rankings hero shows the compact four tabs. The fourth tab was cut off at first; fixed by using the compact size in narrow panels.
- **Phone (375 px):** home, city page, plot results, map, rankings landing, business-tower dashboard, society page, projects, `/plots` and `/lahore/commercial-for-rent` were measured with no sideways scrolling and all four tabs fitting. The map's tabs needed scrolling at first; fixed with slightly tighter padding on phones.
- **Page sweep:** 120 pages on MariaDB (every city × every property type and group, the city and category pages, map, results, projects and a society page) returned 200, each with the four tabs and no "(Demo)".

## 8. Automated test results

**New tests:** `tests/Feature/GlobalUiFixesTest.php`, all passing. They cover:
- natural names with demo flags intact;
- no "Demo" in visible text, map markers, project page or advisor;
- the first-version rename creating no duplicates and leaving the figures unchanged;
- admin demo management and demo mode;
- **the four tabs, in order, with the right one selected on 20 pages**;
- per-tab filters (hidden and disabled);
- `/search` redirects for every tab, including the investment mode and invalid input;
- the right listings per tab.

**Updated:** the `InvestmentDemoDataTest` expectations now follow the new naming (no "(Demo)", "Illustrative" labels). Its demo-flag, separation, mode and removal checks are unchanged.

| Check | Result |
|---|---|
| Targeted run (new and demo tests) | 37 passed, 260 assertions |
| Full suite (`php artisan test`) | **788 passed, 4,386 assertions**, 0 failed (241 s) |
| `vendor/bin/pint --test` | Passed |
| `composer audit` | No advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `npm run build` | Built |

## 9. Remaining issues

1. **Sample property listings (resolved after the first report).** The 16 sample property listings showed a "Demo listing" tag. At your request it now reads **"Sample listing"** on listing cards, AI finder results and the listing page. The tag stays because those listings have real inquiry forms.
   - The page notice now reads "an example for platform preview. This is not real inventory…".
   - Migration `2026_11_04_000050_reword_sample_listing_descriptions` rewrote the seeded opening line of the 16 descriptions, which also appears in the page's meta tags. It touches only listings still flagged `is_demo` that still begin with the seeded line, and it can be rolled back.
   - The seeder writes the new wording.
   - The properties table was backed up first, to `C:\xampp\backups\MeezanProperty-before-sample-listing-2026-11-04\properties.sql`.
   - All 16 listings keep `is_demo`. The admin pages still say "Demo listing" internally.
   - Two tests were added. After this change the full suite passes: 790 tests, 4,395 assertions.
2. **Real development names not used.** Your examples (Packages Mall, DHA Lahore, Bahria Town) are names of real developments. The demo dataset was never given real project names, because invented prices against them would misrepresent those businesses. Demo projects use real localities plus generic names, for example "Iqbal Town Fashion Mall". Without "(Demo)" a visitor could take them for real developments, so the page notice, the project page notice and the noindex rule stay.
3. **Commercial rentals.** On the Commercial tab the budget is a sale budget. Commercial rentals are found with the **Rent** tab, whose types include shops and offices. The `/…/commercial-for-rent` pages open on the Commercial tab.
4. **Commercial investments without a category.** The investment Commercial tab without a category opens the commercial overview, where the yield and status filters don't apply; choose a category to filter.
5. **Old links.** Project slugs changed with the names, so old links to demo project pages (which were never in the sitemap) return 404.
6. **Account dashboard** has its own small search box; it is not a public page and was left unchanged.
7. **Palette:** kept as the Brand Style Guide colours (#4B2E83 purple, #D4AF37 gold), per your 30 October decision. The brief again quotes #4B1592 / #D6A13D / #21133A.
