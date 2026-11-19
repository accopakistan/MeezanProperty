# Meezan Property: Ranking Pages vs Mockup — Gap Report

Date: 19 November 2026. Read-only review: no code was changed.

**Compared:**
- Mockup A, "Top Shopping Malls in Pakistan", against `/investment-rankings/lahore/shopping-malls`
- Mockup B, "Smart Real Estate Investments", against `/investment-rankings/lahore`

Both pages were rendered locally (`localhost:8020`, 1536 px wide, local data). The code is in `rankings/city.blade.php`, `rankings/partials/city-dashboard.blade.php`, `investment-index/category.blade.php` and `components/investment-hero.blade.php`.

## Summary

The **layout already follows the mockups closely.** The 10 Nov 2026 dashboard was built from this reference design. Both pages have:
- the hero, with a city strip overlapping it
- category tabs
- three columns: leaderboard, then map and featured project, then the AI advisor and forecast
- a closing row: compare, banner, upcoming

The real differences fall into four groups:

1. **Data the mockup shows that we don't have, or deliberately don't publish.** Prices per sq ft, price-trend charts, 5-year change, growth forecasts (+20% / +35% / +55%), and "200+ / 150+" counts. Under the working rule against fabricated data, these show "—" or "Not available" on our pages. This is the biggest visual gap.
2. **Photos.** The mockup has a real photo of every project, in every table row and card. We have no project photos on file, so the pages show a purple placeholder illustration, or the city landmark photo for the featured project.
3. **Structure and wording.** Different search panel tabs and filters, different category tabs, "MPIS Score" against our "Index Score", a different header and navigation, and the mockup's satellite map with labelled pins.
4. **Extra content.** Our pages continue well past where the mockup ends. The mockup has nothing below the closing row.

## 1. Global header and navigation (both mockups)

| Element | Mockup | Current site |
|---|---|---|
| Logo | "M" mark + "Meezan Property" wordmark, single line | Stacked logo, "Meezan / Property" |
| Main menu | Buy · Rent · Projects · Societies · **Investment Rankings** · AI Search · Dealers · **Blog** · More ▾ (flat links) | Buy ▾ · Rent ▾ · Investment ▾ · Explore ▾ · Dealers ▾ · AI Search pill (mega-menus) |
| Right side | **Pakistan ▾ location selector**, search icon, heart, **Login** (outline), **Register** (filled) | Phone number, Saved, Login, **List Your Property** (gold) |
| Blog | Present | No blog exists |

## 2. Hero section

| Element | Mockup A (malls) | Mockup B (city) | Current site |
|---|---|---|---|
| Headline | "Top **Shopping Malls** in Pakistan", subtitle "High Rental Yields, Strong Investment Opportunities" | "Smart Real Estate **Investments** for a **Better Tomorrow**" | Malls page: "Top Shopping Malls in **Lahore**", "Ranked on verified evidence". City page: matches mockup B |
| Tagline | Real Data \| AI Analysis \| Transparent Rankings | same | Real Data \| **Evidence-Based Analysis** \| Transparent Rankings |
| Intro text | Marketing copy ("footfall, location, developer reputation…") | Marketing copy | Malls page lists the scoring weights ("legal approval (20%) · operational performance (15%)…"), which is more technical |
| Search panel position | Centre-right, with a KPI box on the far right | same | Right, with **no KPI box beside it** |
| Search tabs | Buy · Rent · **Invest** (active) · **Compare** · **AI Search** | **Buy** (active) · Rent · Invest · Compare · AI Search | Buy · Rent · **Plots** · **Commercial**. No Invest, Compare or AI Search tabs |
| Search filters | Property Type (Shopping Mall) · Budget Range · **Investment Return** · **Completion Status** | Property Type · Property Size · Budget Range · **Investment Duration** | Generic listing filters (type, price, size, beds). Cramped: labels are truncated ("Con", "Pric", "Siz") |
| KPI block | Dark box at top right: 200+ Shopping Malls · 100+ Commercial Projects · 7 Major Cities · 100% Transparent Data | 200+ Societies · 150+ Commercial Projects · 7 Major Cities · 100% Transparent Data | Full-width strip **below** the headline, with exact real counts: Lahore 463 Researched · 20 Ranked · 7 Major cities · 427 Legal records. Malls page: 15 · 4 · 7 · 4 |
| Background | Golden-hour photo of a mall (A) or a Lahore skyline (B) | | Mall photo (A) / Badshahi Mosque (B). Close to the mockup |

