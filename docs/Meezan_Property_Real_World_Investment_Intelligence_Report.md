# Meezan Property: Real-World Investment Intelligence Report

Date: 4 November 2026. Local only: nothing was committed, pushed or deployed, `.env` was not changed, and nothing existing was deleted.

**Backup before this work:** `C:\xampp\backups\MeezanProperty-before-real-world-data-2026-11-04\` (database dump, 76 of 76 tables, and row counts).

## Summary

| Measure | Today |
|---|---|
| Genuine projects on file, across seven cities | **957** |
| With a verified legal status from an official record | **816** |
| Ranked | **0** |
| Insufficient Verified Data | **935** |
| Excluded on legal grounds | **22** |
| With verified occupancy, rent, price history or operations | **0** |

**The project database and legal verification are real. Rankings are not possible yet.** A project needs verified evidence for 60% of its category's weight, and legal status covers only 20–25%. Every leaderboard therefore correctly says "Insufficient Verified Data". Occupancy, rental income and completed transaction data don't exist in any public source I could reach. They need owner documents, inspections or licensed data.

**Status labels used below:**

| Label | Meaning |
|---|---|
| Implemented | The code exists and works |
| Tested with real data | Run against real collected records on MariaDB |
| Tested with sample data | Test fixtures only |
| Waiting for source access | Needs permission, a fixed site or a licence |
| Waiting for manual verification | Needs a person |
| Not yet operational | Not working yet |

## 1. Existing functionality inspected

Investment Index phases 1–4, the MPIS engine (Phase 14), the restored ranking pages, the Phase 13 map, the Gemini setup and the 16 agents. **Most of the brief already existed:** the five scoring engines with your exact weights, the legal gate, the Data Confidence Score, change history, the Overall Index and the collectors. I reused them instead of rebuilding them.

## 2. Existing functionality preserved

| Check | Result |
|---|---|
| Marketplace tables (users, dealers, properties, leads, subscriptions, payments and others) | Row counts unchanged |
| MPIS history | 17,685 ranking rows unchanged; calculation #7 still has 3,522 |
| Demo data | 420 projects kept, flagged, never shown publicly |
| Gemini | Unchanged, still the only provider |
| Agents | All 16 active; five gained one read-only tool, each as a new version |

## 3. Existing real-world data discovered

Before this work there were no real projects: all 420 were demo data. The location directory holds 1,238 real areas, of which 207 now link to a project and give 28 projects an approximate map position.

## 4. Genuine projects by city

| City | Projects | Legal verified | Excluded | Main official source |
|---|---:|---:|---:|---|
| Lahore | 434 | 413 | 5 | LDA |
| Faisalabad | 221 | 205 | 0 | FDA |
| Islamabad | 95 | 70 | 17 | CDA |
| Karachi | 79 | 40 | 0 | SBCA |
| Quetta | 48 | 45 | 0 | QDA |
| Multan | 43 | 35 | 0 | MDA |
| Peshawar | 37 | 8 | 0 | PDA |

These are the records collected so far, **not all developments in these cities**.

## 5. Projects by category

| Category | Projects | Legal verified | Main source |
|---|---:|---:|---|
| Housing Societies | 818 | 813 | Official lists |
| Shopping Malls | 124 | 0 | Wikipedia (secondary) |
| Business Towers | 6 | 0 | Wikipedia (secondary) |
| Commercial Plazas | 5 | 3 | MDA, Wikipedia |
| Apartment Buildings | 4 | 0 | Wikipedia (secondary) |

**Coverage is thin outside housing societies.** Authorities publish scheme lists, not lists of malls, plazas or towers. Those categories mostly hold a name and a secondary source, with legal status "Unknown / Unverified".

## 6. Source registry

See `MEEZAN_PROPERTY_REAL_WORLD_DATA_SOURCE_REGISTER.md`. **8 sources are connected, 7 official sources were read once, and 4 are secondary references.**

## 7. Legal verification coverage

| Legal status | Projects |
|---|---:|
| Verified Approved | 728 |
| Partially Approved (for example, a CDA layout plan without a NOC) | 64 |
| Approval Pending | 2 |
| Excluded (Cancelled, Verified Not Approved or conflicting records) | 22 |
| Unknown / Unverified | 141 |

- **What "Verified Approved" means:** the responsible authority's own published list says so, on the date read. It is not a site inspection or a title check.
- **SBCA is narrower:** a NOC for sale and advertisement, not full approval.
- **Illegal lists:** 551 official entries (LDA 382, CDA 166, PDA 3) stay in the registry and **aren't published as projects**. A project is affected only when it matches one.

**Five projects appear on both an approved and an illegal list:** Bilal Town, Canal View Housing Scheme, Grand Avenue, Nasheman-e-Bahria and Royal Villas Housing Scheme. They stay excluded with an open staff exception. **These may be different schemes sharing a name, so please have someone check them first.**

## 8. Historical data coverage

**None.** No real transaction, valuation or asking-price history was collected. Historical charts stay empty, and earlier MPIS rankings are all demo data.

## 9. Rental and occupancy coverage

**None.** No public source gave traceable figures. Nothing was estimated.

## 10. Data confidence methodology

Already implemented and shown beside every score: coverage 30%, recency 25%, source authority 25%, independent publishers 10%, corroboration 10%. New labels on each profile: **Verified, Partially Verified, Unverified, Under Review, Disputed, Outdated**. A rejected record is marked Rejected and never counts.

## 11. Five category scoring engines

Implemented earlier; the weights match your tables exactly (checked per category; each totals 100%). They are versioned and editable through Admin → Methodology.

## 12. MPIS integration

Untouched and kept separate. Its dashboards show only verified snapshots, and a legally excluded project never shows an MPIS score. There is no real MPIS data yet. The Phase 14 methodology was not replaced.

**A decision for later:** once both have real data, a project could show two different scores. Decide which is the headline number before publishing real MPIS data.

## 13. Overall Investment Index

Implemented earlier, with normalized scores, confidence weighting and the legal gate. It needs at least 3 ranked projects per category, so it is empty today.

## 14. Gemini AI integration

**Unchanged. Live AI wording could not be tested in this work:** the free daily quota is paused until 5 Nov 2026, 13:00 Pakistan time. Everything below ran in rules mode, with provider requests blocked. Gemini can't produce scores or facts: the engine makes no AI calls, and any reply with a figure not in the data is discarded.

## 15. Investment Analyst

| Ability | Status |
|---|---|
| Explain one named project: status, legal records with links, evidence, risks, ranking changes, what's missing | Implemented, tested with real data, rules mode |
| Report leaderboards | Tested with real data, rules mode |
| Same answers worded by Gemini | **Not yet tested live** |
| Compare two projects | **Not extended:** still reads MPIS, so it finds nothing today |
| Website chat routing to it | Narrow: "Which malls rank best in the investment index?" asks for a city instead |

**Other agents, using the new read-only coverage tool:**
- **Global Data Manager:** coverage report.
- **Technical Operations:** collection status.
- **Property Management:** possible duplicate projects.
- **AI CEO:** has the tool, but no workflow uses it yet.
- **Competitor Research:** still has nothing to work from.

## 16. Admin Data Centre

**Admin → Investment Index.** New screens are marked ★.

| Screen | What it does | Status |
|---|---|---|
| ★ Coverage | Counts by city and category, source registry, items waiting, possible duplicates | Tested on real data |
| ★ Verification Centre | Add a legal record; import legal or evidence CSV; verify or reject | Tested with sample data |
| Official sources, registry, exceptions, owner documents, methodology | As before | Tested on real data |
| Projects, Market Data Centre | As before; price records stay separate by type | Unchanged |

**Imports:** invalid rows are listed with reasons, duplicates are skipped, nothing verified is overwritten, and imported rows start unverified. Asking prices can't be imported as evidence. **Not built:** a document-reading import.

## 17. Automated collection status

| Status | Sources |
|---|---|
| **Connected and tested with real data** | 8: LDA (3), CDA (3), MDA (2). A second import of the hand-read sources changes nothing |
| Runs daily at 03:45 | **Only if a queue worker or scheduler is running** |
| **Not automatic** | 7 official sources read once, and 4 references. They will go out of date until read again |

**Access decision:** Phase 2 left the access review to the owner. I recorded it for these 8 lists after checking robots.txt, and **the owner confirmed on 4 November 2026 that they stay on**. Each site's terms were not separately reviewed. Any source can be switched off in Official sources.

## 18. Ranking pages

| Page | Address | Status |
|---|---|---|
| Home: city cards with real counts, search | `/investment-rankings` | Tested on real data |
| City leaderboard | `/investment-rankings/lahore/housing-societies` | Tested on real data |
| Category leaderboard, filters (search, legal, status, development, confidence, risk), map | `/investment-rankings/housing-societies` | Tested on real data |
| Compare, one category at a time | `/investment-rankings/housing-societies/compare` | Tested on real data |
| Project profile: data status, origin, aliases, missing information, sources | `/investment-rankings/projects/{project}` | Tested on real data |
| City and category dashboards | `/investment-rankings/lahore`, `/lahore/commercial/shopping-malls` | Tested on real data |

**Differences from the brief:**
- **Search engines:** pages with no ranked project stay noindex, so none are indexable today. Pages with real lists could reasonably be opened up; that is your call.
- **Ranking tables:** they use one set of columns, not a different set per category.
- **Project cards and images:** there are no project images, since none are licensed.
- **Colours:** kept the Brand Style Guide palette (`#4B2E83`, `#D4AF37`) you chose earlier, not the hex values in this brief.

