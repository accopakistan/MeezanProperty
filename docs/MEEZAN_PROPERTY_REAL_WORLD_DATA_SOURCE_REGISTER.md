# Meezan Property: Real-World Data Source Register

Updated 5 November 2026. This file holds both the sources added on 5 November and, further down, the 4 November sources, all of which remain in use. Every source below is also a row in the `market_sources` table.

**Reliability, highest first:** official records · official developer or manager documents · independent rating reports · news · other references. Nothing in a lower tier can establish a legal status.

## Added on 5 November 2026

All were read once by hand on 5 November 2026. **Nothing from them is verified until staff check it.**

### Official records

| Source | Address | Used for | Publication date |
|---|---|---|---|
| MDA: sanctioned private housing schemes | https://mda.punjab.gov.pk/system/files/approved.pdf | 61 Multan legal records, area, mouza | Listed 26 Sep 2025 |
| FBR: S.R.O. 1554(I)/2021, Lahore | https://download1.fbr.gov.pk/SROs/202112118121824711LAHORE.pdf | 18 projects' tax values | 1 Dec 2021 |
| FBR: S.R.O. 1722(I)/2024, Lahore | https://download1.fbr.gov.pk/SROs/20241030Lahore1722.pdf | 2 projects' tax values | 29 Oct 2024 |

FBR values are tax values under section 68 of the Income Tax Ordinance, not market prices. They are never used in a score.

### Owner and manager documents

| Source | Address | Used for | Date |
|---|---|---|---|
| Dolmen City REIT page, Arif Habib Dolmen REIT Management | https://www.arifhabibdolmenreit.com/dolmen-city-reit-2-3/ | Occupancy, size of two buildings | Undated |
| Quarterly report to 30 Sep 2026, same manager | https://www.arifhabibdolmenreit.com/wp-content/uploads/2026/10/Quarterly%20Report%20%E2%80%93%20September%2030,%202026.pdf | Status and sales for six developments | 30 Sep 2026 |
| Signature Residency REIT Annual Report 2026 | https://www.arifhabibdolmenreit.com/wp-content/uploads/2026/10/SRR%20Annual%20Report%202026.pdf | Signature Tower | 30 Jun 2026 |
| Naya Nazimabad Apartment REIT Annual Report 2026 | https://www.arifhabibdolmenreit.com/wp-content/uploads/2026/10/NNAR%20Annual%20Report%202026.pdf | Peace Apartments | 30 Jun 2026 |
| Askari Corporate Tower website | https://askaricorporatetower.com/?p=11 | Existence, location | Undated |
| Zameen blog, the developer's sister company | https://www.zameen.com/blog/zameen-opals-possession-handover-ceremony-1537.html | Zameen Opal | 4 Aug 2023 |

A manager's or developer's statement is labelled as theirs. It isn't independent verification.

### Independent report

| Source | Address | Used for | Date |
|---|---|---|---|
| PACRA: JS Rental REIT rating report | https://pacra.com/view/storage/app/rating_reports/RR_2300_15417_02-Jan-26.pdf | The Centre | 2 Jan 2026 |

### News

| Source | Address | Used for | Date |
|---|---|---|---|
| Daily Times | https://dailytimes.com.pk/1269257/the-vertical-breaks-ground-on-v3-business-centre-a-new-hub-for-corporate-excellence-lahore-pakistan/ | Existence of 94, V2 and V3 Business Centres | 25 Feb 2025 |
| ProPakistani | https://propakistani.pk/proproperty/?p=5112 | Lifestyle Residency | 31 Oct 2022 |

## Checked and not used

