# Meezan Property — Complete Live Website QA Report

| | |
|---|---|
| **Website** | https://meezanproperty.com |
| **Audit date** | 6 November 2026 |
| **Audit type** | Read-only, browser-based functional / UX / content / SEO audit of the live production site |
| **Tester role** | Senior QA / UX auditor (performed by Claude via Claude in Chrome) |
| **Browser** | Google Chrome (desktop), Claude in Chrome side panel (visible viewport ≈ 980 px); other widths tested with same-origin iframes at 360 / 390 / 768 / 1366 / 1440 px |
| **Sessions used** | (a) Logged-out visitor view — all public pages were fetched with cookies omitted, so they show what an anonymous visitor sees; (b) the existing **"Meezan Admin (Demo)"** administrator session that was already open in the browser, used read-only |

> **Safety statement.** No forms were submitted, no accounts were created, no inquiries, reports, payments, WhatsApp or e-mail messages were sent, no admin setting, listing, dealer, package or ranking was changed. The only interactions were navigation, opening menus, typing into the public search / AI assistant / rental-yield calculator, and client-side validation checks performed on a detached copy of the inquiry form (never submitted). Two harmless informational questions were sent to the public AI assistant.

---

## A. Executive Summary

**Overall status: the platform is technically solid, but the content behind it isn't ready yet.**

The code largely works. Pages load without server errors and there were no JavaScript console errors. Search, filters, sorting and pagination behave correctly, the AI search returns relevant matches, layouts hold together from 360 px to 1440 px, and every admin module opens.

The content and operational layer is not ready for real users:

- **All 17 live listings are sample/test data.** 16 come from a dealer called "Sample Realty", whose profile says *"Sample dealer profile used for local development"*. The 17th belongs to a test dealer, "Jonathon Estate", and its description is lorem-ipsum placeholder text. Both dealers carry a **"Verified dealer"** badge.
- **The Meezan Investment Index has 0 ranked projects out of 1,273**, and every leaderboard is empty. Even so, pages carry headings like "Best Overall Investment in Pakistan" and "Transparent Rankings".
- **Terms and Privacy pages are publicly labelled "Draft for review … must be reviewed by a qualified lawyer."**
- **WhatsApp is not live.** There is no floating button, and the site shows no phone number, e-mail or WhatsApp link in the header, footer or contact page. Admin shows *"Cloud API credentials are not configured"* and *"TEST / MOCK MODE"*.
- **Dealer packages are publicly shown as "Free (draft)" and "Gold (draft)"**, described as *"Draft package for testing. Terms not final."*
- The server is slow: the HTML documents took **2–4.6 s** to arrive.

| Metric | Result |
|---|---|
| Unique public URLs discovered (excluding filter/query variants) | **≈ 1,610** (≈ 1,325 area pages, 162 project pages, 43 investment-ranking URLs, 18 property URLs, ~60 other pages) |
| Pages fetched and checked automatically (status, title, meta, canonical, H1, robots) | **379 public + 23 admin + 4 404-probes** |
| Page templates / pages inspected in depth (content + behaviour) | **46** |
| Features tested (rows in Section C) | **112** |
| PASS | **60** |
| FAIL | **17** |
| PARTIAL | **27** |
| NOT TESTED | **8** (plus admin actions and other untested areas listed in Section I) |
| Critical issues | **4** |
| High-priority issues | **7** |
| Medium issues | **10** |
| Low issues | **8** |
| Cosmetic issues | **3** |
| HTTP 5xx / server error pages found | **0** |
| JavaScript console errors observed | **0** (home, /properties, /map) |
| Broken images observed | **0** |

---

## B. Page Inventory

Status key: **PASS** = loads and works · **PARTIAL** = loads but has content/function gaps · **FAIL** = broken or wrong · **NOT TESTED** = needs credentials or a safe environment.

### B1. Core public pages

