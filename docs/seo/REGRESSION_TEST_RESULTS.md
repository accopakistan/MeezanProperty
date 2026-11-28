# Regression test results

## Automated tests

Full suite run locally on 27 November 2026 after all changes:

**1,063 passed, 14 failed (6,413 assertions, 342 s).**

The 14 failures are the same Investment Index tests that were already failing before this work. They were recorded on 19 November and are caused by wording changed in the 7–13 November redesigns. To confirm this, the 5 failing tests in the files touched here (`InvestmentIndexPagesTest`, `PublicShowcaseTest`) were run against the last committed code with these changes removed: the same 4 failed there too. No test that passed before now fails.

New tests (`tests/Feature/SeoIndexingTest.php`, 4 tests):

- Project profiles with facts, a developer or an approval status are indexable and in the sitemap; name-only profiles are "noindex, follow" and out; demo profiles are out.
- The agents sitemap lists public dealers only (not sample, suspended or empty profiles).
- The research sitemap lists only indexable research pages; the empty Overall Index is "noindex, follow".
- No URL appears twice across sitemap files.

Updated tests (the sitemap is now an index of files; empty leaderboards now leave the sitemap): `InvestmentIndexPagesTest`, `InvestmentRankingsTest`, `NationalExpansionTest`, `PublicPagesTest`, `PublicShowcaseTest`, `SeoLaunchReadinessTest`.

## Checks outside the test suite

| Check | Result |
|---|---|
| Every sitemap URL: 200, indexable, one canonical equal to the sitemap URL (1,193 URLs) | pass |
| Market board order identical before and after the speed fix (120 combinations) | pass |
| JSON-LD on 19 pages parses, no empty values, FAQ text visible on page | pass |
| Local crawl from the home page (6,000 pages): broken internal links | none found |
| Every real project profile linked from the directory pagination (1,273 of 1,273) | pass |
| Browser: home, Lahore, an area guide, a listing, `/projects?page=5`, rankings, About, login all load | pass |
| Browser console errors | one existing CSP error from an inline `onerror` image fallback in the investment hero (`components/investment-hero.blade.php`). It predates this work; see `REMAINING_ISSUES.md` |
| Phone width (375 px): area guide with the FAQ, no horizontal scroll | pass |

## Visual regression

No "before" screenshots were taken, so this is not a pixel comparison. What changed on screen:

- City pages and area guides: a new "Questions about …" card at the end of the page, in the same card style as the existing "About this location" card. Checked on desktop and at 375 px.
- About page: three more items in the existing "What we offer" list.

No other template's markup was changed apart from `<head>` metadata and JSON-LD, which are not visible. Login, registration, admin, dealer dashboard, home, listings, project profiles and investment pages render as before.

Not tested here: user registration, form submissions, and admin and dealer flows end to end in the browser. They are covered by the automated suite only, and the code behind them was not changed.
