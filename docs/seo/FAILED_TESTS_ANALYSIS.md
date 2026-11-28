# Failed tests analysis

## Result

Full suite with the SEO changes: **1,063 passed, 14 failed**. The 4 new SEO tests (`tests/Feature/SeoIndexingTest.php`) pass when run on their own (4 passed, 47 assertions).

## Evidence that the 14 failures existed before the SEO work

1. The original files were taken from the live checkout, which runs commit `2600d66` (the last commit before the SEO work). Each of the 30 files involved was hashed with `git hash-object` and compared with the blob in `2600d66`: **0 mismatches**.
2. Those files were put back in place, the new SEO test file was moved aside, and the four affected test files were run (`InvestmentIndexPagesTest`, `InvestmentRankingActivationTest`, `InvestmentRankingDataTest`, `PublicShowcaseTest`).
3. Result on the original code: **14 failed, 75 passed**. These are the same 14 tests.
4. The failure messages and line numbers were extracted from both runs and compared: **identical** (`diff` reported no difference).
5. The SEO versions of all files were then restored and checked.

The memory notes from 19 November also record 14 failing Investment Index tests. That check ran against the 17 November server code and traced them to wording changed in the 7–13 November ranking redesigns.

## The 14 tests

"Phrase in code?" says where the expected text exists today. "Not found" means it no longer appears in any view, controller, service or config file.

| # | Test | Exact error | Phrase in code? | Before SEO? | Needs correction? |
|---|---|---|---|---|---|
| 1 | InvestmentIndexPagesTest › shows the five leaderboards and the Overall Index on the rankings home | `:95` expected page to contain "Overall Meezan Investment Index" | Yes (`overall`, `methodology` views) but not on the rankings home | Yes | Decide test or page |
| 2 | InvestmentIndexPagesTest › ranks a category Pakistan-wide, lists the others by status… | `:102` "Assessed, not ranked" not found in the specified order | Not found | Yes | Decide test or page |
| 3 | InvestmentIndexPagesTest › shows every required fact on a project profile… | `:120` to contain "Investment score" | Yes, on other templates, not on the Index project profile | Yes | Decide test or page |
| 4 | InvestmentRankingActivationTest › opens all five leaderboards and every city filter… | `:94` to contain "Assessed, not ranked" | Not found | Yes | Decide test or page |
| 5 | InvestmentRankingActivationTest › keeps provisional positions off public pages until the owner turns them on | `:194` to contain "Provisional research ranking" | Yes (`rankings/partials/index-boards`), not on that page | Yes | Decide test or page |
| 6 | InvestmentRankingActivationTest › shows asking figures as facts on the page, never as a price trend | `:264` to contain "Average advertised house price, Sep 2026" | Only in the import service, not on the page | Yes | Decide test or page |
| 7 | InvestmentRankingDataTest › never counts asking prices as history | `:167` to contain "Historical performance data unavailable." | Yes (`investment-index/project`, `compare`), not shown in this case | Yes | Check: may be a real display gap |
| 8 | InvestmentRankingDataTest › does not rank a project on legal evidence alone | `:180` to contain "Insufficient Verified Data — Research in Progress" | Yes (partial `insufficient`), not on the profile tested | Yes | Check: may be a real display gap |
| 9 | InvestmentRankingDataTest › shows stated facts and unchecked evidence with their sources… | `:215` to contain "Reported, awaiting our check" | Not found | Yes | Decide test or page |
| 10 | InvestmentRankingDataTest › labels an unchecked official list entry as unchecked… | `:226` to contain "on its list as approved, not yet checked by us" | Not found | Yes | Decide test or page |
| 11 | InvestmentRankingDataTest › shows confidence in words and filters by it… | `:235` to contain "Evidence covers" | Not found | Yes | Decide test or page |
| 12 | InvestmentRankingDataTest › states a coverage gap where nothing is on file | `:245` to contain "does not mean a project is illegal" | Yes (`investment-index/project`, `legal`), not on the page tested | Yes | Decide test or page |
| 13 | InvestmentRankingDataTest › shows a moved project as unverified, and verified again… | `:376` to contain "on its list as approved, not yet checked by us" | Not found | Yes | Decide test or page |
| 14 | PublicShowcaseTest › uses research wording on the Investment Index while nothing is ranked | `:148` to contain "Under research" | Only in an admin view | Yes | Decide test or page |

## Root cause

All 14 are text expectations on the Investment Index pages. The pages were redesigned on 7–13 November and on 19 November. Some phrases were reworded or removed (the 5 "not found"), and some sections are no longer shown on the page each test opens. None involves sitemap, robots, metadata, FAQ or performance code.

## Recommendation

Not fixed here: changing them means either editing ranking-page wording (business content) or rewriting test expectations, and both need the owner's decision. Handle them as a separate task, one test at a time:

- If the current page wording is intended, update the test to the new wording.
- If the test describes a safeguard the redesign dropped, restore the safeguard. Cases 7, 8 and 12 deserve a close look, because they concern honesty labels: "no history", "insufficient data", "not illegal".