| Page | URL | Type | Access | Status | Notes |
|---|---|---|---|---|---|
| Homepage | `/` | Landing | Public | PARTIAL | Works; all listings are sample data |
| All properties | `/properties` | Search results | Public | PASS | 17 listings, 12 per page, 2 pages |
| Search alias | `/search?…` | Redirect → `/properties?…` | Public | PASS | |
| Buy | `/buy` | Category | Public | PARTIAL | H1 "Houses for Sale" — shows houses only (6), not all 12 for-sale listings |
| Rent | `/rent` | Category | Public | PASS | 5 listings |
| Plots | `/plots` | Category | Public | PASS | 4 listings |
| Commercial | `/commercial` | Category | Public | PASS | 3 listings |
| Houses | `/properties?type=house` | Category | Public | PASS | |
| Apartments & Flats | `/properties?type=apartment` | Category | Public | PASS | 2 listings |
| Property detail ×17 | `/properties/{slug}` | Detail | Public | PARTIAL | 16 "Sample listing"; 1 test listing with lorem ipsum |
| Map Search | `/map` | Search | Public | PASS | Leaflet / OSM, 17 properties |
| Compare | `/compare` | Tool | Public | PASS | Empty state explains how to add |
| AI Property Finder | `/ai-property-finder` | AI tool | Public | PASS | Gemini-backed per admin; returns matches |
| Area Guides index | `/areas` | Directory | Public | PASS | |
| Area pages (≈1,325) | `/areas/{slug}` | Location | Public | PARTIAL | 278 sampled: all 200 OK; nearly all show 0 listings (thin content) |
| City pages ×7 | `/lahore`, `/karachi`, `/islamabad`, `/multan`, `/faisalabad`, `/quetta`, `/peshawar` | Location | Public | PASS | |
| City sub-pages | `/lahore/houses-for-sale`, `/{city}/property-for-sale`, `/{city}/property-for-rent` … | Location | Public | PASS | Non-Lahore cities have 0 listings |
| New Projects | `/projects` | Directory | Public | PASS | All "Status not verified" |
| Project pages (162) | `/projects/{slug}` | Detail | Public | PASS (sampled) | |
| Agents | `/agents` | Directory | Public | FAIL | Lists only 2 dealers — both sample/test, both "Verified" |
| Agent: Sample Realty | `/agents/demo-realty-sample-data` | Profile | Public | FAIL | "Sample dealer profile used for local development" |
| Agent: Jonathon Estate | `/agents/jonathon-estate` | Profile | Public | FAIL | Test dealer, "Verified" |
| Sell / List | `/sell` | Landing | Public | PASS | |
| List as owner | `/sell/owner` | Registration | Public | PASS (inspected) | Not submitted |
| Pricing / Packages | `/pricing` | Commercial | Public (not in nav) | FAIL | Packages labelled "draft … for testing" |
| How it works | `/how-it-works` | Info | Public | PASS | |
| About | `/about` | Info | Public | PASS | |
| Contact | `/contact` | Lead form | Public | PARTIAL | Form only; no phone/e-mail/WhatsApp/address |
| Terms | `/terms` | Legal | Public | FAIL | "Draft for review" |
| Privacy | `/privacy` | Legal | Public | FAIL | "Draft for review" |
| Login | `/login` | Auth | Public | PASS (inspected) | |
| Buyer register | `/register` | Auth | Public | PASS (inspected) | |
| Dealer register | `/dealer/register` | Auth | Public | PASS (inspected) | |
| Forgot password | `/forgot-password` | Auth | Public | PASS (inspected) | Not submitted |
| Save requirement | `/account/requirements/create` | Account | Login required | PASS | Redirects to `/login` when logged out |
| Sitemap | `/sitemap.xml` | SEO | Public | PARTIAL | 64 URLs only |
| Robots | `/robots.txt` | SEO | Public | PASS | Blocks `/dealer/` (incl. registration) |
| Custom 404 | any unknown URL | Error | Public | PASS | Proper 404 status and branded page |

### B2. Investment Index pages

| Page | URL | Status | Notes |
|---|---|---|---|
| Investment Index home | `/investment-rankings` | PARTIAL | Clear stats, but 0 ranked of 1,273 |
| Methodology | `/investment-rankings/methodology` | PASS | |
| City dashboards ×7 + Pakistan | `/investment-rankings/{city}`, `/pakistan` | PARTIAL | "Transparent Rankings" claim, 0 rankings |
| Housing Societies leaderboard | `/investment-rankings/housing-societies` | PARTIAL | Empty; horizontal overflow at 768 px |
| Commercial Plazas | `/investment-rankings/commercial-plazas` | PARTIAL | Empty |
| Apartment Buildings | `/investment-rankings/apartment-buildings` | PARTIAL | Empty |
| Shopping Malls | `/investment-rankings/shopping-malls` | PARTIAL | Empty |
| Business Towers | `/investment-rankings/business-towers` | PARTIAL | Empty |
| Overall Index | `/investment-rankings/overall` | PARTIAL | Empty |
| Project index detail | `/investment-rankings/projects/{slug}` | PARTIAL | Contradictory "Disputed" + "Low risk" signals |
| Investment Insights | `/investment` | FAIL | H1 "Best Overall Investment in Pakistan" with no rankings; noindex; no canonical; duplicates `/investment-rankings` |
| Investment compare | `/investment/compare` | PASS | Honest empty state |
| Rental yield calculator | `/investment/rental-yield-calculator` | PASS | 2 Cr value / 1 Lakh rent → 6% (correct) |
| Investment methodology (dup) | `/investment/methodology` | PASS | Second methodology URL |

### B3. Discovered-but-missing pages (requested by the audit brief)

| Page | URL tried | Result |
|---|---|---|
| Blog | `/blog` | 404 — no blog exists, none linked |
| FAQ | `/faq` | 404 — none linked |
| Disclaimer | `/disclaimer` | 404 — disclaimer exists only as inline text on investment pages |
| Customer dashboard | `/dashboard`, `/saved` | 404 at these guessed URLs (header "Saved" link goes to `/login` when logged out) |

### B4. Admin pages (existing admin session, read-only)

All 24 admin pages returned **200** with no server errors, but each took **1.6 – 5.8 s** to load.

