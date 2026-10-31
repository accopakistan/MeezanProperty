# Meezan Property — Pakistan City Location Research

_Research date: 30 October 2026. Prepared for the national multi-city expansion (Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta, Peshawar)._

This document explains what was researched, from which sources, and how reliable each part is. The structured records are in:

| File | Contents |
|---|---|
| `Meezan_Pakistan_Locations.csv` | Master dataset: 1,248 rows (7 city rows, 1,238 locations, 3 Rawalpindi rows kept for reference and **not imported**) |
| `Meezan_Pakistan_Housing_Societies.csv` | The housing-scheme / society / government / defence subset of the master dataset |
| `Meezan_Pakistan_Location_Sources.csv` | Source register (S00–S21) with access status |
| `Meezan_Pakistan_Data_Quality_Report.md` | Duplicates, conflicts, review flags, coordinate quality and limitations |
| `raw/cda_private_housing_schemes_2026-10-30.json` | CDA lists as parsed from cda.gov.pk |
| `raw/official_lists_transcribed.php` | QDA, MDA and LDA lists as transcribed (names, references and locations only; owner names, CNIC numbers and phone numbers deliberately not copied) |
| `build_dataset.php` | Script that builds the three CSVs from the raw lists and curated records (re-runnable) |

Coverage is **not complete**. Pakistan has thousands of schemes, blocks and streets; this dataset covers the major neighbourhoods, the officially listed schemes that could be obtained, and the best-known phases and sectors. Gaps are listed per city below and in the data quality report.

---

## 1. Method

1. **Official lists first.** Development-authority websites were opened for each city. Where a list was published (CDA, QDA, MDA), every entry was transcribed with its status and reference. Where a site could not be reached (LDA, FDA, PDA), that is recorded as a limitation; a secondary copy was used only for LDA (Zameen News reproduction of the LDA list, March 2021) and is labelled as such.
2. **Established neighbourhoods and commercial areas.** Well-known localities, cantonments, markets and road corridors were added from established local usage (source S00) and portal area guides (Zameen, Graana). These carry the status `not_applicable` because they are not private housing schemes requiring approval.
3. **Hierarchy.** Phases, sectors and blocks were added only where a source describes them (DHA Lahore Phases 1–4 sectors from Graana; DHA Rahbar sub-phases from Zameen; DHA Karachi phases from Wikipedia; Hayatabad phases from Wikipedia; Islamabad sectors from Wikipedia; Clifton blocks 1–9).
4. **Existence check and coordinates.** After import, every location was looked up on OpenStreetMap Nominatim (one request per second). A result is stored only if it lies within the city radius, its OSM name contains the location's distinctive words, and (for a phase or block) it is not simply its parent's point. Everything else is left without coordinates. No coordinate was typed in by hand or estimated.
5. **Approval status kept separate.** A location's status repeats exactly what the cited authority list says. A scheme missing from a list is `unverified`, never `declared_illegal`. Search and listing availability do not depend on status.
6. **Uncertain records are flagged, not dropped.** `needs_review = 1` with a note explains what needs checking (206 records).

Status vocabulary used: `approved`, `layout_plan_approved` (CDA: LOP approved, NOC not issued), `noc_issued`, `under_process`, `cancelled`, `declared_illegal`, `unverified`, `not_applicable`.

Reliability labels used in this document:

- **Official** — taken from the authority's own website during this research.
- **Secondary** — from a publication that reproduces or describes official information (news, portal guides, Wikipedia).
- **Unverified** — candidate from established usage; existence not confirmed by an official list.
- **Missing** — known to exist but not obtained.

---

## 2. Source coverage summary

| Authority / source | City | Result |
|---|---|---|
| CDA — Private Housing Schemes (S01) | Islamabad | **Accessed.** 70 schemes with zone, road, area, layout-plan (LOP) and NOC status |
| CDA — Illegal Housing Schemes (S02) | Islamabad | **Accessed.** 98 entries |
| QDA — Approved schemes (S06) | Quetta | **Accessed** (scanned PDF). 51 rows, row 30 unreadable at a page break; 50 transcribed |
| QDA — Illegal schemes, No. 745 dated 09-12-2024 (S07) | Quetta | **Accessed** (scanned PDF). 139 rows |
| QDA — Under-process schemes (S08) | Quetta | **Accessed.** 110 names; table columns misaligned, so locations were not used |
| MDA — Approved schemes, updated 26 Sep 2025 (S10) | Multan | **Accessed.** 61 schemes with mouza, road, area and sanction date |
| MDA — Illegal schemes (S09) | Multan | **Accessed but not transcribed** — Urdu scanned notice; needs manual transcription |
| MDA — Under-process list (HS1–HS3) | Multan | Downloaded, **not transcribed** |
| LDA website (S05) | Lahore | **Inaccessible** (connection failed). Used the LDA list of 256 approved schemes as reproduced by Zameen News, 1 Mar 2021 (S04) — secondary and possibly outdated |
| FDA website (S11) | Faisalabad | **Inaccessible.** No approval data |
| PDA website (S12) | Peshawar | **Inaccessible.** No approval data |
| KDA / SBCA / MDA (Malir) | Karachi | **Not obtained.** No approval data for private schemes |
| Wikipedia — Sectors of Islamabad (S03), DHA Karachi (S13), Hayatabad (S14) | — | Accessed / via search |
| DHA Islamabad-Rawalpindi (S15) | Islamabad | Phase numbering; jurisdiction notes from secondary sources |
| Graana DHA Lahore guide (S16), Zameen DHA Rahbar (S17) | Lahore | DHA phases, sectors, Rahbar sub-phases |
| Zameen area guides Karachi (S19), Peshawar (S21) | — | Market names |
| OpenStreetMap Nominatim (S18) | all | Existence check and approximate centres |