| Source | Address | Finding |
|---|---|---|
| Zameen `robots.txt` | https://www.zameen.com/robots.txt | Disallows `/property_listings/`, `/search/`, `/indices/`, `/Bahria*` and city paths such as `/Lahore*`. Does not disallow `/new-projects/` or `/index/`, which were read by hand on 5 Nov. Property listings are not collected. |
| Zameen terms | https://www.zameen.com/terms.html | Effective 11 Jan 2022. Database rights are claimed. The part read had no rule on automated collection, but the full text wasn't available |
| Graana `robots.txt` | https://www.graana.com/robots.txt | Returned 403, which the project treats as "don't read". **Not collected** |
| MDA illegal schemes list | https://mda.punjab.gov.pk/system/files/illegal.pdf | Read, not transcribed. Urdu at low resolution, so too risky to copy. **Staff should read it** |
| FBR valuation index | https://www.fbr.gov.pk/valuation-of-immovable-properties/131220 | Used to find the tables |
| FBR Lahore S.R.O. 876(I)/2026 | https://download1.fbr.gov.pk/SROs/20265191955059601SRO876Lahore.pdf | Scan, unreliable as text |
| FBR Karachi S.R.O. 1724(I)/2024 | https://download1.fbr.gov.pk/SROs/20241031Karachi1724.pdf | Clean text; no exact project match |
| FBR Karachi S.R.O. 144(I)/2025 | https://download1.fbr.gov.pk/SROs/20252111624752700SRO144.pdf | Fetched, not read, as nothing from Karachi's table was used |
| FBR Peshawar S.R.O. 1710(I)/2024 | https://download1.fbr.gov.pk/SROs/20241029Peshawar1710.pdf | Clean text; no exact project match |
| FBR Islamabad S.R.O. 644(I)/2026 | https://download1.fbr.gov.pk/SROs/2026416174174449SRO644.pdf | Scan, unreliable as text |
| FBR Multan S.R.O. 650(I)/2026 | https://download1.fbr.gov.pk/SROs/20264221542102SRO650(I)2026.pdf | Scan, unreliable as text |
| FBR Faisalabad S.R.O. 651(I)/2026 | https://download1.fbr.gov.pk/SROs/20264221544525348SRO651(I)2026.pdf | Scan, unreliable as text |
| FBR Quetta S.R.O. 1444(I)/2026 | https://download1.fbr.gov.pk/SROs/202682815836583SRO1444.pdf | Scan, unreliable as text |
| FBR Multan and Faisalabad 2024 tables | https://download1.fbr.gov.pk/SROs/20241101Multan1729.pdf | Over the size limit for reading |
| Dolmen City REIT annual and interim statements | https://www.arifhabibdolmenreit.com/financial-reports/ | The list was read; the reports themselves were over the size limit |
| Globe Residency REIT quarterly report | https://www.arifhabibdolmenreit.com/wp-content/uploads/2026/10/GRR%20Financial%20Statements%20%E2%80%93%20September%2030%202026.pdf | Read; the manager's report was clearer and is the one cited |
| VIS rating document | https://docs.vis.com.pk/RatingReports/OP_01039801010_00010398.pdf | Fetched, not read in detail; not used |
| Real Land Management (Tower 10) | https://reallandmanagement.pk/?p=10314 | 404 |
| LDA approved schemes page | https://lda.gop.pk/approved-schemes | Its area column's unit couldn't be read, so LDA areas show as "unit not stated" |

## Not reached

KDA, PHATA, RDA detail pages, SBCA's other pages, Agency21, and published research from property organisations. No statement is made about them.

**Access:** nothing bypassed a login, CAPTCHA, paywall or technical control. These sources were read once by hand and are not on a schedule. The daily collectors stay as they were on 4 November.

---

# Sources from 4 November 2026

**Connected** means collected automatically, and the last run succeeded. Nothing is listed as connected without that.

## Connected (automatic, tested with real data on 4 Nov 2026)

| Source | Address | Type | City | Read | Entries |
|---|---|---|---|---|---:|
| LDA, approved private housing schemes | https://lda.gop.pk/approved-schemes | Official list | Lahore | Page data | 401 |
| LDA, illegal private housing schemes | https://lda.gop.pk/illegal-schemes | Official list | Lahore | Page data, 32 pages | 382 |
| LDA, schemes developed by LDA | https://lda.gop.pk/regular-schemes | Official list | Lahore | Page data | 19 |
| CDA, private housing schemes | https://www.cda.gov.pk/housing-schemes?letter=all | Official list | Islamabad | Page cards | 70 |
| CDA, private commercial schemes | https://www.cda.gov.pk/commercial-schemes?letter=all | Official list | Islamabad | Page cards | 4 (registry only) |
| CDA, illegal housing schemes | https://www.cda.gov.pk/illegal-housing-schemes | Official list | Islamabad | Page list | 166 |
| MDA, housing schemes developed by MDA | https://mda.punjab.gov.pk/housing-schemes | Official list | Multan | Table | 11 |
| MDA, MIT housing schemes | https://mda.punjab.gov.pk/housing-schemes | Official list | Multan | Table | 21 |

**Access basis:** public official records whose robots.txt allows reading. The Phase 2 review found no terms against automated reading on the LDA site. I did not read each site's terms for this register, so please check them. Requests identify themselves as `MeezanPropertyBot`, obey robots.txt and wait 1 second between pages. Collection runs daily at 03:45 only if a queue worker or scheduler is running.

## Official, read once (not connected; will not refresh by itself)

All last read on 4 Nov 2026. **Since 5 Nov 2026 these wait for a staff check:** they were copied by hand, so their legal records no longer verify automatically.

