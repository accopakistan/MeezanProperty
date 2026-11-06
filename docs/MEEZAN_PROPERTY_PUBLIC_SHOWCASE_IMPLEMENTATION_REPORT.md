# Meezan Property: Public Showcase Implementation Report

Date: 6 November 2026. This builds on the QA report `MEEZAN_PROPERTY_COMPLETE_LIVE_WEBSITE_QA_REPORT.md`. Nothing was rebuilt, deployed or pushed, and nothing is committed yet.

## The situation, honestly

After the cleanup there are **0 published listings and 0 public dealers**: every listing on the live site was sample or test data. The site now says so, and invites dealers and owners to list, instead of showing fake inventory.

The **Investment Index** has 1,273 researched projects and 0 verified ranks. The pages present that research and no longer use ranking language that implies otherwise.

## What changed, by QA finding

| QA finding | Status | What was done |
|---|---|---|
| C-1, C-3: sample and test listings live, lorem ipsum, implausible price | Fixed | 17 listings archived (not deleted). Code keeps sample listings out of every public query. Placeholder text is rejected on submission. See the sample data cleanup report |
| C-2: test dealers shown as Verified | Fixed | Both marked sample. Hidden from `/agents`, no badge, no leads. Verification records kept |
| C-4: draft legal pages | Handled, needs your content | Pages now say "Not yet final", are noindex and out of the sitemap. Registration no longer asks people to accept unapproved terms. New `/disclaimer`. Approved text can be dropped in without code changes |
| H-2, item 79: "Best Overall Investment in Pakistan" | Fixed | Heading is "Investment Insights" until something is ranked. Canonical added. The category label is now "Overall Investment" |
| H-3, items 21 and 100: no contact details | Fixed | Official phone, WhatsApp, email and address in the header, footer, contact page, About page and mobile menu. See the contact report |
| H-4, item 102: no WhatsApp button | Fixed | Floating "WhatsApp us" button on every public page. Copy no longer claims the Cloud API assistant is live |
| H-5: draft packages public | Fixed | Draft packages hidden and can't be bought (404). Records and admin kept. `/pricing` says packages are being finalised and is noindex |
| H-6: no call or WhatsApp on listings | Kept, plus a fallback | Dealer call and WhatsApp buttons already appear for verified dealers with public numbers. A "WhatsApp Meezan Property" link was added for every listing |
| H-7, M-10: slow pages | Partly code | See Performance. The live 2–4.6 s is mostly hosting |
| H-1: zero rankings | By design | Evidence rules unchanged, nothing faked, provisional positions stay hidden |
| M-1: www duplicate | Fixed in the app | `www.` requests get a 301 to the main domain, with path and query kept |
| M-2: "Disputed" beside "Low risk" | Fixed | Projects under research show risk "Not assessed" |
| M-3: mixed terms; city rows don't add up | Fixed | City cards show Projects, Ranked, Under research and Excluded, which add up |
| M-4: phone accepts letters; consent not required | Fixed | Shared phone rule on every form, `required` on consent boxes, numeric keypad on phone fields |
| M-5: `/buy` shows houses only | Fixed by naming | Page is "Homes for Sale", and its intro points to Plots and Commercial |
| M-6: thin area pages | Already handled | Areas with no listings that aren't popular are already noindex. Popular areas stay indexable |
| M-7: sitemap 64 URLs | Improved | Adds the five Index category pages. Drops `/pricing` and draft legal pages until final. Areas and city pages follow the existing indexable rules |
| M-8: AI can't explain the Index | Fixed | New and updated knowledge documents. See the Gemini report |
| M-9: tablet overflow on leaderboards | Fixed | The pagination row wraps. Re-checked at 768, 980, 390 and 360 px |
| L-1: robots.txt blocks dealer registration | Fixed | `Allow: /dealer/register` |
| L-3: missing canonicals | Consistent | Noindex pages (map, compare, auth, filtered searches) carry no canonical by design. Indexable pages have self-canonicals. Canonicals added to contact, Insights and MPIS methodology |
| L-4: two methodology pages | Clarified | They describe different methods. The `/investment/methodology` page is now "Market insight score (MPIS) methodology" and links to the Index methodology |
| L-6: unknown search term; page 99 | Fixed | The heading echoes an unmatched term ("… matching “zzzzqq”"). A page past the end redirects to the last page |
| L-7: sale and rent sorted on one scale | Fixed | Price sorting without a purpose puts sales before rentals |
| X-2: images without alt | Checked | 0 images lack `alt`. The three with `alt=""` are decorative inside a hidden mock-up, which is correct for accessibility |
| **Found during this work: broken structured data** | Fixed | Laravel 12 compiles `@context` as a Blade directive, so every page's JSON-LD (breadcrumbs, organization, listings) contained PHP code. Fixed in 6 templates and covered by a test |