Records citing each source: S18 360 · S00 350 · S04 257 · S07 152 · S08 110 · S02 98 · S10 75 · S01 70 · S06 50 · S16 49 · S03 42 · S21 23 · S19 18 · S13 13 · S14 8 · S17 6 · S15 4 (a record can cite several).

---

## 3. City sections

Counts are dataset rows excluding the city row itself.

### 3.1 Lahore (Punjab) — 409 locations

| Kind | Count |
|---|---|
| Housing schemes | 259 |
| Neighbourhoods / sub-areas / cantonment | 43 |
| DHA (scheme, phases, Rahbar sub-phases, sectors) | 1 + 31 phases + 44 sectors |
| Bahria Town sectors and blocks | 5 sectors + 16 blocks |
| Road corridors / commercial areas | 7 / 7 |

- **Officially verified:** none directly (LDA site unreachable).
- **Secondary:** 256 schemes marked `approved` from the LDA list reproduced by Zameen News (1 Mar 2021, S04). Display names were normalised from the all-caps official listing (for example `WAPDA EMPL:C:H:S: PHASE-I` → *Wapda Town Phase 1*); the official spelling is kept in `official_name`. Names with doubtful spellings in the source (for example "SUPIRIOR", "JODICIAL", "REVNUE") keep them in `official_name` and are flagged for review. DHA Lahore Phase 1–4 sector letters (Graana) and DHA Rahbar sub-phases (Zameen).
- **Unverified:** Bahria Town block names (market usage, parent sector not recorded), Askari numbered schemes, Citi Housing Lahore, DHA Phase 13 (sources disagree on whether DHA Lahore has 12 or 13 phases), and EME Society's relationship to DHA (described by secondary sources as DHA Phase XII).
- **Existing data:** the 39 areas already in the app were matched by exact name, kept their IDs, slugs and coordinates, and received metadata only.
- **Missing:** the current (post-2021) LDA approved and illegal lists; sectors of DHA Phases 5–8; Bahria Town Lahore block-to-sector mapping; most Askari schemes.

### 3.2 Karachi (Sindh) — 84 locations

| Kind | Count |
|---|---|
| Neighbourhoods / cantonments | 43 / 2 |
| DHA Karachi + DHA City Karachi | 2 |
| DHA phases (1–8, 2 Ext, 7 Ext, 8 Ext) | 11 |
| Clifton blocks 1–9 | 9 |
| Housing schemes (KDA schemes, PECHS-type societies, Bahria Town Karachi, Navy, Askari) | 10 |
| Commercial areas | 6 |

- **Officially verified:** none (no Karachi authority list obtained).
- **Secondary:** DHA Karachi phases (Wikipedia S13); neighbourhood names (Zameen S19). DHA Phase 3 is listed by Wikipedia but missing from portal guides — flagged.
- **Unverified:** approval status of every private scheme, including Bahria Town Karachi (subject to Supreme Court land proceedings — not characterised here).
- **Missing:** SBCA / KDA / MDA (Malir) approved and illegal lists; Gulshan-e-Iqbal and Gulistan-e-Jauhar block numbers; Bahria Town Karachi precincts other than Precinct 15; North Nazimabad blocks.

### 3.3 Islamabad (Islamabad Capital Territory) — 240 locations

| Kind | Count |
|---|---|
| CDA sectors (B-17 … I-16) | 42 |
| Registered private schemes from CDA (S01) | 70 |
| Listed as illegal by CDA (S02) | 98 |
| Neighbourhoods, commercial areas, corridors | 10 / 5 / 7 |
| Other (Park Enclave, DHA Phase 2, jurisdiction-to-verify schemes) | 8 |

