# Meezan Property — Phase 12 Report
## SEO, Programmatic Location Pages, Performance, Security & Production Launch Readiness

Date: 2 November 2026 · Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB) · No Git commits, no GitHub push, no deployment, no destructive database operations in this phase.

Status key: **COMPLETE AND VERIFIED** · **COMPLETE BUT CONFIGURATION-BLOCKED** · **PARTIALLY IMPLEMENTED** · **NOT IMPLEMENTED**

---

## 1. Executive summary

Phase 12 prepares the marketplace for search engines and for launch, without inventing content.

**SEO and location pages**
- **One meta system** gives every page a robots directive. Indexable pages also get a canonical URL plus Open Graph and Twitter tags.
- **Listing titles** follow the pattern "10 Marla House for Sale in DHA Phase 6, Lahore", built from the listing's own fields.
- **Category landing pages:** nine categories (houses, flats, plots, commercial, all property × sale/rent) are available for each of the 7 cities and every active area. They are built only from live listings in the existing location data.
- **Thin pages stay out of the index.** A category page is indexed and listed in the sitemap only when it has at least 3 real (non-demo) listings. Below that it stays usable but is "noindex, follow".
- **The sitemap scales:** `/sitemap.xml` is a single list while the site is small and becomes a sitemap index automatically. The split files are `/sitemaps/pages.xml`, `/sitemaps/locations.xml` and `/sitemaps/properties-N.xml`.
- **Search, filter, sort and map pages follow one indexing policy.** A search that defines a market (city / purpose / type / area) is indexable with a clean canonical. Refinements, sorting and the map are "noindex, follow".

**Performance**
- Three composite database indexes for the commonest listing queries.
- Category counts reduced from nine queries to one grouped query, and cached.
- Image dimensions set to prevent layout shift.

**Security**
- **One critical exposure fixed:** the project folder is the web root on this hosting, so `.env`, `composer.json`, `artisan` and `storage/logs/laravel.log` were downloadable. They are now refused with 403, verified against Apache locally.
- **Rate limit added** on password-reset submissions.
- **New staging "noindex" switch.**
- **Two new production-check items.**

> **Production blocker:** an earlier probe of the live site showed `/storage/logs/laravel.log` and `/composer.json` answering 200 (`.env` was already 403). The fix is in the project's root `.htaccess`, but **it is not deployed**, as this phase's rules require. It should be uploaded to the live site first, before anything else.

**Results**
- 477 tests pass (2,562 assertions, 0 failures), including 22 new Phase 12 tests.
- Pint, `npm run build`, `composer audit`, `npm audit --omit=dev`, `route:cache`, `config:cache` and `view:cache` are all clean.
- Every data count is unchanged.

## 2. Scope and rules followed

| Rule | Followed |
|---|---|
| No Git, GitHub or Hostinger deployment | Yes. Nothing committed, pushed or uploaded |
| No `migrate:fresh`, `db:wipe`, dropped tables or deletions | Yes. One additive migration (indexes only) |
| Preserve all data | Yes. Before/after counts in §39 are identical |
| Reference images untouched | Yes. `public/Refrence/` not modified |
| No invented URLs, societies, statistics, counts or scores | Yes. All counts and price ranges are live database queries; no Lighthouse or PageSpeed scores are claimed (none were measured) |
| No CDN claimed | Yes. Assets are served from `ASSET_URL`; no CDN is configured |
| No secrets logged or hard-coded | Yes. New settings are env-driven with safe defaults |
| No live payments or WhatsApp | Yes. Untouched (local `.env` keeps the mock drivers) |

## 3. Audit baseline (before changes)

**Already in place:**
- The `x-layouts.head` component (title, description, optional canonical, noindex).
- JSON-LD: `WebSite` (home), `CollectionPage` + `City` (city pages), `RealEstateListing` (property page), `BreadcrumbList` (breadcrumb component).
- A single `/sitemap.xml` (excluding demo listings) and a static `robots.txt`.
- noindex on dashboards, auth pages, compare and empty area guides.

**Problems found:**
1. Every search URL canonicalised to plain `/properties` (or to the area), so "Houses for sale in Lahore" could never rank as its own page.
2. Search pages became noindex only after more than two filters, so sort orders and price slices were indexable duplicates.
3. There were no category landing pages ("houses for sale in Lahore", "plots for sale in DHA Phase 6").
4. There were no Open Graph image fallback, `og:image:alt` or `robots` meta on indexable pages.
5. Listing titles were the dealer's free text only. The description was the first characters of the dealer's text.
6. Related listings matched only the area and type, not the purpose (a rental could be suggested next to a sale).
7. The sitemap was one file with no split, which would break past 50,000 URLs.
8. Security: POST `/reset-password` was not rate-limited.
9. Security: the hosting layout (project folder as document root) exposed internal files (see §35).

