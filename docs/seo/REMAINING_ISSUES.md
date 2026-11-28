# Remaining issues and manual actions

## Needs the owner (external access or a business fact)

| Item | Why it matters | What to do |
|---|---|---|
| Google Search Console | Only way to see indexing, queries and errors | Verify the domain (DNS, or set `MEEZAN_GOOGLE_SITE_VERIFICATION` in the live `.env`), submit `https://meezanproperty.com/sitemap.xml` |
| Bing Webmaster Tools | Bing also feeds Copilot and other assistants | Verify (`MEEZAN_BING_SITE_VERIFICATION`) and submit the sitemap |
| Official profiles | Tells search engines and AI tools which "Meezan" this is | Create or confirm a Google Business Profile for the Gulberg-III office and official social profiles; put their URLs in `MEEZAN_SAME_AS` (comma-separated) |
| Meezan Bank disambiguation | Searches for the name return Meezan Bank and unrelated "Al Meezan" agencies | If true, approve a one-line statement on the About page such as "Meezan Property is not affiliated with Meezan Bank." Not added, because it is a business fact only the owner can confirm |
| Google Analytics 4 / Tag Manager | No analytics found | Adding it changes privacy behaviour. It needs a privacy-policy update and a Content-Security-Policy change. Not added; approve first |
| Rich Results Test and Schema.org Validator | Final check on the live URLs | Run after deployment on a listing, a city page, an area guide and the home page |
| Core Web Vitals | LCP, INP and CLS not measured | Run PageSpeed Insights after deployment; field data appears in Search Console once there is traffic |

## Needs approval before implementation (higher risk)

| Item | Risk | Notes |
|---|---|---|
| Full-page cache for signed-out visitors | Medium | Biggest remaining TTFB gain (server render is 60–190 ms; live TTFB 1–4 s). Pages embed a CSRF token in forms, so a cached page must strip or refresh it, or forms would fail with 419 errors |
| Home H1 "Find Your Perfect Property" | Low, but visible copy | Suggest "Homes, plots and commercial property in Pakistan". Changes the hero text, so not done without approval |
| Investment Index H1 | Low, visible | Several headlines are combined into one H1 of over 100 characters |
| "Official Verified Approved" badge on project profiles | Wording | Could read as Meezan Property certifying a scheme; suggest "Approved (per authority list)", as on area guides |
| Hero image `images/places/home.jpg` (272 KB JPEG, preloaded) | Low | Convert to WebP/AVIF at the same dimensions; check visually first |
| Inline SVG icons (70–160 per page) | Medium | Move to a cached sprite to cut HTML size; touches the icon component used everywhere |
| Self-hosted fonts (currently fonts.bunny.net) | Low | Removes a third-party connection before text renders |
| CSP blocks inline `onerror` image fallbacks | Low, existing bug | `components/investment-hero.blade.php`, `investment-index/partials/hero.blade.php`, `investment-index/project.blade.php`, `investment-index/compare.blade.php`, `admin/investment-index/evidence-gaps.blade.php`; move the handlers into `app.js` |
| Urdu pages with `hreflang` | Large | Content and translation work |

## Content to commission (AEO)

General buyer guides need researched, dated and cited content (LDA, CDA, RDA, KDA, SBCA, FBR, provincial board of revenue) and a named reviewer:

- how to buy property in Pakistan and the documents to check;
- how to check housing-society approval (per authority);
- file vs plot;
- the transfer process;
- taxes and fees on purchase and sale;
- rental yield and capital appreciation;
- comparing commercial and residential developments.

These were not written here, so that nothing inaccurate is published.

## Known limits of this work

- 1,273 project profiles are reachable only through the 107-page directory and the sitemap. Profiles on deep directory pages are many clicks from the home page. City-level and category-level directory hubs would help, but they are new pages, so they need approval.
- Many area guides with no listings stay "noindex, follow" by design (unchanged).
- Listing count (11 live) is the main limit on how much search traffic the site can earn.
- No claim is made that any page is indexed, ranks, or is cited by ChatGPT, Gemini, Perplexity or Copilot. That can only be judged from Search Console and real queries after deployment.