- **Officially verified:** CDA statuses — 31 `noc_issued`, 20 `layout_plan_approved` (NOC not issued), 17 `cancelled`, 2 `under_process` (provisional LOP), 98 `declared_illegal`. References include zone, road and area as published.
- **Secondary:** sector series (Wikipedia S03).
- **Jurisdiction rules applied:** DHA Islamabad Phase 2 is kept in Islamabad (Zone V). DHA Phases 1 and 4 and Bahria Town Phase 8 lie in Rawalpindi District and are **not** imported (Rawalpindi is not a launch city). PWD Housing Scheme, Pakistan Town, Korang Town, Top City-1, Gulberg (umbrella name) and Faisal Town (F-18) are flagged "jurisdiction / approval to verify". "Faisal Town, Islamabad Highway" on the CDA illegal list is kept as a separate record from "Faisal Town Phase 1 (F-18)".
- **Missing:** sub-sectors (F-7/1 … F-7/4) and blocks of sectors; CDA's own residential sectors under development (E-12, I-15 details).

### 3.4 Multan (Punjab) — 96 locations

| Kind | Count |
|---|---|
| MDA-approved private schemes (S10) | 61 |
| MDA's own schemes (Model Town, Shah Rukn-e-Alam Colony, FJ Town 1–2) | 4 |
| Neighbourhoods, cantonment, DHA Multan, Wapda Town | 17 |
| Corridors / commercial areas | 10 / 4 |

- **Officially verified:** 61 schemes `approved`, each with sanction date, zone, mouza, road and area in kanal (MDA list of 26 Sep 2025).
- **Unverified:** DHA Multan sectors, Wapda Town Multan status.
- **Missing:** MDA illegal list (Urdu scan, not transcribed — no Multan scheme is marked illegal because of this), MDA under-process list.

### 3.5 Faisalabad (Punjab) — 38 locations

| Kind | Count |
|---|---|
| Neighbourhoods (incl. Peoples Colony 1 and 2 under Peoples Colony) | 21 |
| Housing schemes (WAPDA City, Citi Housing, Eden Gardens, Eden Valley, Canal Garden, Sitara Valley) | 6 |
| FDA City (FDA's own scheme) | 1 |
| Corridors / commercial areas | 7 / 3 |

- **Officially verified:** none — the FDA website was unreachable.
- **Unverified:** every private scheme's approval status.
- **Missing:** FDA approved / illegal lists; blocks of Madina Town, Peoples Colony and Gulberg; this is the thinnest city in the dataset and needs the most manual research.

### 3.6 Quetta (Balochistan) — 330 locations

| Kind | Count |
|---|---|
| QDA-approved private schemes (S06) | 50 |
| Listed as illegal by QDA (S07, No. 745 of 09-12-2024) | 139 |
| QDA under-process schemes (S08) | 110 |
| QDA schemes (Zarghoon, Chaman Housing), DHA Quetta, cantonment, neighbourhoods | 17 |
| Corridors / commercial areas | 11 / 3 |

- **Officially verified:** all three QDA lists. Approved entries carry the QDA file number; illegal entries carry the location column from the notice.
- **Data handling:** the under-process PDF contains owners' names, CNIC numbers and phone numbers. Only scheme names were taken; all 110 under-process names are flagged for review because the table columns were misaligned.
- **Missing:** QDA approved row 30 (unreadable); internal blocks of any scheme; most Quetta schemes are not on OpenStreetMap (see coordinates).

### 3.7 Peshawar (Khyber Pakhtunkhwa) — 41 locations

| Kind | Count |
|---|---|
| Hayatabad + Phases 1–7 | 8 |
| Regi Model Town (PDA) | 1 |
| Neighbourhoods, cantonment, DHA Peshawar | 12 |
| Private schemes (Nova City, Shaheen Muslim Town, New University Town, Safia Homes, Shaheen Housing Scheme) | 5 |
| Corridors / commercial areas | 11 / 4 |

- **Officially verified:** none — PDA website unreachable.
- **Secondary:** Hayatabad phases (Wikipedia S14; Phase 5 described as mainly institutional). Sectors A–H were not created because the sector list could not be verified per phase.
- **Unverified:** approval status of all private schemes. In January 2021, 181 schemes in Peshawar were reported banned on PDA's recommendation; the names were not obtained, so none is marked illegal.
- **Missing:** PDA approved / illegal lists; Hayatabad sectors; Regi Model Town zones; DHA Peshawar sectors.

---

## 4. Excluded: Rawalpindi

Rawalpindi is not a launch city. Three records marketed with Islamabad but located in Rawalpindi District are kept in the master CSV with `active = 0` and are rejected by the importer: DHA Islamabad Phase 1, DHA Islamabad Phase 4 and Bahria Town Phase 8. Searching for them, or for "Rawalpindi"/"Pindi", tells the buyer the city is not covered instead of matching an Islamabad location.

## 5. How to extend

1. Add or correct rows in `build_dataset.php` (or edit the CSV directly), keeping `key` stable.
2. `php docs/research/build_dataset.php` to regenerate the CSVs.
3. `php artisan meezan:import-locations --dry-run`, then without `--dry-run`. The import updates in place and never deletes.
4. `php artisan meezan:geocode-areas` (add `--retry` to re-check earlier misses), then `php artisan meezan:export-locations` to write coordinates back to the CSV.
