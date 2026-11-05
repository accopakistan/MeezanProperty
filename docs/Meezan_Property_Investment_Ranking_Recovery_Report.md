# Meezan Property: Investment Ranking Recovery Report

Date: 4 November 2026. Nothing was committed, pushed or deployed. No migration ran, nothing was deleted, and `.env` was not changed.

## 1. Why the pages became empty

1. **Routes:** Phase 3 of the Investment Index pointed every earlier city, group, segment and compare address at redirects.
2. **Demo mode:** its default changed to off. All ranking data in the database is demo data, so a page reading "verified data only" has nothing to rank.

No ranking data was lost. The engine, controller, views and scripts were all still in place.

## 2. What was restored

| Address | Now |
|---|---|
| `/investment-rankings` | Investment Index home, unchanged, with a new "Explore by city" section |
| `/investment-rankings/pakistan` | The earlier all-Pakistan dashboard |
| `/investment-rankings/{city}` | The earlier city dashboard, with that city's Index results added |
| `/investment-rankings/{city}/{group}` | Residential or commercial group page |
| `/investment-rankings/{city}/{group}/{category}` | Category dashboard: table, filters, map, chart, forecast scenarios, comparison, advisor |
| `/investment-rankings/{city}/{group}/{category}/compare` | Comparison |
| `/investment-rankings/{category}`, `/overall`, `/methodology`, `/projects/{project}` | Investment Index, unchanged |
| `…/plots`, `…/houses`, `…/commercial-shops`, `…/office-spaces`, `…/commercial-plots`, `…/mixed-use` | Permanent redirect to that city's dashboard: these are not ranked |

**A decision to review:** both the Index home and the earlier dashboard used to live at `/investment-rankings`. The Index home stayed there, because it is where verified results appear, and its links and sitemap entries already point there. The earlier dashboard moved to `/pakistan`. Swapping them is a small change.

## 3. How the verified-data rules work

- **Public visitors, signed in or not, never see demo data**, even with demo mode on. `InvestmentCalculation::demoVisible()` needs demo mode on and a signed-in administrator. It now guards the ranking snapshot, the dashboards, `/investment` and project pages.
- **Administrators** keep the labelled, noindex preview, plus the Demo Data screen and every calculation.
- **With no verified snapshot,** pages say "Insufficient Verified Data" and explain that projects are undergoing data verification. No score, yield or chart is shown.
- **Search engines:** pages without verified scores are noindex. The sitemap lists only dashboards with verified scores in the five categories.
- **The AI advisor** answers from data without starting a task or a Gemini request when nothing verified is published. This stops visitors using up the free daily quota.

## 4. Five categories on the restored pages

- Public dashboards offer only housing societies, apartment buildings, shopping malls, commercial plazas and business towers.
- The apartments page lists apartment buildings only: no area-level rows and no mixed use.
- A project the Investment Index **excluded on legal grounds** appears as "Excluded: legal issue", with no score or figures, even if an earlier MPIS snapshot scored it.
- City pages and the all-Pakistan dashboard show the Index's top projects for that place, and category pages link to the matching leaderboard.
- Marketplace listings aren't affected: plots, houses, shops and apartments are still bought and sold as before.

The other segments stay in the engine and its history, for the AI tools and the admin. If an administrator later publishes a real MPIS snapshot, the advisor could still mention a plot or house segment. Its link leads to the city dashboard.

## 5. What was preserved

| Record | Count, before and after |
|---|---|
| Ranking rows, all calculations | 17,685 |
| Calculations (including #7 with 3,522 rows) | 6 |
| Market observations / indicators | 7,187 / 3,524 |
| Projects | 420 |
| Users / dealers / properties | 4 / 2 / 18 |
| Leads / subscriptions / payments / invoices | 2 / 1 / 2 / 1 |
| AI agents / tasks | 16 / 186 |

75 of the 76 tables have the same row count as this task's backup. The other is `property_view_stats`, 7 to 8, from one real page check.

## 6. Files changed

**New:** `resources/views/rankings/partials/insufficient.blade.php`, `index-boards.blade.php`.

**Changed:**
- `routes/web.php`, `InvestmentRankingsController`, `InvestmentIndexController` (redirect methods removed), `RankingBrowser`, `InvestmentCalculation`, `ProjectController`, `AdvisorGateway`, `SitemapController`
- Views: `rankings/index`, `city`, `group`, `segment`, `compare`, and the `category-nav`, `city-results`, `segment-results`, `badges` and `advisor` partials; `investment-index/home`; `admin/market/demo`
- `.env.example` (comment only)
- Tests: `InvestmentRankingsTest`, `InvestmentDemoDataTest`, `GlobalUiFixesTest`

No second engine, new table or new controller was added.

## 7. Test results

| Kind | What | Result |
|---|---|---|
| Automated | Full suite, including 10 new or restored ranking tests | **809 passed, 4,901 assertions, 0 failed** |
| Automated | Tests changed on purpose: redirect checks became page checks; demo previews now run as an administrator; the public must not see demo data | Passed |
| Real pages, MariaDB | 33 public addresses as an anonymous visitor | All returned the expected page or redirect. No "DEMO DATA" and none of 60 sampled demo project names on any page. Demo project pages return 404 |
| Real pages, MariaDB | 10 admin pages, including calculation #7 | Opened without errors |
| Browser | City and category dashboards at desktop width; six pages at phone width | Correct layout, no sideways scrolling, no console errors |
| Build and style | `npm run build`, Pint, route/config/view cache then clear | Passed; the build produced no asset changes |
| Marketplace | Home, listings, listing detail, map, agents, pricing, projects; the full test suite | Passed. Row counts unchanged |

**Not tested:**
- Real rankings on real public data, because none exists yet. Ranked pages were tested with test fixtures only.
- The restored map script drawing real markers: the marker data is tested, and today's pages have no markers.
- Dashboards and admin pages while signed in, in a real browser: the admin password is the owner's. They were checked through the application on MariaDB and in tests.

## 8. What you'll see today

Every ranking page correctly says **Insufficient Verified Data**, because every project in the database is a demo project. Real rankings appear only after real data is added:
1. Review and activate the LDA sources (Admin → Investment Index → Official sources).
2. Add real developments (Admin → New Projects).
3. Check owner documents as they arrive, and enter verified market records for MPIS.