**Local data at the start:** 16 of the 17 listings are published, and all 16 are demo listings (`is_demo = 1`). By design, therefore, no local category page reaches the indexing threshold. This is expected and tested; see §14.

## 4. What was reused, not rebuilt

- `Property::published()`, `forCards()`, `inGroup()` scopes; `PropertyType` groups; `Cities` (names, slugs, provinces); `LocationResolver::withDescendants()` (area hierarchy); the `area` route binding and merged-area handling.
- The existing head and app layout components, the breadcrumb component (now also emitting JSON-LD as before), and the property card.
- The existing sitemap view (`sitemap.blade.php`) and its tests.
- `SecurityHeaders` middleware, CSP nonce, `ProductionCheck` command.

## 5. Indexing policy — **COMPLETE AND VERIFIED**

| Page | robots | Canonical | In sitemap |
|---|---|---|---|
| Home, static pages, pricing, about, contact | index, follow | self | yes |
| City page `/lahore` | index, follow | self | yes |
| City / area category page with ≥ 3 real listings | index, follow | self (+ `?page=N`) | yes |
| City / area category page below threshold | noindex, follow | none | no |
| Area guide with listings, or a popular area | index, follow | self | yes |
| Area guide with no listings and not popular | noindex, follow | none | no |
| Property page (real, published) | index, follow | self | yes |
| Property page (demo) | noindex, follow | none | no |
| Property preview (owner, unpublished) | noindex, nofollow | none | no |
| Search with only defining filters (city, purpose, type, group, area) | index, follow | defining filters + page | no (reachable via links) |
| Search with refinements (price, beds, sort, keywords…) or 0 results | noindex, follow | none | no |
| Map search | noindex, follow | none | no |
| Login, register, account, dealer, admin, compare, notifications | noindex, nofollow | none | no |
| 404 / error pages | noindex | none | no |

"noindex, **follow**" is deliberate for public pages: crawlers still follow their links to listings that are indexable.

## 6. URL strategy — **COMPLETE AND VERIFIED**

These follow the existing URL scheme; nothing was renamed and no existing URL changed.

- City: `/lahore` (existing).
- City category: `/lahore/houses-for-sale`, `/karachi/flats-for-rent`, … (new). The route accepts only the 7 launch-city slugs and the 9 configured category slugs, so it cannot catch other URLs.
- Area guide: `/areas/{slug}` (existing). Area category: `/areas/{slug}/plots-for-sale` (new).
- Property: `/properties/{slug}` (existing).
- Sitemaps: `/sitemap.xml` (existing), `/sitemaps/{pages|locations|properties-N}.xml` (new).

The categories live in `config/meezan.php` → `seo.categories`: property, houses, flats, plots or commercial, each × sale/rent where it makes sense. Plots are sale-only, so there are 9 in all. Each one maps to existing purposes and `PropertyType` values. Rented "houses" include upper and lower portions, which is how they are let in Pakistan.

## 7. Canonical strategy — **COMPLETE AND VERIFIED**

- Canonicals are output only on indexable pages, never on noindex pages, where a canonical would send a mixed signal.
- Search keeps only the defining parameters plus `page`. `/properties?city=Lahore&purpose=sale` is canonical to itself. Adding `sort`, a price or bedrooms makes the page noindex.
- Category pages canonicalise to their clean URL, with `?page=N` from page 2 on. Requesting a page past the end returns 404 instead of an empty indexable page.
- Merged areas 301-redirect to the surviving area (for both area guides and area category pages).

## 8. Reusable meta system — **COMPLETE AND VERIFIED**

`resources/views/components/layouts/head.blade.php` takes `title`, `description`, `canonical`, `image`, `imageAlt`, `noindex`, `robots`, `type`:
- **Robots:** always outputs `<meta name="robots">`.
  - `noindex` gives "noindex, nofollow" (private pages).
  - `robots` sets a custom value (e.g. "noindex, follow").
  - Otherwise: "index, follow, max-image-preview:large".
- **Canonical:** only when indexable; defaults to the current URL.
- **Open Graph:** site name, `en_PK` locale, type, title, description, URL, image (the listing photo or the default brand image), `og:image:alt`.
- **Twitter:** `summary_large_image` card.
- **Description:** whitespace collapsed and capped at 160 characters.

`x-layouts.app` passes these through, so any page sets them with attributes.

## 9. Property page SEO — **COMPLETE AND VERIFIED**

