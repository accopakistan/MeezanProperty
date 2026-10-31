# Meezan Property — National Multi-City Expansion: Completion Report

_Local development (XAMPP, Laravel 12, MariaDB). 30–31 October 2026. No Git, GitHub or Hostinger actions were taken._

All counts below were read from the database and the dataset files after the final import, not estimated.

## 1. Executive summary

Meezan Property now runs in seven launch cities: Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar. A researched dataset of **1,238 locations** (neighbourhoods, housing schemes, phases, sectors, blocks, corridors and markets) was built from official authority lists where they could be obtained, plus labelled secondary sources. It was imported through an idempotent command into a hierarchical location model with aliases, approval metadata, sources and OpenStreetMap centres.

Search, city landing pages, the homepage, maps, the listing form, dealer coverage, the AI requirement parser, lead routing and admin location management are all city-aware. All existing property, user and area data was preserved. **The automated suite passes (291 tests).** The frontend build succeeds.

Coverage is substantial but not complete. Karachi, Faisalabad and Peshawar have no official approval data (their authority sites were unreachable or have no list), and Lahore's approval data is a 2021 secondary copy. Gaps are listed in §28.

## 2. Research methodology

1. Official authority lists first: CDA, QDA and MDA lists transcribed in full, with status and reference. Personal data in those documents (owner names, CNIC numbers, phone numbers) was not copied.
2. Secondary sources only where labelled: the LDA list reproduced by Zameen News (2021), Wikipedia, Graana and Zameen guides.
3. Established neighbourhoods, markets and road corridors added from local usage and portal guides, with status `not_applicable`.
4. Hierarchy (phase → sector/block) only where a source describes it.
5. Existence and centres checked on OpenStreetMap Nominatim with strict acceptance rules; nothing typed or estimated.
6. Uncertain records flagged `needs_review` rather than dropped or presented as verified.

Full detail: `docs/research/Meezan_Pakistan_City_Location_Research.md`.

## 3. Sources used

Twenty-two sources (S00–S21) are registered in `docs/research/Meezan_Pakistan_Location_Sources.csv` with their access status.

- **Accessed official sources:**
  - CDA registered schemes (70) and illegal schemes (98)
  - QDA approved (51 rows, 50 legible), illegal (139) and under-process (110) lists
  - MDA approved list (61)
- **Accessed but not transcribed:** the MDA illegal list (an Urdu scan) and the MDA under-process list.
- **Inaccessible:** the LDA, FDA and PDA websites.
- **Secondary sources:** LDA approved list as reproduced by Zameen News (256), Wikipedia (Islamabad sectors, DHA Karachi, Hayatabad), DHA I-R, and the Graana and Zameen guides.
- **Map data:** OpenStreetMap Nominatim.

## 4. City-wise research results

| City | Locations | Schemes / societies | Neighbourhoods* | Phases / sectors / blocks / sub-areas | Corridors + markets | With official status | Flagged |
|---|---|---|---|---|---|---|---|
| Lahore | 409 | 261 | 38 | 96 | 14 | 0 (256 secondary) | 79 |
| Karachi | 84 | 12 | 45 | 21 | 6 | 0 | 4 |
| Islamabad | 240 | 176 | 10 | 42 | 12 | 168 | 6 |
| Multan | 96 | 67 | 15 | 0 | 14 | 61 | 0 |
| Faisalabad | 38 | 7 | 21 | 0 | 10 | 0 | 3 |
| Quetta | 330 | 302 | 14 | 0 | 14 | 299 | 113 |
| Peshawar | 41 | 8 | 11 | 7 | 15 | 0 | 4 |
| **Total** | **1,238** | **833** | **154** | **166** | **85** | **528** | **209** |

\*Neighbourhoods include cantonments. "Schemes / societies" covers housing, cooperative, government and defence schemes. For Islamabad, the 42 CDA sectors are counted as sectors.

## 5. Total cities created

**7** rows in the new `cities` table: Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar.

- Each row has a province, a slug and an OpenStreetMap centre point; all 7 were geocoded.
- `config('meezan.cities')` lists the same seven.
- Rawalpindi is not a launch city.