## 19. Database changes

One additive migration, `000054`: four tracking columns on `projects`, a `project_aliases` table, five columns on `market_sources`. No existing column changed.

| Table | Before → after |
|---|---|
| `projects` | 420 → 1,377 (957 real) |
| `investment_registry_entries` | 0 → 1,380 |
| `investment_legal_records` | 0 → 839 (13 superseded after a fix below) |
| `market_sources` | 3 → 22 |
| `investment_exceptions` | 0 → 105 (47 open) |

**Problems found and fixed during this work:**
- **Migration:** its first run stopped at the agent history step because of my error. I made it safely repeatable and reran it.
- **False merges:** matching treated "Block-B" and "Block-C", or "Sector A & B" and "Sector C & D", as one project. I fixed the matcher, unlinked the 13 affected entries and marked their 13 legal records as superseded. They are kept as history, not deleted.

## 20. Tests and results

| Kind | Result |
|---|---|
| Automated, full suite | **828 passed, 5,066 assertions, 0 failed** (one WhatsApp test failed once in an earlier full run, then passed in every rerun; cause not identified) |
| New real-data tests | 19 tests with sample data |
| Style, security audits | Pint passed; `composer audit` and `npm audit` clean |
| Real pages, MariaDB | 17 addresses load under 0.4 s, with no demo data on any page |
| Admin, MariaDB (rolled back) | 12 pages open; slowest about 1 s |
| Agents, MariaDB (rolled back) | Coverage, collection, duplicates and project questions answered with no failed tools |
| Browser | Desktop checked; phone width has no sideways scrolling; no console errors |