## 3. City strip

| Element | Mockup | Current site |
|---|---|---|
| Cities | Lahore, Karachi, Islamabad, **Rawalpindi**, Faisalabad, Multan, Peshawar | Lahore, Karachi, Islamabad, Multan, Faisalabad, **Quetta**, Peshawar. Rawalpindi was left out on purpose (`config/meezan.php`) |
| Count text | Rounded, **category-specific** on the malls page ("35+ Malls", "30+ Malls"); "120+ Projects" on the city page | Exact counts, and **always the total for the city** ("463 Projects"), even on the malls page |
| Card art | Illustrated skyline per city | Landmark photo per city. Similar look |

## 4. Category tabs

| Mockup A (commercial set) | Mockup B (city set) | Current site (same on both pages) |
|---|---|---|
| Shopping Malls · Commercial Plazas · Business Towers · **Office Spaces** · **Commercial Shops** · **Commercial Plots** · **Mixed Use** · Highest Rental Yield · Fastest Growing · New & Upcoming | **Residential Societies** · **Commercial Projects** · **Plots** · **Houses** · **Apartments** · Shopping Malls · Business Towers · Highest Rental Yield · Fastest Growing · New & Upcoming | **All Investments** · Housing Societies · Apartment Buildings · Shopping Malls · Commercial Plazas · Business Towers · Highest Rental Yield · Fastest Growing · New & Upcoming (overflows and scrolls at 1536 px) |

- The Meezan Investment Index has five categories. The mockup's Office Spaces, Commercial Shops, Commercial Plots, Mixed Use, Plots and Houses exist only in the older MPIS segment pages (`/investment-rankings/{city}/{group}/{segment}`), not in this tab bar.
- The mockup changes the tab set by context (commercial tabs on the malls page). Ours uses one set everywhere.

## 5. Column 1: leaderboard tables

### Mockup B: "Top Residential Societies in Lahore"
| Element | Mockup | Current site |
|---|---|---|
| Size tabs | 5 Marla · 10 Marla · 1 Kanal · Houses · Apartments | Same ✔ |
| Row thumbnail | Photo of each society | **None** (no project photos on file) |
| Tier badge | Premium / Popular / Value | Same ✔ |
| Price column | "Current Price (10 Marla)" | "Advertised Price (10 Marla)", sourced from Zameen's price index, with a source tooltip |
| 1-year / 2-year change | Filled for every row | Filled where sourced ✔ |
| Rental yield | 4.5%, 4.2%… for every row | Mostly **"—"** (no verified yield) |
| Score label | **MPIS Score** | **Index Score** |
| Risk | Low / Medium | Same ✔ |
| Footnote | None | Disclaimer under the table ("advertised (asking) plot prices… not verified sales") |

### Mockup A: "Top Shopping Malls in Lahore"
| Element | Mockup | Current site |
|---|---|---|
| Status tabs | All · Operational · Under Construction · Upcoming | Same ✔ |
| Rows | 6 | 4 (only 4 malls are ranked in Lahore) |
| Thumbnail | Photo per mall | **None** |
| Location | Area (Walton Road, Johar Town…) | Mostly just "Lahore"; one row shows "The Mall Lahore" |
| Price | "Current Price (Per Sq Ft)": Rs 45,000… | "Advertised Price" column, **all "—"** |
| Yield / Score / Occupancy | Filled | Filled from real evidence ✔ (9.4%, 98, 95.2%) |

### "Top Commercial Projects" (B) and "Featured Shopping Malls" (A)
| Element | Mockup | Current site |
|---|---|---|
| Card image | Real building photo | **Purple placeholder illustration** on every card |
| Card fields | MPIS, Rental Yield, use type ("Shops & Offices") or status tag ("Operational") | Index Score, Rental Yield, legal status ("Verified Approved") |
| Carousel arrows | ‹ › on both sides | Static 4-card grid, no arrows |

## 6. Column 2: map and featured project