| Module | URL | Load | Key observation |
|---|---|---|---|
| Dashboard | `/admin` | ✓ | 18 properties, 4 users, 2 verified dealers, **0 inquiries ever**, 17 published |
| Properties | `/admin/properties` | 4.6 s | 18 total: 1 draft, 17 published, queue empty |
| Users | `/admin/users` | 2.1 s | |
| Dealers | `/admin/dealers` | **5.8 s** | 2 dealers, both "Verified" (test/sample) |
| Listing Reports | `/admin/reports` | 2.5 s | |
| Marketplace Trust | `/admin/trust` | 3.0 s | |
| Duplicate Review | `/admin/properties-duplicates` | 1.5 s | |
| New Projects | `/admin/projects` | 2.3 s | |
| Locations | `/admin/areas` | 3.3 s | |
| Map Boundaries | `/admin/boundaries` | 2.2 s | |
| Market Data Centre | `/admin/market` | 1.9 s | |
| Investment Index | `/admin/investment-index` | 1.7 s | "0 ranked of 1273", **47 exceptions** awaiting decision |
| Leads | `/admin/leads` | 2.2 s | |
| Lead Oversight (CRM) | `/admin/crm` | 1.7 s | |
| AI Command Center | `/admin/ai` | 2.2 s | Google Gemini configured, 16 agents |
| AI & WhatsApp conversations | `/admin/conversations` | 1.9 s | "WhatsApp: integration pending. Cloud API credentials are not configured" |
| WhatsApp Control | `/admin/whatsapp` | 3.1 s | "TEST / MOCK MODE" |
| Billing / Packages / Subscriptions / Payments | `/admin/billing` … | 1.6–2.3 s | |
| Analytics | `/admin/analytics` | 2.3 s | |
| Settings | `/admin/settings` | 2.7 s | |
| Audit log | `/admin/audit-logs` | 1.8 s | |

---

## C. Feature Testing Results

### C1. Homepage & header

| # | Feature | Status | Evidence |
|---|---|---|---|
| 1 | Logo → home | PASS | Links to `/` |
| 2 | Main navigation links (Buy, Rent, Plots, Commercial, Projects, Investment Rankings, Agents, AI Search) | PASS | All return 200 |
| 3 | "More" dropdown (Sell, Map, Investment Insights, Houses, Apartments, Area Guides, Compare…) | PASS | All return 200 |
| 4 | Mobile / hamburger menu | PASS | Opens full-width menu, items listed |
| 5 | Desktop nav at ~980 px viewport | PARTIAL | Collapses to hamburger at ~980 px. Small laptops and split windows get the mobile menu |
| 6 | Hero heading / copy | PASS | "Find Your Perfect Property" |
| 7 | Hero search tabs (Buy/Rent/Plots/Commercial) swap filters | PASS | Rent swaps to monthly rent ranges; Plots shows size; Commercial shows stage + rankings selector |
| 8 | Hero search submit | PASS | `/search` → `/properties` with correct filters |
| 9 | "Buy" tab property-type list | PARTIAL | Lists plots, shops, factories, agricultural land under "Buy" alongside homes (overlaps Plots/Commercial tabs) |
| 10 | Select labels at 980 px | PARTIAL | "Property Typ…" text truncated in select box (cosmetic) |
| 11 | Popular searches chips | PASS | DHA Lahore, Bahria Town, Lake City, Gulberg, Johar Town, Model Town |
| 12 | Featured Properties | PARTIAL | Render correctly, but every card is badged "Sample listing" |
| 13 | Explore by Property Type tiles | PASS | |
| 14 | Popular Areas in Lahore | PASS | |
| 15 | Explore Properties Across Pakistan (city cards) | PARTIAL | Works; area counts look odd (Quetta 330 areas vs Karachi 84); 6 of 7 cities "New on Meezan" with 0 listings |
| 16 | Newly Listed | PARTIAL | Real-looking newest listing uses a placeholder illustration ("No photos yet") |
| 17 | "WhatsApp Support" benefit tile | FAIL | Links to `/ai-property-finder`, where WhatsApp says "being set up and will be available soon" |
| 18 | "Verified Dealers" benefit tile | PARTIAL | Links to How-it-works; the only "verified" dealers are sample/test |
| 19 | Dealer CTA (Join as a Dealer / Dealer Login) | PASS | |
| 20 | Footer links (Quick Links, Popular Locations, Support) | PASS | |
| 21 | Footer contact block | FAIL | Only "Head office: Lahore, Pakistan" + "Send us a message"; no phone, e-mail, WhatsApp, street address |
| 22 | Social media links | FAIL | None anywhere on the site |
| 23 | Images load | PASS | 0 broken; 3 of 26 homepage images lack `alt` |
| 24 | Hero photo attribution (CC BY-SA) | PASS | Attribution present |

### C2. Property search engine

