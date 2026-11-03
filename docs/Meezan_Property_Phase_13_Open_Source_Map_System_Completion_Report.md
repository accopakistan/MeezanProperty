# Meezan Property — Phase 13 Completion Report
## Free Open-Source Advanced Property Map (MapLibre GL JS + OpenStreetMap data + GeoJSON boundaries)

Date: 2 November 2026 · Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB) · No Git commits, no GitHub push, no deployment, no destructive database operations in this phase.

Status key: **COMPLETE AND VERIFIED** · **PARTIALLY IMPLEMENTED** · **NOT IMPLEMENTED**

---

## 1. Executive summary

Map search (`/map`) now runs on **MapLibre GL JS 6.15.0** with an OpenStreetMap-based vector style. The page keeps the layout from the "Map Search Dashboard" reference: list | map | detail panel.

**Map search**
- **Clustered markers** (price pins, approximate pins, area groups) drawn as accessible HTML buttons.
- **Pop-ups** from real listing data.
- **Two-way sync:** marker → card highlight and scroll; card "Show on map" → fly to the marker and open its pop-up; hover a card to raise its marker.
- **In-place updates:** filters, sort, category chips and pagination update the list, markers and counts together without reloading the page. The URL follows (back and forward buttons work).
- **"Search this area"** button, plus an optional "Search as I move the map".
- **Freehand draw-area search.**
- **City selector** for the seven launch cities, flying to centres stored in the `cities` table.
- **Phones:** Map / List / Filters switch.
- **Fallback:** if the vector style cannot load, the map falls back to raster OSM tiles; if the map fails entirely, the list keeps working.

**Large result sets:** above 500 located listings in a search, the map switches to counted grid cells computed in SQL, so the response stays bounded.

**Boundaries**
- A new `area_boundaries` table stores GeoJSON outlines (Polygon or MultiPolygon) attached to existing locations, with a draft → verified → published → archived workflow and audit logs.
- Geometry is validated server-side: closed rings, at least 3 distinct points, inside Pakistan, no self-intersection, non-zero area, holes inside their polygon, at most 2,000 points.
- The public map loads only **published** outlines, only for the visible area, only from zoom 10. Clicking one shows real listing counts per category, with links.
- **Admin tool** (`/admin/boundaries`, routes `admin.boundaries.*`): draw, edit (drag points, insert via midpoints, delete, undo), paste or import GeoJSON, save as draft, verify, publish, unpublish, archive, and start a new draft from a published outline.

**Privacy:** only `public_latitude`/`public_longitude` (the existing privacy-blurred coordinates) ever leave the server. Tests check that private exact pins never appear in the page or the JSON.

**Results:** 505 tests pass (2,754 assertions, 0 failures), including 28 new Phase 13 tests. Pint, the build, `composer audit`, `npm audit --omit=dev`, `route:cache`, `config:cache` and `view:cache` are all clean. All data counts are unchanged.

**What the local data allows:**
- **No exact pins:** 0 published local listings have their own map pin, so all 16 appear as approximate area-centre markers (199 of 1,238 areas have a centre).
- **No outlines:** 0 boundaries exist. I created none in your database, because the brief forbids invented outlines. The whole boundary workflow was tested end to end in a throwaway QA database (§30).

## 2. Initial map audit

**Before Phase 13:**

