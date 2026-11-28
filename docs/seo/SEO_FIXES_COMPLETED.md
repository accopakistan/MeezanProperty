# SEO fixes completed

Implemented locally on 27 November 2026 and tested (see `REGRESSION_TEST_RESULTS.md`). **Not yet deployed**: commit, push and deploy wait for the owner's approval.

No design, layout, navigation, URL, database schema, business rule or ranking algorithm was changed. No database records were changed.

## Stage 2 — crawling and indexing

| Fix | Before | After |
|---|---|---|
| Sitemap structure | `/sitemap.xml` was one list of 80 URLs | `/sitemap.xml` is a sitemap index of `pages`, `research`, `locations`, `agents`, `projects-N`, `properties-N` (5,000 URLs per numbered file) |
| Project profiles in sitemap | 0 | every profile that passes `Project::indexable()` (1,120 of 1,273), with `lastmod` from the profile |
| Dealer profiles in sitemap | 0 | the public dealer set (`DealerDirectoryController::listed()`, the same rule the directory uses) |
| Research pages in sitemap | 5 unranked category pages that were noindex | Index home and methodology, MPIS methodology, rental yield calculator; a leaderboard, the Overall Index and Index project profiles only once they have ranked projects |
| Unranked ranking pages | `noindex, nofollow` | `noindex, follow` (category leaderboards, city leaderboards, Overall Index, Index project profiles, Index compare) |
| Name-only project profiles (153) | indexable | `noindex, follow`, out of the sitemap; still public |
| Project directory pagination | `/projects?page=5` canonical pointed to `/projects` | each page is its own canonical |
| Project directory filters | indexable, canonical to `/projects` | `noindex, follow` |
| Dealer profile pagination | canonical pointed to page 1 | each page is its own canonical |
| robots.txt | served copy lacked `Allow: /dealer/register` | both copies identical |

Rules kept: every sitemap URL returns 200, says `index` and has exactly one canonical equal to its sitemap entry (verified for all URLs, see `SITEMAP_VALIDATION.md`). Pages that are noindex never appear in the sitemap. New or edited records appear automatically: sitemap files are rebuilt from the database (1-hour cache).

## Stage 3 — performance

See `PERFORMANCE_BEFORE_AFTER.md`. The root cause of the slow investment pages was found and fixed in `IndexBrowser::marketBoard` (sort keys recomputed inside comparators). Output order is unchanged (checked for all 5 categories × 8 places × 3 limits = 120 combinations).

## Stage 4 — metadata

| Fix | Example before | Example after |
|---|---|---|
| Brand not repeated in titles | `About Meezan Property \| Meezan Property` | `About Meezan Property` |
| Brand suffix dropped from long titles (over 50 characters) | `5 Marla House for Sale in Etihad Town, Lahore — PKR 3.5 Crore \| Meezan Property` (81) | same without the suffix (62) |
| Dealer profile description | `Arham Estate & builders on Meezan Property: 1 live listings in Cantt.` | `Arham Estate & builders is a property dealer in Lahore with 1 live listing on Meezan Property, covering Cantt.` |
| Properties description capitals | `Browse 11 properties in pakistan.` | `Browse 11 properties in Pakistan.` |
| Directory description | `Official factual directory … with verified authority approval status.` | `Directory of … with sources and authority status where recorded.` (no implied official status) |

Internal linking: the About page's existing "What we offer" list now links to Area guides, the Project Directory and the Index methodology. City pages and area guides gained the FAQ block (Stage 5). No navigation or sitewide links were added.
