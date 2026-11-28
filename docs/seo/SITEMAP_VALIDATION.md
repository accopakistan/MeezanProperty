# Sitemap validation

Checked locally on 27 November 2026 against a database that mirrors live for projects and areas (local listings and dealers differ slightly from live).

## Before (live)

`/sitemap.xml`: a single `urlset` of **80 URLs**, 11 with `lastmod`. It included 5 Investment Index category pages that returned `noindex, nofollow`. It had no project or dealer profiles.

## After (local)

`/sitemap.xml` is a `sitemapindex`:

| File | URLs | With lastmod | Rule |
|---|---|---|---|
| `/sitemaps/pages.xml` | 21 | 0 | static public pages, 7 city pages; pricing and legal pages only once final |
| `/sitemaps/research.xml` | 4 | 0 | Index home, Index methodology, MPIS methodology, yield calculator; leaderboards, Overall and Index project profiles only when ranked; MPIS dashboards only with verified data |
| `/sitemaps/locations.xml` | 39 | 0 | indexable area guides; city and area category pages with at least 3 real listings |
| `/sitemaps/agents.xml` | 0 locally (2 expected on live) | all | public dealer profiles (not sample, rejected, suspended or empty) |
| `/sitemaps/projects-1.xml` | 1,120 | 1,120 | `Project::indexable()`: published, not demo, with researched facts, an approval status or a developer |
| `/sitemaps/properties-1.xml` | 9 locally (11 on live) | 9 | live, non-demo listings |
| **Total** | **1,193** | | **1,193 unique, no duplicates across files** |

Expected on live after deployment: about 1,197 URLs (live has 2 more listings and 2 public dealer profiles).

## Checks run

| Check | Result |
|---|---|
| Every file parses as XML (`simplexml_load_string`) | pass, all 7 files |
| Namespace `http://www.sitemaps.org/schemas/sitemap/0.9` | pass |
| Absolute URLs from `APP_URL` (`https://meezanproperty.com` on live) | pass |
| Each URL returns 200 | pass, 1,193 / 1,193 |
| Each URL's robots meta starts with `index` | pass, 1,193 / 1,193 |
| Each URL has exactly one canonical, equal to its sitemap URL | pass, 1,193 / 1,193 |
| No URL listed twice across files | pass (also a test in `SeoIndexingTest`) |
| Noindex pages excluded (unranked leaderboards, Overall, 153 name-only profiles, demo, filtered views) | pass (tests in `SeoIndexingTest`, `PublicShowcaseTest`, `InvestmentIndexPagesTest`) |
| Limits: 50,000 URLs / 50 MB per file | numbered files split at 5,000 URLs |
| Private paths (`/admin`, `/dealer/`, `/account`) absent | pass (test in `SeoLaunchReadinessTest`) |
| `X-Robots-Tag: noindex` on the XML files themselves | kept, as before |

Being listed in the sitemap does not mean Google has indexed a page. Indexing can only be confirmed in Search Console after the sitemap is submitted.
