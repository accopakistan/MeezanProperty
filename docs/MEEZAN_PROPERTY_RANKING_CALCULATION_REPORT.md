# Meezan Property: Ranking Calculation Report

Date: 5 November 2026. Latest published run: #26. Methodology version 1, unchanged. Calculation #7 of the older market engine is untouched.

## Result

| State | Projects |
|---|---:|
| State 1: Verified and rank eligible | **0** |
| State 2: Provisional Research Ranking (hidden from visitors) | 17 |
| State 3: Research in progress | 1,251, including the 17 above |
| State 4: Excluded on official legal grounds | 22 |
| **Total** | **1,273** |

**There is no top-ranked project in any category, because none has earned a rank.**

| Category | Ranked | Provisional | Top ranked |
|---|---:|---:|---|
| Housing Societies | 0 | 17 | None |
| Commercial Plazas | 0 | 0 | None |
| Apartment Buildings | 0 | 0 | None |
| Shopping Malls | 0 | 0 | None |
| Business Towers | 0 | 0 | None |

Commercial Plazas, Apartment Buildings, Shopping Malls and Business Towers have no provisional list because they have no second scored factor. Their legal records alone give at most 20%.

## What did not change

- Weights, the 60% coverage rule, the 35 confidence minimum, the coverage penalty and the legal rules.
- Verified scores and ranks. `score`, `category_rank`, `status` and `coverage` are calculated as before, and the provisional step runs afterwards without changing them.
- No randomness and no AI in any score. The same evidence gives the same figures and the same result hash.

## How missing evidence affects a score

A missing value is never positive and never filled in. It lowers coverage, and:

`score = weighted score of the factors with evidence × (1 − 0.2 × (1 − coverage))`

Missing evidence keeps a project out of the ranking at under 60% coverage, so the score isn't shown at all.

## The Provisional Research Ranking

The brief allows this tier only if the methodology and an owner-approved policy permit it. So it is calculated and stored, **and visitors don't see it until you switch it on**.

| Rule | Value |
|---|---|
| Evidence counted | Verified and awaiting a check. Rejected records never count |
| Legal status | Approved or partially approved on an authority's record. Excluded, adverse and unknown projects get no figure |
| Minimum scored factors | 2 |
| Minimum coverage | 35% of the weight |
| Scoring | The same assessor and weights |
| Order | Score, then coverage, then project id |
| Stored | Separately, in `provisional_*` columns. It can't be read as a verified rank |

### The current list

| Position | Project | City | Score | Evidence | Confidence | Unchecked |
|---:|---|---|---:|---:|---:|---:|
| P1 | Kashmir Egerton Road | Lahore | 88.0 | 40% | 65 | 1 |
| P2 | Allama Iqbal Town | Lahore | 87.7 | 40% | 65 | 1 |
| P3 | Banobazar | Lahore | 87.7 | 40% | 65 | 1 |
| P4 | Old Rifels Society | Lahore | 87.7 | 40% | 65 | 1 |
| P5 | Shad Bagh | Lahore | 87.3 | 40% | 65 | 1 |
| P6 | Shah Jamal | Lahore | 87.3 | 40% | 65 | 1 |
| P7 | Shadman Colony | Lahore | 86.7 | 40% | 65 | 1 |
| P8 | Aarya Nagar | Lahore | 84.7 | 40% | 65 | 1 |
| P9 | Gulshan-E-Ravi | Lahore | 84.7 | 40% | 65 | 1 |
| P10 | New Muslim Town | Lahore | 84.7 | 40% | 65 | 1 |
| P11 | Timber Market | Lahore | 84.7 | 40% | 65 | 1 |
| P12 | Gujjarpura | Lahore | 81.4 | 40% | 65 | 1 |
| P13 | Mustafa Town | Lahore | 81.4 | 40% | 65 | 1 |
| P14 | Sabzazar | Lahore | 78.1 | 40% | 65 | 1 |
| P15 | Jubilee Town | Lahore | 76.5 | 40% | 65 | 1 |
| P16 | Mohlanwal | Lahore | 71.5 | 40% | 65 | 1 |
| P17 | Lda Avenue-I | Lahore | 68.2 | 40% | 65 | 1 |

### Why I recommend keeping it hidden for now

- **These scores rest on two things:** LDA's approval and LDA's "colonized" percentage. LDA doesn't define "colonized", and no one has confirmed that it means occupancy.
- **The list compares old LDA-developed Lahore neighbourhoods** with each other. It says nothing about the projects people most often ask about.
- **88 looks like a strong result.** It only reflects high scores on two factors, with 60% of the weight unknown.

Have staff check the 17 "colonized" records first, then decide.

## Price and yield handling

| Kind of figure | On file | Used in a score |
|---|---:|---|
| Verified transaction prices | 0 | Would be |
| Asking prices (Zameen Index) | 21, status submitted | No |
| Tax valuations (FBR) | 40, status submitted | No |
| Verified rent | 0 | Would be |
| Rental advertisements | 0 | No |
| Rental yield | None calculated | Asking-based yield is not calculated, because no approved rule allows it |
| Historical performance | Zameen's "6 months, 1 year, 2 years earlier" figures are facts labelled asking-based | No |

## Excluded projects

These 22 have an official record showing cancellation or an illegal listing. They get no score or provisional figure.

**Islamabad:** AGOCHS Phase-I, AGOCHS Phase-II, Al-Hamra Hills Agro Farming Scheme, Federation of Employees, Gulshan-e-Rabia, Jinnah Garden Phase-I, Khayaban-e-Kashmir Phase-II, Morgah City, National Assembly Employees CHS, National Police Foundation, New Islamabad Garden, Pakistan Medical CHS, Pakistan Navy Farms Simly Dam Road, Parliamentarians Enclave, Roshan Pakistan Corporation Housing Scheme, Services Co-operative Housing Society, Soan Garden Housing Scheme.

**Lahore:** Bilal Town, Grand Avenue, Hassan Town Housing Scheme, Nasheman-e-Bahria, Royal Villas Housing Scheme.

CDA's scheme pages were cross-checked for this report: all 69 pages read agree with the legal status on file.

## Database changes

- Migration `2026_11_05_000059_add_research_state_to_index_scores` adds six nullable or defaulted columns and one index to `investment_index_scores`. Nothing was dropped or rewritten.
- New index runs were published as usual. Earlier runs and history are kept.