| Element | Mockup | Current site |
|---|---|---|
| Map style | **Satellite** imagery with a Satellite ▾ toggle | Street map (OpenFreeMap). No satellite option, because `MEEZAN_MAP_SATELLITE_URL` isn't set |
| Markers | Coloured **pins with name labels** (Bahria Town, Emporium Mall…) | **Numbered cluster bubbles** (2, 4…); names appear only on click or zoom |
| Popup | Info card open on the map (photo, score, price, 1-year / 2-year change) | No popup open by default |
| Legend | All · Residential Society · Commercial Project · Shopping Mall · Business Tower · **Office Space** · **Mixed Use** | All · Housing society · Commercial plaza · Apartment building · Shopping mall · Business tower |
| Featured card image | Photo of the project | **City landmark photo** (Badshahi Mosque behind "Madison Square Central") |
| Featured fields | Current price · 1, 2 and **5** year change · Rental yield · Occupancy (A) · Risk · **Development / Completion status** | Advertised price · 1 and 2 year change (mostly "—") · Rental yield · **Data Confidence** · Risk · **Legal Status**. No 5-year change, occupancy or development status |
| Sub-tabs | Overview · Price Trend · Rental Yield · Future Forecast · **Gallery** · **Location** (A) / Details (B) | Overview · Price Trend · Rental Yield · Future Forecast · Details (links to the project page) |
| Price Trend | **Line chart**, Jan 2022 – Oct 2026, with a 1Y / 2Y / 5Y / All switch and the current price marked | Text only: "Not available. A price trend needs at least two dated prices…" |

## 7. Column 3: AI advisor and forecast

| Element | Mockup | Current site |
|---|---|---|
| Advisor tabs | Ask AI · Compare · **City Insights** (B) or Rental Analysis (A) · Forecast | Ask AI · Compare · Rental Analysis · Forecast (no City Insights) |
| Top-3 answer | Photo thumbnails, with bullets such as **"Expected growth: 25% – 40%"** | Placeholder thumbnails; bullets from evidence ("Legal approval: Verified Approved", "Low risk"). No growth figures |
| Buttons | Compare These · Show Price Trends · More Options → | Compare These · **How They're Scored** · More Options → |
| Online badge | "● Online" | "● Uses verified data only" |
| Forecast | **+20% / +35% / +55%** (B), +18% / +35% / +55% (A), over the next 2 years | "—" in all three boxes, with the note "Forecasts are not published…" |
| "Why it ranks #1" | Market-style bullets ("Strong historical price performance", "Prime location…") | Factor scores ("Legal approval (100/100)", "Footfall and demand (100/100)…") plus "Not yet evidenced: Market performance" |

## 8. Closing row

| Element | Mockup | Current site |
|---|---|---|
| Compare | 4 photo thumbnails, each with an **✕ remove** button, then Compare Now | Placeholder thumbnails, **no remove buttons**, then Compare Now ✔ |
| Banner | "**AI-Powered** Investment Analysis for a Smarter Future": Real Market Data · **Advanced AI Analysis** · Transparent Rankings · **No Hidden Information** | "**Evidence-Based** Investment Analysis…": Real Market Data · AI Advisor · Transparent Rankings · **No Paid Rankings**, plus a "How projects are scored →" link |
| Upcoming & New | 4 cards with photos, location, **price ("Rs 65 Lakh, 10 Marla Plot")** and a New / Upcoming tag | 3 cards, no photos, **"Price not published"**, "Under construction" tag |
| Future Growth Forecast (A) | In column 3, beside the upcoming list | Present in column 3, empty ("—") |

## 9. Content below the mockup (ours only)

After the closing row, the current city page continues with:
- "Lahore research coverage": 6 KPI cards (Researched, Ranked, Verified legal files, Under research, Awaiting legal check, Legally excluded)
- Category portal links
- **4 Chart.js charts**: category breakdown, legal status, risk, score distribution
- "Overall Investment Index in Lahore": a 9-column table with confidence, evidence coverage and last verified date
- Category leaderboard boards and the older results list

The mockup has none of this. The page is roughly three times as long as the mockup.

## 10. What it would take to close each gap

| Gap | Kind of work | Notes and constraints |
|---|---|---|
| Header, nav and search tabs (Invest / Compare / AI Search), context-specific category tabs, hero KPI box, carousel arrows, ✕ on compare, satellite toggle, labelled pins and map popup, Gallery / Location tabs, "MPIS" wording | **Front-end only** | Can be built now. A satellite layer needs a licensed tile key (e.g. MapTiler). "MPIS Score" vs "Index Score" is a naming decision |
| Category-specific city counts ("35+ Malls"), area names in the location column, 5-year change and development status fields | **Small back-end** | The data partly exists. Area names depend on projects being linked to areas |
| Project photos in tables, cards, advisor and compare | **Content** | Needs owner-supplied or licensed photos per project. Copying portal photos is ruled out |
| Price per sq ft, price-trend chart, rental yields for societies, upcoming-project prices | **Data** | Needs sourced, dated price records. Shows "—" until they exist |
| Growth forecasts (+20 / +35 / +55%), "Expected growth" bullets, "200+ / 100% Transparent" claims | **Policy decision** | Conflicts with the no-fabricated-data rule. Showing them needs a sourced method or a clearly labelled model |
| Rawalpindi in the city strip, Quetta removed | **Policy decision** | Rawalpindi was excluded on purpose |
| Extra sections below the closing row | **Design decision** | Remove, collapse, or move to a separate "Research coverage" page |

