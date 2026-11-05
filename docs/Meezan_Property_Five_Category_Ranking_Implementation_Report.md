# Meezan Property: Five-Category Ranking Implementation Report

Date: 4 November 2026. **The five-category engine was already built in Investment Index Phases 1 to 4.** This task verified it and applied the same rules to the restored dashboards. Full reference: `Meezan_Property_Investment_Index_Documentation.md`.

## 1. Status

| Requirement | Status | Where |
|---|---|---|
| Five categories only | **Complete** | `config/investment_index.php` |
| Exact weights, each totalling 100% | **Complete, checked in this task** | Active methodology v1, compared with the brief table by table |
| Versioned weights; old calculations kept | **Complete** | `investment_index_methodologies` (draft, activate, recalculate). MPIS history stays in its own tables |
| Individual plots, houses, shops, apartments and listings stay out | **Complete** | The Index ranks `Project` records only; restored dashboards offer the five categories only |
| Marketplace records untouched | **Complete** | No listing was changed or deleted |
| Source, dates, status, reference and confidence on records | **Complete** | `HasEvidenceProvenance`, `investment_evidence`, `investment_legal_records` |
| Unverified legal status never shown as approved | **Complete** | `LegalStatus` |
| Serious legal issues excluded | **Complete, now also on the restored dashboards** | `LegalStatus`, `RankingBrowser::publicItems()` |
| "Insufficient Verified Data" | **Complete** | Index and restored dashboards |
| No invented figures; asking prices aren't transactions | **Complete** | `FactorScorer`, `UNTRUSTED_SOURCE_TYPES` |
| Score, rank and change history with reasons | **Complete** | `investment_index_scores`, `investment_index_changes` |
| No paid or sponsored positions | **Complete** | Subscriptions and featured flags play no part in scoring |
| Deterministic calculation | **Complete** | `IndexEngine`. Gemini never produces scores |
| Authorized collection only | **Built, not active** | LDA sources were added inactive, pending your access review |
| Real rankings published | **Pending real data** | 0 real projects today |
| Gemini explanations of Index results, live | **Pending** | See the Gemini report |
| Gemini document reading and classification | **Not built** | Owner documents are checked by staff |

## 2. Categories and data model

| Category | `Project` categories | Not ranked on their own |
|---|---|---|
| Housing Societies | `residential_society` | Individual residential plots and houses |
| Commercial Plazas | `commercial_plaza` | Individual shops |
| Apartment Buildings | `apartment_complex` | Individual apartments |
| Shopping Malls | `shopping_mall` | Individual shops |
| Business Towers | `business_tower`, `office_building` | Individual offices |

**Out of scope:** `mixed_use` and `commercial_land` projects aren't ranked by the Index and don't appear on the public dashboards. Their history stays in the database.

## 3. Weights (checked against the brief)

| Parameter | Societies | Plazas | Apartments | Malls | Towers |
|---|---:|---:|---:|---:|---:|
| Legal approval | 25 | 20 | 20 | 20 | 20 |
| Development progress | 20 | | | | |
| Occupancy | 15 | 15 | 15 | 15 | 15 |
| Rental income | | 20 | 15 | 15 | 15 |
| Historical market performance | 10 | 5 | 5 | | |
| Market demand and liquidity | 10 | | 5 | | 5 |
| Footfall and demand | | 15 | | 10 | |
| Location | 10 | 10 | 10 | 5 | 10 |
| Utilities | 5 | | | | |
| Developer track record or reputation | 5 | | 10 | | 5 |
| Tenant quality or mix | | 10 | | 10 | 15 |
| Maintenance or management | | 5 | 5 | | 5 |
| Construction and structural safety | | | 15 | 5 | 10 |
| Operational performance | | | | 15 | |
| Financial stability | | | | 5 | |
| **Total** | **100** | **100** | **100** | **100** | **100** |

## 4. Two engines, one rule

| | Meezan Investment Index | Earlier MPIS engine |
|---|---|---|
| Ranks | Developments in five categories, on legal and verified evidence | Areas and projects, on verified market records |
| Publishes | Automatically, after recalculation | When an administrator publishes |
| Public pages | Leaderboards, Overall Index, profiles | City, group, category and compare dashboards |
| Data today | No real projects | Only demo calculations, kept as history |

No third engine was added. Where both have results, the dashboards show the Index results and link to its leaderboard. A project the Index excludes on legal grounds is never shown with an MPIS score.

**A known gap:** when both engines have real data, a project can have two different scores, one on each page. Decide which is the headline number before real MPIS data is published.

## 5. Next steps, in order

1. Review and activate the LDA sources.
2. Add real developments and verified records.
3. Check that the first real rankings look right, then run a live Investment Analyst test.
4. Add Gemini document reading only if staff checks become a bottleneck, and keep a person approving legal status.
