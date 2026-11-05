# Meezan Property: Ranking Implementation Report

Date: 5 November 2026. Local only: nothing is committed or deployed. The existing system was extended, not rebuilt.

## What was built

| Area | Change |
|---|---|
| Research import | `ResearchImport` and `meezan:investment-research-import`. Safe to re-run; verifies nothing |
| Data files | `database/data/investment/real_world_evidence.php`, `official_valuations.php`, and the MDA list in `real_world_sources.php` |
| Scan review | Lists marked `review` create legal records that wait for staff, rather than verifying automatically |
| Validator | `DataValidator`, `meezan:investment-validate`, and the Admin → Investment Index → Data validation page |
| Tax values | New price basis `fbr_tax`, kept out of `FactorScorer` and `MarketMetrics` |
| Name matcher | Codes such as V2, V3 and A1 must now agree |

## Files

**New:**
- `app/Services/InvestmentIndex/ResearchImport.php`, `DataValidator.php`
- `app/Console/Commands/InvestmentResearchImport.php`, `InvestmentValidate.php`
- `resources/views/admin/investment-index/validation.blade.php`
- `database/data/investment/real_world_evidence.php`, `official_valuations.php`
- `database/migrations/2026_11_05_000057_add_research_facts_to_projects.php`
- `tests/Feature/InvestmentRankingDataTest.php`
- The seven `MEEZAN_PROPERTY_*` reports in `docs/`

**Changed:**
- **Services:** `IndexBrowser`, `Coverage`, `ProjectDiscovery`, `Collection/Registry`, `Collection/SchemeNames`, `Investment/MarketMetrics`
- **Agents:** `Agents/ToolRegistry`, `Workers/InvestmentAnalystAgent`
- **Models and controller:** `Project`, `MarketSource`, `Admin/InvestmentCoverageController`
- **Config and routes:** `config/meezan.php`, `routes/web.php`
- **Data:** `database/data/investment/real_world_sources.php`
- **Views:** `investment-index/category`, `project`, `compare`, `partials/insufficient`, `admin/investment-index/verification`, `partials/nav`

**Database:** two migrations. `000057` adds `projects.research_facts`. `000058` moves 303 automatically verified records from hand-read lists back to staff review, by the owner's decision, and audits each list. It leaves staff-verified records alone and deletes nothing. No table was dropped, truncated or reset.

After deploying, or changing the data files, run `php artisan meezan:investment-research-import`. It also recalculates the Index.

## Public pages

The existing pages and design are kept.

| Requirement | Status |
|---|---|
| Name, city, area, developer, status, legal status | Yes |
| Investment score, only with enough evidence | Yes |
| Data confidence, in the brief's labels | Yes |
| Evidence coverage and last updated | Yes |
| Key strengths and risk factors | Yes, on the category list and profile |
| Source references | Yes |
| Filters: city, category, legal, project status, confidence | Yes |
| "Insufficient Verified Data — Research in Progress" | Yes |
| Unranked explained as different from low-scoring | Yes |
| "Historical performance data unavailable." | Yes |
| Coverage gaps | Yes, for empty city and category pages |
| Legal disclaimer | Yes |
| Price series kept apart | Yes: asking, transaction, rental asking, verified rent, valuation and FBR |
| Unchecked findings | Shown separately, labelled as awaiting our check |
| Map | Existing Phase 13 map reused. **No project has coordinates, so none appears on it** |

## Admin Data Centre

No second admin area was built. The existing **Investment Index** section already covered most of this.

| Section in the brief | Where it is |
|---|---|
| Overview | Investment Index → Overview, Coverage |
| Project management | Admin → New Projects; aliases on Data validation |
| Legal verification | Verification Centre, with new whole-list approval |
| Market data | Market Data Centre, with review steps and CSV import |
| Research and collection | Official sources, Official registry, Exceptions, Owner documents, Coverage's duplicate list |
| Ranking engine | Overview, Methodology; score history on profiles |
| Data checks | **New:** Data validation |

Automated collection can't verify any of today's research. A person must check it, and whole-list approval needs a ticked confirmation and is audited.

## AI

Gemini remains the only provider. No separate chatbot or agent was added, and all 16 agents stay active.

- **Investment Analyst:** project profiles now include stated facts and evidence awaiting a check, each labelled as unverified.
- **Global Property Data Manager:** its coverage data now includes the validation results.
- **Grounding:** the existing check still rejects any model figure that isn't in the data.

The agents use deterministic workflows, and no live Gemini call was made in this stage.

## Acceptance criteria

| # | Criterion | Status |
|---|---|---|
| 1 | Pages load | **Met** |
| 2 | Database inspected | **Met** |
| 3 | Genuine records preserved | **Met** |
| 4 | Genuine projects researched and entered | **Met**, with gaps reported |
| 5 | Traceable evidence of existence | **Met**: validator finds 0 without a source |
| 6 | Legal claims supported or unverified | **Met** |
| 7 | Prices classified | **Met** |
| 8 | Asking prices never shown as sales | **Met**: none on file, and tested |
| 9 | Yields not fabricated | **Met**: none on file |
| 10 | Historical performance not fabricated | **Met**: none on file |
| 11 | Approved methodology used | **Met**: unchanged |
| 12 | Confidence separate | **Met** |
| 13 | Insufficient evidence not ranked | **Met** |
| 14 | Real data and sources on public pages | **Met** for existence, legal status, stated facts and unchecked evidence. **No numeric rankings exist yet** |
| 15 | Gemini the only provider | **Met** |
| 16 | Agents operational | **Met** by tests with the model off; not tested live with Gemini |
| 17 | No demo shown as real | **Met** |
| 18 | Gaps reported | **Met** |
| 19 | Tests pass | See the test report |
| 20 | Reports produced | **Met** |

**In plain terms:** the system and its data are honest and working, but **it doesn't yet rank anything**. Your final objective of trustworthy rankings needs evidence that public websites don't publish. The methodology report sets out what each category needs.

## Known limitations

1. No asking prices, transactions or rents.
2. No coordinates.
3. FBR values only for Lahore, and only by exact name match, so a same-named place could be different. Staff should confirm.
4. Hand-read research doesn't refresh itself.
5. Karachi, Faisalabad, Quetta and Peshawar have no verified legal status until staff check their hand-read lists, by the owner's decision. Data read directly from LDA, CDA and MDA still verifies automatically.
6. Developer names are on only 51 projects, because most lists don't state them.
7. Coverage of plazas, towers and apartments is thin.
8. Admin pages were checked by tests and on the real database, not by signing in with your password.