| # | Test | Status | Result |
|---|---|---|---|
| 25 | Buy house in Lahore | PASS | "5 Houses for Sale in Lahore", 5 correct listings |
| 26 | Rent apartment in Islamabad | PASS | 0 results + helpful "No listings match … try widening" message |
| 27 | Commercial in Karachi | PASS | 0 results + helpful message |
| 28 | Plots in Lahore | PASS | 4 plots |
| 29 | DHA + max budget 5 Cr | PASS | 2 results, both ≤ 5 Cr |
| 30 | Nonsense location ("zzzzqq") | PARTIAL | 0 results, but heading still says "Properties for Sale in Pakistan" (location silently ignored in heading) |
| 31 | Min + max price | PASS | 7 results |
| 32 | Beds ≥ 3 | PASS | 8 results |
| 33 | Keyword q=DHA | PASS | 5 results |
| 34 | Area filter `area=dha-lahore` | PASS | 5 results |
| 35 | Sort price ascending | PASS | Correct order |
| 36 | Sort price descending | PASS | Correct order |
| 37 | Sort mixing sale + rent | PARTIAL | Unfiltered `/properties` sorts monthly rents (PKR 85,000) and sale prices (PKR 35 Cr) on one scale |
| 38 | Pagination | PASS | Page 1 = 12, page 2 = 5 (17 total) |
| 39 | Out-of-range page (`page=99`) | PARTIAL | Heading says "17 Properties" while showing "No listings match" |
| 40 | Clear All filters | PASS | Resets to `/properties?purpose=sale` |
| 41 | URL parameters | PASS | Shareable, readable query strings |
| 42 | Result counts vs cards | PASS | Consistent |
| 43 | Map search | PASS | "17 Properties Found", list/map toggle, filters |

### C3. Property listing / detail pages

Tested: `10-marla-house-in-dha-lahore` (dealer listing) plus card checks on all 17.

| # | Feature | Status | Evidence |
|---|---|---|---|
| 44 | Detail page loads, title & SEO title | PASS | "10 Marla House for Sale in DHA Lahore, Lahore — PKR 1.7 Crore" |
| 45 | Breadcrumbs | PASS | Home › Lahore › DHA Lahore › Houses for Sale › … |
| 46 | Price, price-per-marla, beds, baths, size, type, furnishing | PASS | |
| 47 | Property description | FAIL | Lorem-ipsum placeholder text: *"is a nonsensical, pseudo-Latin placeholder text widely used in graphic design…"* |
| 48 | Data plausibility | FAIL | 10 Marla DHA Lahore house at PKR 1.7 Cr, status "Under construction" — implausible price, inconsistent with other DHA listings (5.8–9.5 Cr) |
| 49 | Photos / gallery | PARTIAL | Dealer listing: "No photos yet" illustration. Sample listings use stock/AI-style photos |
| 50 | Dealer card | FAIL | "Jonathon Estate — Verified Dealer" (test account presented as verified) |
| 51 | Call button / tel link | FAIL | None on the detail page |
| 52 | WhatsApp button on listing | FAIL | None on the detail page |
| 53 | Inquiry form (Send Message / Request a Visit) | PARTIAL | Required-field and e-mail validation work; **phone field accepts letters** ("abc" passes); consent checkbox not required client-side. Not submitted |
| 54 | Honeypot / CSRF on forms | PASS | Hidden `website` honeypot and `_token` present |
| 55 | Map + "View on Google Maps" | PASS | Leaflet/OSM pin with advertiser disclaimer |
| 56 | Nearby places | PARTIAL | Category headings render; no places listed in the extracted text for this pin |
| 57 | Home-loan calculator | PASS | Renders estimate with disclaimer |
| 58 | Similar properties | PASS | 4 relevant Lahore houses |
| 59 | Save / Share / Add to compare / Report | NOT TESTED | Not clicked (Save and Report create records) |
| 60 | Listing indexability | PASS | 16 sample listings are `noindex`; only the dealer listing is indexable and in the sitemap |

### C4. Categories & locations

| # | Feature | Status | Evidence |
|---|---|---|---|
| 61 | `/buy` | PARTIAL | Titled "Houses for Sale" — excludes the apartment, plots and commercial listings that are also for sale |
| 62 | `/rent`, `/plots`, `/commercial` | PASS | |
| 63 | City pages ×7 | PASS | All load; 6 of 7 have zero listings |
| 64 | Lahore sub-pages (houses/flats/plots/commercial for sale/rent) | PASS | 10 URLs, all 200 |
| 65 | Area pages | PARTIAL | 278 of ≈1,325 sampled, all 200, but nearly all show "0 live listings, 0 verified dealers" |
| 66 | Area approval sources | PARTIAL | Example (Talib Homes): approval source is a *2021 Zameen News* article, checked 30 Oct 2026. Stale, and sourced from a competitor |
| 67 | Projects directory | PASS | Filters (Upcoming / Under construction / Completed) present; all "Status not verified" |

### C5. Meezan Investment Index

