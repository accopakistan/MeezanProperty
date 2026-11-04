# Meezan Investment Index: Phase 4 Report
## Final QA and documentation

Date: 4 November 2026. Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB 10.4).

**Safety:**
- Nothing was committed, pushed or deployed, and `.env` was not changed.
- No `migrate:fresh` or `db:wipe` was run on the project database. The fresh-install check used a **separate temporary database**, which was dropped afterwards.
- Every check on the project database ran inside a transaction that was **rolled back**, and counts were compared before and after.

---

## 1. What was checked

| Check | Result |
|---|---|
| **Schedule** | `meezan:investment-collect` daily 03:45, `meezan:investment-index run` daily 04:30 (plus the earlier weekly MPIS recalculation) |
| **Queue** | No Index jobs stuck. Recalculation and collection jobs use the queues that `MEEZAN_QUEUE_VIA_SCHEDULER` drains on Hostinger. Older local notification and agent jobs are waiting because no local worker runs |
| **Production caches** | `route:cache` and `config:cache` both work; pages served with cached routes; caches cleared afterwards |
| **Fresh install on MariaDB** (temporary database `meezan_qa_tmp`) | All 56 migrations applied in 13 s, giving 76 tables (the same as the project database). **No column with `ON UPDATE`.** All 16 agents start on Gemini; OpenAI and Anthropic start disabled. An Index run published; built-in sources were added inactive, and the scheduled collection then did nothing (not authorized). Database dropped |
| **End-to-end on the project MariaDB** (rolled back) | See §2 |
| **Scale, 500 developments** (rolled back) | Engine run **2.2 s**, 18 MB extra memory; an unchanged rerun 1.9 s ("unchanged", nothing written). Pages: home 119 ms (20 queries), leaderboard 51 ms (7), Overall Index 109 ms (4), profile 28 ms (11) |
| **Security** | No unescaped output in the Index views. Public source links: http(s) only, never the admin area. Every admin route needs an admin; collection and connection tests are rate-limited; owner submissions need sign-in (10 an hour); documents are private and stream only to admins with `nosniff` and no caching. The search query helper escapes `LIKE` wildcards |
| **Requirements** | All 15 mandatory requirements, the legal safety rule and the website-display fields are mapped to code and tests: see `Meezan_Property_Investment_Index_Documentation.md` §8 |

## 2. End-to-end run with live official data

Everything below ran on the project database inside one transaction, which was rolled back. The run took 67 s, mostly polite paging of the LDA site.

1. **Collection.**
   - Three LDA sources were authorized and collected live: approved 401 entries, illegal 382 (all 32 pages), LDA-developed 19.
   - Six approval stages it couldn't read went to staff as exceptions.
2. **Real developments named from the official lists**, each matched to its LDA record as soon as it was created:
   - "Abdullah Garden LSD": an approval dated 31 Oct 2013;
   - "Green Palms Housing Scheme": partially approved (technical), dated 23 Dec 2022;
   - "Sajid Garden HS": on the illegal list, dated 29 May 2026.
3. **Owner documents.** Five were submitted for the approved scheme and verified by staff: progress 92%, occupancy 78%, electricity connected, gas partial, delivered on time. One for the technical scheme was rejected with a reason.
4. **Scores:**

| Development | Result |
|---|---|
| Abdullah Garden LSD | **Ranked #1**: score 84.2, confidence 63, low risk, 65% of the weight evidenced |
| Green Palms Housing Scheme | **Insufficient Verified Data**: 30% of the weight evidenced, 60% needed |
| Sajid Garden HS | **Excluded from recommendations**: official illegal listing |

5. **Pages.** The profiles, the leaderboard and the home page all rendered correctly, with official LDA row links and owner documents named but not linked.
6. **Methodology change.** Version 2 was activated, the Index republished automatically (the score went from 84.2 to 83.2), and "Methodology changed from version 1 to 2" was recorded for the project.

**After rollback:** projects 420, sources 3, registry 0, legal records 0, evidence 0, runs 1, methodologies 1, submissions 0, all as before. No uploaded test files were left.

## 3. Problems found and fixed

| Problem | Fix | Test |
|---|---|---|
| A submission verified straight after being stored was refused: its "pending" status existed only as the database default. Staff never hit this, because the admin screen reloads the record first | Status set explicitly on creation | New regression test |
| Official names kept stray punctuation ("Abdullah Garden LSD,") | Trailing and leading punctuation trimmed; brackets kept | Added to the name tests |
| A public source link could in principle use a non-web address | `IndexBrowser::safeUrl()`: http(s) only, never `/admin/` | New test |
| The Overall Index page listed every ranked project: 470 entries made 466 KB | Leaderboards and the Overall Index show the top 100, with a note; every rank stays on the profile | Covered by page tests |

## 4. Automated tests

| Check | Result |
|---|---|
| Full suite | **800 passed**, 0 failed |
| Investment Index tests | Engine 15, collection 13, pages 11 |
| Pint | Passed |
| Assets | Built |

## 5. Documentation

- **New:** `Meezan_Property_Investment_Index_Documentation.md`, the consolidated reference: how it works, code map, scoring, public pages, operations runbook, admin guide, owner guide, requirements traceability and known limits.
- **Earlier:** the phase reports (1–3) remain as the build history.

## 6. What remains before real results appear

1. **You:**
   - authorize the LDA sources (Admin → Investment Index → Official sources);
   - add real developments (Admin → New Projects);
   - check owner documents as they arrive.
2. **Optional:**
   - collector presets for RDA, CDA and SBCA, once their list formats are checked;
   - an Index map, once real projects have positions;
   - removing the unused earlier rankings views and scripts.
3. **Deployment:**
   - commit and push when you ask;
   - on Hostinger: `php artisan migrate --force`, `php artisan config:cache`, the scheduler cron with `MEEZAN_QUEUE_VIA_SCHEDULER=true`, and the Gemini key in Gemini Settings.
