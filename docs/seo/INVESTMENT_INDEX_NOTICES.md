# Investment Index notices — investigation

Three of the 14 failing tests check honesty notices on the Investment Index. The SEO release does not touch any of them. This report shows where each notice went, using the git history and the live site.

## How it was checked

- Commit history fetched for `investment-index/project.blade.php` and `investment-index/category.blade.php`, and each notice counted in every commit from `212e334` to the current `2600d66`.
- Live production (`2600d66`) fetched on 28 November 2026 for `/investment-rankings/projects/dha-lahore`, `/investment-rankings/projects/galleria-residences`, `/investment-rankings/housing-societies` and `/investment-rankings/quetta/business-towers`.
- The local version is the same as production for these templates, apart from the robots meta change in this release.

All three notices changed in commit **`7f663b0` (7 November 2026, "Redesign the Investment Index, add the premium logo and nationwide footer links")**. They were present in `131278c`.

## 1. "Insufficient data" on an unranked project profile — still covered

| | Before `7f663b0` | Now (production and local) |
|---|---|---|
| Text | "Insufficient Verified Data — Research in Progress. An unranked project has not scored low: there is not yet enough verified evidence to compare it." | "Project Directory Profile — Research in Progress … It has not yet been awarded a public ranking because verified documentation on file covers N% of the required scoring weight (≥ 60% required). Being unranked reflects a documentation gap rather than a negative assessment." |
| Shown live | — | Yes, on both profiles checked |

**Finding:** the meaning is preserved, in new wording. Only the test's expected wording is out of date. **No page change needed;** the test should be updated to the new wording, with owner approval.

## 2. "No price history" when only asking prices exist — partly lost

| | Before `7f663b0` | Now |
|---|---|---|
| Facts list | A "Historical performance" row that said "Historical performance data unavailable." whenever no verified transaction or valuation history existed, even if asking prices were on file | Row removed |
| Price section | "Historical performance data unavailable." when no price records at all | Same, but **only when there are no records of any kind** |
| Asking-only projects | Both notices | The asking series is shown with the general note "Asking prices, verified transactions, and rental records are strictly segregated. An asking price is never recorded as a sale price." |
| Live | — | `/investment-rankings/projects/dha-lahore` (asking prices on file): no "data unavailable" notice; segregation note present. Galleria Residences (no prices): notice present |

**Finding:** the scoring safeguard is intact. Asking prices are still not counted as history; the test's first check on the score passes. What was lost is the explicit statement that verified historical performance is unavailable when only asking prices exist.

**Smallest correction (proposed, not made):** in `resources/views/investment-index/project.blade.php`, inside the `@else` branch of the price section, add one line shown only when no verified transaction or valuation series has rows:

```blade
<p class="text-xs text-muted"><strong class="text-ink">Historical performance data unavailable.</strong> Only asking prices are on file; they are not used as price history.</p>
```

The line reuses the existing classes. It changes no logic and no layout.

## 3. "Not illegal" disclaimer on leaderboards — removed from category pages

| | Before `7f663b0` | Now |
|---|---|---|
| Category and city-category leaderboards | Closing line: the investment disclaimer, then "Legal status shows only what official records on file say. "Unknown / Unverified" means no official record has been found here; it does not mean a project is illegal." | Line removed. The legal-status filter and "Unknown / Unverified" labels are still shown |
| Index project profile | Same sentence | **Still present** (sidebar "Standard Investment Disclaimer") |
| Live | — | Not on `/investment-rankings/housing-societies` or `/quetta/business-towers`; present on both project profiles |

**Finding:** an important clarification is missing where many projects are listed with "Unknown / Unverified" legal status. Readers could take that label as a negative finding.

**Smallest correction (proposed, not made):** restore the original single paragraph at the end of `resources/views/investment-index/category.blade.php`:

```blade
<p class="mt-6 text-[13px] text-muted">{{ config('meezan.investment.disclaimer') }} Legal status shows only what official records on file say. "Unknown / Unverified" means no official record has been found here; it does not mean a project is illegal.</p>
```

The text is identical to what was there before 7 November. It uses existing classes and changes no logic.

## Recommendation

Corrections 2 and 3 are each one line of existing wording and carry no logic risk, but they change visible text on ranking pages. They are left for your approval, and could go out in this release or a follow-up. In every case, the matching tests should then be updated or will pass. The other 11 failing tests are wording-only and are listed in `FAILED_TESTS_ANALYSIS.md`.
