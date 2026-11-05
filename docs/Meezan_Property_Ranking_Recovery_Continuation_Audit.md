# Meezan Property: Ranking Recovery Continuation Audit

Date: 4 November 2026. Environment: localhost / XAMPP (Laravel 12, PHP 8.2, MariaDB 10.4). Starting point: `main` at `cb6379a`.

This audit checked the previous session's handover against the code, database, Git history and backups before anything changed.

## 1. Handover findings, checked

| Handover finding | Result | Evidence |
|---|---|---|
| No ranking data was deleted | **Confirmed** | `investment_rankings` 17,685, `investment_calculations` 6, `market_observations` 7,187, `market_indicators` 3,524 and `projects` 420 are the same in the live database and in the backups taken before Index Phase 1, before the Gemini migration and before Index Phase 3 |
| Calculation #7 holds 3,522 rankings | **Confirmed** | 3,522 rows, status `published`, `includes_demo` true, trigger `demo` |
| Demo mode was switched off by default | **Confirmed** | `config/meezan.php`: `INVESTMENT_DEMO_MODE` default changed from `true` to `false` in `cb6379a` |
| Public routes were changed | **Confirmed** | `routes/web.php` in `cb6379a` sent every city, group, segment and compare page to `InvestmentIndexController` redirects |
| "Only nine wiring files changed since `0e69d7b`" | **Partly correct** | `cb6379a` changed **116 files** overall, because it also added the Index and Gemini. About nine existing files connect the rankings to the Index. The old engine really is untouched: `InvestmentRankingsController`, `RankingBrowser`, `RankingCalculator`, `InvestmentRanking`, `InvestmentCalculation`, `resources/views/rankings` and both ranking scripts have no differences |
| The old ranking pages "disappeared" by accident | **Not quite** | The redirects were deliberate in Phase 3, under the 4 November re-scope to the Investment Index, and its report recorded them. This task reverses that part because the owner now wants the earlier pages reachable |
| The Gemini check was unfinished | **Confirmed, now finished** | See `Meezan_Property_Gemini_AI_Verification_Report.md` |
| A script named `dump_counts.py` exists | **Confirmed** | In the previous session's temporary folder. It only counts rows per table in a database dump file. It changes nothing. It was reused to compare the three backups above |

**Not in the handover:**
- **The five-category engine and exact weights already exist.** All five weight tables match the brief exactly, in the active methodology (version 1) in the database.
- **Every project is demo data.** All 420 projects, 7,187 observations and 3,524 indicators are flagged demo, so "Insufficient Verified Data" is the true state today. Restoring the pages can't produce real rankings by itself.
- **Demo mode was a public switch.** With demo mode on, any visitor saw the fictional rankings. This is now closed.
- **The free Gemini quota is paused** until 5 November 2026, 13:00 Pakistan time, so no live AI request could be tried in this task.
- **An untracked `MeezanProperty.zip`** sits in the project root. It was left untouched and uncommitted. It should not be added to Git.

## 2. Calculation #7

| Check | Result |
|---|---|
| Rows | 3,522, all flagged demo |
| Linked to | 420 demo projects, no missing links, no real projects |
| Row status | 3,361 ranked, 161 insufficient data |
| General rows | 2,444, in `overall`, `residential`, `commercial`, `appreciation`, `rental`, `low_risk` and `emerging` |
| Segment rows | 1,078: apartments 168, business towers 63, commercial plazas 56, commercial plots 112, commercial shops 161, houses 63, mixed use 42, office spaces 119, plots 154, shopping malls 63, societies 77 |
| Inputs | 7,187 observations and 3,524 indicators, all demo |
| Valid as a calculation | Yes, for the demo data it was given |
| Valid as market information | **No.** It must stay out of public rankings |
| Decision | Kept unchanged as internal history. `InvestmentCalculation::current()` no longer returns it for the public |

**Pairs of rows:** 184 groups look like duplicates, all from the 42 mixed-use projects. They are scored separately as residential and as commercial. This is how the engine works, not a data fault.

**A risk that remains:** Admin → Market Data → Demo Data → "Remove all demo data" permanently deletes calculations #3 to #7 with the demo records. That is the button's existing purpose, and it asks for confirmation. Don't press it if you want this history kept.

## 3. State after this work

| Area | State |
|---|---|
| Code | Changes are uncommitted in the working tree, on top of `cb6379a` |
| Database | No migration and no schema change. Row counts match this task's backup, except one extra `property_view_stats` row from a real page check |
| Routes | `/investment-rankings` stays the Index home. City, group, category and compare addresses serve the earlier dashboards again. `/investment-rankings/pakistan` is the earlier all-Pakistan dashboard |
| Demo mode | Off. Even when on, only a signed-in administrator sees the labelled preview |
| Gemini | Unchanged, and still the only provider |

**Backup before changes:** `C:\xampp\backups\MeezanProperty-before-ranking-recovery-2026-11-04\`: database dump (76 of 76 tables), row counts and the Git commit. `.env` was not changed.
