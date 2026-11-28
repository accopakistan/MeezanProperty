# Files modified

No database schema, migrations or records were changed. No public URL was changed or removed. New URLs: `/llms.txt`, `/sitemaps/research.xml`, `/sitemaps/agents.xml`, `/sitemaps/projects-N.xml`.

## Application code

| File | Change |
|---|---|
| `app/Http/Controllers/SitemapController.php` | Sitemap index of `pages`, `research`, `locations`, `agents`, `projects-N`, `properties-N`; noindex pages excluded |
| `app/Http/Controllers/DealerDirectoryController.php` | `listed()` made `public static` so the sitemap uses the directory's own rule (no behaviour change) |
| `app/Http/Controllers/LlmsTxtController.php` | New: `/llms.txt` |
| `app/Models/Project.php` | New `scopeIndexable()` and `isIndexable()` |
| `app/Services/InvestmentIndex/IndexBrowser.php` | `marketBoard()`: sort keys computed once (same order); cached with a key that changes on any source update |
| `app/Support/Faq.php` | New: data-only FAQ builder and FAQPage schema; `sourceKind()` labels each approval source as official (the authority's own `.gov.pk` site, or `qda.gob.pk`), secondary (e.g. a news report) or none |
| `app/Support/Seo.php` | Organization `description` and `sameAs` |
| `config/meezan.php` | `seo.same_as`, `seo.google_site_verification`, `seo.bing_site_verification` |
| `routes/web.php` | `/llms.txt` route; sitemap file names extended |

## Views

| File | Change |
|---|---|
| `resources/views/components/layouts/head.blade.php` | Title suffix rule; Search Console and Bing verification meta when set |
| `resources/views/components/faq.blade.php` | New: visible Q&A card and FAQPage JSON-LD |
| `resources/views/cities/show.blade.php` | FAQ card |
| `resources/views/areas/show.blade.php` | FAQ card |
| `resources/views/pages/about.blade.php` | Organization JSON-LD; three items added to "What we offer" |
| `resources/views/projects/show.blade.php` | "noindex, follow" for name-only profiles |
| `resources/views/projects/index.blade.php` | Self-canonical pagination; filtered views "noindex, follow"; neutral description |
| `resources/views/dealers/show.blade.php` | Description grammar; self-canonical pagination |
| `resources/views/properties/index.blade.php` | Description keeps capitals |
| `resources/views/properties/show.blade.php` | RealEstateListing `about` and `offers.seller` |
| `resources/views/investment-index/category.blade.php`, `overall.blade.php`, `project.blade.php`, `compare.blade.php` | "noindex, nofollow" → "noindex, follow" |

## Other

| File | Change |
|---|---|
| `robots.txt` | Same as `public/robots.txt` (adds `Allow: /dealer/register`) |
| `.env.example` | `MEEZAN_SAME_AS`, `MEEZAN_GOOGLE_SITE_VERIFICATION`, `MEEZAN_BING_SITE_VERIFICATION` |
| `public/build/*` | Rebuilt CSS (new utility classes used by the FAQ card) |
| `docs/seo/*.md` | These reports |

## Tests

| File | Change |
|---|---|
| `tests/Feature/SeoIndexingTest.php` | New (4 tests) |
| `tests/Feature/InvestmentIndexPagesTest.php`, `InvestmentRankingsTest.php`, `NationalExpansionTest.php`, `PublicPagesTest.php`, `PublicShowcaseTest.php`, `SeoLaunchReadinessTest.php` | Read URLs from the sitemap child files; expect empty leaderboards out of the sitemap |

Scratch scripts used for measurement are in `scratch/` (not part of the commit): `perf-probe.php`, `prof-index.php`, `sort-check.php`, `sitemap-dump.php`, `sitemap-verify.php`, `ld-check.php`, `faq-peek.php`, `crawl.php`.