## Not done, and why

| Item | Reason |
|---|---|
| Real listings and dealers | Needs real dealers. Nothing was invented |
| Final Terms, Privacy, Disclaimer | Needs you or legal counsel |
| Final packages | Needs your prices and limits |
| Social media links | None were given, so none were added |
| Blog and FAQ | No approved content |
| Desktop menu collapsing at about 980 px (X-3) | A design change to the header. Left as it is |
| Price-outlier moderation | Needs trusted price data |
| Area import oddities (L-8) | A data review, outside this brief |

## Performance

Measured on the local copy through the app itself: one warm-up, then the median of three runs.

| Page | Before | After |
|---|---|---|
| `/investment-rankings/pakistan` | 952 ms, 123 queries | 718 ms, 79 queries |
| `/investment-rankings/lahore` | 715 ms, 122 queries | 542 ms, 78 queries |
| Other public pages | 23–322 ms | 23–319 ms |

What was fixed:

- **One Index browser per request.** The published run is looked up once, not 16 times.
- **Missing settings are cached.** Fourteen cache writes on every page view are gone.

A cache for the ranking dashboards was tried and **removed**: the cached data was too large for the database cache store, which MySQL rejected.

**The live site's 2–4.6 s is mainly hosting.** The same code serves most pages here in under 0.3 s. See the hosting actions below.

## Files changed

- **New:** `app/Support/Contact.php`, `app/Support/Legal.php`, `app/Rules/PhoneNumber.php`, `resources/views/components/site/whatsapp-float.blade.php`, `components/site/contact-list.blade.php`, `auth/partials/terms-consent.blade.php`, migrations `2026_11_06_000060` and `2026_11_06_000061`, `tests/Feature/PublicShowcaseTest.php`.
- **Changed:**
  - Models: `Property`, `DealerProfile`, `User`, `Package`, `AppSetting`, `InvestmentIndexRun`.
  - Controllers: `DealerDirectoryController`, `AiFinderController`, `PageController`, `PricingController`, `Dealer/SubscriptionController`, `PropertyController`, `CategoryController`, `SitemapController`, plus phone validation in `InquiryController`, `SellController`, both registration controllers and both profile controllers.
  - Services and support: `PropertySearch`, `Leads/LeadRouter`, `CityDirectory`, `Agents/ToolRegistry`, `Investment/RankingBrowser`, `Seo`.
  - Middleware and providers: `TrustAppHostsOnly`, `AppServiceProvider`, `PropertyRequest`.
  - Config and robots: `config/meezan.php`, `.env.example`, `routes/web.php`, `public/robots.txt`.
  - Views: layouts, header, footer, home, contact, about, legal, pricing, registration forms, property page, AI finder, Index home, category and project pages, investment index and methodology pages, rankings hero, group page, field component, and the six JSON-LD templates.
  - Tests: `HostHeaderTest` (www now redirects).

## Database changes

| Migration | Change |
|---|---|
| `2026_11_06_000060` | Adds `dealer_profiles.is_demo`; marks 2 dealers; archives 17 listings; writes 19 audit entries |
| `2026_11_06_000061` | Adds 3 knowledge documents and updates 3 |

Nothing was dropped or deleted. A backup was taken first, at `C:\xampp\backups\MeezanProperty-before-public-showcase-2026-11-06\`.

## Owner decisions

1. Approved Terms, Privacy and Disclaimer text. Put it in `resources/views/pages/legal/approved/{terms,privacy,disclaimer}.blade.php` and set `MEEZAN_TERMS_APPROVED_ON` (and the privacy and disclaimer equivalents) in `.env`.
2. Final dealer packages: set `is_demo` off on the packages you approve, in admin.
3. Whether to rename the "Meezan Admin (Demo)" account.
4. Social media links, if any.
5. When to commit, push and deploy.

## Hosting actions after deployment

1. Back up the live database, then run `php artisan migrate --force`.
2. Run `php artisan optimize` (config, route and view caches).
3. Turn on OPcache.
4. Consider `CACHE_STORE=file` or Redis instead of `database`.
5. Check that the live `.env` doesn't override the contact details.
6. Keep `WHATSAPP_MOCK_MODE=true` until you approve a live test.
