# Meezan Property: Ranking Final Test Report

Date: 5 November 2026.

## Result

| Check | Result |
|---|---|
| Full test suite (Pest, SQLite) | **977 passed, 0 failed**, 5,761 assertions |
| New tests in `tests/Feature/InvestmentRankingActivationTest.php` | 18 |
| Code style (Pint) | Passed |
| Data validator on real MariaDB | **0 issues** (11 checks at error or warning level) |
| Import run twice on MariaDB | The second run adds nothing: 0 new projects, evidence, facts or asking records |
| Public and admin pages through the app on MariaDB | All returned 200 |
| Browser check | `/investment-rankings/pakistan`, a category page, a city page and two project profiles read and checked. No horizontal overflow at the width tested |

## What the new tests cover

| Requirement | Test |
|---|---|
| Five leaderboards, seven city filters | Every category in every city opens and lists researched projects |
| Project visibility, no score without earning one | Listed by name, with no score and no rank |
| Score and ranking order | Provisional order is score, then evidence, then id |
| Deterministic results | The same evidence gives the same figures, and no new run is published |
| Missing data | No provisional figure without two factors, enough weight and a usable legal status |
| Verified scoring unchanged | Unchecked records reach no verified score, and rejected ones count for nothing |
| Legal display | "On LDA's list, not yet checked by us", and unknown is never shown as illegal |
| Excluded projects | Adverse records get no figure |
| Owner control | Provisional positions stay off public pages until switched on, the switch needs confirmation, is audited and is admin only |
| Demo exclusion | Demo projects are not assessed or listed |
| Price classification | Zameen figures are `asking` and `submitted`, and reach no score |
| Attribution | Facts carry a source and address, and are labelled asking-based |
| Repeat imports | No duplicates, and earlier facts are kept when a portal listing matches a project |
| SBCA data | All lists wait for review, categories are marked inferred, no duplicate names, every row has an SBCA reference |
| Developer field | A list fills a blank and never overwrites |
| Admin workflow | Filters, per-project view and research requests. A request changes no record |
| Public and private separation | Research notes don't appear publicly, and non-admins are refused |
| Performance rule | Public pages make no web requests |

Existing tests already cover the older parts of this list, including the 60% rule, history classification, yield rules and legal exclusion. They all still pass.

## Bugs found and fixed during this stage

| Bug | How it showed | Fix |
|---|---|---|
| **The import could overwrite a project's existing facts.** A matched project was loaded without its facts column, so adding a new fact replaced what was there | The import log kept reporting "4" facts on every rerun, even with tests green | `addFact` now reads the stored facts first. Regression test added, and the lost facts were restored by re-running the import |
| A duplicate import statement stopped the whole app from loading | Caught at the first page render, before tests | Removed |
| Labels recorded "CDA layout plan approved" against values such as "Cancelled" | Noticed when reviewing the CDA data, before import | Label changed to "status" |
| "Park View City" on Zameen matched CDA's Islamabad scheme of a similar name | The import stopped because the Lahore project didn't exist | Added as its own Lahore project, not merged |
| Ranking tests ran a full recalculation on every project save | One test took 92 seconds | Tests fake that one job. Production already runs it once, delayed |

## What these tests don't prove

- **The real admin screens weren't clicked in a browser**, because I don't use your admin password. They were rendered through the app as an admin in a rolled-back transaction, and their actions are covered by tests.
- **SQLite isn't MariaDB.** The new research queries were also run on MariaDB, through the import, the pages and the audit script. The Provisional switch and the research-request action ran on SQLite only.
- **SBCA rows came through a text conversion of the page.** The tests check their shape, not each row's accuracy against SBCA. That is what the staff check is for.
- **Provisional positions on a real public page** were tested with fixtures. They've never been switched on against real data, because that is your decision.
- **Phone width** wasn't retested at 375 px for the new sections. The layouts use the same responsive grids as the rest of the page, and the width tested was 664 px.

## Unchanged

Nothing was committed or deployed. `.env`, `public/Refrence/` and the methodology are unchanged, and Gemini remains the only active AI provider. No migration dropped or changed existing data, and no `migrate:fresh` or `db:wipe` was run. No WhatsApp message was sent.
