# Meezan Property: Final QA Report

Date: 6 November 2026. **Local copy only.** The live site wasn't deployed or tested, so this isn't a live QA. See the production deployment report.

## Automated tests

| Run | Result |
|---|---|
| Full suite | 1,021 tests: 1,020 passed on the final code. The one failure was an older test expecting the old "Top Housing Societies" heading; I updated it, and its file then passed (11 of 11) |
| New `Top10AndAuthorizedListingsTest` | 10 passed: top-10 limit, city filter, honest headings, rank beyond 10 hidden, Overall limit, publication-basis gate, import dry run and commit, import refusals, test-account rule, sample-content command |
| Earlier `PublicShowcaseTest` | 34 passed |
| Pint | Passed |

## Checks on the real local database (MariaDB)

| Check | Result |
|---|---|
| Sample listings shown publicly | **0** (`Property::published()` returns 0; 19 listings kept, archived or draft) |
| Sample or test dealers listed or "Verified" | **0** (3 flagged) |
| Placeholder text published | 0 |
| Imported listings without a basis | None imported. Every real listing has `direct_submission` |
| Published rankings with evidence below the rules | 0 ranked; run #28 published |
| A category showing more than 10 ranked | Can't happen: query-level limit, tested with 12 and 14 |
| Provisional score shown as verified | No. The provisional tier is hidden; `provisional_public` is off |
| Gemini active | Yes; it answered today's connection test |
| Agents | 16 active |
| Index and admin pages | All returned 200 through the app, including the five categories, methodology, research queue and verification |

## Browser checks

The mega menu was checked at 1440, 1366, 1280, 1100, 1024, 980, 768 and 375 px: no overflow and no overlaps, and keyboard and Escape handling work. The public pages were checked at six widths earlier today with no overflow, after the pagination fix.

## Not done

| Item | Why |
|---|---|
| Live-site deployment and live QA | No production access. See the deployment report |
| Real marketplace listings | No authorised source yet. See the market research and import reports |
| Verified rankings | No project meets the evidence rules. See the ranking publication report |
| Portal listing research | Paused for your decision on Zameen's terms |
