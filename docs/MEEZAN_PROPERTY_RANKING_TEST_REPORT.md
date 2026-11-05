# Meezan Property: Ranking Test Report

Date: 5 November 2026. Every result below was actually run. No live Gemini call was made, and no WhatsApp message was sent.

## Results

| Check | Result |
|---|---|
| Full test suite (SQLite) | **956 passed, 5,552 assertions, 0 failed** |
| New ranking tests, `InvestmentRankingDataTest` | 23 cases, all passed |
| Real import on MariaDB | Worked after two real fixes (below) |
| Re-running the real import | Changes nothing |
| `meezan:investment-validate` on real data | 0 errors, 0 warnings. Exit 0 |
| Agents on MariaDB, rolled back, model off | Investment Analyst and Global Property Data Manager completed |
| Public pages on MariaDB | 13 pages returned 200, with no demo wording |
| Admin pages on MariaDB | Validation, Coverage and Verification returned 200 |
| Browser, 1366 and 375 px | No overflow and no console errors on the pages read |
| Pint | Passed |
| `composer audit`, `npm audit` | Clean |
| Route and view cache | Worked, then cleared |

**Browser limits:** page contents and overflow were checked by script. Screenshots timed out in this session, so the visual look of the new sections wasn't seen. Admin pages were rendered through the application, not by signing in, because the password is yours.

## What the tests cover

| Brief's list | Covered by |
|---|---|
| Project uniqueness, duplicate aliases | Aliases prevent a second project; validator finds duplicates; V2 and V3 stay separate |
| Source attribution | Every imported record has a source, address and date; validator finds projects with none |
| Legal verification states | Scanned list waits for staff; exact official data still verifies; unverified never reads as approved |
| Missing market data | "Historical performance data unavailable." |
| Asking-price classification | Asking prices never count as history; shown in their own list |
| Transaction classification | Validator catches a transaction price from an asking-price source |
| Rental yield | No yield is created from dividends or sold percentages |
| Confidence | Labels, and separation from the score |
| Category weights | All five categories locked to the approved values |
| Insufficient evidence | Legal evidence alone doesn't rank; "Research in Progress" shows |
| Ranking calculation | Existing suites, plus the new cases |
| Public page visibility | Facts, unchecked evidence, coverage gaps and disclaimers |
| Demo exclusion | Demo projects absent from pages and the run |
| Historical preservation | Second import changes nothing; existing suites cover snapshots |
| City and category filters | City, legal status and confidence |
| Human approval | Whole-list approval needs a ticked confirmation and records who did it |
| Access | New admin pages refuse guests and non-admins |
| AI | Analyst labels unchecked evidence; Data Manager receives validation results |

## Problems found while testing

| Problem | How it was found | Fix |
|---|---|---|
| **V3 Business Centre merged into V2** | Checking the import's results by hand | Matcher now requires codes such as V2 and A1 to agree. The one wrong alias was removed. Test added |
| **False label:** manager reports showed "staff-verified" | Reading the page in a browser | Label now reads "Owner or manager document" |
| "Verified Approved" shown on an unchecked record | Reviewing the page | Now reads "on its list as approved, not yet checked by us" |
| `price_basis` column too short on MariaDB | Real import; **SQLite didn't catch it** | Basis renamed `fbr_tax` |
| `sample_size` required on MariaDB | Real import; **SQLite didn't catch it** | Set on each record |
| FBR 2024 rows wrapped, truncating names | Reading the matches | Only exact names kept; one wrong match dropped |
| **I overwrote yesterday's source register** | Windows filenames ignore case, so the new name was the same file | Restored from the session's copy into one file holding both |
| Test helper ignored its own arguments | Failing tests | Helper fixed; not a product fault |

## Test changes

`InvestmentRankingDataTest` is new. No existing test was edited in this stage.

The suite now takes about 6 minutes, up from about 2.5, because several new cases run the real import.

## Not tested

- A real project reaching "Ranked" on real data: none has enough evidence. The ranking path itself is covered by existing tests with fixtures.
- Live Gemini answers about the new evidence.
- Verifying the 29 evidence records and 61 legal records on the real database: that is for staff to do, so only the test copy was verified.
- Anything on Hostinger.
