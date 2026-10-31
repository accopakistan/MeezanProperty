# Meezan Property — Location Data Quality Report

_Dataset: `Meezan_Pakistan_Locations.csv`, research date 30 October 2026; database state checked after import on 31 October 2026._

## 1. Summary

| Measure | Value |
|---|---|
| Rows in master CSV | 1,248 |
| City rows (become `cities`, not areas) | 7 |
| Rawalpindi rows (reference only, rejected by importer) | 3 |
| Locations imported (`areas`, all active) | 1,238 |
| — of which pre-existing Lahore areas (IDs and slugs kept) | 39 |
| Aliases stored | 267 |
| Locations with a parent (phase / sector / block / sub-area) | 126 |
| Flagged `needs_review` | 209 |
| Same normalised name twice in one city | 2 pairs, both in Quetta (see §3) |
| Same name in more than one city | 9 names (expected; handled by the city rules) |
| Locations with an OpenStreetMap centre | 199 (16%); 1,039 with no reliable match |

## 2. Validation performed

| Check | How | Result |
|---|---|---|
| Duplicate keys | `key` = city slug + name slug; a repeated key merges sources instead of duplicating | 0 duplicate keys |
| Parent links | Every `parent_key` must exist in the same city (builder aborts otherwise; importer re-checks) | 0 missing parents |
| City association | Importer rejects any city not in `config('meezan.cities')` | 3 Rawalpindi rows rejected |
| Types / statuses | Importer rejects unknown `type` or `approval_status` | 0 rejected in the real dataset |
| Status conflicts | A record found on two authority lists with different statuses is flagged | 0 conflicts found |
| Idempotency | Import run twice | 2nd run: 0 created, 0 updated, 1,238 unchanged, 0 aliases added |
| Existing data | Existing 39 Lahore areas matched by exact name | IDs, names, slugs and coordinates unchanged; metadata added |
| Coordinates | Geocoder accepts a point only within the city radius (40 km; Karachi 65 km), whose OSM name contains the place's distinctive words, that is not the parent's point and not another location's point | see §5 |
| Source URLs | Every official or secondary row carries `source_url`; neighbourhoods from established usage cite S00 + S18 | — |

## 3. Possible duplicates needing a decision

The importer flags records of one city whose names normalise to the same text (`possible_duplicates` = 4) and never merges them itself:

| City | Records | Statuses | Note |
|---|---|---|---|
| Quetta | "Al Zia H/S" and "Al Zia Housing Scheme" | `under_process` (QDA S08) and `declared_illegal` (QDA S07) | **Conflict between two QDA lists.** Confirm with QDA which applies before merging |
| Quetta | "Wahdat H/S" and "Wahdat Housing Scheme" | both `declared_illegal` | Listed twice in the QDA illegal notice; likely one scheme |

A first check also paired "Bahria Town Islamabad Phase 7" with "Phase 7-E"; that was a normaliser bug (the "E" suffix was treated as an Urdu connector), fixed and covered by a test. CDA lists them as separate schemes with different statuses.

Admin → Locations → Edit → **Merge a duplicate** moves listings, leads, dealer areas and requirements to the kept record and turns the other name into an alias.

Names that exist in more than one city (for example Garden Town — Lahore and Quetta; Gulshan-e-Iqbal — Karachi and an Islamabad scheme; Faisal Town — Lahore and Islamabad) are kept as separate records. Search and the AI parser ask which city is meant instead of guessing.

## 4. Records flagged for review (209)

By city: Lahore 79, Quetta 113, Islamabad 6, Karachi 4, Peshawar 4, Faisalabad 3.

| Reason | Count |
|---|---|
| QDA under-process names read from a misaligned table | 110 (Quetta) |
| Possible duplicates (§3) | 4 (Quetta; 1 of them is also an under-process name) |
| LDA names with doubtful source spelling or glued words (e.g. "Green Fort1", "Irrigationsociety", "Jodicial") | 59 (Lahore) |
| Bahria Town Lahore block names without a confirmed sector | 16 (Lahore) |
| DHA Lahore Phase 13, EME Society relationship, Askari, Citi Housing Lahore | 4 (Lahore) |
| Islamabad jurisdiction / approval to confirm (PWD, Pakistan Town, Korang Town, Top City-1, Gulberg umbrella, Faisal Town F-18) | 6 |
| DHA Karachi Phase 3, Askari 4/5 Karachi, Bahria Town Karachi | 4 |
| Faisalabad and Peshawar schemes without any source beyond usage | 6 |

