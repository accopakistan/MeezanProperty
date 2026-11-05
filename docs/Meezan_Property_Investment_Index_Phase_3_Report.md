# Meezan Investment Index: Phase 3 Report
## Public leaderboards, Overall Index, project profiles, methodology and weights editor

Date: 4 November 2026. Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB 10.4).

Nothing was committed, pushed or deployed. No migration was needed, nothing was deleted, and `.env` was not changed.

**Backup before changes:** `C:\xampp\backups\MeezanProperty-before-investment-index-phase3-2026-11-04\meezanproperty.sql`, 76 of 76 tables.

---

## 1. What was inspected

**The earlier public rankings module (`/investment-rankings`)** had city, group (residential, commercial, mixed use) and segment pages for 11 property types, including individual plots, houses, shops, offices and commercial plots. It also had compare pages, a map, an advisor box and an investment-mode search.
- **Linked from:** the header and footer ("Investment Rankings"), the sitemap and the shared search.
- **Tested by:** four test files.

**The new Index engine (Phases 1–2)** had data but no public pages.

## 2. What Phase 3 built

The address and the menu item stay the same: **Investment Rankings** at `/investment-rankings` now shows the Meezan Investment Index.

| Page | Address | Shows |
|---|---|---|
| Home | `/investment-rankings` | The five leaderboards (top 3 each), the Overall Index (top 5), and the evidence, legal-safety and no-paid-placement rules. "Insufficient Verified Data" wherever nothing is ranked |
| Leaderboards | `/investment-rankings/{housing-societies, commercial-plazas, apartment-buildings, shopping-malls, business-towers}` (optional `?city=`) | Pakistan-wide ranks: score, Data Confidence Score, risk, legal status, verified yield, last verification. "Assessed, not ranked" lists the other projects by name and status (excluded, not eligible, insufficient data) with the reason and **no score**. The category's weights come from the active methodology |
| Overall Index | `/investment-rankings/overall` | Overall rank, category and category rank, normalized score, confidence and Index value. Only categories with at least 3 ranked projects take part |
| Project profile | `/investment-rankings/projects/{project}` | Details below |
| Methodology | `/investment-rankings/methodology` | Generated from the methodology in force: the weights per category, evidence rules, sources, legal safety, confidence, risk classes, Overall Index formula, independence and updates |

**Every field the brief requires is on the project profile:**
- project name, category, city and developer;
- legal verification status, operational status and development progress;
- investment score, data confidence and risk classification;
- verified rental yield and historical performance, if available;
- ranking position, also in the Overall Index;
- last verification date;
- original data sources: official sources link to their own row (for example `https://lda.gop.pk/approved-schemes#58`), while owner and audited documents are named, not linked, because the documents are private;
- reasons for ranking changes.

The profile also has a score breakdown by factor, the risks found, official legal records, the score over time, and "Submit evidence" for owners.

**Rules on every page:**
- **No score is shown for a project that isn't ranked.** A pending project shows "scored X; not ranked".
- **Search engines:** pages without ranked results are marked noindex.
- **Refused profiles:** demo, unpublished and out-of-scope projects (mixed use, commercial land) return 404.
- **Search bar:** the shared Buy | Rent | Plots | Commercial search appears on every Index page.

### Earlier pages: permanent redirects (301)

> **Changed on 4 November 2026:** the redirects for city, group, the five categories and compare were replaced by the restored pages. See `Meezan_Property_Investment_Ranking_Recovery_Report.md`.

| Earlier address | Now |
|---|---|
| `/investment-rankings/{city}` and `/{city}/{group}` | Index home |
| `…/societies`, `…/apartments`, `…/shopping-malls`, `…/commercial-plazas`, `…/business-towers` (and their `/compare`) | That Index leaderboard, with the city kept as `?city=` |
| `…/plots`, `…/houses`, `…/commercial-shops`, `…/office-spaces`, `…/commercial-plots`, `…/mixed-use` | Index home: no longer ranked |

**Kept:**
- The `/investment-rankings/advisor` JSON endpoint and the earlier engine (Market Data Centre, admin calculations), which the AI tools still read.
- The earlier views and scripts (`resources/views/rankings`, `investment-map.js`, `investment-rankings.js`) are left on disk, unused.

### Site-wide changes

