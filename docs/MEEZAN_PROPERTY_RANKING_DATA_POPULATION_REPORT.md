# Meezan Property: Ranking Data Population Report

Date: 5 November 2026. **Nothing here is invented, and no project was ranked.**

## Update: Rankings Activation stage, 5 November 2026

The latest figures are below. The rest of this report records the earlier run #18 and stays as written.

**Published run #26. 1,273 real projects, 0 ranked, 1,251 under research, 22 excluded.** Verified legal records are unchanged at 515 projects, because nothing here was approved automatically.

| Category | Projects | Legal verified | Legal awaiting a check | Other evidence awaiting | Asking or tax figures | Ranked |
|---|---:|---:|---:|---:|---:|---:|
| Housing Societies | 900 | 515 | 369 | 17 | 26 | 0 |
| Commercial Plazas | 25 | 0 | 11 | 0 | 0 | 0 |
| Apartment Buildings | 188 | 0 | 147 | 6 | 0 | 0 |
| Shopping Malls | 143 | 0 | 11 | 1 | 0 | 0 |
| Business Towers | 17 | 0 | 2 | 1 | 0 | 0 |

| What was added | Count | Status |
|---|---:|---|
| New projects | 242 | Real, each with a named source |
| SBCA building and scheme entries | 178 | Legal records wait for a staff check |
| CDA owner, layout plan, NOC and notice details | 69 projects | Facts. Developer filled only where blank |
| Zameen asking-price records | 21 | Submitted, reach no score |
| Copied source facts | On 1,065 projects | Labelled, reach no score |

No dummy, random or Faker data was used, and no record was deleted. Demo mode stays off.

**Backups, kept in `C:\xampp\backups\MeezanProperty-before-rankings-activation-2026-11-05\`:** a full dump before this stage and another before the SBCA import.

**Correction:** earlier reports implied that Zameen was closed to research. Its `/new-projects/` and `/index/` pages are allowed by `robots.txt`, and those were read by hand. See `MEEZAN_PROPERTY_REAL_WORLD_RESEARCH_PROGRESS.md`.

---

## Result of the calculation

Published run #18, methodology version 1, unchanged. This is after the owner's decision that all hand-read lists wait for staff.

| Result | Projects |
|---|---:|
| Ranked | **0** |
| Insufficient Verified Data | 1,009 |
| Excluded on official legal grounds | 22 |
| **Total real projects** | **1,031** |

**This is the correct result for the evidence on file, not a failure.** A rank needs verified evidence for 60% of a category's weight, and the most any project has today is 25%. Lowering that bar would have produced rankings based on legal status alone, which would mislead investors.

| Legal status in the run | Projects |
|---|---:|
| Verified Approved | 427 |
| Partially Approved | 64 |
| Approval Pending | 2 |
| Excluded (cancelled or on an official illegal list) | 22 |
| Unknown / Unverified | 516 |

| Data confidence | Projects |
|---|---:|
| Moderate Confidence | 215 |
| Low Confidence | 300 |
| Insufficient Evidence | 516 |
| High Confidence | 0 |

## Legal status by city

| City | Projects | Verified | Awaiting a staff check |
|---|---:|---:|---:|
| Lahore | 439 | 413 | 0 |
| Islamabad | 96 | 70 | 0 |
| Multan | 104 | 32 | 64 |
| Karachi | 86 | **0** | 40 |
| Faisalabad | 221 | **0** | 205 |
| Quetta | 48 | **0** | 45 |
| Peshawar | 37 | **0** | 8 |

Verified records come only from data read directly from an authority: LDA, CDA and MDA's tables. Four cities show none because every list for them was copied by hand. Until a person checks those lists, their projects show Unknown / Unverified, and profiles say the record is on the authority's list but not yet checked.

## What changed in the database

Before this stage there were 957 real projects and no real evidence.

| Item | Before | After | Status of what was added |
|---|---:|---:|---|
| Real projects | 957 | 1,031 | 61 Multan schemes, 13 others |
| Legal records | 839 | 900 | 364 awaiting staff: 61 new, 303 moved back from verified. None was deleted |
| Evidence records | 0 | 29 | All awaiting staff |
| Real market records | 0 | 40 | All awaiting staff; FBR tax values only |
| Projects showing stated facts | 0 | 824 | Copied from sources, in no score |
| Real sources | 19 | 31 | |
| Project aliases | 2 | 2 | One wrong alias from this import was removed |

Fields filled where a source states them: name, category, city, developer, status, description, website, location, area, plots, blocks and sanction date.

**Not filled, because no source gave them:** coordinates, launch dates, asking prices, transaction prices, rental income, rental yields and historical performance.

## The evidence records

### Occupancy (19, awaiting staff)
- **Dolmen Mall, Clifton:** 97.8%.
- **The Harbour Front:** 100%.
- **17 LDA-developed schemes:** LDA's "colonized" percentage. LDA doesn't define the term, so **staff should confirm it means occupancy** before verifying. Two schemes with a published 0% were left out, as that looked like a blank.

### Operational status (8, awaiting staff)
Dolmen Mall, The Harbour Front, Globe Residency, Signature Tower, Rahat Residency, Peace Apartments, Silk Gardens and Zameen Opal.

### Delivery status (2, awaiting staff)
Globe Residency and Signature Tower, both from the manager's own wording.

## What approval would change

Verifying all of this would produce **no rankings yet**:

- LDA schemes would reach 40% (legal 25 + occupancy 15).
- Dolmen Mall and The Harbour Front would reach only 15%, because no official legal record exists for them.

## Existing records preserved

| Item | Rows | Changed? |
|---|---:|---|
| Calculation #7 rankings | 3,522 | No |
| All ranking rows | 17,685 | No |
| Demo projects | 420 | No |
| Demo market observations | 7,187 | No |
| Demo market indicators | 3,524 | No |
| Users | 4 | No |
| Marketplace properties | 18 | No |
| Leads | 2 | No |
| Active AI agents | 16 | No |

**Backup:** `C:\xampp\backups\MeezanProperty-before-ranking-data-population-2026-11-05\`

## Queues for staff

| Queue | Items | Where |
|---|---:|---|
| Legal records, by list | 364 | Verification Centre: **Whole lists waiting for a check**. FDA private 188, MDA sanctioned 61, QDA 45, SBCA 40, FDA own schemes 19, PDA 8, MDA commercial 3 |
| Evidence records | 29 | Verification Centre |
| FBR valuations | 40 | Market Data Centre, observations |
| Ambiguous name matches | 36 | Exceptions |
| Official records that conflict | 5 | Exceptions |
| Unreadable official stages | 6 | Exceptions |

The five conflicts are Bilal Town, Canal View Housing Scheme, Grand Avenue, Nasheman-e-Bahria and Royal Villas Housing Scheme.