Flagged records are searchable and usable; the flag only marks them for admin follow-up (Admin → Locations → "Flagged for review").

## 5. Coordinates

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

- Of the 199: 35 are the earlier Phase-1 OpenStreetMap centres of pre-existing Lahore areas (kind not recorded, left untouched); 164 were found in this phase. Kinds of OSM object: residential 54, neighbourhood 38, suburb 19, locality 7, town 3, village 2, quarter 1 (areas); primary 12, secondary 7, tertiary 6, trunk 4, road 2 (road corridors); shop 5, commercial 2, retail 2 (markets only).
- By location type: housing schemes 56, neighbourhoods 48, corridors 35, sectors 19, phases 17, commercial areas 10, blocks 7, government schemes 4, sub-areas 2, cantonment 1.
- Popular areas with a centre: 13 of 33.
- 1,039 locations were looked up and have **no reliable match** (`coordinates_source = osm_no_match`); 0 were left untried.
- Clean-up during this phase removed 17 points that several locations shared (15 groups) and 147 points whose OSM object was the wrong kind (shops, offices, buildings, amenities, shrines, roads for non-road locations). After clean-up, no two locations share a point.
- All 7 cities have an OpenStreetMap centre.

Rules applied (see `app/Console/Commands/GeocodeAreas.php`):

- Source: OpenStreetMap Nominatim only; each stored point has `coordinates_source = openstreetmap` and `coordinate_precision` = the OSM address type (for example `suburb`, `residential`, `neighbourhood`).
- A point is an **approximate centre for map navigation**, never a property location. Listings without a dealer-placed pin are shown as approximate.
- Each location type only accepts fitting OSM object kinds (areas: suburb, neighbourhood, residential, locality …; corridors: road classes; markets: also commercial/retail/shop). Up to five candidates are checked and the first that passes every rule is used.
- A phase or block never inherits its society's point; two locations never share a point. 15 groups (17 points) that shared a point after the first run were cleaned by `meezan:geocode-areas --fix-shared`, keeping the earliest record (the society or pre-existing area).
- Locations with no reliable match carry `coordinates_source = osm_no_match` and no coordinates. Re-check with `--retry`, or enter a centre by hand in Admin → Locations (stored as `manual`).
- Points were matched automatically and have **not** been checked one by one against the ground; treat them as unreviewed approximations.

## 6. Approval-status coverage

| Status | Records | Origin |
|---|---|---|
| approved | 367 | LDA 2021 list as reported (256, secondary), MDA official (61), QDA official (50) |
| noc_issued | 31 | CDA official |
| layout_plan_approved | 20 | CDA official (LOP approved, NOC not issued) |
| under_process | 112 | QDA official (110), CDA provisional LOP (2) |
| cancelled | 17 | CDA official |
| declared_illegal | 237 | CDA official (98), QDA official No. 745 of 09-12-2024 (139) |
| unverified | 65 | No authority list obtained (Karachi, Faisalabad, Peshawar schemes; some Lahore/Islamabad) |
| not_applicable | 389 | Neighbourhoods, sectors, corridors, markets, cantonments, DHA and authority-owned schemes |

No Lahore, Karachi, Faisalabad, Multan or Peshawar scheme is marked illegal: the relevant illegal lists were inaccessible (LDA, FDA, PDA, SBCA) or not transcribed (MDA, Urdu scan). Absence from an approved list was never treated as illegality.

## 7. Known limitations

1. LDA, FDA and PDA websites were unreachable from the research machine; Lahore approvals rely on a March 2021 secondary copy.
2. MDA illegal and under-process lists were downloaded but not transcribed.
3. Karachi has no approval data at all.
4. Sub-sectors (Islamabad F-7/1 etc.), most society blocks, and Hayatabad sectors are not in the dataset.
5. Display names of LDA schemes were normalised automatically from all-caps listings; about 60 need a human spelling check.
6. Coordinates are automatic OpenStreetMap matches; many official scheme names (especially in Quetta) do not exist on OpenStreetMap.