**Not tested:**
- Live Gemini answers.
- Live WhatsApp.
- A real project reaching "Ranked", which has only been tested with sample data.
- Admin screens in a real browser, because the admin password is yours. They were checked through the application instead.

## 21. Data still unavailable

Occupancy, rental income, transactions, valuations, footfall, tenant quality, maintenance, structural safety, financial stability, developer track records, exact coordinates and project images.

## 22. Manual verification still required

1. The 5 projects on both approved and illegal lists.
2. 36 uncertain name matches against illegal lists.
3. 6 LDA entries whose approval stage can't be read.
4. 22 possible duplicate projects.
5. SBCA projects: the "housing" category is inferred from names.
6. Wikipedia entries: confirm they exist and belong in their category.

## 23. Source-access limitations

See the Source Register. In short: Multan's lists are scanned images, FDA's certificate chain and SBCA's robots.txt block automation, and LUBCA and the PDA downloads page disallow bots.

## 24. Remaining tasks

1. **Get evidence that can rank projects:** owner documents, site inspections or licensed data. This is what unlocks rankings.
2. **Check the items in §22.**
3. **Keep a queue worker or scheduler running** so collection stays current.
4. **Live Gemini test** after 5 Nov, 13:00 Pakistan time.
5. **Ask the authorities for access:** MDA for readable lists, FDA to fix its certificate chain, SBCA to fix its robots.txt, and LUBCA for permission.
6. **Build next:** per-category table columns, Analyst comparison on Index data, better chat routing, and fuller coverage of malls, plazas, towers and apartments.
