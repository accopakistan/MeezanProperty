# Meezan Property: Withdrawal of Invented Ranking Data, and Real Public Data

Date: 19 November 2026. **This was done on the local copy only.** The live site was checked read-only and not changed.

## 1. What was found

While researching real public data for the ranking pages, we found that the 7 Nov 2026 work (`CityWiseRankingsExpansionService`, `meezan:populate-real-data`) had invented the data behind most rankings.

| Invented on 7 Nov | Local | Live (read-only check) |
|---|---:|---:|
| Evidence rows marked "verified", credited to sources that don't exist | 720 | 450 |
| Legal records with made-up references (e.g. "MDA/MCB/TOW-1988/42") | 80 | not counted |
| Whole projects that were made up (e.g. "Zarghoon Heights Apartments", "Jinnah Corporate Center") | 16 | present, ranked publicly |
| Real records edited (relabelled "verified", repointed, figures changed) | 7 evidence, 16 legal | not counted |

- **The sources that don't exist:** "Meezan Property Physical Site Inspection & Occupancy Census", "Verified Land Registry & Property Transfer Register" and "Licensed Commercial Lease & Tenancy Transaction Register". Their links point to `meezanproperty.com/verification/…` pages, which don't exist. A "PEC Structural Register" and an "SECP / PSX" catch-all were also used.
- **Real authority names used as cover:** "LDA approved schemes" is credited as the source of a "location: excellent" rating for Islamabad projects, which LDA doesn't cover.
- **Real figures that were changed:** LDA publishes Jubilee Town as "colonized 65%"; it was changed to 90%. LDA Avenue's 40% was changed to 55%.
- **Real references that were replaced:** 16 genuine FDA, QDA, PDA, SBCA and MDA references were replaced with made-up ones and marked verified.

The original values were recovered from the backup taken before the 7 Nov work: `database/backups/meezanproperty_backup_part2_pre_execution.sql`, 7 Nov 11:15.

## 2. What was changed (local)

- **Backup first:** `C:\xampp\backups\MeezanProperty-before-evidence-withdrawal-2026-11-19\meezanproperty.sql` (240 MB, complete).
- **New command:** `php artisan meezan:withdraw-fabricated-evidence`. It reports what it would change by default; `--commit` applies it. It matches records by content, not row ID, so the same command works on the live database.
  - **Rejects** the 720 invented evidence rows and 80 invented legal records. They are marked `rejected`, with the reason recorded; nothing is deleted.
  - **Restores** the 7 edited evidence rows and 16 edited legal records to what was actually read (source, figure, reference), set back to awaiting staff review.
  - **Unpublishes** the 16 invented projects.
  - **Switches off** the 5 sources that don't exist.
  - **Records everything** in the audit log (`investment.fabricated_evidence_withdrawn`, `project.unpublished`).
  - Running it a second time changes nothing.
