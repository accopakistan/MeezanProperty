# Meezan Property: Ranking Methodology Validation

Date: 5 November 2026. **The methodology was not changed.** Version 1 is still active, and no threshold was lowered to produce rankings.

## Weights compared with the brief

Every factor in `config/investment_index.php` matches section 9 of the brief, and each category adds up to 100. A test now locks these values.

| Housing Societies | % | Commercial Plazas | % | Apartment Buildings | % | Shopping Malls | % | Business Towers | % |
|---|---:|---|---:|---|---:|---|---:|---|---:|
| Legal status | 25 | Legal status | 20 | Legal status | 20 | Legal status | 20 | Legal status | 20 |
| Development progress | 20 | Verified rental | 20 | Occupancy | 15 | Operational performance | 15 | Office occupancy | 15 |
| Occupancy | 15 | Shop occupancy | 15 | Rental | 15 | Occupancy | 15 | Rental | 15 |
| Historical performance | 10 | Footfall and demand | 15 | Structural safety | 15 | Verified rental | 15 | Corporate tenants | 15 |
| Demand and liquidity | 10 | Location | 10 | Developer reputation | 10 | Footfall | 10 | Location | 10 |
| Location | 10 | Tenant quality | 10 | Location | 10 | Tenant mix | 10 | Structural safety | 10 |
| Utilities | 5 | Maintenance | 5 | Maintenance | 5 | Location | 5 | Developer reputation | 5 |
| Developer record | 5 | Market performance | 5 | Historical price | 5 | Safety | 5 | Management | 5 |
| | | | | Demand | 5 | Financial stability | 5 | Demand | 5 |

**Conflict with existing methodology: none.**

## Rules checked

| Rule | Status |
|---|---|
| Calculations are deterministic Laravel services | Yes: `IndexEngine`, `ProjectAssessor`, `FactorScorer` |
| Gemini never produces a score | Yes. It only words reports, and any number not in the data is rejected |
| Investment and confidence scores are separate | Yes |
| Asking prices never count as history | Yes. Only transaction and valuation count (tested) |
| Unknown legal status is never "approved" | Yes (tested) |
| Serious legal records exclude a project | Yes: 22 excluded |
| Paid placement has no effect | Yes: the engine doesn't read those fields |

## Confidence thresholds

These are configurable in `config/investment_index.php`. The public labels now match the brief.

| Score | Label |
|---:|---|
| 75 and over | High Confidence |
| 55 to 74 | Moderate Confidence |
| 35 to 54 | Low Confidence |
| Under 35 | Insufficient Evidence |

Confidence is built from coverage (30), recency (25), source authority (25), independence (10) and corroboration (10).

**One weakness to know about.** A project with one fresh official record scores about 61, **Moderate Confidence**, even though that record covers only a quarter of the weight. It reflects that the evidence on file is strong, not that there is much of it. It can't produce a ranking, because the 60% coverage rule still applies, and the page shows the coverage next to it. Changing this would be a methodology change, so it is left for you to decide.

## Decisions for the owner

1. **FBR tax valuations.** The methodology counts "verified valuations" as price history. FBR values are revised by notification, and can rise or fall for tax policy reasons. I added them as a separate basis, `fbr_tax`, that reaches no score. If you'd rather count them, that is a methodology change.
2. **Official lists that auto-verify (partly decided).** On 5 November you chose to move all hand-read lists to staff review: 303 records across 301 projects, none of them adverse, so no exclusion was lifted. **Still your call:** records read directly from an authority's own data (LDA, CDA and MDA's tables) keep verifying automatically, as decided in Phase 2. They are exact copies, so I'd leave them, but a strict reading of "automated collection must not silently mark projects legally approved" would include them. Moving them would leave almost no verified legal status anywhere until staff check them.
3. **LDA "colonized".** LDA publishes this percentage for its own schemes, but doesn't define it. It looks like occupancy, so it is imported as occupancy awaiting review. Staff should confirm the meaning before verifying it.

## What real ranking needs

Official lists can establish legal status for 20% to 25% of the weight. Reaching 60% takes evidence from outside public websites.

| Category | Fastest realistic route to 60% |
|---|---|
| Housing societies | Legal (25) + development progress (20) + occupancy (15), from inspections or authority progress reports |
| Shopping malls | Legal (20) + occupancy (15) + rental (15) + operational performance (15). Dolmen Mall Clifton is closest, but has no official legal record |
| Business towers | Legal (20) + occupancy (15) + rental (15) + corporate tenants (15). The Harbour Front is closest, on the same condition |
| Apartment buildings | Legal (20) + occupancy (15) + rental (15) + structural safety (15). Most apartment projects on file are still being built |
| Commercial plazas | Legal (20) + rental (20) + occupancy (15) + one more factor. Very little public data exists |

The Overall Index also needs at least 3 ranked projects in a category.
