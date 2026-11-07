# Meezan Property: Premium Logo Replacement Report

Date: 7 November 2026. The work was done locally on `http://localhost:8000`. Nothing was committed, pushed or deployed.

## Summary

The premium logo, `public/images/logo/Meezan Property Premium Logo.png`, now appears everywhere the main Meezan Property brand logo is shown:

- the site header (every public page)
- the footer
- the sign-in and registration pages
- the admin sidebar
- the invoice page
- the error pages
- the AI Property Finder card on the homepage
- the Organization schema used by search engines

The change is made in one shared component, `<x-logo>`, plus a few small one-line edits. The header's height, navigation, buttons, spacing and behaviour are unchanged. Antigravity's Investment Index work was checked and left untouched.

The original logo file was not renamed, moved or edited. Its MD5 hash was the same before and after the work: `8e2953f436a6b85013bcee4b35fd493e`.

## 1. Files inspected

**Antigravity's work**

- I checked `git status`: Antigravity's 24 modified files and its new files are all uncommitted on `main`. None of them was reset, reverted or overwritten.
- `resources/views/investment-index/project.blade.php`:
  - There is no frosted-glass or "Investment Standing" card any more. No `backdrop-blur` or `glass` class is left in the Investment Index views, partials or `components/investment-hero.blade.php`.
  - The Investment Score, Category Rank, Data Confidence and Risk Rating are still shown in the hero.
  - "Category standing", the score breakdown, the data status and legal verification are still shown in the page sections.
  - These are visible in screenshot 07 and match Antigravity's report.
- `resources/views/investment-index/*`, `components/investment-hero.blade.php` and `components/chart-card.blade.php`.
- `docs/screenshots/marketplace_search_in_hero/*`, including `10_project_detail_gulberg_greens_no_glass_box.png`.

**Logo locations**

- `components/logo.blade.php`
- `components/site/header.blade.php`
- `components/site/footer.blade.php`
- `components/layouts/auth.blade.php`
- `components/layouts/dashboard.blade.php`
- `components/layouts/head.blade.php`
- `components/layouts/app.blade.php`
- `errors/layout.blade.php`
- `billing/invoice.blade.php`
- `home.blade.php`
- `rankings/partials/advisor.blade.php`
- `vendor/mail/html/*`
- `app/Support/Seo.php`
- `resources/css`, `resources/js`
- `tests/Feature/AssetUrlTest.php`

## 2. Files changed

| File | Change |
|---|---|
| `resources/views/components/logo.blade.php` | It now shows the premium logo. It frames the artwork so the blank margins are hidden, adds a white rounded background for dark surfaces, and has a new `priority` option. The `compact` variant is unchanged. |
| `resources/views/components/site/header.blade.php` | Added `priority` to the existing `<x-logo>`. The size classes are unchanged. |
| `resources/views/components/layouts/auth.blade.php` | Added `priority`. |
| `resources/views/components/layouts/dashboard.blade.php` | Added `priority` to the admin sidebar logo. |
| `resources/views/errors/layout.blade.php` | The hard-coded `<img src="images/logo/meezan-logo.png">` was replaced with `<x-logo priority class="h-20 w-auto" />`. |
| `app/Support/Seo.php` | The Organization schema `logo` now points to the premium logo. It was the M mark before. |
| `tests/Feature/AssetUrlTest.php` | The expected logo URL is now the premium file. |
| `tests/Feature/PremiumLogoTest.php` | New file, with 8 tests covering the header and footer, sign-in and registration, the admin and dealer dashboards, the error page and the schema. |
| `docs/screenshots/premium_logo/*` | New screenshots. |

No CSS or JavaScript source changed. Everything structural is written as inline styles, so no `npm run build` was needed, and `public/build` was not touched.

## 3. Previous logo references found

