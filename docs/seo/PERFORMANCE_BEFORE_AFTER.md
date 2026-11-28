# Performance before and after

## Method

Lab measurement only: each page rendered in-process by Laravel as a signed-out visitor, twice (cold, then warm), on the local XAMPP machine with a database that mirrors live for projects and areas. Same script (`scratch/perf-probe.php`), same machine and data before and after. These are server render times, not field Core Web Vitals. Live TTFB from the earlier audit is included for reference.

## Server render time (warm run)

| Page | Before | After | Queries before → after | Live TTFB before (audit) |
|---|---|---|---|---|
| `/investment-rankings` | 4,955 ms | 1,152 ms | 63 → 58 | 3.3 s |
| `/investment-rankings/housing-societies` | 8,868 ms | 1,368 ms | 50 → 43 | 4.3 s |
| `/investment-rankings/lahore` | — (measured after fix: 1,601 ms) | 1,601 ms | 100 | — |
| `/` | 136 ms | 159 ms | 17 → 17 | 1.3 s |
| `/buy` | 113 ms | 115 ms | 12 → 12 | 1.4 s |
| `/properties` | 181 ms | 184 ms | 15 → 15 | 1.3 s |
| `/lahore` | 159 ms | 185 ms | 16 → 33 (FAQ queries, about 20 ms) | 1.4 s |
| `/areas` | 96 ms | 87 ms | 4 → 4 | 3.0 s |
| `/areas/dha-lahore` | 81 ms | 79 ms | 14 → 14 | 4.2 s |
| `/projects` | 70 ms | 69 ms | 7 → 7 | 1.3 s |
| `/projects/dha-lahore` | 80 ms | 79 ms | 27 → 27 | 2.0 s |
| `/agents` | 60 ms | 61 ms | 4 → 4 | 1.3 s |
| `/sell` | 62 ms | 60 ms | 4 → 3 | 10 s (one request) |

## Root cause of the slow investment pages

`IndexBrowser::marketBoard()` sorted about 900 housing-society rows with comparators that called `marketChange()` and `marketRichness()` on both rows of every comparison. Each call decoded the project's `research_facts` JSON again, tens of thousands of times per page. The fix computes each row's sort keys once, then sorts. The board is also cached for an hour, keyed on the run, the latest score, project and occupancy-evidence update times, so any change produces a fresh board. The resulting order is identical to before, checked for 120 combinations of category, city and limit.

The board alone went from 3,708 ms to about 790 ms uncached for housing societies.

## What the numbers mean for live

Most non-investment pages already render in about 60–190 ms on the server. Their 1–4 s live TTFB is therefore mostly hosting and network overhead: shared-host PHP start-up, a remote database and the CDN round trip, with every page `DYNAMIC` and uncached. Reducing that needs either a full-page cache for signed-out visitors or a hosting change; both need approval (see `REMAINING_ISSUES.md`).

Not measured: LCP, INP and CLS. They need a browser lab run (Lighthouse/PageSpeed Insights) on the deployed site, and field data from Search Console's Core Web Vitals report once traffic exists. Page weight and request count are unchanged apart from the added FAQ blocks (about 1–4 KB of HTML per city or area page).