| # | Feature | Status | Evidence |
|---|---|---|---|
| 68 | Index overview page | PASS | City table sums correctly (463+273+110+113+222+49+43 = 1,273) |
| 69 | Rankings actually published | FAIL | **0 ranked** in every city and category; 1,251 under research, 22 excluded |
| 70 | Housing Societies / Plazas / Apartments / Malls / Towers / Overall leaderboards | PARTIAL | All load with an honest "Insufficient Verified Data — Research in Progress" message, but all are empty |
| 71 | "Top …" widgets | PARTIAL | Titled "Top Housing Societies" etc. while showing "Most documented so far, not a ranking" |
| 72 | Terminology consistency | PARTIAL | Same state is called "Insufficient data", "Under research", "Under review" and "Research in Progress" on one page. Per-city "Under review" = 0 while overall "Under research" = 1,251 |
| 73 | City rows that don't add up | PARTIAL | Lahore 463 projects = 0 ranked + 0 review + 458 insufficient (5 unexplained, presumably excluded); Islamabad 110 vs 93 (17 unexplained) |
| 74 | Project detail page | PARTIAL | Canal View: "Data status: **Disputed**" yet "Risk: **Low risk**" and "Legal: Verified Approved", with confidence 61 while evidence covers 25% |
| 75 | Missing fields explained | PASS | Each missing factor listed; "nothing is estimated" |
| 76 | Sources / evidence shown | PASS | Authority list + read date shown |
| 77 | Provisional rankings hidden from public | PASS | No provisional scores exposed to logged-out visitors |
| 78 | Methodology page | PASS | Weights published per category |
| 79 | `/investment` "Best Overall Investment in Pakistan" | FAIL | Heading implies a recommendation while there are no rankings; page is `noindex`, has no canonical, duplicates `/investment-rankings` |
| 80 | City dashboards' "Real Data · AI Analysis · Transparent Rankings" tagline | PARTIAL | Claim not yet backed by any ranking |
| 81 | Project search (city + required category) | PASS | Form present |
| 82 | Investment compare | PASS | Honest empty state |
| 83 | Rental yield calculator | PASS | Correct maths (6%) and clear disclaimer |
| 84 | Leaderboard at tablet width | FAIL | 768 px: page scrolls horizontally (scrollWidth 774 > 753); the filter chip list (`UL.flex`) overflows |

### C6. Registration & login

| # | Feature | Status | Evidence |
|---|---|---|---|
| 85 | Login form | PASS | Email*, password*, remember |
| 86 | Buyer registration form | PARTIAL | Fields OK; **terms checkbox not `required`**; no client-side password minimum; phone optional |
| 87 | Dealer registration form | PARTIAL | Individual/agency, cities, ≈40 area checkboxes; **terms not `required`**; no client-side password rules |
| 88 | Forgot password | PASS (inspected) | Email* only; not submitted |
| 89 | Owner listing signup (`/sell/owner`) | PASS (inspected) | |
| 90 | Auth redirect for protected pages | PASS | `/account/requirements/create` → `/login` |
| 91 | Server-side validation / success / verification e-mails | NOT TESTED | Would create real accounts |
| 92 | Logged-in users hitting `/dealer/register` | PASS | Redirects to the user's dashboard (admin) |

### C7. Customer dashboard

| # | Feature | Status |
|---|---|---|
| 93 | Dashboard, saved properties, inquiries, notifications, profile, password | NOT TESTED — no test customer account was provided |

### C8. Dealer dashboard

| # | Feature | Status |
|---|---|---|
| 94 | Dealer profile, listing management, leads, subscription, limits | NOT TESTED — no test dealer account was provided |

### C9. Subscriptions & packages

| # | Feature | Status | Evidence |
|---|---|---|---|
| 95 | Pricing page loads | PASS | `/pricing` |
| 96 | Package content | FAIL | Only "Free (draft)" and "Gold (draft) PKR 7,500/month", both "Draft package for testing. Terms not final." |
| 97 | Discoverability | PARTIAL | `/pricing` is in the sitemap but not in header or footer navigation |
| 98 | Upgrade / purchase / payment | NOT TESTED | Payment sandbox required |

### C10. Contact & lead generation

| # | Feature | Status | Evidence |
|---|---|---|---|
| 99 | Contact form fields + validation | PASS | name*, email*, phone*, message*, honeypot. Not submitted |
| 100 | Contact details (phone / e-mail / address / hours) | FAIL | None shown anywhere on the site |
| 101 | Lead delivery end-to-end | NOT TESTED | Would send a real lead (admin shows 0 inquiries ever) |

### C11. WhatsApp

| # | Feature | Status | Evidence |
|---|---|---|---|
| 102 | Floating WhatsApp button | FAIL | None on any page |
| 103 | Intended number +92 311 1749849 shown publicly | FAIL | Only appears as `wa.me` link on the **Sample Realty** dealer profile (direct link, no pre-filled text) |
| 104 | WhatsApp assistant / Cloud API | FAIL | AI page: "Our WhatsApp assistant is being set up…". Admin: "Cloud API credentials are not configured", "TEST / MOCK MODE" |
| 105 | Outbound message delivery | NOT TESTED | Prohibited by brief |

**Implementation observed:** a direct `wa.me` link (dealer profile only). The WhatsApp Cloud API chatbot is built in admin but not configured.

### C12. AI assistant

| # | Feature | Status | Evidence |
|---|---|---|---|
| 106 | Entry points, welcome message (English/Urdu/Roman Urdu), input, send | PASS | |
| 107 | Property search by natural language | PASS | "10 marla house in DHA Lahore under 6 crore with 4 bedrooms" → parsed chips (city, area, buy, house, size, budget, beds) and 3 matches (100% / 87%…) |
| 108 | Informational Q&A | PARTIAL | Dealer-registration answer correct; on the Index it replied "Information regarding how the Meezan Investment Index works is missing from the provided data" |
| 109 | "Contact dealers" / "Save requirement" / "Share with dealers" | NOT TESTED | Would create leads |

### C13. Admin dashboard (read-only)

