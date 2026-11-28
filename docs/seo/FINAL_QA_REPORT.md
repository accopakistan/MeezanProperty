# Final QA report — SEO, AEO and GEO release

Status on 28 November 2026: **ready for deployment, waiting for owner approval.** Nothing has been committed, pushed or deployed, and production was not modified. The only access to live in this review was read-only: file hashes, one read-only query, timings and public URLs.

## Scope respected

No redesign, architecture change, schema change, public URL change, data change, business-logic change or ranking-methodology change. All changes are listed in `FILES_MODIFIED.md`.

## Results by task

| Task | Result | Report |
|---|---|---|
| 1. The 14 failing tests | All 14 fail identically on the original commit `2600d66` (files verified by hash; same tests, same messages, same lines). They come from earlier ranking-page wording changes, not from this release. The 4 new SEO tests pass on their own. | `FAILED_TESTS_ANALYSIS.md` |
| 2. The 153 noindex profiles | Listed from live with reason and recommendation: 32 high potential (official MDA/FDA lists whose details were not captured), 5 medium (tall buildings), 116 low (malls known only by name). Policy unchanged: noindex, follow until real facts exist. | `PROJECT_INDEXING_REVIEW.md`, `noindex-project-profiles.csv` |
| 3. Server performance | Normal pages spend about 0.3 s at the origin (Laravel boot 0.16 s, database 8 ms). Most of the 1.2–1.8 s first byte is network and TLS to the CDN edge, the same as for static files. The ranking pages (2–3 s at the origin) are fixed in this release. A full-page cache is not recommended now: small gain, real CSRF and session risk. A safe plan is documented. | `PERFORMANCE_RECOMMENDATIONS.md` |
| 4. Visual regression | CSS: 2 rules added, 0 removed. Markup on 40 pages: changes only on city pages and area guides (the FAQ card) and About (3 list items). The FAQ card uses existing styles and works at 375 px. | `VISUAL_REGRESSION_REPORT.md` |
| 5. Live SEO readiness | 17-step verification list for after deployment | `PRODUCTION_DEPLOYMENT_CHECKLIST.md` |
| 6. Core Web Vitals | Test plan for 6 pages, mobile and desktop, lab vs field kept separate | `PRODUCTION_DEPLOYMENT_CHECKLIST.md` |

## Fixes made during this review

| Fix | Why |
|---|---|
| City FAQ wording: "mostly as recorded from LDA approval lists", link labelled "Most-cited source" (was "mostly from the list published by LDA" / "Authority list used") | For Lahore, the most-cited source is a Zameen News report of the LDA list, not LDA's own website. The old wording overstated it. Re-rendered and checked; related tests pass (67 of 67). |

## Test summary

| Suite | Result |
|---|---|
| Full suite (after the first round) | 1,063 passed, 14 failed (all failing before this release) |
| SEO tests alone (`SeoIndexingTest`) | 4 of 4 passed |
| Sitemap, city, launch-readiness tests after the wording fix | 67 of 67 passed |
| All 1,193 local sitemap URLs: 200, `index`, one canonical matching the sitemap | passed |

## Open items (not blocking deployment)

- Owner: Search Console and Bing verification, sitemap submission, official profiles for `MEEZAN_SAME_AS`, a Meezan Bank non-affiliation statement if true, an analytics decision.
- Decide test vs page for the 14 Investment Index tests; cases 7, 8 and 12 concern honesty labels and should be looked at first.
- Research content for the 32 high-potential profiles; general buyer guides with sources and a named reviewer.
- After deployment: Rich Results Test, Schema.org Validator, PageSpeed Insights.
- Existing CSP error from inline `onerror` image fallbacks on investment pages (bug that predates this release).
- Local XAMPP MySQL was found stopped during this review and was started to finish the checks. It is local only.

Nothing in these reports claims that pages are indexed, rank better, or are cited by AI assistants. That needs Search Console data and real queries after deployment.