- **Search, investment mode:**
  - Rent goes to Apartment Buildings;
  - Buy and Plots go to Housing Societies (plots aren't ranked);
  - Commercial goes to the chosen category, or the Index home.
- **Search, property mode:** Commercial plus an investment category opens that category's leaderboard. The menu offers only the three commercial Index categories (malls, plazas, towers).
- **Sitemap:** the methodology page always; then each category with ranked projects, the Overall Index once it has entries, and each ranked profile. The earlier segment addresses are gone.
- **Project pages:** in-scope developments link to their **Investment Index profile**, alongside "Submit evidence".
- **Investment Analyst agent:**
  - Its default workflow now reports the **Index leaderboards**: the category asked about ("shopping malls", "plazas", "towers", "societies", "apartments"), or all five.
  - The earlier MPIS snapshot is added only when one is published.
  - Index words ("investment index", "leaderboard", "shopping mall"…) route to it.

### Admin: methodology and weights

**Admin → Investment Index → Methodology.**
1. **Draft** a new version from the active one, then edit each category's weights. They're validated: whole numbers, summing to 100, and legal approval can't be 0. A reason is required.
2. **Activate** it. The previous version is retired, the Index recalculates automatically, and every resulting change records "Methodology changed from version X to Y".
3. **Public page:** the methodology page shows the new version at once.

Every step is in the audit log.

## 3. Files

**New:**
- **Controller:** `app/Http/Controllers/InvestmentIndexController.php`.
- **Service:** `app/Services/InvestmentIndex/IndexBrowser.php`.
- **Public views:** `resources/views/investment-index/` — `home`, `category`, `overall`, `project`, `methodology`, plus partials `hero`, `leaderboard`, `status`, `insufficient`.
- **Admin view:** `resources/views/admin/investment-index/methodology.blade.php`.
- **Tests:** `tests/Feature/InvestmentIndexPagesTest.php`.

**Changed:**
- `routes/web.php`: the rankings routes now serve the Index, with the legacy redirects and the admin methodology routes.
- `app/Http/Controllers/SearchController.php`.
- `app/Http/Controllers/SitemapController.php`.
- `app/Http/Controllers/Admin/InvestmentIndexController.php` (methodology actions).
- `app/Services/Agents/Workers/InvestmentAnalystAgent.php`.
- `resources/views/components/search-bar.blade.php` (commercial categories).
- `resources/views/projects/show.blade.php`.
- `resources/views/admin/investment-index/partials/nav.blade.php`.
- Rebuilt front-end assets.

## 4. Tests

| Check | Result |
|---|---|
| New page tests (9) | Passed. They cover: the home page with the five leaderboards and Overall Index; ranks and "assessed, not ranked" with no score; the city filter; noindex on empty pages; the Overall Index peer rule; every required profile field with official links and unlinked owner documents; no score for excluded or thin projects; 404 for demo, unpublished and mixed-use; the sitemap; the weights editor (sum and legal-weight validation, activation, recalculation, the change reason, the public page following, admin only); the Investment Analyst reporting the Index; the search bar's commercial categories |
| Earlier rankings tests | Converted. Checks of the retired pages now check their 301 redirects; checks of the earlier engine's browsing, price charts, admin categories and imports stay. Two tests were **removed** because their feature is gone with the segment pages: the segment map markers, and the sitemap listing of segment pages (now covered by the Index sitemap test). The demo-data tests now check that the Index never shows demo data, and that the Investment Insights preview still discloses it |
| Full suite | **798 passed, 4,714 assertions**, 0 failed; Pint passed; assets built |
| Real pages (local server on MariaDB) | Home, five leaderboards, Overall Index and methodology return 200. Every earlier address returns 301 to the right page. The sitemap is correct for today's data |
| MariaDB with ranked fixtures (rolled back) | Three societies ranked; home, leaderboard (Lahore), Overall Index, profile (rank #1, official LDA link) and methodology all rendered; counts unchanged after rollback |
| Browser | Desktop: home and shopping-mall leaderboard checked. Phone (375 px): home, methodology, housing societies and Overall Index have no sideways scrolling. No console errors |

## 5. What you'll see today

All 420 projects in the database are demo data, which the Index never uses. Until real developments are added and their official records or documents are verified, every leaderboard and the Overall Index correctly say **Insufficient Verified Data**.

The fastest way to see real rankings:
1. Authorize the LDA sources (Admin → Investment Index → Official sources).
2. Add real housing societies (Admin → New Projects). They're matched to the LDA lists automatically.
3. Check owner documents as they arrive.

## 6. Remaining issues

1. **No Index map yet.** The earlier segment map was retired with those pages. A map of ranked projects can be added once real projects have positions.
2. **No AI question box on the Index pages.** This was deliberate, because the free Gemini tier allows about 20 requests a day. The Investment Analyst answers Index questions through the AI Brain.
3. **Investment Insights (`/investment`)** keeps the earlier area-level market pages, and its demo preview when an admin switches demo mode on. It isn't part of the Index.
4. **Earlier views and scripts** remain on disk, unused. They can be deleted later once you're happy with the Index pages.
5. **Phase 4** is QA across the whole Index with real projects, plus final documentation.
6. **Palette:** kept as the Brand Style Guide colours (#4B2E83 / #D4AF37).