| # | Feature | Status | Evidence |
|---|---|---|---|
| 110 | All 24 admin modules load | PASS | No errors |
| 111 | Admin performance | PARTIAL | 1.6–5.8 s per page (Dealers 5.8 s, Properties 4.6 s) |
| — | Approve / edit / publish / AI actions | NOT TESTED | Prohibited by brief |

### C14. Blog & content

| # | Feature | Status | Evidence |
|---|---|---|---|
| 112 | Blog, FAQ, Disclaimer page | FAIL | Do not exist (404); no news/guides content for SEO |
| — | About, How it works | PASS | Clear, well written |
| — | Terms & Privacy | FAIL (counted in Section D) | Publicly marked draft |

---

## D. Bugs and Issues

### Critical

| ID | Page / URL | Journey | Action | Expected | Actual | Recommended fix |
|---|---|---|---|---|---|---|
| C-1 | `/`, `/properties`, all 17 `/properties/*` | Visitor / buyer | Browse listings | Real, reviewed listings | 16 of 17 listings badged "Sample listing" from dealer "Sample Realty" (*"Sample dealer profile used for local development"*); the 17th is a test listing | Unpublish/archive all sample listings and the sample dealer before promoting the site; seed real dealer inventory first, or show a clear "launching soon" state |
| C-2 | `/agents`, `/agents/demo-realty-sample-data`, `/agents/jonathon-estate`, listing dealer cards | Buyer trust | View dealers | "Verified dealer" only after real checks | Both public dealers are sample/test accounts **badged "Verified"** | Revoke the verified status of test accounts; hide test dealers from `/agents`; block the badge for accounts flagged as sample |
| C-3 | `/properties/10-marla-house-in-dha-lahore` | Buyer | Read description | Real description | Lorem-ipsum placeholder text; implausible price (1.7 Cr for 10 Marla DHA) | Remove or reject the listing; add a moderation rule that rejects lorem ipsum and price-per-marla outliers |
| C-4 | `/terms`, `/privacy` | All users | Read legal pages | Final, enforceable policies | Both start with *"Draft for review … must be reviewed by a qualified lawyer"* | Have a lawyer finalise both; remove the draft banner; add a Disclaimer page |

### High

| ID | Page / URL | Issue | Recommended fix |
|---|---|---|---|
| H-1 | `/investment-rankings` and all leaderboards | 0 of 1,273 projects ranked — the flagship feature has no output | Complete verification for a first batch (e.g. top 20 Lahore societies) and publish; until then label the section "Beta — research in progress" in nav |
| H-2 | `/investment` | H1 "Best Overall Investment in Pakistan" with no rankings is misleading | Rename (e.g. "Investment Insights") until rankings exist; merge with `/investment-rankings` or canonicalise |
| H-3 | Site-wide | No phone, e-mail, WhatsApp, street address or social links anywhere | Add contact details to header/footer/contact page (+92 311 1749849, business e-mail, office address) |
| H-4 | Site-wide / `/ai-property-finder` | WhatsApp not live; "WhatsApp Support" tile leads to "being set up" | Configure Cloud API, or add a direct `wa.me/923111749849` floating button now and change the tile copy |
| H-5 | `/pricing` | Public packages labelled "draft … for testing. Terms not final." | Finalise packages and prices, or hide `/pricing` (remove from sitemap) until ready |
| H-6 | Property detail pages | No call / WhatsApp option for buyers — only a form, and 0 inquiries recorded to date | Add "Call" and "WhatsApp" buttons (masked/relayed numbers if needed) |
| H-7 | All public pages | Server response 2–4.6 s per HTML page (home 3.3 s, `/areas/dha-lahore` 4.6 s, `/investment-rankings` 3.5 s; TTFB on navigation 2.7 s) | Enable full-page / query caching, OPcache, DB indexes, CDN; target < 800 ms TTFB |

### Medium

| ID | Page / URL | Issue | Recommended fix |
|---|---|---|---|
| M-1 | `https://www.meezanproperty.com/*` | www does not redirect to the apex domain and its canonical points to the www URL → duplicate site | 301 redirect www → `https://meezanproperty.com` |
| M-2 | `/investment-rankings/projects/{slug}` | "Disputed" data status shown alongside "Low risk" and "Verified Approved" | Suppress the risk rating when data is disputed or insufficient; show "Not assessed" |
| M-3 | `/investment-rankings` | Mixed terms: Insufficient data / Under research / Under review / Research in Progress; city rows don't sum (Lahore +5, Islamabad +17 unexplained) | Use one vocabulary; add an "Excluded" column per city |
| M-4 | Inquiry / contact forms | Phone accepts letters; consent and terms checkboxes not `required` client-side | Add `pattern="^(\+92|0)3\d{9}$"`, `inputmode="tel"`, and `required` on consent/terms (and enforce server-side) |
| M-5 | `/buy` | Shows houses only ("Houses for Sale"); 6 other for-sale listings hidden | Make `/buy` all-for-sale, or rename the nav item "Houses" |
| M-6 | ≈1,325 `/areas/*` pages | Almost all show 0 listings and 0 dealers → thin content at scale | `noindex` areas with no listings and no unique guide content, or add real area-guide text |
| M-7 | `/sitemap.xml` | 64 URLs: only 38 of ≈1,325 areas, 1 property, no projects/leaderboards/city sub-pages | Generate a sitemap index covering all indexable pages |
| M-8 | AI assistant | Can't answer "How does the Meezan Investment Index work?" | Add methodology and FAQ content to the assistant's knowledge base |
| M-9 | `/investment-rankings/housing-societies` @768 px | Horizontal scroll (filter chip row overflows) | Add `flex-wrap` / `overflow-x:auto` to the chip list |
| M-10 | Admin | Pages load in 1.6–5.8 s | Profile the Dealers and Properties queries; add eager loading and pagination |

