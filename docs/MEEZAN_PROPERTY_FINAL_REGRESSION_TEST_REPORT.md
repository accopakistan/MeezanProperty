# Meezan Property: Final Regression Test Report

Date: 6 November 2026. Tested on the local copy of the application and database. **The live site was not touched.**

## Automated tests

| Run | Result |
|---|---|
| Full suite (Pest, SQLite) | **1,011 passed, 0 failed** (5,954 assertions, 470 s), up from 977 before this work |
| New `tests/Feature/PublicShowcaseTest.php` | **34 passed** |
| Code style (Pint) | Passed |

### An older flaky test, fixed

`WhatsAppIntegrationTest` "handles a repeated webhook delivery once" failed in 2 of 3 full runs, and also on its own about half the time. The cause was in the test, not the app. The test puts the current second into each payload, so when its two "identical" deliveries straddled a second boundary they were different payloads, and the app correctly didn't treat them as duplicates. The test now freezes the clock, and it passed 8 times in a row and with its file.

### Older tests updated because the behaviour changed on purpose

| Test | Was | Now |
|---|---|---|
| `GlobalUiFixesTest`: sample listings | Visitors saw a "Sample listing" badge | Visitors get 404; an admin still sees the badge |
| `SeoLaunchReadinessTest`: demo listing robots | Public page with noindex | Public 404; the admin preview stays noindex |
| `SeoLaunchReadinessTest`: sitemap | `/pricing` always listed | Listed only once a final package exists |
| `InvestmentIndexPagesTest`: rankings home | "Top …" for every category | "Top …" only where something is ranked |
| `InvestmentIndexPagesTest`: sitemap | Category pages only once ranked | All five category pages always |
| `InvestmentRealWorldDataTest`: rankings home | Old hero heading | "Meezan Investment Index — Research, Verification & Investment Intelligence" |
| `BillingTest`: empty pricing | "Packages are being finalised" | "Dealer packages are being finalised" |
| `NotificationsTest`: contact routing | No contact email by default | The official email is the default, so the test unsets it to check the admin fallback |
| `DealerCrmTest`: agenda | Page must not contain "Call" | Must not contain "Call ·" (a call follow-up). The header's new "Call us" button made the old check too broad |
| `HostHeaderTest`: www | `www.` returned 200 | `www.` returns 301 to the main domain |

## Browser checks

**Method.** Each page was loaded in a same-origin iframe at each width. The check measured horizontal overflow, broken images, the official `tel:`, `wa.me` and `mailto:` links, the floating WhatsApp button, and any sample or test text.

**Coverage.** 26 pages at 6 widths (1440, 1366, 980, 768, 390 and 360 px), 156 loads:

- **Public pages:** home, properties, Buy, Rent, Lahore, DHA Lahore area, agents, projects, contact, about, pricing, terms, privacy, disclaimer, sell and the AI finder.
- **Index and investment pages:** Index home, the Pakistan dashboard, Housing Societies and Apartment Buildings leaderboards, a project profile, Investment Insights and map.
- **Account pages:** login, register and dealer register.

| Check | Result |
|---|---|
| Horizontal scroll | 1 found: Housing Societies at 768 px, caused by the pagination row (the QA report's M-9). **Fixed** (the row now wraps) and re-checked at 768, 980, 390 and 360 px: none |
| Broken images | 0 |
| Sample or test content (Sample Realty, Jonathon, lorem ipsum, sample listings) | 0. One false match: the Pakistan dashboard says "sample listings excluded" |
| Office phone, WhatsApp and email links | All three on every page except login and register, which use the minimal sign-in layout without header or footer |
| Floating WhatsApp button | On every public page; not on login and register for the same reason |
| Admin pages (9) | All 200, through the app as an admin |

The browser pane was hidden, so most checks read the page instead of taking screenshots. The contact page was confirmed visually.

## Sample content and contact consistency

- **Live listings:** none published publicly (`Property::published()` returns 0).
- **Public dealers:** none (`/agents` is empty).
- **Contact numbers:** the office phone is always `tel:+923002358853` and WhatsApp always `wa.me/923111749849`, never swapped (automated test).
- **Provisional scores:** none published. The setting is off, and pages contain no provisional positions (automated test).

## Gemini

Default provider `gemini`. A connection test on 6 Nov 2026 got an answer from `gemini-3.6-flash`. All 16 agents are active. See `MEEZAN_PROPERTY_GEMINI_PRESERVATION_REPORT.md`.

## Structured data

Every page's JSON-LD was broken: Laravel 12 compiles `@context` as a Blade directive. It's fixed, and a test checks that the JSON on the home, city, Buy and contact pages parses.

## Performance

**Method.** Each page was rendered through the application on the local MariaDB copy: one warm-up, then the median of three runs, and the queries of the last run. Before and after used the same script and data.

| Page | Before | After |
|---|---|---|
| `/investment-rankings/pakistan` | 952 ms, 123 queries | **718 ms, 79 queries** |
| `/investment-rankings/lahore` | 715 ms, 122 queries | **542 ms, 78 queries** |
| `/investment-rankings` | 245 ms, 49 queries | **171 ms, 39 queries** |
| `/investment-rankings/apartment-buildings` | 196 ms, 14 queries | 171 ms, 12 queries |
| `/admin/whatsapp` | 175 ms, 58 queries | 181 ms, 48 queries |
| `/` | 64 ms, 7 queries | 44 ms, 7 queries |
| `/admin/dealers` | 72 ms, 17 queries | 72 ms, 17 queries |
| `/admin/properties` | 149 ms, 15 queries | 163 ms, 15 queries |
| Other public pages | 23–98 ms | 23–101 ms |

Differences under about 20 ms are measurement noise.

**The QA report measured 2–4.6 s on the live site, and 5.8 s for admin Dealers.** Locally the same code serves these pages in 0.02–0.7 s, so most of the live time is hosting: PHP start-up without OPcache, uncached config and routes, and a database cache store. The hosting actions are listed in the implementation report.

## Not tested

| Area | Why |
|---|---|
| Real form submissions on the live site, account creation, emails | Would create real records. Covered locally by tests |
| WhatsApp sending | Prohibited. Mock mode stays on |
| Payment and checkout | Needs a payment sandbox. Draft packages are blocked by test |
| Real phones and tablets | Emulated widths only |
| Live-site timing after deployment | Not deployed |
