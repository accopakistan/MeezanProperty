# Meezan Property: Ranking Data Recovery Audit

Date: 5 November 2026. Done before any change. Backup first: `C:\xampp\backups\MeezanProperty-before-ranking-data-population-2026-11-05\` (full database dump, 190 MB, and a copy of `.env`).

## Why the ranking pages look empty

**Nothing is broken. The data the pages used to show was demo data, and it is now hidden on purpose.**

| Question from the brief | Finding |
|---|---|
| Database records missing? | No. Everything is still there |
| Not linked to the current categories? | No. All real projects are in the five categories |
| Filtered out? | Yes, correctly: every older ranking row is demo |
| Marked inactive? | No |
| Hidden by demo-mode settings? | Yes, correctly. `INVESTMENT_DEMO_MODE` is off, and `demoVisible()` needs demo mode on and a signed-in admin |
| Calculations not run? | No. The Investment Index has published runs |
| Routes pointing to the wrong source? | No. Restored in the recovery stage |
| Relationships or queries broken? | No |

## What is in the database

| Table | Rows | Real or demo |
|---|---:|---|
| `investment_rankings` | 17,685 | **All demo.** Calculation #7, the published one, has 3,522 rows, and its `includes_demo` is true |
| `investment_calculations` | 6 | All demo runs, one of them failed |
| `market_observations` | 7,187 | **All demo** |
| `market_indicators` | 3,524 | **All demo** |
| `projects` | 1,377 | 420 demo, 957 real |
| `investment_legal_records` | 839 | Real, from official lists |
| `investment_registry_entries` | 1,380 | Real, from official lists |
| `investment_evidence` | **0** | Nothing but legal records existed for real projects |
| `investment_index_scores`, latest published run | 957 | 0 ranked, 935 insufficient, 22 excluded |

## Why no real project was ranked

The methodology needs verified evidence for at least 60% of a category's weight. Legal status is 20% to 25%. With nothing else on file, no project can pass, and that is the correct result.

Most other factors need evidence that public websites rarely publish: occupancy, rent rolls, safety certificates, tenant quality and inspections.

## Methodology

The weights in `config/investment_index.php` match section 9 of the brief for all five categories, factor by factor, so there is no conflict and nothing was changed. See `MEEZAN_PROPERTY_RANKING_METHODOLOGY_VALIDATION.md`.

## What already existed

| Brief asks for | Already there |
|---|---|
| Source registry | `market_sources` with authority level, access method and notes |
| Evidence attribution | `investment_evidence` and `investment_legal_records`, with source, address, dates and verification status |
| Project aliases | `project_aliases` |
| Asking, transaction and rent kept apart | `market_observations.kind` and `price_basis` |
| CSV import and review | Market Data Centre, with draft, submitted, reviewed and verified steps |
| Document evidence | Owner document submissions |
| Scheduled refresh and collection logs | `investment_collection_runs`, exceptions queue |
| Separate investment and confidence scores | `FactorScorer`, `DataConfidence` |
| Maps | Phase 13 maps, showing only projects with coordinates |
| Gemini agents | Investment Analyst and Global Property Data Manager |

## Gaps found

1. No real evidence beyond legal records.
2. No real market records at all.
3. Multan's main list of private schemes was unread, because it is a scan.
4. Very few real commercial plazas (5), apartment buildings (4) and business towers (6).
5. No way to show unchecked findings honestly on a public page.
6. No validator for the checks in section 19 of the brief.
7. Confidence labels didn't match the brief.
8. Some public wording didn't say "Research in Progress", or explain the difference between unranked and low-scoring.
9. **Name matcher bug:** "V2" and "V3" counted as the same project. Found during this work and fixed.
10. **False label:** manager reports showed as "Owner-submitted document (staff-verified)" before anyone had verified them. Found during this work and fixed.

## Protected and unchanged

Calculation #7 and its 3,522 rows, all 17,685 ranking rows, the 420 demo projects, all demo market records, users, properties and leads. Demo mode stays off.
