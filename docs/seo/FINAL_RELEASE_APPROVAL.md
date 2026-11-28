# Final release approval report — SEO, AEO and GEO release

Date: 28 November 2026. Status: **ready for deployment, awaiting your explicit approval.** Nothing has been committed, pushed or deployed. Production was only read (file checksums, read-only queries, public URLs).

Design, architecture, layout, public URLs, database schema and records, ranking logic and business functions are unchanged. The visible changes are the FAQ cards on city pages and area guides, and three items added to the About page's "What we offer" list.

## 1. Investment Index notices

Full details: `INVESTMENT_INDEX_NOTICES.md`. All three changed on 7 November in commit `7f663b0` (Index redesign), not in this release.

| Notice | Status now | Action |
|---|---|---|
| Insufficient data / "has not scored low" | Present on unranked project profiles in new wording ("…reflects a documentation gap rather than a negative assessment"), and shown live | None to the page; the test expects old wording |
| No price history | Shown only when a project has no price records at all. When only asking prices exist, the explicit "historical performance unavailable" line is gone; scoring still excludes asking prices | Proposed one-line restore (not made) |
| "Not illegal" | Still on Index project profiles; **removed from the category and city leaderboards** | Proposed one-line restore of the original sentence (not made) |

## 2. Source accuracy of FAQ answers

Every approval answer now states its source type, decided by the source link (`Faq::sourceKind`):

| City | Statuses | Source | Wording now |
|---|---|---|---|
| Lahore | 243 | Zameen News report of the LDA list (`zameen.com`) | "come from a secondary source, a published report of LDA's list, not from the authority's own website, so treat them as unconfirmed"; link labelled "Source (secondary report)" |
| Islamabad | 168 | CDA (`cda.gov.pk`) | "come from CDA's own published lists (official source)"; link "Official source" |
| Multan | 61 | MDA (`mda.punjab.gov.pk`) | official |
| Quetta | 299 | QDA (`qda.gob.pk`, confirmed as QDA's own site) | official |
| Karachi, Faisalabad, Peshawar | 0 | — | "has not yet recorded their approval status" |

Area-guide answers say the same per society. A secondary-source answer adds "Meezan Property has not confirmed this with {authority}". No approval status is invented: a status is only repeated, with its source note and the date it was read. `llms.txt` now describes sources the same way. A new test covers the classification.

**Existing page (not part of this release):** the area guide's "About this location" card shows "Approved (per authority list)" with a plain "Source" link, including for the Lahore Zameen News source. Proposed small correction (not made): label the link by source type, the same way the FAQ does.

## 3. Project indexing (unchanged)

Recounted on 28 November: **1,120 indexable, 153 noindex** (32 high, 5 medium, 116 low potential). No classification changed. See `PROJECT_INDEXING_REVIEW.md`.

## 4. Final regression

| Check | Result |
|---|---|
| Full test suite | **1,064 passed, 14 failed**. The 14 are the same tests that fail on the original commit `2600d66` (proven by running them against the hash-verified original files). No previously passing test fails. The one extra pass is the new source test. |
| SEO tests alone | 5 of 5 |
| Home, search (`/search?q=house`, filtered `/properties`), listing, project profiles (indexable and noindex), dealer directory, rankings, Lahore, area guide, `llms.txt`, sitemap | 200, expected robots tags (browser) |
| Registration | New local test buyer registered and landed on My account |
| Login and logout | Logout worked; login page works. The seeded local admin password is no longer valid, so I didn't guess one. |
| Inquiry form | Sent as the test buyer: "Thank you — your inquiry has been sent to the dealer." |
| Admin | `/admin`, listings (all, page 2, pending), dealers, settings and Index research all 200 (rendered in-process as the local admin, no password used) |
| Dealer | Dashboard, my listings, add listing all 200 |
| Mobile (375 px) | No horizontal scroll on home, 2 city pages, 2 area guides, a listing, a project, rankings, About and agents |
| Stylesheet | 2 rules added (FAQ card), 0 removed or changed |

Local test data created: one buyer account (`qa-release-…@meezanproperty.test`) and one inquiry on a local listing. Local only, not on production.

## 5. Production readiness

`PRODUCTION_DEPLOYMENT_CHECKLIST.md` covers:
- backup (database dump through a temporary protected option file, `.env` copy, previous commit recorded);
- the commit summary and deployment steps;
- cache clearing (`view:clear`, `cache:clear`, which also refreshes the hour-long sitemap and `llms.txt` caches);
- 17 post-deployment checks, the Core Web Vitals plan and rollback (revert the commit and clear caches; no database rollback needed).

No credentials appear in any document.

## 6. Clean file list

Compared with commit `2600d66`, file by file, with nothing unexpected:

- **Modified (28):** `.env.example`; `app/Http/Controllers/DealerDirectoryController.php`, `SitemapController.php`; `app/Models/Project.php`; `app/Services/InvestmentIndex/IndexBrowser.php`; `app/Support/Seo.php`; `config/meezan.php`; `public/build/manifest.json`; views `areas/show`, `cities/show`, `components/layouts/head`, `dealers/show`, `investment-index/category`, `compare`, `overall`, `project`, `pages/about`, `projects/index`, `projects/show`, `properties/index`, `properties/show`; `robots.txt`; `routes/web.php`; tests `InvestmentIndexPagesTest`, `InvestmentRankingsTest`, `NationalExpansionTest`, `PublicPagesTest`, `PublicShowcaseTest`, `SeoLaunchReadinessTest`.
- **Added:** `app/Http/Controllers/LlmsTxtController.php`, `app/Support/Faq.php`, `resources/views/components/faq.blade.php`, `tests/Feature/SeoIndexingTest.php`, `public/build/assets/app-DySRoiEN.css`, and `docs/seo/*` (reports and `noindex-project-profiles.csv`).
- **Removed:** `public/build/assets/app-CzCrREAc.css` (replaced by the new build).
- **Not to commit:** `scratch/` (measurement scripts, renders, CSVs), `storage/`.
- **Checks:** no `*.tmp`, `*.bak`, `zz-*` or debug calls in the project or on live.

## Remaining risks

| Risk | Level | Note |
|---|---|---|
| 14 Investment Index tests fail | Low for this release | Failing before; wording drift since 7 November. Fix as a separate task |
| Two missing honesty notices on Index pages ("not illegal" on leaderboards; "no history" with asking-only data) | Medium (trust and legal clarity) | Live today, not caused by this release; one-line restores proposed |
| Lahore statuses rely on a 2021 news report of the LDA list | Medium | Now clearly labelled secondary in the FAQ; the area card's plain "Source" label remains. Re-sourcing from LDA's own site is recommended |
| Removing 5 unranked leaderboards and 153 profiles from the index | Low | They stay public with "noindex, follow"; they return automatically once they qualify |
| Sitemap and `llms.txt` caches up to 1 h after deploy | Low | Cleared by `cache:clear` in the checklist |
| TTFB still about 1.2 s from network and TLS to the CDN edge | Low (outside the code) | Ranking pages' 2–3 s origin time is fixed in this release; the rest is a hosting matter |
| Local XAMPP MySQL had stopped and was restarted for testing | None for production | Local only |
| No Search Console data yet | — | No claims about indexing, ranking or AI citations until Search Console shows evidence |

## Decision needed

1. Approve deployment of this release as described.
2. Optional, in this release or later: the two one-line notice restores and the area-card source label.