### Low

| ID | Issue | Fix |
|---|---|---|
| L-1 | `robots.txt` disallows `/dealer/`, which blocks `/dealer/register` from search engines | Allow `/dealer/register` explicitly |
| L-2 | `/sell/owner`, `/compare`, `/login`, `/register`, `/dealer/register`, `/forgot-password` have no `og:image` | Add a default social share image |
| L-3 | `/investment`, `/map`, `/compare`, auth pages lack canonical tags | Add self-canonicals (or keep noindex consistently) |
| L-4 | Duplicate methodology URLs (`/investment/methodology` and `/investment-rankings/methodology`) | 301 one to the other |
| L-5 | No Blog / FAQ / Disclaimer pages | Create FAQ and Disclaimer; plan a guides/blog section for SEO |
| L-6 | Heading ignores unknown location ("Properties for Sale in Pakistan" for "zzzzqq"); `page=99` heading says "17 Properties" with no results | Echo the search term; return 404 or redirect for out-of-range pages |
| L-7 | Unfiltered sort mixes monthly rent and sale prices | Default `/properties` to purpose=sale, or sort within purpose |
| L-8 | Homepage city cards: area counts look inconsistent (Quetta 330 vs Karachi 84); area approval source is a 2021 Zameen News article | Review area import data and prefer primary authority sources |

### Cosmetic

| ID | Issue | Fix |
|---|---|---|
| X-1 | Hero search select text truncated ("Property Typ…") at ~980 px | Shorter labels or a wider select |
| X-2 | 3 homepage images without `alt` | Add descriptive alt text |
| X-3 | Desktop navigation collapses to a hamburger at ~980 px | Lower the breakpoint to ~900 px or condense the menu |

---

## E. User Journey Results

| # | Journey | Result | Notes |
|---|---|---|---|
| 1 | Visitor searches for a property | **PASS (data FAIL)** | Search works well; results are sample data |
| 2 | Visitor opens a property detail page | **PARTIAL** | Page works; sample data / lorem ipsum / no photos |
| 3 | Buyer attempts to contact a dealer | **PARTIAL** | Only a form (not submitted); no call or WhatsApp; dealers are test accounts; 0 inquiries ever recorded |
| 4 | Tenant searches rentals | **PARTIAL** | Works for Lahore (5 rentals); every other city returns 0 |
| 5 | Dealer explores registration | **PASS (inspected)** | Clear form; packages page shows "draft" |
| 6 | Customer explores account registration | **PASS (inspected)** | Terms checkbox not enforced client-side |
| 7 | Investor explores the Investment Index | **PARTIAL** | Navigable and transparent, but 0 rankings |
| 8 | Visitor uses WhatsApp contact | **FAIL** | No site-level WhatsApp; assistant "being set up" |
| 9 | Visitor uses the AI assistant | **PASS** | Good NL search; weak on Index questions |
| 10 | Visitor reads info and blog pages | **PARTIAL** | About / How-it-works good; no blog/FAQ; legal pages are drafts |

---

## F. Responsive Testing

Method: each of 20 key pages loaded in same-origin iframes at each width. Measured horizontal overflow, broken images and small tap targets. Visual checks were done at ~980 px.

Pages: `/`, `/properties`, property detail, `/investment-rankings`, housing-societies leaderboard, project index detail, `/lahore`, `/areas/dha-lahore`, `/projects`, `/agents`, `/pricing`, `/login`, `/register`, `/dealer/register`, `/contact`, `/ai-property-finder`, `/map`, `/compare`, rental-yield calculator, `/sell`.

| Viewport | Horizontal scroll | Broken images | Result |
|---|---|---|---|
| Desktop 1440 px | None | 0 | **PASS** |
| Laptop 1366 px | None | 0 | **PASS** |
| ~980 px (side-panel view, visual) | None | 0 | **PASS** — nav collapses to hamburger; select label truncation (X-1) |
| Tablet 768 px | **1 page**: housing-societies leaderboard (774 > 753 px) | 0 | **PARTIAL** (M-9) |
| Mobile 390 px | None | 0 | **PASS** |
| Small mobile 360 px | None | 0 | **PASS** |

Note on tap targets: 26–44 elements under 24 px per page at 360 px, mostly footer and inline links. `/lahore` has 329 because of its long area-link directory. A visual review is recommended for footer link spacing on mobile. The mobile menu opens and lists every section.

---

## G. Security & Safety Observations (visible only — no penetration testing)