- **Title:** `{size} {Type} for Sale|Rent in {Area}, {City} — {price} | Meezan Property`. For example: "8 Marla Commercial Plot for Sale in Johar Town, Lahore — PKR 12 Crore | Meezan Property" (verified in the browser).
- **Description:** the same headline, then bedrooms · bathrooms · size · price, then the dealer's own description, capped at 155 characters. It uses only the listing's own fields.
- **Canonical, Open Graph and Twitter tags:** canonical to the listing URL; the Open Graph image is the cover photo, with the title as alt text.
- **Demo listings:** "noindex, follow" and no listing JSON-LD. Previews are "noindex, nofollow".
- **Breadcrumb:** Home → City → Area → Category page (e.g. "Plots for Sale" in that area) → listing. All links are real, existing pages.
- **"Explore" links:** to the area guide, the category in the area, the category in the city, and the city page.

## 10. City landing pages — **COMPLETE AND VERIFIED**

The 7 existing city pages now:
- link their Buy and Rent cards to `/city/property-for-sale` and `/city/property-for-rent`;
- show a list of category links with live counts, only for categories that have listings.

The city counts come from one cached grouped query (§25).

## 11. City category pages — **COMPLETE AND VERIFIED**

`LocationListingController@city` → `resources/views/locations/listings.blade.php`:

- **Headline:** "Houses for Sale in Lahore".
- **Factual summary:** "N reviewed listings, asking PKR X to PKR Y". Both figures come from live min/max queries.
- **Buttons:** "Refine this search", "Map" and "AI Property Finder".
- **Listings:** a paginated grid of 24 per page, featured first and then newest.
- **"Popular areas":** the areas of the city with listings in this category, with real counts (top 12).
- **Related categories** in the same city.
- **Empty state:** when there are no listings it says so plainly and links to search. It is never padded with other content.

## 12. Area guides — **COMPLETE AND VERIFIED** (existing page, extended)

- Buy and Rent now link to the area's category pages.
- A row of category links with live counts was added.
- An area with no listings that is not a popular area is now "noindex, follow" instead of fully noindex, so crawlers can still follow its links.

## 13. Area category pages — **COMPLETE AND VERIFIED**

`/areas/{slug}/{category}`:
- **Listings include sub-areas:** listings in the area and all its descendants count (for example, phases and blocks under a society).
- **Navigation:** links to child areas, the parent city category and the area guide.
- **Merged areas** 301 to the target.
- **Inactive areas, and areas outside the 7 launch cities,** return 404.

## 14. Thin-content protection — **COMPLETE AND VERIFIED**

- **The threshold** is `MEEZAN_SEO_MIN_LISTINGS` (default 3) **real** listings. Demo listings never count towards indexing.
- **Below the threshold** a page is still served to visitors, but with "noindex, follow", no canonical, no CollectionPage JSON-LD, and no sitemap entry.
- **Area category pages are only considered for the sitemap** when the area has at least one real listing at all.
- **Locally,** all 16 published listings are demo listings, so `/lahore/houses-for-sale` correctly shows 4 listings and is "noindex, follow". The sitemap lists 0 category pages and 37 area guides (the popular ones). Both were verified in the browser and covered by tests using real (non-demo) factory listings.

## 15. Structured data — **COMPLETE AND VERIFIED**

All JSON-LD is printed with `JSON_HEX_TAG | JSON_HEX_AMP`, which escapes any `<`, `>` or `&` from dealer-entered text, so it is safe inside `<script>`. It contains no ratings, reviews, awards, "best" claims or invented figures.