| Reference | Where | What it was |
|---|---|---|
| `meezan-logo.png` (374×240) | `<x-logo>` default: header, sign-in and registration, invoice, homepage AI card | Main brand logo |
| `meezan-logo-light.png` | `<x-logo variant="light">`: admin sidebar | Brand logo for dark surfaces |
| `meezan-logo-full-light.png` (900×710, with the verse) | `<x-logo full variant="light">`: footer | Brand logo for dark surfaces |
| `meezan-logo.png`, hard-coded | `errors/layout.blade.php` | Brand logo |
| `meezan-mark.png` | `Seo::organization()` | Logo in the schema |
| `meezan-mark.png` | `<x-logo compact>`: AI advisor avatar (`size-7`, 28px) | Icon-sized mark |
| `meezan-icon-32/192.png`, `meezan-apple-touch-icon.png`, `favicon.ico` | `layouts/head.blade.php`, `errors/layout.blade.php` | Favicons and app icons |

None of these were found in the CSS, the JS or the mail templates. The mail theme uses text only.

## 4. Where the premium logo now appears

1. The global header, on every public page and on the dealer and buyer dashboards, which use the site header.
2. The footer, on every public page.
3. The sign-in, registration and dealer registration pages, which share the auth layout.
4. The admin dashboard sidebar.
5. The invoice and receipt page.
6. The error pages (404, 500, …).
7. The AI Property Finder card on the homepage.
8. The schema.org Organization `logo`.

All of these use `asset('images/logo/'.rawurlencode('Meezan Property Premium Logo.png'))`. That produces `/images/logo/Meezan%20Property%20Premium%20Logo.png`, which matches the original filename exactly, and every request returned 200.

## 5. Image dimensions and display dimensions

**The file:**

