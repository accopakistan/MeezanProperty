# Meezan Property: Why the Rankings Show Zero

Date: 5 November 2026. Figures are from published run #26 on the real MariaDB database.

## The answer

**The engine works, and no page is hiding results. No project has verified evidence for 60% of its category's scoring weight.** The most any project has is 25%, which is its legal record alone.

Most of the missing evidence isn't something public websites publish: audited occupancy, verified rents, site inspections, structural safety and footfall. Research can add projects, facts and legal records, but it can't produce those.

**Verified rankings stay at 0 until staff checks, site inspections, owner documents or licensed data arrive, or you approve a methodology change.** I did not lower the bar.

## What was ruled out

| Possible cause | Finding |
|---|---|
| Engine not running | It runs. Run #26 published 1,273 assessments |
| Page filter hiding results | No. Pages read the published run, and the run itself has 0 ranked |
| Sources not linked to projects | Linked. Every project has a discovery source, and the validator reports 0 problems |
| Demo mode or demo records | Off. Demo projects aren't assessed |
| Evidence stored under the wrong factor | No. Each factor has its accepted source types, and the mapping is correct |
| Official records not recognised | Recognised. LDA, CDA and MDA records read from the authority's own data count as verified |
| Hand-read lists wrongly excluded | Not wrongly. **You decided** on 5 November that hand-read lists wait for a staff check. That decision is why 540 projects have a legal record that doesn't count yet |

## What "60%" means

It is the share of a category's factor weight backed by verified evidence, not 60% of a project's fields. No factor is irrelevant to its category, so none should be dropped to reach 60%.

| Category | Factors and weights |
|---|---|
| Housing Societies | Legal 25, development progress 20, occupancy 15, market performance 10, market demand 10, location 10, utilities 5, developer record 5 |
| Commercial Plazas | Legal 20, rental income 20, occupancy 15, footfall 15, location 10, tenant quality 10, maintenance 5, market performance 5 |
| Apartment Buildings | Legal 20, occupancy 15, rental income 15, structural safety 15, developer 10, location 10, maintenance 5, price history 5, market demand 5 |
| Shopping Malls | Legal 20, operations 15, occupancy 15, rental income 15, footfall 10, tenant mix 10, location 5, safety 5, financial stability 5 |
| Business Towers | Legal 20, occupancy 15, rental income 15, tenant quality 15, location 10, structural safety 10, developer 5, management 5, market demand 5 |

The checks run in this order:

1. Excluded on a legal record.
2. Legal status not verified: insufficient.
3. Coverage under 60%: insufficient.
4. Confidence under 35: insufficient.
5. Legal status not eligible: not eligible.
6. Otherwise ranked.

## Project-level audit by category

| Category | Genuine projects | Location known\* | Facts on file | Developer | Official discovery source | Legal verified | Legal awaiting a check | Other evidence verified | Other evidence awaiting | Asking or tax figures | Rank eligible | Excluded | Highest coverage |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Housing Societies | 900 | 269 | 828 | 116 | 889 | 515 | 369 | 0 | 17 | 26 | **0** | 22 | 25% |
| Commercial Plazas | 25 | 0 | 23 | 8 | 11 | 0 | 11 | 0 | 0 | 0 | **0** | 0 | 0% |
| Apartment Buildings | 188 | 0 | 184 | 154 | 144 | 0 | 147 | 0 | 6 | 0 | **0** | 0 | 0% |
| Shopping Malls | 143 | 1 | 22 | 11 | 11 | 0 | 11 | 0 | 1 | 0 | **0** | 0 | 0% |
| Business Towers | 17 | 0 | 8 | 6 | 2 | 0 | 2 | 0 | 1 | 0 | **0** | 0 | 0% |
| **All** | **1,273** | **270** | **1,065** | **295** | **1,057** | **515** | **540** | **0** | **25** | **26** | **0** | **22** | **25%** |

\* Linked to a location directory entry or coordinates. Many more projects have a written address in their facts.

Every project is real and came from a named source. No category has market data that counts: all 61 price records are asking prices or tax values, which reach no score by rule.

### By city

| City | Projects | Legal verified | Legal awaiting a check | Excluded | Highest coverage |
|---|---:|---:|---:|---:|---:|
| Lahore | 463 | 413 | 0 | 5 | 25% |
| Karachi | 273 | 0 | 218 | 0 | 0% |
| Islamabad | 110 | 70 | 0 | 17 | 25% |
| Multan | 113 | 32 | 64 | 0 | 25% |
| Faisalabad | 222 | 0 | 205 | 0 | 0% |
| Quetta | 49 | 0 | 45 | 0 | 0% |
| Peshawar | 43 | 0 | 8 | 0 | 0% |

## Why each project is excluded or unranked

| Reason | Projects |
|---|---:|
| Official record shows cancellation or an illegal listing (excluded) | 22 |
| Legal record on file but awaiting a staff check, so legal status isn't verified | 540 |
| No legal record found | 218 |
| Legal record verified, but coverage stops at 25% | 493 |

"No legal record found" never means illegal. Those projects are shown as Unknown / Unverified.

## What evidence would move them

1. **Staff check of the 540 waiting legal records.** This gives 540 projects a verified legal status and up to 20–25% coverage. It can't produce a rank by itself.
2. **Occupancy.** LDA's "colonized" figure covers 17 schemes. If staff confirm that it means occupancy, those schemes reach 40%, which is still under 60%.
3. **Site inspections or owner documents.** Inspections can supply development progress, occupancy, utilities, location and maintenance. For a housing society, development progress and either occupancy or market data are what would pass 60%.
4. **Licensed or verified market data.** Verified sales and rents are what the market factors need.

## Official data checked for hidden evidence

| Data | Finding |
|---|---|
| LDA `approval_stage` | Approval stages only. It can't honestly be read as development progress |
| LDA `map_center` | The same value for every scheme, so it isn't a position |
| LDA `mortgaged_plots_text` | Security held for development, not progress |
| LDA KML and map links | Exist on some regular schemes, not explored yet. They could give real coordinates |
| CDA scheme pages | Owner, layout plan status and date, NOC status and date. Now on file, and all consistent with the legal statuses already recorded |
| SBCA public sale buildings | **Previously unread**, and the only official source found for apartment, mall, plaza and tower records. Now imported, awaiting a staff check |

## What this changed

The evidence threshold, weights and rules weren't changed. See `MEEZAN_PROPERTY_RANKING_CALCULATION_REPORT.md`.