| Page | Types |
|---|---|
| Home | `WebSite` (with SearchAction, existing), **`Organization`** (new: name, URL, logo, configured contact email/phone only, `areaServed` Pakistan) |
| City | `CollectionPage` + `City` (existing), `BreadcrumbList` |
| Category page (indexable only) | **`CollectionPage` + `ItemList`** of the listings on the page (non-demo), `BreadcrumbList` |
| Property (real) | **`RealEstateListing`**, enriched: description, `datePosted`, `dateModified`, images, `offers` (price, PKR, `businessFunction` Sell / LeaseOut), `contentLocation` → `Place` + `PostalAddress` (locality, region from the city's province, country PK). `geo` **only** when the dealer chose to show the exact location. Approximate or hidden pins never leak coordinates (tested) |
| All pages with breadcrumbs | `BreadcrumbList` |

## 16. Breadcrumbs — **COMPLETE AND VERIFIED**

- Visible breadcrumbs and `BreadcrumbList` JSON-LD come from the one existing component.
- Property pages go City → Area → Category → Listing. Category pages go City → (Area) → Category.
- Every crumb links to a page that exists and returns 200.

## 17. XML sitemaps — **COMPLETE AND VERIFIED**

`SitemapController`:
- **`/sitemap.xml`:** while real listings fit one file (≤ 5,000), a single `urlset` of pages, locations and listings. This keeps the existing tests and Search Console submission unchanged. Beyond that it becomes a `sitemapindex` of the split files.
- **`/sitemaps/pages.xml`:** 17 static public routes and the 7 city pages.
- **`/sitemaps/locations.xml`:** indexable city category pages, indexed area guides, and indexable area category pages.
- **`/sitemaps/properties-N.xml`:** published non-demo listings, 5,000 per file, with `lastmod`.
- Unknown names, or property pages past the end, return 404.
- Each file is cached for one hour. Responses are `application/xml` with `X-Robots-Tag: noindex`, so the XML itself is never indexed.
- Dashboards, account, admin, drafts, pending, expired and demo listings are never included (tested).

Local result: 61 URLs (24 pages + 37 area guides). There are 0 listings and 0 category pages, because all local listings are demo.

> Note: cached sitemap URLs use the host of the first request that built them. Production should answer on one host only (redirect `www.` to the apex, or the reverse) so the sitemap always shows the canonical host.

## 18. robots.txt — **COMPLETE AND VERIFIED**

`public/robots.txt` and the root `robots.txt` are identical (the root one is served because the project folder is the document root):
- **Disallowed:** `/account`, `/dealer/`, `/admin`, `/notifications`, `/compare`, `/locations/`, `/webhooks/`, `/properties/*/nearby`, sorted URLs (`/*?*sort=`), and map queries (`/map?`).
- **Sitemap:** `https://meezanproperty.com/sitemap.xml`.
- **Search, city, area and property pages are not blocked.** Blocking them would stop crawlers from seeing their noindex directives.

## 19. Filters, sort, pagination and map — **COMPLETE AND VERIFIED**

- **Defining filters** (city, purpose, type, group, area; single values only) give an indexable page with a clean canonical.
- **Anything else** (price, size, bedrooms, amenities, keywords, sort, "verified only", …) makes the page "noindex, follow", and so does a search with no results.
- **Pagination** keeps `?page=N` in the canonical, so each page canonicalises to itself, not to page 1.
- **The map is always "noindex, follow"**, and `/map?` is disallowed in robots.txt.

## 20. Internal linking and related listings — **COMPLETE AND VERIFIED**

- **Related listings** on a property page:
  - same city, purpose and type;
  - the same area first, then the closest price, then newest;
  - 4 listings, under the heading "Similar Properties in {city}", with a link to the matching category page.
- **Link chain:** city pages → category pages → area category pages → listings; area guides → category pages; property pages → area guide / category pages / city.
- **Footer** links to the popular areas and to the sitemap.

## 21. Query review and N+1 — **COMPLETE AND VERIFIED**

- **`preventLazyLoading()` was already active,** so every test run fails on any lazy load. The new pages run under it with no violations.
- **Category pages:** the listing cards are eager-loaded with `forCards()` (area, cover image, latest price change, dealer and profile). The area category page runs **13 queries** with a warm cache, whether it shows 3 or 15 listings.
- **New test:** the city page, city category page, area category page and area guide must not run more queries with 15 listings than with 3, and must stay within 25 queries even on a cold cache (§40).
- **Bug found by these tests and fixed:** "popular areas" on city category pages joined `areas` to `properties`, and both tables have `city` and `type` columns. Both SQLite and MariaDB reject the ambiguous query once a city has listings in the category. It now groups `properties` alone, then names the areas in a second small query.

## 22. Database indexes — **COMPLETE AND VERIFIED**

Additive migration `2026_11_02_000039_add_listing_query_indexes` (ran; `down()` drops only these):

| Index | For |
|---|---|
| `properties (status, city, purpose, published_at)` | city pages, city category pages, search by city |
| `properties (status, area_id, published_at)` | area guides, area category pages |
| `properties (user_id, status)` | dealer listing counts (package allowance, dashboards) |

EXPLAIN on local MariaDB (17 rows, so indicative only; real benefit shows with volume):

| Query | Before | After |
|---|---|---|
| Published listings by city + purpose, newest | `status_purpose_type` index + filesort | new composite index, no filesort |
| Published listings in an area, newest | `area_id` FK index + filesort | new composite index (filesort remains when an area has sub-areas: `IN (…)`) |
| Dealer's published count | full table scan | covering index ("Using index") |

## 23. Search and map performance — **COMPLETE AND VERIFIED** (reviewed; no change needed)

- **Map:** capped at `MAP_LIMIT = 500` markers, selects only the needed columns, and outputs only public coordinates (`MapMarkers`).
- **Search:** pagination is unchanged. The existing query-budget test (`/properties` ≤ 20 queries, `/map` ≤ 25) still passes.

## 24. Images — **COMPLETE AND VERIFIED**

- **Property cards:** `width="640" height="360"` reserves space before the image loads (they were already `loading="lazy" decoding="async"`).
- **Property page:**
  - the main photo has width and height from the stored image (default 1600×1000) and keeps `fetchpriority="high"`;
  - gallery photos get their stored dimensions, plus `decoding="async"` and `h-auto`.
- **Uploads** are already converted to WebP on upload (existing). No image CDN is configured.

## 25. Caching — **COMPLETE AND VERIFIED**

| What | Where | TTL |
|---|---|---|
| Category counts per city/area | `Seo::categoryCounts()`, key `seo:category-counts:{city:…|area:id}` | 10 min |
| Sitemap files | `sitemap:{index|pages|locations|properties-N}` | 1 hour |
| Config, routes, views | `config:cache`, `route:cache`, `view:cache` | until next deploy (all three verified to work) |

**Category counts are now one grouped query** (purpose × type, with real-listing sums) instead of nine separate counts per page. Cold-cache category pages dropped from about 30 queries to within the 25 budget.

A stale count can show for up to 10 minutes. It only affects the link labels and the index decision, never the listings shown, which are always live.

## 26. Front-end build — **COMPLETE AND VERIFIED**

`npm run build` (Vite 7) output:

| File | Size | gzip |
|---|---|---|
| app.css | 141.34 kB | 23.48 kB (+0.26 kB / +0.04 kB for the new page) |
| app.js | 76.46 kB | 27.71 kB (unchanged) |
| leaflet.js (map pages only) | 149.57 kB | 43.30 kB |
| map / location-picker / ai-finder | 6.57 / 6.56 / 5.09 kB | 2.67 / 3.12 / 1.98 kB |

Known item: Bunny Fonts CSS is render-blocking (an external request on first paint). Self-hosting the fonts would remove it; this is not done.

## 27. Authentication and authorization — **COMPLETE AND VERIFIED** (reviewed)

- **Dashboards are protected by role middleware,** and policies cover listings, leads and lead assignments. The new pages are public and read-only; they expose only published listings.
- **A regression test re-checks the role boundaries:** guests are redirected from the dealer dashboard, buyers are refused (403), and dealers are refused the admin area (403).

## 28. CSRF, mass assignment and validation — **COMPLETE AND VERIFIED** (reviewed)

- **CSRF:** Laravel's CSRF middleware covers all web POST/PUT/DELETE requests. Webhooks are the only exemption, and they use signature checks (Phase 10).
- **Mass assignment:** no `$request->all()` or `fill($request->all())` anywhere in `app/`. `role`, `status` and verification fields are not fillable; they are set with `forceFill` in admin-only code.
- **Validation:** all writes go through FormRequests or `validate()` with typed rules and bounds (e.g. price 1,000 to 50,000,000,000, size limits, enum rules).
- **New routes are GET-only**, with constrained parameters (`whereIn` for cities and categories, a regex for sitemap names).

## 29. Uploads — **COMPLETE AND VERIFIED** (reviewed)

| Upload | Rules | Storage |
|---|---|---|
| Listing photos | `image`, jpg/jpeg/png/webp, `max_image_kb`, dimensions 400×300 to 8000×8000, re-encoded to WebP | public disk |
| Dealer verification documents | jpg/jpeg/png/webp/pdf, `max_document_kb`, integrity check | **private** disk; served only through an authorised download route |
| Manual payment proofs | jpg/jpeg/png/webp/pdf, `max_proof_kb` | **private** disk |
| Project cover (admin) | image, jpg/jpeg/png/webp, 5 MB | public disk |

## 30. XSS and SQL injection — **COMPLETE AND VERIFIED** (reviewed)

- **XSS:** every `{!! !!}` in the views was reviewed.
  - JSON-LD blocks use `JSON_HEX_TAG`.
  - Admin stat cards build their HTML from `e()`-escaped labels and `number_format()` numbers.
  - The property description uses `nl2br(e(...))`.
  - Icons come from a fixed internal map.
  - The sitemap XML prolog is a constant.
  - No user input is output unescaped.
- **SQL:** all user input is bound.
  - The only interpolated raw SQL is `DealerPerformance::sumBy()`, whose column name is an internal constant, not input.
  - The new price-proximity order uses a bound parameter.

## 31. Rate limiting — **COMPLETE AND VERIFIED**

- **Existing limits:**
  - login (`RateLimiter`);
  - registration, forgot-password, inquiries, AI finder, nearby lookup and webhooks (32 throttled routes in all).
- **Fixed in Phase 12:**
  - **POST `/reset-password`** is now `throttle:6,1`. Before, it accepted unlimited token or password guesses.
  - A test confirms that the 7th attempt within a minute gets HTTP 429.

## 32. Sessions and cookies — **COMPLETE BUT CONFIGURATION-BLOCKED**

- **Code defaults:** HttpOnly on, `SameSite=lax`, 120-minute lifetime, regenerated on login.
- **Needs a production setting:** `SESSION_SECURE_COOKIE=true`, so the cookie is only sent over HTTPS. `meezan:production-check` warns until it is set.
- **Optional:** `SESSION_ENCRYPT=true`.

## 33. Error handling and debug — **COMPLETE BUT CONFIGURATION-BLOCKED**

- **Branded error pages:** 403, 404, 419, 429, 500 and 503, all `noindex` and showing no stack traces.
- **Production must use `APP_DEBUG=false`.** `meezan:production-check` fails while debug is on (it is on locally, as expected).

## 34. Security headers — **COMPLETE AND VERIFIED**

- **Existing headers:**
  - strict nonce-based Content-Security-Policy;
  - `X-Content-Type-Options: nosniff`;
  - `X-Frame-Options: SAMEORIGIN`;
  - `Referrer-Policy: strict-origin-when-cross-origin`;
  - a restrictive `Permissions-Policy`;
  - `Cross-Origin-Opener-Policy: same-origin`;
  - HSTS, sent only over HTTPS in production.
- **New: `MEEZAN_SEO_NOINDEX_SITE`** (default `false`).
  - When `true`, every response carries `X-Robots-Tag: noindex, nofollow`, so a staging or test copy can never be indexed.
  - It is a setting rather than "any non-production environment" on purpose: if a live server's `APP_ENV` were wrong, the latter would silently deindex the real site.
  - `meezan:production-check` fails if it is on.

## 35. Web-server file exposure — **COMPLETE AND VERIFIED (local) · NOT DEPLOYED**

**The problem:**
- On this hosting, the project folder is the document root (root `index.php`, with `ASSET_URL=…/public/`).
- The root `.htaccess` served any file that existed, so the whole project could be downloaded.

**The fix:** the root `.htaccess` now has two rules, placed before the front-controller rules:
1. Refuse (403) every existing file except `index.php`, `robots.txt`, favicons and `public/…`.
2. Refuse hidden paths (`.env`, `.git`, …) everywhere, except `.well-known/` (needed for SSL certificate checks).

Verified against XAMPP Apache at `http://localhost/MeezanProperty`:

| Path | Before | After |
|---|---|---|
| `/.env` | 200 | **403** |
| `/composer.json` | 200 | **403** |
| `/artisan` | 200 | **403** |
| `/storage/logs/laravel.log` | 200 | **403** |
| `/.git/config`, `/app/Models/User.php`, `/vendor/autoload.php`, `/database/database.sqlite` | — | **403** |
| `/`, `/lahore`, `/sitemap.xml`, `/robots.txt` | 200 | 200 |
| `/public/favicon.svg`, `/public/build/manifest.json` | 200 | 200 |

Protection added:
- `meezan:production-check` now verifies that the rule is present whenever the root front controller exists.
- A test fails if the rule is removed or moved after the front-controller rule.

**Live site:**
- An earlier probe found `/storage/logs/laravel.log` and `/composer.json` answering 200; `.env` was already 403.
- **Action for the owner:**
  - upload the updated root `.htaccess`;
  - re-check those URLs;
  - consider rotating any secret that ever appeared in that log.

## 36. Production configuration — **COMPLETE BUT CONFIGURATION-BLOCKED**

Required `.env` values on the live server. Never commit them, and never put real values in docs.

| Setting | Value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://meezanproperty.com` |
| `ASSET_URL` | `https://meezanproperty.com/public/` (project-root hosting) |
| `SESSION_SECURE_COOKIE` | `true` |
| `LOG_LEVEL` | `warning` (or `error`) |
| `MAIL_MAILER` + `MAIL_FROM_ADDRESS` | a real SMTP mailer and a real from address |
| `QUEUE_CONNECTION` | `database`, plus `MEEZAN_QUEUE_VIA_SCHEDULER=true` on shared hosting |
| `MEEZAN_SEO_MIN_LISTINGS` | `3` (default) |
| `MEEZAN_SEO_NOINDEX_SITE` | `false` (`true` only on staging) |
| `MEEZAN_CONTACT_EMAIL`, map tile and Overpass providers | as per `.env.example` |

**After every deploy:**
1. `php artisan migrate --force`
2. `php artisan config:cache`
3. `php artisan route:cache`
4. `php artisan view:cache`
5. `php artisan storage:link` (first deploy only)
6. Run the scheduler cron every minute.
7. Run `php artisan meezan:production-check`.

The new variables are documented in `.env.example`.

## 37. Backup and recovery — **COMPLETE BUT CONFIGURATION-BLOCKED** (documented; not scheduled)

What to back up:
- **The database:** a daily `mysqldump --single-transaction` of the application database, kept for at least 14 days, with one copy stored off the server.
- **`storage/app/public/`:** listing photos (public, but not reproducible).
- **`storage/app/private/`:** dealer verification documents and payment proofs. These are personal data, so store the backups encrypted, with restricted access.
- **`.env`:** keep a copy separately in a password manager. Never put it in the code repository or in a shared backup archive.

Recovery order:
1. Restore the code.
2. Restore `.env`.
3. Import the database.
4. Restore both storage folders.
5. Run `storage:link`.
6. Rebuild the caches.
7. Run `meezan:production-check`.

A restore has **not yet been tested**; this should be done once before launch.

## 38. Logging and observability — **PARTIALLY IMPLEMENTED**

- **Logs:** Laravel writes to `storage/logs/laravel.log`. The new production-check item warns while `LOG_LEVEL=debug`.
- **What is never logged:** passwords, tokens and secrets. The WhatsApp and payment code from earlier phases already redacts them.
- **Admin visibility:** failed jobs are counted in `meezan:production-check`, and WhatsApp delivery failures and assistant hand-offs show in the admin area.
- **Not implemented:** external error tracking (e.g. Sentry) and uptime monitoring. Both need an account the owner chooses.
- **Local log check:** the only errors logged today are from a Phase 11 migration attempt earlier this morning (05:18, MariaDB "invalid default value for issued_at"), resolved then by migration `…000038`. No errors were logged during Phase 12 QA.

## 39. Data preservation — **COMPLETE AND VERIFIED**

| Table | Before | After |
|---|---|---|
| users | 4 | 4 |
| dealer_profiles | 2 | 2 |
| properties (published / demo) | 17 (16 / 16) | 17 (16 / 16) |
| property_images | 42 | 42 |
| leads / lead_assignments | 2 / 2 | 2 / 2 |
| conversations / conversation_messages | 10 / 46 | 10 / 46 |
| outbound_messages | 18 | 18 |
| CRM: notes / follow-ups / appointments / recommendations / activities | 1 / 1 / 1 / 1 / 8 | 1 / 1 / 1 / 1 / 8 |
| subscriptions / payments / invoices | 1 / 2 / 1 | 1 / 2 / 1 |
| cities / areas | 7 / 1,238 | 7 / 1,238 |
| buyer_requirements / projects | 2 / 0 | 2 / 0 |

The only schema change is the additive index migration in §22.

## 40. Tests, manual QA and final validation — **COMPLETE AND VERIFIED**

**New test file:** `tests/Feature/SeoLaunchReadinessTest.php` (22 tests).

*Meta and property pages:*
- Meta on public and private pages.
- Property title, description and canonical.
- Listing schema with and without exact coordinates.
- Demo listings: noindex, follow, and no schema.
- Related listings (same city, purpose and type).

*Location pages:*
- City category pages show only matching live listings.
- Thin and demo-only pages are noindex.
- 404s for unknown categories or cities and past-the-end pages.
- Paginated canonical.
- Area category pages and merged-area 301s.
- City page category links.

*Indexing rules:*
- Search canonical and noindex rules.
- The map is noindex.

*Sitemaps and robots:*
- Category pages appear in the sitemap only when indexable; private URLs never do.
- The split sitemap files work.
- Both robots.txt files.

*Performance and security:*
- Query stability on four location pages.
- Reset-password throttle.
- Staging noindex header.
- `.htaccess` rule present and correctly placed.
- Role-boundary regression.

**Fixes found by testing:**
- the ambiguous-column query (§21);
- property descriptions running past 155 characters (the "…" was added after the limit);
- the purpose wording in titles ("for Sale", not "for sale").

**Manual QA** (built-in browser, `php artisan serve`, logged out):
- **Robots, canonical and JSON-LD checked on 12 URLs:**
  - Home: indexable, with WebSite + Organization.
  - `/properties?city=Lahore&purpose=sale`: indexable, canonical to itself.
  - `…&sort=price_asc`: noindex, follow.
  - `/map`: noindex, follow.
  - `/lahore`: indexable, CollectionPage.
  - `/lahore/houses-for-sale`: noindex, follow (demo-only data).
  - `/lahore/castles-for-sale` and `/sialkot/houses-for-sale`: 404.
- **A demo property page** had the expected title and description, noindex, follow, and the breadcrumb Home › Lahore › Johar Town › Plots for Sale.
- **Sitemap and robots:** `/sitemap.xml` is `application/xml` with `X-Robots-Tag: noindex` and 61 URLs; robots.txt content is correct.
- **Mobile (375 px):** the category page has no horizontal scroll, and the hero, cards, images and footer render correctly.
- **Console:** no errors except the two intentional 404 probes.
- **Apache file-exposure probe:** see §35.

**Final validation:**

| Command | Result |
|---|---|
| `php artisan test` | **477 passed, 2,562 assertions, 0 failed** (52.8 s) |
| `vendor/bin/pint --test` | passed |
| `npm run build` | built in 1.68 s |
| `composer audit` | no advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `php artisan route:cache` / `config:cache` / `view:cache` | all succeeded (then cleared for local development) |
| `php artisan meezan:production-check` | 18 pass, 10 warn, 4 fail. All 4 failures are the local `.env` values: `APP_ENV=local`, debug on, local `APP_URL`, mail set to `log` |

## 41. Launch checklist

**Ready**
- [x] SEO meta, canonicals, Open Graph and Twitter tags on every public page
- [x] Structured data (Organization, WebSite, BreadcrumbList, CollectionPage/ItemList, RealEstateListing)
- [x] City pages, city and area category pages, area guides; thin pages protected
- [x] Sitemaps (single list or index plus split files) and robots.txt
- [x] Indexing rules for search, filters, sort, pagination and map
- [x] Database indexes, N+1 guards, query-budget tests, caching
- [x] Security headers, CSP, rate limits (including password reset), upload rules, private documents
- [x] Root `.htaccess` file-blocking rule (in the project)
- [x] Production-check command covering configuration, server, data and services

**Configuration required (on the live server)**
- [ ] **Upload the updated root `.htaccess` first.** Then confirm that `/.env`, `/composer.json`, `/artisan` and `/storage/logs/laravel.log` all return 403.
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`.
- [ ] Configure real SMTP mail and `MAIL_FROM_ADDRESS`, then turn on `MEEZAN_REQUIRE_EMAIL_VERIFICATION`.
- [ ] Set up the scheduler cron and `MEEZAN_QUEUE_VIA_SCHEDULER=true`.
- [ ] Set `MEEZAN_CONTACT_EMAIL` (also used in the Organization schema).
- [ ] Choose production map tile and Overpass providers.
- [ ] Remove the demo listings and `.test` accounts before launch. Only the owner should do this; it was not done here, as this phase forbids deletions.
- [ ] Redirect to a single canonical host (www or apex) over HTTPS.
- [ ] Submit `https://meezanproperty.com/sitemap.xml` in Google Search Console and Bing Webmaster Tools.
- [ ] Schedule database and storage backups (§37).
- [ ] Rebuild caches after every deploy (§36).

**Not yet tested**
- [ ] Any of this on the live server; nothing was deployed in this phase.
- [ ] Indexable category pages with real listings in the real database. This is covered by tests only, because local data is all demo.
- [ ] Rich-results validation in Google's Rich Results Test, which needs a public URL.
- [ ] Lighthouse or PageSpeed scores, which were not measured. None are claimed.
- [ ] A backup restore drill.
- [ ] Sitemap index mode with more than 5,000 real listings. The logic is tested at the unit level only, with the switch-over threshold unchanged.

**Known limitations**
- Times outside the CRM display in UTC (from earlier phases).
- Bunny Fonts load render-blocking.
- No CDN.
- No external error tracking or uptime monitoring.

## 42. Final figures

| Item | Figure |
|---|---|
| Tests | **477** passed (was 455; **+22** new) |
| Assertions | **2,562** |
| Failures | **0** |
| Security issues fixed | **2**: internal files served by the root `.htaccess` (critical), unthrottled password reset. Plus **1** hardening option: the staging noindex header |
| Correctness bug fixed | 1: ambiguous-column query on city category pages |
| New routes | **3**: `cities.category`, `areas.category`, `sitemap.show` (214 routes in total) |
| Database changes | **1** additive migration: 3 indexes on `properties`. No columns or tables added, no data changed |
| SEO pages available | 7 city pages; 63 city category pages (7 × 9); 9 category pages for every active area in the launch cities; existing area guides and listing pages. **Indexable locally: 0 category pages** (all local listings are demo) |
| Sitemap status | Working. Single list now (61 URLs locally), automatic index mode beyond 5,000 real listings; split files `/sitemaps/pages.xml`, `/sitemaps/locations.xml`, `/sitemaps/properties-N.xml` |
| Production blockers | 1 urgent (deploy the root `.htaccess`), plus the configuration items in §41 |
| External services | None added. Already configured but not live: SMTP mail, WhatsApp Cloud API (mock locally), payment gateways (mock locally), map tiles and Overpass (public endpoints) |