| Source | Address | City | How read | Why not connected | Used |
|---|---|---|---|---|---:|
| QDA, approved private housing schemes | https://qda.gob.pk/img/scheme_lists/approved%20scheme-2024.pdf | Quetta | PDF text | The collector doesn't read PDFs | 45 |
| FDA, approved private housing schemes | https://fda.gov.pk/schemes_private | Faisalabad | Browser | Incomplete certificate chain; TLS checks stay on | 188 lines of 201 |
| FDA, schemes developed by FDA | https://fda.gov.pk/schemes | Faisalabad | Browser | Same | 19 |
| MDA, commercial schemes | https://mda.punjab.gov.pk/housing-schemes | Multan | Page | Only 3 rows are in scope | 3 |
| PDA, approved housing societies | https://www.pda.kp.gov.pk/illegalsocieties | Peshawar | Page | No collector format yet | 8 |
| PDA, un-approved housing societies | https://www.pda.kp.gov.pk/illegalsocieties | Peshawar | Page | Same (registry only) | 3 |
| SBCA, NOC for sale and advertisement, 2021–2025 | https://sbca.gos.pk/housing-Year-2025 | Karachi | Browser | robots.txt returns a server error, which the collector treats as "don't read" | 40 |

**Notes:**
- **FDA:** its page reports 201 approved, 367 illegal and 226 under process. Only approved rows were copied, and some of the 201 may be missing because of how its table loads.
- **SBCA:** I also fetched one SBCA page (2025) with the bot before seeing the robots error, and read the other years once in a browser. Six industrial or warehouse projects were left out. SBCA doesn't state a category, so "housing" is inferred from names.
- **Personal data:** QDA's lists show individuals' names, and its under-process list shows CNIC and phone numbers. None of these were copied.

## Secondary references (they verify nothing)

Used only to learn that a named development is reported to exist. Its legal status stays "Unknown / Unverified". Last read on 4 Nov 2026.

| Source | Address | Used |
|---|---|---:|
| Wikipedia, List of shopping malls in Pakistan | https://en.wikipedia.org/wiki/List_of_shopping_malls_in_Pakistan | 124 malls, 2 plazas |
| Wikipedia, List of tallest buildings in Pakistan | https://en.wikipedia.org/wiki/List_of_tallest_buildings_in_Pakistan | Towers and apartment buildings |
| Wikipedia, List of tallest buildings in Karachi | https://en.wikipedia.org/wiki/List_of_tallest_buildings_in_Karachi | Stated use only |
| Wikipedia, List of tallest buildings in Lahore | https://en.wikipedia.org/wiki/List_of_tallest_buildings_in_Lahore | Stated use only |

**Project websites:** 8 developments list a website, shown as developer-reported. Seven answered normally when opened on 4 Nov 2026. `packagesmall.com` returned HTTP 403 to the bot, and is kept because the search results also pointed to it. Two listed sites were dropped: `theaquaticmall.com` doesn't resolve and `ahgroup-pk.com/091mall` is a parked domain. `millenniummall.net.pk` returned an error, so it isn't linked.

## Checked on 4 November, not used

| Source | Finding | What it needs |
|---|---|---|
| MDA, approved and illegal schemes: https://mda.punjab.gov.pk/private-housing-and-land-sub-division | Scanned images. **Update, 5 Nov:** the approved list was read by eye (see above); the illegal list still needs staff | Manual check against the scan |
| QDA, illegal schemes: https://qda.gob.pk/img/scheme_lists/illegal_scheme_list.pdf | Scanned images | Same |
| QDA, under process: https://qda.gob.pk/img/scheme_lists/under%20process.pdf | Contains personal data; not used | Names only, if wanted |
| LUBCA KP, approved housing societies: https://lubca.gkp.pk/approved-housing-societies/ | **robots.txt disallows bots; not read** | Permission from LUBCA |
| PDA downloads: https://www.pda.gkp.pk/About_Us/downloads | **robots.txt disallows bots; not read** | Permission from PDA |
| CDA, status PDF: https://www.cda.gov.pk/Assets/pdf/status_of_housing_socities.pdf | Older than CDA's own live pages | Not needed |
| RDA: https://rda.gop.pk/ | Home page only; no list found | Further checking |
| SBCA, `Year-20xx` pages | Not read | Further checking |
| KDA, PHATA | Reached their sites; no lists were checked | Further checking |
| FDA, illegal and under-process rows | Not copied | Available on FDA's page |
| Property portals (for example Zameen) | Allowed pages read by hand on 5 Nov, in low volume and with attribution. Not scraped, and not collected automatically | A permitted feed or licence before relying on this at scale, because Zameen's terms claim database rights. Asking prices stay separate from transactions |
| Transaction, valuation, rental or occupancy data | **No source found on 4 Nov. Update, 5 Nov:** REIT filings give some occupancy, and FBR publishes tax values | Owner documents, inspections or licensed data |

## Source priority

Stored per source as `authority_level`, following your hierarchy: 1 official records (used) · 2 official project documents · 3 independently verified records · 4 professional research · 5 reputable market sources · 6 asking-price listings · 7 other secondary information (used).