- **`meezan:populate-real-data` disabled:** it now refuses to run.
- **Index re-run (#33):** 0 projects ranked, 1,251 under research, 22 excluded on legal grounds. That is the honest state: no real evidence has been staff-verified yet. It matches the 6 Nov position.

## 3. Real public data added

**Source:** Zameen.com's public price index pages (`/index/…`). They aren't disallowed in robots.txt, which we rechecked on 19 Nov. They hold advertised (asking) averages, not sales.

**Readings:** recorded in `database/data/investment/market_price_readings_2026_11_19.php`, with notes in `docs/research/zameen_index_readings_2026-11-19.md`. Loaded with `php artisan meezan:market-facts`.

**Coverage:** 17 societies now carry price data.
- **New areas:**
  - LDA Avenue (Lahore)
  - Gulberg Residencia (Islamabad)
  - Naya Nazimabad (Karachi)
  - Sitara Sapna City and Sitara Gold City (Faisalabad)
  - Sheikh Yaseen Town (Peshawar)
  - Multi Gardens Phase II (Islamabad)
- **Full monthly history:** 13 societies have 15 months of published averages (Jul 2025 → Sep 2026) plus the 2-year-earlier price, so the price chart shows real points only.

**Not used, with the reason:**

| Area | Why |
|---|---|
| Bahria Enclave | Its page showed the parent area's figures |
| Gulberg Greens | Farmhouse plots, which this index doesn't measure |
| CBR Town, Gul-e-Rehmat, Ali Town (Quetta) | No index page exists |
| Shops | Index pages render without data |

## 4. Ranking pages: market data when nothing is ranked

Where a leaderboard is empty, the dashboards now show researched projects as **market data, not a ranking**:
- Each table or card is headed "… : Market Data" and carries a "Not a ranking" note.
- No rank numbers, medals or Index scores; every row shows **Not ranked**.
- Each row shows the advertised price and its 1- and 2-year change, the official legal record, and the source.
- Societies are ordered by 1-year advertised change, which is stated on the page as a market fact, not a score.
- A **"Market Snapshot"** card takes the featured slot. It holds the society with the fullest public price record (chosen for that, not for performance) and its real monthly price chart.
- Search and filters apply to these lists too.

**Checked on the local site at 1536 px:**

| City pages | Market data | Price chart |
|---|---|---|
| Lahore, Karachi, Islamabad, Faisalabad, Peshawar, Multan | Yes | Yes |
| Quetta | Yes | No price index on file |

None of the invented projects appear anywhere.

**Files:**
- `app/Console/Commands/WithdrawFabricatedEvidence.php`
- `app/Services/InvestmentIndex/IndexBrowser.php` (`marketBoard`, `marketChange`)
- `app/Support/PriceIndexTrend.php` (published series, all-sizes averages)
- `app/Console/Commands/ImportMarketFacts.php` (two data files, series)
- `resources/views/rankings/partials/city-dashboard.blade.php`
- `resources/views/rankings/partials/price-trend.blade.php` (time-scaled x-axis)

**Tests:** `tests/Feature/FabricatedEvidenceWithdrawalTest.php` and `tests/Feature/PriceIndexTrendTest.php`. The ranking test set gives 352 passed, 14 failed; the 14 failures already existed before today (redesign wording, 7–13 Nov).

## 5. Still to do

1. **Apartments and commercial projects:** Zameen indexes areas, not buildings. Next sources:
   - area flat price and rent indexes, giving an advertised gross yield;
   - developers' published prices on Zameen's new-project pages;
   - REIT reports (Dolmen City, JS Rental REIT).
2. **Staff verification:** projects become rankable only once staff verify real evidence. The 29 genuine 5 Nov readings and the official list entries are waiting in the admin review queue.
3. **Live rollout, after the owner reviews the local pages:**
   1. Back up the live database.
   2. Deploy the code.
   3. Run `meezan:withdraw-fabricated-evidence`, check its report, then run it with `--commit`.
   4. Run `meezan:market-facts`.
   5. Run `meezan:investment-index run`.

   Live row IDs differ from local, which is why the command matches by content.

## 6. Update, 19 Nov 2026 (later): apartment and commercial pages

The national pages for commercial plazas, apartment buildings, shopping malls and business towers listed projects with "—" in every column. They now show what is on public record.

- **New data:** `database/data/investment/market_area_readings_2026_11_19.php`.
  - Zameen area flat price and rent indexes for 11 apartment projects, with an advertised gross yield worked out per sq ft and labelled "area, advertised". Bahria Town Islamabad's yield is left out because it looks implausible (about 20%).
  - Wikipedia references for Emporium Mall and Packages Mall, labelled as an unverified secondary reference.
- **Board order:** projects with the most public data come first: price, yield, stated occupancy, other facts (`IndexBrowser::marketRichness`).
- **Columns:**
  - **Location:** the advertised or official location.
  - **Price:** the developer's advertised price range (Zameen new-project listings, 5 Nov), or the area's flat price per sq ft.
  - **Yield:** the area yield.
  - **Occupancy:** occupancy stated in a source on file, e.g. Dolmen Mall 97.8% and Harbour Front 100% from the REIT manager, marked "stated, unchecked".
  - **Name cell:** one more public fact, e.g. leasable area, opening year or certification.
- **City pages:** the commercial cards show the advertised price and stated occupancy.
- **AI advisor:** it no longer shows market-data rows as a "top three" with "Index 0"; it shows its question suggestions instead.
- **Tests:** the ranking test set shows only the 14 older failures.

### 6a. Malls and towers (19 Nov, later)

Added Wikipedia facts, labelled "Secondary public reference (unverified)", for: Lucky One Mall, The Centaurus (mall and corporate tower), Giga Mall / World Trade Center Islamabad, Ocean Mall and Tower, Fortress Square, Arfa Software Technology Park, MCB Tower, Habib Bank Plaza and UBL Tower. Each gets a location (where given), opening or build date, height and floors, stores, and owner or developer.

Left out on purpose:
- Fortress Square's floor area (60,942 sq ft for five levels is implausible).
- Habib Bank Plaza's completion year and floor count (Wikipedia gives 1963 and 1971, and 22, 24 and 25 floors).
- Dolmen Mall Lahore (the article's 2024 opening date is doubtful).
- Mall of Lahore and Amanah Mall (only blog figures found).
- The Dolmen City REIT Annual Report 2025 (the PDF is too large to read here). The occupancy shown still comes from the REIT manager's web page.