| Item | Finding |
|---|---|
| Map technology | **Leaflet 1.9.4** with raster tiles from `MEEZAN_MAP_TILE_URL` (default `tile.openstreetmap.org`). No Google Maps or Mapbox |
| Routes | `GET /map` → `PropertyController@map` (`properties.map`); `/properties` with a sidebar map; property page mini-map; nearby-places endpoint; location look-ups (`/locations/*`) |
| JavaScript | `resources/js/map.js` (Leaflet markers, popups, "search as I move", freehand draw); `location-picker.js` (dealer pin picker) |
| Marker data | `App\Support\MapMarkers`: `pin`, `approx` and `area` markers, at most 500 per page |
| Search | `App\Services\PropertySearch`: all filters, viewport (`north/south/east/west`) and drawn polygon (`poly`) |
| Coordinates | `properties.latitude/longitude` (dealer's pin, private) and `public_latitude/public_longitude` (what may be shown: exact, blurred about 500 m, or none, by `location_privacy`); index on `(public_latitude, public_longitude)` |
| Location data | `cities` (7 rows, all with centres); `areas` (1,238: 815 housing schemes, 87 sectors, 49 phases, …; 199 with centre coordinates); `area_aliases` (267); hierarchy through `parent_id` |
| Boundaries | None; no geometry anywhere |
| SEO | `/map` is "noindex, follow" (Phase 12) |

**Reused:**
- `PropertySearch` (every filter), `MapMarkers`, `Property::publicCoordinates()` and its privacy rules, `City::centre()`, `Lookup::areas()`, `LocationResolver::withDescendants()`.
- The property card, save button and share button components.
- `AuditLogger`, the admin layout, and the `role:admin` middleware.

**Extended:**
- `PropertySearch`: the opt-in `centres` viewport mode.
- `MapMarkers`: area totals for large result sets.
- `SecurityHeaders`: map sources in the CSP.

**Replaced:** only the `/map` page's map engine and its server/JS flow. The Leaflet maps elsewhere stay as they are (§31).

## 3. MapLibre integration — **COMPLETE AND VERIFIED**

- **Package:** `maplibre-gl` **6.15.0** (BSD-3-Clause), an npm dev dependency pinned to the exact version. It is bundled locally by Vite; no CDN is involved. Version 6.16.0 (released 25 October) was skipped because it was only a week old.
- **Worker:** MapLibre 6 loads its tile worker from a separate file, so Vite serves `maplibre-gl-worker.mjs` as its own asset and the code sets it with `setWorkerUrl()`.
- **Modules** (loaded only when their element is on the page):

| File | Role |
|---|---|
| `resources/js/geo-map/common.js` | Map creation, style with raster fallback, notices, shared helpers |
| `resources/js/geo-map/index.js` | Map search page: data loading, sync, URL state, controls, mobile switch |
| `resources/js/geo-map/markers.js` | Clustered GeoJSON source, HTML markers, popups |
| `resources/js/geo-map/boundaries.js` | Published outlines: viewport loading, hover, select, popup |
| `resources/js/geo-map/draw.js` | Freehand draw-area search (Douglas–Peucker, at most 100 points) |
| `resources/js/geo-map/boundary-editor.js` | Admin outline editor |

- **Not tied to one provider:** the style URL and the raster fallback come from config. Swapping providers is a configuration change, not a code change.
- **No inline script:** configuration is passed through `data-*` JSON attributes, so the strict nonce CSP still applies.

## 4. Map style / tile provider — **COMPLETE AND VERIFIED** (development); production choice needed

| | What it is | Cost / limits |
|---|---|---|
| **FREE SOFTWARE** | MapLibre GL JS (BSD-3), bundled in the app | Free, no limits, runs in the browser |
| **FREE MAP DATA** | OpenStreetMap (ODbL), © OpenStreetMap contributors; attribution is always shown on the map | Free to use with attribution; share-alike applies to databases derived from OSM (see §12) |
| **FREE HOSTING (of tiles/style)** | **OpenFreeMap** "liberty" style: `https://tiles.openfreemap.org/styles/liberty` (vector tiles, fonts, sprites) | No API key. Run on donations with **no service guarantee**. Not "unlimited free infrastructure" |
| Fallback | Raster tiles from `MEEZAN_MAP_TILE_URL` (default OSM's tile server) | OSM's usage policy allows light use only; not for production traffic |

**Configuration:** `MEEZAN_MAP_STYLE_URL` (new; empty means raster tiles only) and the existing `MEEZAN_MAP_TILE_URL` / `MEEZAN_MAP_ATTRIBUTION`. The CSP `connect-src` and `img-src` are derived from these two URLs automatically.

**Production options:**
1. Keep OpenFreeMap, accepting no SLA (sensible to donate).
2. Self-host OpenFreeMap's open-source stack, or a **PMTiles** file of Pakistan (a Protomaps basemap) on the hosting or object storage. This is free software, but the storage and bandwidth are ours to pay for.
3. A paid vector-tile provider: any MapLibre-compatible style URL works.

On shared hosting, option 2 with a Pakistan-only PMTiles extract is the most predictable.

**Observed limitation:** on a first visit to an area, tiles took a few seconds to appear locally, depending on the provider's latency.

## 5. Property marker system — **COMPLETE AND VERIFIED**

**Marker types:**
- `pin`: exact public pin, purple price label (e.g. "2.5 Cr", using `Format::compactPrice`), with a "New" badge.
- `approx`: privacy-blurred pin, dashed.
- `area`: listings without a pin, at the area centre, dashed, "N listings" plus "from …".
- `grid`: counted cell, large result sets only.

**Behaviour:**
- **Buttons:** markers are HTML `<button>`s with descriptive `aria-label`s, so they work with the keyboard.
- **Selected state:** gold highlight, matching the card's gold ring.
- **Data source:** real listings only. Nothing is placed without coordinates: a listing with no pin and no area centre stays in the list only, and the legend says so.

## 6. Property clustering — **COMPLETE AND VERIFIED**

- **How it works:** MapLibre's clustered GeoJSON source (radius 48 px, clusters break apart above zoom 14).
- **Real counts:** the cluster count is the **sum of real listing counts**, so an area group of 3 counts as 3.
- **Click:** a cluster zooms to its expansion zoom. Verified in the browser ("5 listings" split into "4 listings" plus individual markers).
- **Large sets:** above `max_points` (500), the server sends SQL grid cells instead of rows (§19).

## 7. Marker popups — **COMPLETE AND VERIFIED**

- **Exact or approximate listing:** photo, price, title, beds, size and area, plus "View property". Approximate ones include an "about 500 m" note.
- **Area group:** the area name, count, an "approximate: area centre" note, up to 4 listings, and "Show all N in {area}", which filters in place.
- **Safety:** all text is escaped in JavaScript. No dealer phone numbers, emails, documents or notes (tested).

## 8. Map ↔ list synchronization — **COMPLETE AND VERIFIED**

- **Marker click:** opens the pop-up, highlights the matching card(s) with a gold ring and scrolls it into view. On wide screens (2xl) it also loads the detail panel (`/map/listings/{slug}`).
- **Card "Show on map":** flies to the marker, opens its pop-up and highlights it. On phones it switches to the map view first. A listing without a location gets a friendly message.
- **Card hover or keyboard focus:** raises its marker.
- **Moving the map:** shows "Search this area" (no request until pressed). With "Search as I move the map" ticked, it searches automatically, debounced to 600 ms.
- **Programmatic moves** (fit, fly-to, cluster zoom) never trigger searches.

## 9. Map filters — **COMPLETE AND VERIFIED**

The existing filters are reused unchanged through `PropertySearch`, with no second filter system:
- purpose tabs (Buy / Rent / Plot / Commercial);
- city, area/society, property type (all existing types), maximum price, size presets, bedrooms;
- sort;
- category chips with live facet counts.

Each change refreshes the list, the markers, the clusters, the "N Properties Found" count, the legend and the Area dropdown (for the chosen city) together. Tested for purpose, type, city, price and area (including sub-areas).

## 10. Bounds-based search — **COMPLETE AND VERIFIED**

- **Strict mode (unchanged):** `north/south/east/west` match only listings whose **public** coordinates are in view, using the existing `(public_latitude, public_longitude)` index. The existing tests still pass.
- **New opt-in `centres=1`** (used by map search): also matches listings without their own pin whose **area centre** is in view, exactly as they are drawn on the map. The list notes this ("including those shown at their area's centre (approximate)").
- **Validation:** coordinates are checked by `PropertySearch`. Incomplete or invalid bounds are ignored, never trusted.
- **Precedence:** a drawn polygon (`poly`) takes priority over bounds (existing rule).

## 11. API / data architecture — **COMPLETE AND VERIFIED**

| Endpoint | Returns |
|---|---|
| `GET /map/data` | `total`, `mode` (points/grid), `markers`, `located`, `approximate`, `polygon`, `areaOptions`, rendered `summary` and `list` HTML (the same Blade partial the page uses) |
| `GET /map/listings/{slug}` | Detail-panel HTML for a **public** listing (404 otherwise) |
| `GET /map/boundaries?north&south&east&west` | GeoJSON FeatureCollection of **published** outlines in view (at most 300). Empty with `zoomIn: true` when the box is wider than 3°. Cached publicly for 5 minutes |
| `GET /map/boundaries/{id}` | Name, city, live count, per-category counts and links (published only) |

**How the endpoints behave:**
- **Limits:** all are GET-only, throttled to 120 requests per minute, and send `X-Robots-Tag: noindex`. `/map/` is also disallowed in robots.txt.
- **Marker fields:** only the id, kind, lat/lng (public only), label, price, title, short meta, thumbnail, URL and area-group ids/counts.
- **No-JavaScript fallback:** the page renders the first results server-side, so it works without JavaScript and keeps its existing tests.

## 12. Society / area boundary system — **COMPLETE AND VERIFIED**

`City → Area (existing) → area_boundaries (versions) → GeoJSON → MapLibre fill + line layers.`

**Design choices:**
- **Every outline belongs to one existing `areas` row** (foreign key, restrict on delete). There is no second location system.
- **Storage is GeoJSON text plus bounding-box columns** (`min/max_lat`, `min/max_lng`, indexed with status), not spatial types. This works on the current MariaDB/MySQL and SQLite without GIS extensions; `area_sq_km` and `vertex_count` are derived.
- **Provenance is recorded:** `source` (admin_drawn | imported | openstreetmap) and `source_reference` (required for imported or OSM data, e.g. "OSM relation 123 (ODbL)").
- **ODbL note:** outlines copied from OpenStreetMap can make the boundary table a derivative database. Its public use then carries ODbL attribution and share-alike duties. Get advice before importing OSM outlines in bulk.

## 13. GeoJSON support — **COMPLETE AND VERIFIED**

- **Accepted:** a geometry object, a `Feature`, or a `FeatureCollection` with one feature, of type `Polygon` or `MultiPolygon`. Holes are allowed.
- **Stored:** the normalised geometry, with positions rounded to 6 decimals (about 0.1 m) and consecutive duplicates dropped.
- **Ways in:** admins can paste GeoJSON or import a `.geojson` file (up to 2 MB).

## 14. Boundary validation — **COMPLETE AND VERIFIED**

`App\Support\BoundaryGeometry::parse()` rejects each of the following with a clear message (one test per rule):
- invalid JSON, or types other than Polygon/MultiPolygon;
- more than one feature;
- unclosed rings, or fewer than 3 distinct points;
- self-intersection (e.g. a bow-tie); zero area (collinear points);
- points outside Pakistan, which also catches swapped lat/lng;
- non-numeric positions; holes outside their polygon;
- more than 2,000 points in total, or more than 20 polygons or rings.

Invalid input is never stored, so malformed geometry cannot reach the public map.

The editor also warns, without blocking, when the location's recorded centre falls outside the outline.

## 15. Boundary publishing workflow — **COMPLETE AND VERIFIED**

Draft → (Mark verified) → Verified → (Publish) → Published → (Unpublish) → Verified. Archive is available from draft or verified, and "Back to draft" from verified.

- **No skipping:** publishing straight from draft is refused (tested).
- **One published outline per area:** publishing archives the previous one (tested).
- **No in-place edits:** geometry can be edited only in **draft**. A verified or published outline is revised with "New draft from this" and keeps its version history.
- **Audit trail:** every step records `verified_by`/`published_by`, timestamps and an `AuditLog` entry (`boundary.created/updated/verified/published/unpublished/archived/reopened`).

## 16. Admin boundary management — **COMPLETE AND VERIFIED**

- **Pages:** `Admin → Map Boundaries` (`/admin/boundaries`) has status counts, a city/status/name filter, and "Draw outline" for any matching active location. The location record page has a "Map boundary" card linking to it.
- **Editor:**
  - a MapLibre map centred on the location, showing other published outlines nearby (dashed) and the recorded centre (gold pin);
  - **Add points** (click), drag to move, drag a midpoint to insert, select and Delete, Undo, Clear, Zoom to outline;
  - a GeoJSON field with paste or file import;
  - source, reference and notes;
  - status actions with a confirmation before publishing;
  - version list and history.
- **Authorization:** the `auth` + `role:admin` route group. Guests are redirected; buyers and dealers get 403 on every action (tested).
- **Verified in a real browser** (throwaway QA database): drew 4 points, inserted a 5th via a midpoint, saved a draft, verified, published, and saw it on the public map with the hover and selected states and the pop-up.
- **Limitation:** the map tools edit one outer ring. Holes and multi-part shapes can be imported and edited through GeoJSON only.

## 17. Property privacy — **COMPLETE AND VERIFIED**

- **Only public coordinates are used:** `public_latitude/public_longitude` (exact only if the dealer allows it, about 500 m blur for "approximate", none for "area" or "none"). Raw `latitude/longitude` are never selected into marker payloads.
- **Area groups use the area's centre**, never a listing's.
- **Tested:** private exact coordinates (approximate and area-only listings) appear nowhere in `/map` HTML or `/map/data` JSON.
- **Preview endpoint:** 404 for non-public listings; no contact numbers.
- **Marker JSON:** no phones, emails, descriptions or documents (tested).

## 18. Seven-city support — **COMPLETE AND VERIFIED**

- **Selector:** the map's city selector lists Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar, and flies to each city's centre from the `cities` table. All 7 have stored centres; nothing is hard-coded in JavaScript.
- **No city chosen:** the map frames Pakistan using the configured bounds.
- **Choosing a city** updates the results, both city fields, the Area options and the URL.
- **Local results** from `/map/data`:

| City | Listings |
|---|---|
| Lahore | 16 (all approximate, area centre) |
| Karachi, Islamabad, Multan, Faisalabad, Quetta, Peshawar | 0 each, with the empty state shown |

Quetta was verified visually.

## 19. Performance — **COMPLETE AND VERIFIED**

**Queries**
- Bounds use the existing public-coordinate index; area filters use the Phase 12 `(status, area_id, published_at)` index.
- No N+1: marker rows load with `area` and `coverImage` eagerly, under `preventLazyLoading`. `/map/data` must not run more queries with 23 listings than with 3, and stays within 30 queries (tested).

**Bounded responses**
- Points mode sends at most 500 marker rows.
- Grid mode sends one row per occupied cell (about 40×40 at most) plus area totals. Listing rows aren't loaded for markers in grid mode.

**Boundaries**
- Loaded per viewport, only from zoom 10, at most 300, cached 5 minutes.
- Listing counts per area come from one grouped query plus one tree query, cached 10 minutes (`AreaListingCounts`), instead of one query per outline.

**Front end**
- Map moves never reload the page. Requests are aborted when superseded; follow mode is debounced; images in pop-ups and cards are lazy-loaded.

**Bundle** (loaded only on `/map` and the admin editor):

| Asset | Size | gzip |
|---|---|---|
| MapLibre | 1,090 kB | 297.5 kB |
| Worker | 519 kB | 147 kB |
| MapLibre CSS | 71.5 kB | 10.2 kB |
| Map search code | 20.6 kB | — |
| Editor | 5.7 kB | — |

`app.js` and `app.css` are unchanged in role.

**Local timings:** `/map` page 0.61 s, `/map/data` 0.61 s, measured on `php artisan serve` (not production numbers).

## 20. Mobile experience — **COMPLETE AND VERIFIED**

**At 375 px:**
- a sticky Map / List / Filters switch;
- the map fills the screen height;
- the list hides in map view;
- "Show on map" from the list switches to the map, flies to the listing and opens it;
- Filters scrolls to the filter form;
- the attribution collapses behind its (i) button;
- no horizontal scroll (scroll width 375).

**Verified** in the browser at mobile size.

## 21. Accessibility — **PARTIALLY IMPLEMENTED**

**Done**
- Markers and clusters are labelled buttons, with a visible gold focus ring.
- Every control has a label: the city select, "search as I move", Search this area, Draw/Clear, zoom, fullscreen and locate.
- The map region is labelled and keyboard-pannable (arrow keys and +/− when focused).
- The result count and list update politely (`aria-live`, `aria-busy`), and the status message uses `role="alert"`.
- Cards raise their marker on keyboard focus; the "Show on map" link is keyboard-operable.
- The mobile switch uses `aria-pressed`.

**Not keyboard-operable:** freehand draw-area search and the admin outline drawing are pointer-only. The keyboard alternatives are the Area filter and the GeoJSON field.

## 22. Security — **COMPLETE AND VERIFIED**

- **Public endpoints:** read-only GET, throttled, validated input, published data only.
- **Admin routes:** behind `auth` + `role:admin`; drafts and verified outlines are never public.
- **Geometry:** fully validated server-side, with size limits (2 MB text, 2,000 points).
- **Mass assignment:** only `source`, `source_reference` and `notes` are fillable. Status and user ids are set explicitly.
- **XSS:** Blade escaping, JSON in attributes escaped, and all pop-up text escaped in JavaScript.
- **CSP:**
  - still nonce-based with no `unsafe-eval`;
  - `connect-src`/`img-src` gain only the configured style and tile hosts;
  - `worker-src 'self' blob:` added for MapLibre's worker (tested).
- **Geolocation** is requested only when the user presses the locate button and is never stored or sent to the server. Denial shows a friendly message and the map keeps working.

## 23. Data preservation — **COMPLETE AND VERIFIED**

| Table | Before | After |
|---|---|---|
| users | 4 | 4 |
| dealer_profiles | 2 | 2 |
| properties (published / demo) | 17 (16 / 16) | 17 (16 / 16) |
| property_images | 42 | 42 |
| cities | 7 | 7 |
| areas (societies*) | 1,238 (833) | 1,238 (833) |
| area_aliases | 267 | 267 |
| leads / lead_assignments / lead_notes | 2 / 2 / 1 | 2 / 2 / 1 |
| conversations / conversation_messages | 10 / 46 | 10 / 46 |
| subscriptions / payments | 1 / 2 | 1 / 2 |
| audit_logs | 32 | 32 |
| area_boundaries (new) | — | 0 |

\* housing, defence, cooperative and government schemes.

**QA copy and photo files:**
- The QA copy ran on a separate SQLite file in the session scratchpad. It was deleted after QA and never touched the local MariaDB.
- Its demo seeder rewrote the 42 demo photo files in the shared `storage/app/public/properties`. **All 42 were checked byte-for-byte as identical** to their sources, and none of the real database's image records point to a missing file.

## 24. Files added

- `app/Http/Controllers/MapController.php`
- `app/Http/Controllers/Admin/BoundaryController.php`
- `app/Models/AreaBoundary.php`
- `app/Support/BoundaryGeometry.php`
- `app/Support/AreaListingCounts.php`
- `database/migrations/2026_11_02_000040_create_area_boundaries_table.php`
- `resources/js/geo-map/common.js`, `index.js`, `markers.js`, `boundaries.js`, `draw.js`, `boundary-editor.js`
- `resources/views/properties/partials/map-results.blade.php`, `map-detail.blade.php`, `map-summary.blade.php`
- `resources/views/admin/boundaries/index.blade.php`, `edit.blade.php`
- `tests/Feature/MapSystemTest.php`
- this report

## 25. Files modified

- `resources/views/properties/map.blade.php`: rebuilt for MapLibre; same reference layout, filters and SEO.
- `app/Http/Controllers/PropertyController.php`: `map()` moved to `MapController`.
- `app/Services/PropertySearch.php`: the `centres` viewport option.
- `app/Support/MapMarkers.php`: `areaSummary()`; `centres` removed from area links.
- `app/Http/Middleware/SecurityHeaders.php`: map sources and worker in the CSP.
- `app/Models/Area.php`: `boundaries()` relation.
- `config/meezan.php`: `map.style_url`, `map.max_points`, `map.boundary_min_zoom`.
- `routes/web.php`: 4 public and 7 admin routes.
- `resources/js/app.js`: lazy-loads the two new modules.
- `resources/css/app.css`: MapLibre markers, clusters, popups, mobile switch, editor handles.
- `resources/views/admin/areas-edit.blade.php`: "Map boundary" card.
- `resources/views/components/layouts/dashboard.blade.php`: "Map Boundaries" admin nav item.
- `public/robots.txt` and `robots.txt`: `Disallow: /map/`.
- `.env.example`: `MEEZAN_MAP_STYLE_URL`.
- `package.json` / `package-lock.json`: `maplibre-gl` 6.15.0.

## 26. Database changes

One additive migration: `2026_11_02_000040_create_area_boundaries_table`, which creates a new table only, with foreign keys to `areas` (restrict) and `users` (null on delete).
- No existing table or column was changed and no data was modified.
- No "on update" timestamp columns (production check passes).
- `down()` drops only the new table.

## 27. Routes added / changed

| Route | Name | Notes |
|---|---|---|
| `GET /map` | `properties.map` | **Changed handler** (now `MapController@page`); same URL, name and query parameters |
| `GET /map/data` | `properties.map.data` | new, throttled |
| `GET /map/listings/{property}` | `properties.map.preview` | new, throttled |
| `GET /map/boundaries` | `map.boundaries` | new, throttled |
| `GET /map/boundaries/{boundary}` | `map.boundaries.show` | new, throttled |
| `GET /admin/boundaries` | `admin.boundaries.index` | admin |
| `GET /admin/boundaries/create` | `admin.boundaries.create` | admin |
| `POST /admin/boundaries` | `admin.boundaries.store` | admin |
| `GET /admin/boundaries/{boundary}/edit` | `admin.boundaries.edit` | admin |
| `PATCH /admin/boundaries/{boundary}` | `admin.boundaries.update` | admin, drafts only |
| `PATCH /admin/boundaries/{boundary}/status` | `admin.boundaries.transition` | admin |
| `POST /admin/boundaries/{boundary}/duplicate` | `admin.boundaries.duplicate` | admin |

## 28. API endpoints

See §11. All public map endpoints are anonymous and read-only. Nothing in them requires or exposes a session, contact details or private data.

## 29. Automated test results

| Command | Result |
|---|---|
| `php artisan test` | **505 passed, 2,754 assertions, 0 failed** (69.7 s). Was 477 / 2,562 after Phase 12 |
| New `tests/Feature/MapSystemTest.php` | 28 tests, 192 assertions |
| `vendor/bin/pint --test` | passed |
| `npm run build` | built (only the expected warning about MapLibre's large chunk) |
| `composer audit` | no advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `php artisan route:cache` / `config:cache` / `view:cache` | all succeeded, then cleared for local development |
| `php artisan meezan:production-check` | 18 pass, 10 warn, 4 fail. The 4 failures are the local `.env` values (`APP_ENV`, debug, `APP_URL`, mail), unchanged from Phase 12 |

**What the new tests cover:**
- **Map page:** config, the seven cities, noindex; CSP.
- **Data endpoint:** published-only data; filters (purpose, type, city, price, area with sub-areas); strict and `centres` bounds, with invalid bounds ignored; grid mode.
- **Minimal payloads:** marker data and query-count stability.
- **Privacy:** private pins never sent; the preview endpoint.
- **Geometry:** valid GeoJSON forms; 10 invalid cases plus the size limit.
- **Public boundaries:** published-only, by viewport, with real counts, zoom-in guard.
- **Admin:** authorization for guests, buyers and dealers; draw → verify → publish, replacing the old outline, with audit entries; validation on save; edits only on drafts; duplicate; unknown locations refused.

**Regression:** the existing search, property detail, AI finder, leads, CRM, WhatsApp, billing, SEO and production-readiness suites all pass unchanged. No existing test was edited.

## 30. Manual QA results

**Built-in browser, `http://localhost:8000` (your local data):**
- **Map:** loads with OpenFreeMap streets and Urdu/English labels; zoom, pan and fullscreen work.
- **Clusters:** clicking "5 listings" zooms in and splits it.
- **Area marker:** opens a pop-up with real data; the matching card is gold-ringed and scrolled into view.
- **"Search Valencia Town"** filters in place to "1 Property Found" and updates the URL.
- **Map movement:** dragging shows "Search this area"; pressing it updates the list and URL.
- **City switching:** Lahore → Karachi updates the results (0) and both city fields; Back restores the previous search; Quetta flies to the right place.
- **Mobile (375 px):** the Map / List / Filters switch works; "Show on map" from the list opens the marker; no horizontal scroll.
- **Console:** no errors from the new code (only two intentional 404 probes from Phase 12 and a "geolocation not available" warning from the test browser).
- **Laravel log:** no errors during Phase 13.

**Throwaway QA database** (separate SQLite file and server on `127.0.0.1:8010`, demo admin from `DemoContentSeeder`, deleted afterwards):
- **Admin index and editor:** drew 4 points, dragged a midpoint to add a 5th, saved the draft, verified, published.
- **Public map:**
  - the outline appears from zoom 10;
  - hover thickens it;
  - clicking it shows the gold selected state and a pop-up ("Gulberg · 3 live listings", flats for sale 1, flats for rent 1, commercial for rent 1, with "Show 3 on the map" and an area-guide link);
  - a second click keeps the pop-up open, and a click outside closes it.
- **Freehand Draw Area:** produced a 21-point `poly` search, showed "Clear Area" and "Redraw", and outlined the drawn area.

**Bugs found and fixed during QA:**
1. The boundary pop-up closed itself when the same click reopened it.
2. The drawn search outline was skipped while tiles were loading (`isStyleLoaded()` is false then).
3. Map controls overlapped at medium widths.
4. The legend covered the attribution.

**Not manually tested:**
- Browser geolocation: the test browser has none, so only the error path in the code exists.
- Real outlines in your database: there are none.
- Behaviour with more than 500 located listings in a browser: covered by the grid-mode test only.

## 31. Known limitations

1. **Local data has no exact pins.** All 16 published listings show at area centres (approximate), and 1,039 areas have no centre, so their listings appear only in the list.
2. **No outlines exist yet.** Societies need outlines drawn or imported and verified by Meezan staff. None were invented.
3. **Two map engines.** Leaflet still powers the `/properties` sidebar map, the property-page mini-map, the admin listing map and the dealer location picker. Migrating them to MapLibre is a follow-up.
4. **Outline editor:** one outer ring; holes and multi-part shapes only through GeoJSON. No snapping and no overlap check against neighbouring outlines.
5. **Grid mode** is a simple SQL grid, not true server-side clustering. Its cost grows with matching rows (fine for tens of thousands, not millions).
6. **Tile provider:** OpenFreeMap has no service guarantee; tiles on first visits can take a few seconds.
7. **No satellite view.** No free imagery source with suitable terms is configured.
8. **Cached counts:** boundary counts are up to 10 minutes old; category counts in pop-ups follow Phase 12's 10-minute cache.
9. **Drawing tools are pointer-only** (§21).
10. **Detail panel:** wide screens (2xl) only. Other sizes use the pop-up and the listing page.
11. **Brand colours:** the brief's `#4B1592` / `#D6A13D` were **not** applied. The project keeps the Brand Style Guide palette `#4B2E83` / `#D4AF37`, as decided earlier for this project.

## 32. External dependencies

| Dependency | Local / free? | Notes |
|---|---|---|
| `maplibre-gl` 6.15.0 | **Fully local** (bundled by Vite), free software (BSD-3) | No account, no key, no runtime calls to MapLibre |
| OpenFreeMap style, vector tiles, fonts, sprites | **External, free, no key**, donation-funded, no SLA | Map data © OpenStreetMap contributors (ODbL), © OpenMapTiles |
| OSM raster tiles (fallback, and the Leaflet maps) | External, free for light use only | Production needs another provider (`MEEZAN_MAP_TILE_URL`) |
| Boundaries, listing data, counts | **Fully local** (our database) | — |
| Browser geolocation | The user's browser, only on request | Never stored |

**Possible future production infrastructure:**
- self-hosted tiles (a PMTiles file of Pakistan on our storage/CDN, or OpenFreeMap self-hosted);
- for very large datasets: server-side clustering (supercluster on the server or precomputed), vector tiles of listings, or a spatial database (MySQL spatial indexes, or PostGIS if the database ever moves to PostgreSQL). No database migration was done or is needed now.

## 33. Recommended next phase

1. **Location data quality:**
   - encourage dealers to set pins (the existing picker);
   - add centres for the 1,039 areas without one (staff-verified);
   - draw and verify outlines for the most-searched societies first (DHA phases, Bahria Town, Gulberg, Johar Town).
2. **Move the remaining Leaflet maps** (search sidebar, property page, dealer picker, admin listing) to MapLibre, then drop Leaflet.
3. **Production tiles:** choose OpenFreeMap with a donation, a self-hosted Pakistan PMTiles extract, or a paid provider; set `MEEZAN_MAP_STYLE_URL` and `MEEZAN_MAP_TILE_URL` on the server.
4. **Saved searches by drawn area or society outline,** with alerts.
5. **If the dataset grows past about 100k located listings:** server-side clustering or listing vector tiles.