- HTTPS works and HTTP upgrades to HTTPS. CSRF tokens and honeypot fields are present on forms; `X-Content-Type-Options: nosniff` header observed.
- Protected pages redirect to `/login` when logged out. Admin pages were not reachable logged-out in our checks (`/admin` is also disallowed in robots).
- The admin account is named **"Meezan Admin (Demo)"**. If this is the production super-admin, rename it and make sure demo credentials are not in use.
- **Trust/safety risk:** "Verified dealer" badges on sample/test accounts (C-2) could mislead buyers about who has been vetted.
- The login page says "Sign in to your buyer, dealer or admin account". Admin logs in through the public form, so consider 2FA for admin roles.
- WhatsApp module correctly reports test/mock mode and does not expose secrets.
- Not performed: penetration testing, authentication attacks, rate-limit testing, header/CSP audit beyond what was visible.

---

## H. Recommended Fixes (prioritised)

| Priority | Issue | Impact | Module | Suggested correction |
|---|---|---|---|---|
| P0 | Sample/test listings and dealers live (C-1, C-2, C-3) | Destroys trust; visitors can't transact | Listings, Dealers | Archive sample data; revoke test "Verified" badges; add a lorem-ipsum / outlier-price moderation check |
| P0 | Draft legal pages (C-4) | Legal exposure | Terms, Privacy | Lawyer-approved final versions; add Disclaimer page |
| P0 | No contact details (H-3) | No way to reach the business | Header, Footer, Contact | Add phone, WhatsApp (+92 311 1749849), e-mail, address |
| P1 | WhatsApp not live (H-4) | Advertised channel missing | WhatsApp | Configure Cloud API or add a direct `wa.me` button now |
| P1 | 0 rankings in the Index (H-1, H-2) | Flagship feature empty; misleading headings | Investment Index | Publish a first verified batch; rename "Best Overall Investment" page; label Beta |
| P1 | Draft packages public (H-5) | Dealers see unfinished pricing | Pricing | Finalise or hide |
| P1 | No call/WhatsApp on listings (H-6) | Lost leads | Property detail | Add contact buttons |
| P1 | Slow server responses (H-7, M-10) | SEO and conversion loss | Infrastructure | Caching, query optimisation, CDN |
| P2 | www duplicate (M-1) | Duplicate indexing | Server config | 301 www → apex |
| P2 | Investment data contradictions (M-2, M-3) | Confusing investor signals | Index detail | Hide risk when disputed; unify terminology |
| P2 | Form validation (M-4) | Bad lead data | Forms | Phone pattern; required consent/terms |
| P2 | `/buy` scope (M-5) | Hidden inventory | Categories | All-for-sale or rename |
| P2 | Thin area pages and incomplete sitemap (M-6, M-7) | SEO quality | SEO | noindex empty areas; full sitemap index |
| P2 | AI knowledge gap (M-8) | Wrong/empty answers | AI assistant | Add Index/FAQ knowledge |
| P2 | Tablet overflow (M-9) | Layout bug | Leaderboards | Wrap chip row |
| P3 | Low and cosmetic items (L-1…L-8, X-1…X-3) | Polish / SEO | Various | As listed in Section D |

---

## I. Not Tested

| Area | Reason |
|---|---|
| Customer dashboard (saved, inquiries, notifications, profile, password) | No authorised test customer account |
| Dealer dashboard (listings, leads, subscription, limits, profile) | No authorised test dealer account |
| Account creation, server-side validation, verification e-mails, password reset | Would create real records / send real e-mails — needs staging |
| Inquiry, contact, callback, report-listing, save-property, save-requirement, share-with-dealers submissions | Would create real leads/records |
| Package upgrade, checkout, payment gateway | Needs payment sandbox |
| WhatsApp outbound / chatbot | Not configured; prohibited by brief |
| Admin actions (approve, edit, publish, recalculate, AI agent runs, imports, settings) | Read-only audit — needs staging |
| Share button, Save button, Add to compare | Not clicked (create records / not needed) |
| Core Web Vitals (LCP, CLS, INP) | Not measured with a lab tool; only HTML response times were timed |
| Real device testing (iOS Safari, Android Chrome) | Emulated widths only |

---

## J. Final Verdict

### **Requires important fixes**

**Evidence:**

- **What works (engineering):** 0 server errors across 379 public URLs and 24 admin modules, 0 console errors, 0 broken images, proper 404s. Search, filters, sorting, pagination, map, compare, AI search and the yield calculator all work. Responsive layout is clean from 360–1440 px except one tablet overflow. Honest empty states and noindex on sample listings show careful design.
- **What blocks a public launch (content and operations):** 100% of listings and dealers are sample/test data, and the test dealers carry "Verified" badges. One live listing contains lorem ipsum. The Investment Index has 0 rankings. Terms and Privacy are drafts. Packages are drafts. There are no contact details and no live WhatsApp. HTML responses are slow (2–4.6 s).

Once the P0 and P1 items in Section H are fixed (mostly data clean-up, configuration and content, not re-engineering), the site should move to **"Ready with minor fixes."** Until then it should not be actively promoted to real buyers, sellers or investors.

---

*Report file: `MEEZAN_PROPERTY_COMPLETE_LIVE_WEBSITE_QA_REPORT.md` — prepared 6 Nov 2026. No website data, content or settings were changed during this audit.*