- 1254 × 1254 px, a square (1 : 1).
- PNG, 1.08 MB.
- **It is not transparent.** The background is opaque near-white (#FDFDFD to #FEFEFE). On the white header it is the same colour to the eye; the measured difference is 2/255.
- **It has wide blank margins.** The artwork only covers x 126–1128 and y 220–1070, about 80% of the width and 68% of the height.

**How it is framed**

Shown as a plain square at header height, the artwork would come out at about 49 px tall and "Property" could not be read. So `<x-logo>` puts the image in a frame that shows only the artwork plus a safety margin:

- **Light surfaces:** the frame shows a 1050 × 898 px window of the image (x 102–1152, y 196–1094), with a 24 px margin around the artwork.
- **Dark surfaces:** a wider 1130 × 978 window (x 62–1192, y 156–1134), on a white background with 0.75 rem rounded corners, so it looks like a deliberate badge.

The image itself:

- keeps its 1 : 1 proportions and is never stretched or squashed;
- uses `object-fit: contain`;
- has no filters, so the brand colours are unchanged;
- loses only blank background, never any of the artwork.

**Display sizes**

| Placement | Size classes | Displayed size |
|---|---|---|
| Header, mobile (< 1024px) | `h-[60px]`, unchanged | 70 × 60 px |
| Header, laptop (≥ 1024px) | `lg:h-[64px]`, unchanged | 75 × 64 px |
| Header, desktop (≥ 1280px) | `xl:h-[72px]`, unchanged | 84 × 72 px |
| Footer | `w-36 sm:w-40`, unchanged | 144 × 125 / 160 × 138 px |
| Sign-in and registration | `h-16` | 75 × 64 px |
| Admin sidebar | `h-16` | 74 × 64 px |
| Invoice | `h-16` | 75 × 64 px |
| Error pages | `h-20` | 94 × 80 px |
| Homepage AI card | `h-10` | 47 × 40 px |

At the largest display size (84 px wide), the image is shown about 13 times smaller than its source, so it is sharp on high-density screens too.

## 6. Header preservation

The header is unchanged apart from the logo image:

- **Unchanged:** the bar height (76 px on mobile, 88 px on desktop), the logo's place on the left, the logo heights, and the Buy, Rent, Investment, Explore and Dealers menus.
- **Unchanged:** AI Search, the phone number, Saved, Login, List Your Property, the sticky behaviour, and the mobile menu button.

In the browser at 1440 px, the logo frame was 84 × 72 px at (32, 8), centred in the 88 px bar. Antigravity's Investment menu underline still shows on Investment pages (screenshot 07). At 390 px there was no sideways scrolling (`scrollWidth` = 390).

The header and sign-in copies use `fetchpriority="high"`, and the lower copies (footer, invoice, cards) use `loading="lazy"`, so the header logo is fetched first.

## 7. Footer, sign-in pages and dashboards

- **Footer:** the premium logo appears on a white rounded badge at the top of the first column, with the description and the four link columns unchanged (screenshots 14 and 15). It replaces the light-lettered logo with the Qur'anic verse. **The premium file has no verse and no light-lettered version**, so the verse no longer appears in the footer.
- **Sign-in and registration:** the logo sits top left, at 75 × 64 px (screenshots 08, 09 and 13).
- **Admin and dealer dashboards:** checked through the rendered page in `PremiumLogoTest`. I didn't sign in to the browser because the admin password is the owner's. The admin sidebar uses the same white badge as the footer. The dealer dashboard uses the site header.

## 8. Screenshots

All screenshots are in `docs/screenshots/premium_logo/`:

| File | Page |
|---|---|
| `00_before_homepage_header.jpg` | Before: the old logo, in the in-app browser |
| `01_desktop_homepage.png` | Homepage, 1440 × 900 |
| `02_desktop_buy.png` | Buy |
| `03_desktop_rent.png` | Rent |
| `04_desktop_investment_index.png` | Investment Index (national) |
| `05_desktop_city_lahore.png` | Lahore investment dashboard |
| `06_desktop_lahore_housing_societies.png` | Lahore × Housing Societies ranking |
| `07_desktop_project_gulberg_greens.png` | Project page: Gulberg Greens Farm Housing Scheme |
| `08_desktop_login.png` | Sign-in |
| `09_desktop_register.png` | Registration |
| `10_mobile_homepage.jpg` | Homepage, 390 × 844 |
| `11_mobile_investment_index.jpg` | Investment Index, mobile |
| `12_mobile_project_gulberg_greens.jpg` | Project page, mobile |
| `13_mobile_login.jpg` | Sign-in, mobile |
| `14_desktop_footer.png` | Footer, desktop |
| `15_mobile_footer.png` | Footer, narrow layout |

How they were taken:

- The desktop screenshots were taken with headless Chrome.
- The mobile screenshots were taken in the in-app browser with a 390 px viewport. Headless Chrome cannot lay out narrower than about 500 px.
- Screenshot 15 is cropped from a narrow headless capture, so it shows the footer's stacked layout, not true phone emulation.

Every page returned HTTP 200, and every request for the logo returned 200; there were no 404s.

## 9. Test results

| Run | Command | Result | Exit code |
|---|---|---|---|
| New and changed logo tests | `php artisan test --compact tests/Feature/PremiumLogoTest.php tests/Feature/AssetUrlTest.php` | **13 passed** (51 assertions) | 0 |
| Full suite, after the logo change | `php artisan test --compact` | **1,027 passed, 15 failed** (6,174 assertions, 213.7 s) | **1** |
| The 4 failing files, with the logo change temporarily removed | `php artisan test --compact` on the 4 files listed below | **The same 15 failed**, 74 passed | 1 |

**The full suite does not pass.** The 15 failures were already there before the logo change: with my 7 changed files temporarily put back to their committed versions, exactly the same 15 tests still failed. My versions were then restored, and I checked that they are back in place.

All 15 failures are page-wording checks on the Investment Index pages. These pages were redesigned in Antigravity's uncommitted changes. For example, `assertSee('Verified Housing Society Rankings')` no longer matches the redesigned rankings home.

| File | Failing tests |
|---|---:|
| `tests/Feature/InvestmentIndexPagesTest.php` | 3 |
| `tests/Feature/InvestmentRankingActivationTest.php` | 3 |
| `tests/Feature/InvestmentRankingDataTest.php` | 7 |
| `tests/Feature/PublicShowcaseTest.php` | 2 |

Antigravity's report of "105 passing tests, 1,040 assertions" can only have been a subset; the full suite has 1,042 tests. Either those tests need updating to the new page wording, or the redesign removed wording that the tests were deliberately protecting, such as "Insufficient Verified Data" and "No paid or sponsored ranking". That needs checking one test at a time, and I haven't done it.

Pint was run on the changed PHP files and fixed one import in the new test.

## 10. Remaining old logo references, and why

| Reference | Kept because |
|---|---|
| `meezan-mark.png` in `<x-logo compact>` (AI advisor avatar, 28 px) | This is icon-sized. The full premium logo would be unreadable at 28 px. **It needs an icon-only asset**, such as the premium "M" on its own. |
| `favicon.ico`, `meezan-icon-32.png`, `meezan-icon-192.png`, `meezan-apple-touch-icon.png` | These are browser and app icons, not the brand logo. **They need new icon-sized exports of the premium M** to match the new brand. They are left as they were. |
| `meezan-logo*.png`, `meezan-logo-full*.png`, `Meezan Logo.png` | These are still on disk but no page uses them any more. Nothing was deleted. |
| `.claude/worktrees/gallant-mclaren-100627/…` | This is an old, separate worktree copy, not the live site. It was not touched. |

## Recommendations for the owner (not done)

1. **Ask the designer for web versions of the logo.** The premium PNG is 1.08 MB and is downloaded on every page; most of that is background. These would load much faster and look cleaner on the dark footer and admin sidebar:
   - a transparent, trimmed version of about 400 px (around 30–60 KB),
   - a version with white or light lettering for dark surfaces,
   - an icon-only "M" for favicons and app icons.

   Once they exist, changing the path in `<x-logo>` switches every location at once.
2. **The footer verse.** If the Qur'anic verse should stay in the footer, it needs a premium version with the verse, or a line of text under the logo.

## Existing issue found, unrelated to the logo and not fixed

The Investment Index pages show 7 browser console errors. Each one is "Executing inline script violates the following Content Security Policy directive 'script-src 'self' 'nonce-…''".

The cause is the `<script>` blocks in Antigravity's new `resources/views/components/chart-card.blade.php`, which have no CSP nonce, so the browser blocks them and **the chart cards probably don't draw**. The fix is to add `nonce="{{ Vite::cspNonce() }}"` (or whatever nonce helper the layout uses) to those script tags, or to move the chart code into `resources/js`.

## Safety

- Nothing was committed, pushed or deployed.
- No reset, discard, revert or `.env` change.
- No `migrate:fresh` or `db:wipe`, and no changes to the database or ranking records.
- No changes to the scoring methodology.
- Gemini, the Central AI Brain and the 16 agents were not touched.

## Follow-up (same day): risk label on projects under research

When I went through the 15 failing tests, one turned out to be a real page problem rather than a wording change. The redesigned project page showed a risk class for every project, including the projects still under research. In the live data, 16,903 "insufficient" score rows carry a risk class: 8,545 low, 8,284 elevated and 74 moderate. A project with no verified evidence could therefore be shown publicly as "Elevated risk" or "Low risk".

What changed:
- The earlier rule is restored. A risk class is shown only for projects that are ranked, not eligible, or excluded; every other project reads **"Not assessed"**.
- The rule now lives in one place, `InvestmentIndexScore::publicRiskLabel()` in `app/Models/InvestmentIndexScore.php`. The project page hero (`investment-index/project.blade.php`) and the comparison table (`investment-index/compare.blade.php`) both use it.
- The leaderboards were not changed, because they only list ranked projects.

How it was checked:
- The test `PublicShowcaseTest > shows "Not assessed" instead of a risk level` now passes.
- In the browser, Zameen NEO (under research) now shows "Not assessed" (screenshot `17_zameen_neo_risk_not_assessed.jpg`). Ranked projects such as Gulberg Greens and Dolmen Mall still show "Low risk".
- The other 14 Investment Index failures were left as they are, as the owner asked.