---

## Update, 19 Nov 2026: front-end gaps closed (local only, not deployed)

| Gap | Done | Where |
|---|---|---|
| Hero search tabs | **Buy · Rent · Invest · Compare · AI Search**, with Invest selected first. Buy and Rent search listings; Invest opens a leaderboard with project search, city, Property Type, Completion Status, Investment Risk and Legal Status; Compare opens a category comparison; AI Search asks the advisor on the page | `components/investment-search.blade.php`, `SearchController::rankings()` |
| Filters | Labelled boxed selects, as in the mockup. **No Budget or Investment Return filter**: the Index has no sourced prices or yields for most projects, so these filters would do nothing | same |
| Hero KPI box | Dark 2 × 2 box to the right of the search from 1280 px (previously 1700 px). Real counts; labels wrap instead of being cut off | `components/investment-hero.blade.php` |
| Category tabs | Follow the page. Commercial pages: Shopping Malls · Commercial Plazas · Business Towers · Office Spaces · Commercial Shops · Commercial Plots · Mixed Use. Other pages: Residential Societies · Commercial Projects · Plots · Houses · Apartments · Shopping Malls · Business Towers. Both sets end with Yield · Growing · New. Types the Index doesn't rank yet (offices, shops, commercial plots, mixed use, plots, houses) show greyed with a **"Soon"** badge instead of links that bounce back | `rankings/partials/category-nav.blade.php` |
| Map | Named pins without clustering on dashboard maps (up to 80 pins). The best-ranked project that has a pin opens its info card on load. The popup says "Index Score" | `resources/js/investment-map.js`, `dash-map.blade.php` |
| Card carousels | Featured and commercial cards scroll, up to 10, with ‹ › arrows that appear only when the row overflows | `rankings/partials/carousel-arrows.blade.php`, `city-dashboard.blade.php` |
| Compare row | Four projects of one category, each with an **✕**. Compare Now opens the comparison with exactly those projects. On the city page this uses the category with the most ranked projects | `city-dashboard.blade.php` |
| Featured sub-tabs | **Location** tab added: the project highlighted on its city map, shown only when the project has a map position. **Gallery** was not added because no project photos are on file | `city-dashboard.blade.php` |

**Not changed (decisions or data needed):**
- the site header and navigation (site-wide)
- the "MPIS Score" vs "Index Score" naming
- the satellite map (needs a licensed tile key)
- project photos, prices, price-trend charts and forecasts
- Rawalpindi
- the extra sections below the closing row

Tests are in `tests/Feature/RankingMockupAlignmentTest.php`.

### Price trend chart (added later on 19 Nov 2026, owner's decision: "Advertised index, labelled")

- **Data source:** the Zameen.com advertised price index on file in each project's research facts: the current asking price per plot size, plus the published 1-year and 2-year change. The two earlier points are worked out from those changes (shown hollow). Nothing else is estimated, and there is no forecast.
- **Which project:**
  - the featured project, when it has an index on file;
  - otherwise the best-ranked project on the page that has one, named in the chart ("No price index is on file for Madison Square Central. Shown: WAPDA ECHS Ph-I…");
  - otherwise the "Not available" message, e.g. the Lahore malls page and the Karachi city page.
- **Look:** SVG drawn on the server, in the mockup's style (purple line, area fill, end-price badge), with 1Y / 2Y and 5 Marla / 10 Marla / 1 Kanal switches. Every chart says "Advertised (asking) prices, not sales" and links the source.
- **Files:** `app/Support/PriceIndexTrend.php`, `resources/views/rankings/partials/price-trend.blade.php`, `city-dashboard.blade.php`. Tests: `tests/Feature/PriceIndexTrendTest.php`.
- **Not changed:** the project profile page still lists asking figures as facts, not as a trend. The 5Y / All periods from the mockup are left out because only two years are published.
