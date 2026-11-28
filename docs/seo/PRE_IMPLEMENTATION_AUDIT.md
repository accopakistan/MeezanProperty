# Pre-implementation audit — SEO, AEO and GEO

Date: 27 November 2026. Baseline: live build `2600d66` and the same code locally (local database mirrors live: 1,273 real published project profiles).

## Stack inventory

| Item | Finding |
|---|---|
| Framework | Laravel 12.70 (PHP 8.3 live, 8.5 local), Blade views, Tailwind 4 + Vite 7, small vanilla JS (`resources/js/app.js`) |
| Hosting | Hostinger shared hosting behind the Hostinger CDN (`hcdn`); project folder is the document root; Brotli on; HSTS on; cache store `file`, sessions `database` |
| Metadata | One component: `resources/views/components/layouts/head.blade.php` (title, description, robots, canonical, Open Graph, Twitter) |
| SEO helpers | `app/Support/Seo.php` (listing titles/descriptions, category pages, Organization JSON-LD) |
| Sitemap | `app/Http/Controllers/SitemapController.php`: one list at `/sitemap.xml` while small, split files at `/sitemaps/{pages,locations,properties-N}.xml` |
| robots.txt | Two copies: `robots.txt` (served, project root) and `public/robots.txt` (differed by one `Allow: /dealer/register` line) |
| Structured data | WebSite + SearchAction + Organization (home), RealEstateListing (listings), CollectionPage/ItemList (city and category pages), BreadcrumbList (all) |
| Caching | No full-page cache; some query caches (city summaries, category counts, sitemap 1 h) |
| Scheduler/queue | Cron-driven scheduler; queue drained by the scheduler (see earlier notes) |

## Page inventory (public templates)

| Page type | Template | Count live | Robots before |
|---|---|---|---|
| Home | `home.blade.php` | 1 | index |
| City pages | `cities/show.blade.php` | 7 | index |
| City/area category pages | `locations/listings.blade.php` | by listings | index only with ≥ 3 real listings |
| Area and housing-society guides | `areas/show.blade.php` | 1,238 active areas | index when popular or with live listings |
| Project profiles | `projects/show.blade.php` | 1,273 real (+420 demo, 404 on live) | index for every real profile |
| Dealer profiles | `dealers/show.blade.php` | 2 public | index |
| Listings | `properties/show.blade.php` | 11 public | index |
| Investment Index home, methodology | `investment-index/home`, `methodology` | 2 | index |
| Index category leaderboards, Overall | `investment-index/category`, `overall` | 5 + 1 | **noindex, nofollow** (none ranked) |
| Index project profiles | `investment-index/project` | per project | **noindex, nofollow** unless ranked |
| MPIS dashboards | `rankings/*` | per city/segment | noindex, follow unless verified data |
| About, How it works, Contact | `pages/*` | 3 | index |
| Blog / guides | none | 0 | — |

Reconciliation: the earlier report quoted 1,693 published project profiles. That figure included 420 demo profiles, which return 404 on live. The real figure is **1,273**, matching the directory.

## Issues found

| # | Issue | Affected | Root cause | Correction | Risk | Files |
|---|---|---|---|---|---|---|
| 1 | Sitemap listed 5 ranking pages that were noindex | `/investment-rankings/{category}` | Sitemap always added category pages; pages are noindex while nothing is ranked | List a category only when its leaderboard has ranked projects | Low | `SitemapController.php` |
| 2 | "noindex, nofollow" on unranked ranking pages | categories, overall, Index project profiles, Index compare | `:noindex` prop gives "noindex, nofollow" | Use "noindex, follow" | Low | 4 `investment-index/*.blade.php` |
| 3 | Sitemap covered 80 of ~1,200 indexable pages | project profiles, dealer profiles, MPIS methodology, yield calculator | Sitemap had no project/agent files; research pages missing | Sitemap index with `pages`, `research`, `locations`, `projects-N`, `agents`, `properties-N` | Low | `SitemapController.php`, `routes/web.php` |
| 4 | 153 name-only project profiles indexable | `/projects/{slug}` | Every real profile indexable | Indexable only with researched facts, approval status or developer; others "noindex, follow" | Low–medium | `Project.php`, `projects/show.blade.php` |
| 5 | Rankings pages slow (5–9 s warm locally; 3–4.3 s TTFB live) | `/investment-rankings`, categories, city pages | `IndexBrowser::marketBoard` recomputed sort keys inside comparators, re-decoding JSON facts tens of thousands of times | Compute keys once (same order, verified); cache the board with a key that changes on any source update | Low | `IndexBrowser.php` |
| 6 | Paginated directory canonicalised to page 1 | `/projects?page=N` | Default canonical drops the query string | Self-canonical per page; filtered views "noindex, follow" | Low | `projects/index.blade.php` |
| 7 | Brand repeated in titles; very long titles | About, Contact, How it works, listings, rankings | Suffix always appended | Drop suffix if title names the brand or exceeds 50 characters | Low | `head.blade.php` |
| 8 | Grammar/case errors in descriptions | dealer profiles ("1 live listings"), `/properties` ("in pakistan") | String building | Pluralise; keep capitals | Low | `dealers/show.blade.php`, `properties/index.blade.php` |
| 9 | "Official factual directory" in directory description | `/projects` | Wording | Neutral wording; no implied official status | Low | `projects/index.blade.php` |
| 10 | No question-and-answer content | city pages, area guides | Not built | Data-only FAQ blocks + FAQPage JSON-LD | Low | `Faq.php`, `faq.blade.php`, two views |
| 11 | No `llms.txt` | site | Not built | Generated `/llms.txt` route | Low | `LlmsTxtController.php`, `routes/web.php` |
| 12 | Organization without description/sameAs; only on home | Organization JSON-LD | Not built | Description; `sameAs` from config; also on About | Low | `Seo.php`, `about.blade.php`, `config/meezan.php` |
| 13 | Listing JSON-LD without size, rooms, seller | listings | Not built | `about` (residence type, bedrooms, bathrooms, covered area) and public dealer as seller | Low | `properties/show.blade.php` |
| 14 | No Search Console / Bing verification hook | site | Not built | Config + meta tags when tokens are set | Low | `config/meezan.php`, `head.blade.php`, `.env.example` |
| 15 | Two robots.txt copies differ | robots.txt | Edited separately | Make identical | Low | `robots.txt` |

Items that need approval or outside access are listed in `REMAINING_ISSUES.md`.