## 6. Total locations created

- **1,238** active locations in `areas`: 1,199 created by the import, plus the 39 pre-existing Lahore areas, which were updated in place.
- **267** aliases in `area_aliases`.
- The import rejected 3 rows (Rawalpindi) and skipped 7 (city rows, which live in `cities`).

## 7. Total societies and schemes created

**833**:

| Type | Count |
|---|---|
| housing schemes | 815 |
| government / authority schemes | 10 |
| defence schemes | 7 |
| cooperative society | 1 |

`Meezan_Pakistan_Housing_Societies.csv` holds the same subset with sources.

## 8. Total neighbourhoods created

**154**: 148 neighbourhoods and 6 cantonments. In addition there are 53 road corridors and 32 commercial areas.

## 9. Total phases, sectors and blocks created

**166**:

| Type | Count |
|---|---|
| phases | 49 |
| sectors | 87 (42 of them are Islamabad's CDA sectors) |
| blocks | 25 |
| sub-areas (Gulberg I–V) | 5 |

126 locations have a parent: 87 at depth 1 and 39 at depth 2.

## 10. Records with verified coordinates

**199 of 1,238 locations (16%)** have an approximate OpenStreetMap centre, plus all 7 cities. None was typed or estimated; each passed the city-radius, name, object-kind, not-the-parent's-point and not-shared rules.

| City | Locations | With centre | Share |
|---|---|---|---|
| Lahore | 409 | 94 | 23% |
| Karachi | 84 | 26 | 31% |
| Islamabad | 240 | 26 | 11% |
| Multan | 96 | 13 | 14% |
| Faisalabad | 38 | 12 | 32% |
| Quetta | 330 | 16 | 5% |
| Peshawar | 41 | 12 | 29% |
| **Total** | **1,238** | **199** | **16%** |


These are **automatic, unreviewed** matches: "verified" here means verified against OpenStreetMap by those rules, not checked on the ground. 35 of them are the pre-existing Lahore centres from Phase 1. Coordinates were written back to `Meezan_Pakistan_Locations.csv` with `meezan:export-locations` (199 rows).

## 11. Records with unverified coordinates

**1,039 locations (84%)** have no centre: OpenStreetMap had no object that passed the rules. They are marked `osm_no_match`, keep empty coordinates and remain fully usable for listings and search; listings there simply show no approximate area pin until a centre exists.

- Most are official scheme names that do not exist on OpenStreetMap (Quetta 314 of 330, Islamabad CDA lists 214 of 240).
- 164 earlier matches were removed during this phase because they were shared or the wrong kind of object (§10 rules).
- Next step: `meezan:geocode-areas --retry` after OSM improves, or admins enter centres by hand (Admin → Locations → "No map centre" filter), starting with the 20 popular areas still missing one.

## 12. Approval-status data coverage

| Status | Records |
|---|---|
| approved | 367 (MDA 61 and QDA 50 official; LDA 256 secondary, 2021) |
| noc_issued | 31 (CDA) |
| layout_plan_approved | 20 (CDA) |
| under_process | 112 (QDA 110, CDA 2) |
| cancelled | 17 (CDA) |
| declared_illegal | 237 (CDA 98, QDA 139) |
| unverified | 65 |
| not_applicable | 389 |

Status is stored separately from availability: every active location is searchable and can be used for listings whatever its status.

The area guide shows the status with its reference, source link, check date, and a note that Meezan Property does not certify schemes.

No scheme was marked illegal for being absent from a list.

## 13. Database changes

Four additive migrations. Before running them I listed them and scanned the generated SQL for drops; none were found. No existing column was dropped or changed.

| Migration | Change |
|---|---|
| `2026_10_30_000017_create_cities_table` | New `cities` table plus the 7 launch cities (inserted only if missing) |
| `2026_10_30_000018_add_hierarchy_and_verification_to_areas` | New nullable or defaulted columns on `areas`: `city_id`, `parent_id`, `depth`, `official_name`, `normalized_name`, `type`, `authority`, `approval_status`, `approval_reference`, `verification_source_url`, `source_ids`, `verified_on`, `confidence`, `coordinate_precision`, `needs_review`, `is_active`, `merged_into_id`, `research_key` (unique), `notes`. Indexes on (`city_id`, `is_active`, `name`), `normalized_name` and `approval_status`. Existing rows linked to Lahore. |
| `2026_10_30_000019_create_area_aliases_table` | New `area_aliases` table (unique per area + normalised alias) |
| `2026_10_30_000020_create_city_dealer_profile_table` | New dealer ↔ city pivot. The 2 existing dealers were given only their current city. |

The `areas.city` string, `properties.city`, `dealer_profiles.city` and the `unique(city, name)` constraint are unchanged, so existing code and records keep working.

## 14. Files changed

**New:**

- **Migrations:** `database/migrations/2026_10_30_000017` – `000020`
- **Models:** `app/Models/City.php`, `AreaAlias.php`
- **Support:** `app/Support/LocationName.php`, `LocationResolver.php`, `LocationLevels.php`, `Cities.php`, `DealerCoverage.php`
- **Services:** `app/Services/CityDirectory.php`, `LocationMerger.php`, `Ai/LocationExtractor.php`
- **Commands:** `app/Console/Commands/ImportLocations.php`, `ExportLocations.php`
- **Controllers:** `app/Http/Controllers/CityController.php`, `LocationController.php`
- **Views:**
  - `resources/views/cities/show.blade.php`
  - `admin/areas-edit.blade.php`
  - `areas/partials/location-details.blade.php`
  - `components/coverage-picker.blade.php`
  - `components/area-multi-picker.blade.php`
  - `dealer/properties/partials/location-select.blade.php`
- **Tests:** `tests/Feature/NationalExpansionTest.php`
- **Research files:** `docs/research/*` (builder script, raw lists, 3 CSVs, 2 reports)

**Modified:**

- **Models and config:** `app/Models/Area.php`, `DealerProfile.php`, `config/meezan.php`, `routes/web.php`
- **Commands:** `app/Console/Commands/GeocodeAreas.php`
- **Search and matching:**
  - `app/Services/PropertySearch.php`
  - `AreaInsights.php`
  - `Leads/LeadRouter.php`
  - `Matching/MatchCriteria.php`
  - `Matching/PropertyMatcher.php`
  - `Ai/RulesRequirementParser.php`
  - `Ai/LlmRequirementParser.php`
  - `app/Support/Lookup.php`
  - `app/Http/Requests/PropertyRequest.php`
- **Controllers:**
  - `AiFinderController`
  - `AreaGuideController`
  - `Admin/AreaController`
  - `Auth/DealerRegistrationController`
  - `Dealer/ProfileController`
  - `Dealer/PropertyController`
  - `Account/RequirementController`
  - `DealerDirectoryController`
  - `HomeController`
  - `SitemapController`
  - `CategoryController`
- **Views:**
  - `home`
  - `components/area-combobox`
  - `components/search-bar`
  - `properties/index`
  - `properties/map`
  - `properties/partials/filters`
  - `properties/partials/map-panel`
  - `areas/index`
  - `areas/show`
  - `admin/areas`
  - `dealer/properties/form`
  - `dealer/properties/partials/location-picker`
  - `auth/dealer-register`
  - `dealer/profile`
  - `account/requirements/form`
  - `dealers/index`
- **Copy updates (Lahore-only wording):**
  - `components/layouts/head`
  - `components/layouts/auth`
  - `components/site/footer`
  - `pages/about`
  - `pages/contact`
  - `projects/index`
  - `sell/index`
  - `ai-finder/show`
  - `categories/show`
  - `account/saved-searches`
  - `account/dashboard`
  - `admin/leads/index`
  - `dealer/subscription`
- **JavaScript:** `resources/js/app.js`, `location-picker.js`

`public/Refrence/` was not touched.

## 15. Existing data preservation

| Data | Before | After |
|---|---|---|
| Properties | 17 | 17 (all 17 still have an area) |
| Users | 4 | 4 (no passwords changed) |
| Pre-existing areas | 39 | 39: same IDs, names and slugs; existing coordinates kept |
| Dealer area links | — | Unchanged |

- The importer never overwrites a name, slug, coordinate or popularity flag, and never deletes anything.
- Running it again changes nothing: the second run reported 0 created, 0 updated and 1,238 unchanged.
- Merging (an admin action) deactivates the duplicate and keeps it for redirects; nothing is deleted.
- No `migrate:fresh`, rollback, wipe or table drop was run.

## 16. Homepage changes

**New "Explore Properties Across Pakistan" section.** It has a card for each of the 7 cities, showing:

- the province
- the real published-listing count, or "New on Meezan" when there are none
- the number of areas and societies in the directory

The counts come from the database and are cached for 10 minutes.

Copy now says Pakistan or names the seven cities instead of "Lahore". The "Popular Areas in Lahore" tiles stay, scoped to Lahore. The DHA tile links to the DHA Lahore location.

## 17. City landing pages

**URLs:** `/lahore`, `/karachi`, `/islamabad`, `/multan`, `/faisalabad`, `/quetta`, `/peshawar`. The route is constrained to the config slugs and stays cacheable; any other slug returns 404 (for example `/rawalpindi`).

**Each page has:**

- a hero with the province
- a city-scoped location search
- real counts: listings, directory areas and verified dealers
- browse links (buy, rent, homes, plots, commercial, map)
- the latest listings, or an honest empty state
- popular and listed areas
- the full directory of top-level areas grouped by kind
- verified dealers serving the city
- links to the other cities

## 18. Search changes

- **Location box:** suggests matches from `/locations/suggest` as you type: names and aliases, scoped to a city when one is known, with the parent shown. Pages no longer embed the full location list.
- **Typed text:**
  - "Karachi" searches the whole city.
  - "Clifton Karachi" or "Clifton, Karachi" picks the area inside that city.
  - Spellings and aliases match ("Gulshan e Iqbal", "DHA Ph V", "F7").
  - A name found in several cities shows "Did you mean …" links instead of guessing.
- **Hierarchy:** searching a society includes its phases, sectors and blocks.
- **Bounded lists:** filter lists and the map page's area list show only popular and listed areas.
- **Merged locations:** old slugs (for example in saved searches) resolve to the kept record.
- **Map framing:** maps centre on the searched city's OpenStreetMap point, or on all of Pakistan when no city is set. Listing pins are unchanged: only dealer-placed points are exact.

## 19. Dealer changes

- **Registration and profile:**
  - "Head office city" plus "Cities you serve" (multi-select); the head-office city is always included.
  - Areas can be chosen from any served city, from popular quick picks plus a search box. The limit is still 10.
- **Validation:** rejects areas outside the served cities.
- **Dealer directory:** has a city filter and matches dealers by served cities.
- **Area matching:** a dealer serving a society also matches its phases, and the reverse.

## 20. Property form changes

- **Dependent location selects:** city → area/society → phase → sector/block.
  - Only the chosen city's top-level locations are loaded: 314 for Lahore, compared with the 1,238 a single select would have needed.
  - Deeper levels load on demand from `/locations/{id}/children`.
- **Saved location:** the deepest choice that really belongs under the previous one. A leftover lower choice from another branch is ignored.
- **Validation:** the area must be active and belong to the selected city.
- **Location picker:**
  - The map centres on the chosen location's centre, or the city's.
  - The distance warning uses the deepest chosen location that has a centre.
  - Centres are never saved as the property's position.

## 21. AI changes

**New `LocationExtractor`, shared by the rules parser and (through it) the optional LLM parser:**

- recognises all 7 cities and short forms (lhr, khi, isb, fsd, Lyallpur) plus every location name and alias
- a stated city restricts areas to that city
- with no city stated, the areas must all point to one city; otherwise it asks, e.g. "Garden Town, Lahore · Garden Town Multan, Multan · Garden Town, Quetta"
- a city word inside a place name is read as part of the place ("Multan Road" is in Lahore)
- non-launch cities (Rawalpindi/Pindi and others) get a plain "not covered yet" reply instead of a guess
- "DHA" alone means any DHA phase in the stated city

**Matching:**

- The matcher no longer defaults to Lahore: a stated city filters listings, so there are no cross-city matches.
- Preferred areas include their phases and blocks.

**LLM parser:** place names the model returns are accepted only if they resolve within the rules-detected city (or a single city). The live LLM integration was **not** tested against a real API; it remains optional and behind `MEEZAN_AI_DRIVER=llm`.

## 22. Lead distribution changes

**`LeadRouter::eligibleDealers` now requires the dealer to serve the requirement's city.** A served city means either of:

- the city is in their `cities` pivot
- they have no pivot rows and it is their home city

A Karachi-only dealer can no longer receive a Lahore lead.

**Area overlap is hierarchy-aware:** a society covers its phases, and a phase request reaches dealers covering the society.

## 23. Admin changes

**Admin → Locations (`/admin/areas`):**

- Stats: active, flagged for review, and without a map centre.
- Filters: city, name/alias search, type, approval status, flagged, no centre, inactive/merged. Pagination is 50 per page.
- Quick inline edit is kept.

**New edit page (`/admin/areas/{id}/edit`):**

- display and official name, city, parent, type
- authority, approval status, reference, source URL, check date, confidence
- centre, order, aliases (one per line), notes, popular / flag / active
- the last 20 audit entries

**Merge (`POST /admin/areas/{id}/merge`)**, same city only, with explicit confirmation. It moves:

- listings, leads and projects
- dealer areas
- buyer requirements
- child locations
- aliases

The duplicate's name becomes an alias of the kept record; the duplicate is deactivated and old links 301-redirect. Every create, update, merge and import is written to the audit log.

## 24. SEO changes

- City pages:
  - title "Property for Sale & Rent in {City}"
  - description built from real counts
  - canonical URL
  - `CollectionPage` + `City` JSON-LD (with `GeoCoordinates` when known)
  - `BreadcrumbList`
- Area guides:
  - breadcrumbs Home → City → Society → Phase
  - `noindex` when they have no listings and are not popular, to avoid thin pages
- Sitemap: adds the 7 city pages, and lists area guides only for popular or listed areas (not the whole directory).
- Default title and description now mention Pakistan and the seven cities.

## 25. Automated test results

**`php artisan test`: 291 passed (1,196 assertions), 0 failed.** That is the 250 tests that existed before this phase, all still passing, plus 41 new tests in `NationalExpansionTest.php`. One existing test (LLM area mapping) failed after the parser change and was fixed in the code, not by editing the test.

The new tests cover:

- cities and city links
- normalisation (Gulshan-e-Iqbal, DHA Ph V, F7, E-11, Gulberg III, DHA Karachi, lettered sectors, Phase 7-E)
- import: idempotency, preserved coordinates, Rawalpindi and invalid-type rejection, dry run, duplicate flags
- export to CSV
- city pages, homepage counts, sitemap, area-guide status and disclaimer
- search: city text, ambiguity, hierarchy, suggestions, bounded page payload
- listing form: path, stale level, cross-city rejection, per-city loading
- AI: city and area detection, clarify, unsupported city, "Multan Road", no cross-city matches, generic-word place names
- lead routing by city and by hierarchy
- dealer registration with several cities and out-of-city rejection
- admin edit, aliases, merge, cross-city merge refusal
- geocoder name, object-kind, parent-point and shared-point rejection, and choosing the first valid candidate

**Other checks:**

- `npm run build`: success
- `composer audit` and `npm audit --omit=dev`: no vulnerabilities
- `vendor/bin/pint --test`: passes
- `php artisan route:cache`: works, including the city route

`meezan:production-check` reports the same local-environment items as before (mail driver `log`, demo data, public OSM tiles); none are new.

## 26. Manual testing results

In the in-app browser on `localhost:8000`:

| Check | Result |
|---|---|
| Homepage | Cities section shows Lahore 16 listings / 409 areas; the other cities show "New on Meezan" with their directory counts |
| `/karachi` | Page renders. Location search typed "dha ph" → only DHA Karachi phases |
| `/properties?location=Garden Town` | "matches more than one place" with Lahore and Quetta links |
| Listing form (demo dealer's existing listing; nothing saved) | Server-rendered Gulberg + sub-areas. Choosing DHA Lahore → Phase (14) → DHA Phase 1 → Sector (15) worked, and the map recentred on DHA Phase 1. Switching to Karachi reloaded 64 Karachi top-level locations. The listing's area was confirmed unchanged afterwards, and the demo dealer was signed out. |
| AI finder | "house in Garden Town" → asks which city. "Garden Town Lahore" → results. "plot in Rawalpindi" → not-covered reply. "F-7 Islamabad" → understood as F-7. "DHA phase 6 lahore" → 3 matches. This check found two bugs, both fixed and tested: generic-word place names were ignored, and "garden" was read as an amenity. |
| Browser console | No errors |

**Not checked manually:**

- Admin pages: the admin password was changed by you and was not reset, so admin QA is still yours to do. Admin flows are covered by automated tests.
- The dealer registration and buyer requirement pickers in the browser (covered by tests).
- Mobile widths.

## 27. Known issues

1. **Coordinates are automatic and unreviewed, and sparse.** 84% of locations (1,039) have no centre; see §11. Listings in those areas still work but are not grouped on the map until a centre exists.
2. **Lahore approvals are a March 2021 secondary copy** of the LDA list.
3. **About 59 automatically normalised LDA names need a spelling check** (flagged).
4. **A city-summary count can lag by up to 10 minutes** after a listing is published (cache).
5. **The "Area / society" select in the listing form lists all of a city's top-level locations** (314 for Lahore). It is usable but long; a searchable select would be friendlier.
6. **The demo dealer is pending verification,** so the new-listing form could not be opened in the browser (the edit form was used).
7. **The Nominatim connection from this machine was unstable** (timeouts and DNS failures). Failed look-ups are not marked as tried and can be re-run.

## 28. Missing data requiring manual research

- **Lahore:**
  - the current LDA approved and illegal lists
  - sectors of DHA Phases 5–8
  - Bahria Town Lahore block → sector mapping
  - Askari schemes
  - the DHA Phase 13 / EME (Phase XII) status
- **Karachi:**
  - SBCA / KDA / Malir DA approved and illegal lists
  - Gulshan-e-Iqbal / Gulistan-e-Jauhar / North Nazimabad blocks
  - Bahria Town Karachi precincts
  - DHA Phase 3
- **Islamabad:**
  - sub-sectors (F-7/1 …)
  - the jurisdiction of PWD, Pakistan Town, Korang Town, Top City-1 and Faisal Town (F-18)
  - whether CDA lists have changed since 30 Oct 2026
- **Multan:** transcription of the MDA illegal (Urdu) and under-process lists; DHA Multan sectors.
- **Faisalabad:**
  - FDA approved and illegal lists
  - blocks of major colonies (the thinnest city: 38 locations)
- **Quetta:**
  - QDA approved row 30
  - the conflict between QDA lists for Al Zia, and the Wahdat duplicate
  - checking the 110 under-process names
- **Peshawar:**
  - PDA approved and illegal lists (including the 181 schemes reported banned in 2021)
  - Hayatabad sectors
  - Regi Model Town zones
  - DHA Peshawar sectors
- **All cities:** manual centres for important locations with no OpenStreetMap match.

## 29. Recommended next development phase

1. **Location data review sprint:**
   - Work through the "Flagged for review" and "No map centre" admin filters, starting with popular areas.
   - Resolve the Quetta conflicts.
   - Add manual centres for key societies.
2. **Official list refresh:** obtain the LDA, FDA, PDA and SBCA lists (in person or by request if the sites stay unreachable), add them to `build_dataset.php`, and re-import.
3. **Searchable select for the listing form's top level,** replacing the long select (reusing `/locations/suggest`).
4. **City-level market pages:** asking-price medians per city and society, once there are at least 5 comparables (the area-insights rules already exist).
5. **Admin QA by you** on the new Locations pages, then a production readiness pass (mail provider, tile provider) before any deployment, which remains your decision.
