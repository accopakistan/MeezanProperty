# Meezan Property: Ranking Public Page Report

Date: 5 November 2026. The design and the brand palette are unchanged.

## What changed

**Before:** every card said "Insufficient Verified Data" and showed no projects.

**After:** the same pages list real projects, say what is on file for each, and explain why they aren't ranked. Nothing shows a score that hasn't been earned. The warning is still there, now beside the data instead of in place of it.

| Page | URL | Now shows |
|---|---|---|
| Rankings home | `/investment-rankings` | Projects researched 1,273, ranked 0, official legal status 515, awaiting our check 540, excluded 22, latest calculation, projects by city, and the five category cards with their most documented projects |
| City pages (7) | `/investment-rankings/{city}` | The same figures for that city |
| Category leaderboards (5) | `/investment-rankings/{category}` | Ranked table when any exist, then every researched project with legal line, evidence share, sources, last updated, and the reason it isn't ranked |
| City and category | `/investment-rankings/{city}/{category}` | The same, filtered |
| Project profile | `/investment-rankings/projects/{slug}` | Identity, developer, legal status, facts with source and date, records awaiting a check, price records by kind, and missing evidence |
| Methodology | `/investment-rankings/methodology` | New section explaining the four display states and how missing evidence counts |

### The most documented projects, shown now

These lists aren't rankings. They are ordered by how much is on file, and the page says so.

| Category | First shown |
|---|---|
| Housing Societies | Canal View Housing Scheme, Al Hamra Town, Zaamin City (Lahore, Verified Approved) |
| Commercial Plazas | Plaza opposite Civil Hospital, Lohari Gate Shopping Centre, MDA Arcade (Multan, on MDA's list, unchecked) |
| Apartment Buildings | Globe Residency, Signature Tower, Abraz Ul Minal Towers (Karachi) |
| Shopping Malls | Dolmen Mall Clifton, Ruby Cinema & Shopping Mall, Icon Mall & Residency (Karachi) |
| Business Towers | The Harbour Front, The Centre, Country Finance Tower (Karachi) |

## The four states

| State | How it appears |
|---|---|
| 1. Verified and rank eligible | Rank, score, confidence, coverage, legal status, last updated, strengths, risks, details link. **None qualify today** |
| 2. Provisional research position | Labelled "Not fully verified, and not an investment recommendation", with position P1, score, confidence, evidence share, unchecked records and updated date. **Hidden until the owner switches it on** |
| 3. Research in progress | Identity, city, category, developer, legal line, evidence share, last updated and missing evidence. No score |
| 4. Excluded or restricted | Shown as excluded, with the official reason. An unknown status always reads "Unknown / Unverified", never illegal |

A new legal line says "On SBCA's list, not yet checked by us" when a record is waiting. It doesn't count as verified, and the verified legal status stays Unknown until a person checks it.

## Admin research queue

**Admin → Investment Index → Research queue**, at `/admin/investment-index/research`.

| Requirement | Where |
|---|---|
| Counts: researched, ranked, provisional, under research, waiting, excluded | Top of the page |
| Legal, market and developer gaps | "What is on file" |
| By city and category, source count | Under the counts |
| Latest jobs and calculations | Two panels |
| Open a project and review its source link | Each row |
| Identify missing fields | "Missing" column |
| Find work | Filters: category, city, name, waiting for a person, no legal record, no evidence, no market data, no developer |
| View evidence, verify a field, reject | "Check, verify or reject" opens the Verification Centre for that project |
| Add evidence | "Add evidence" |
| Request further research | Note on the row, creates a staff task. The note isn't public |
| Approve for publication | "Publication" opens the project's admin form, and legal evidence is approved in the Verification Centre |
| Provisional switch | Needs a tick to confirm, and is written to the audit log |

The import and the AI can't approve legal evidence. Every verification is a person's action.

## Performance and privacy

- Public pages make no web requests, which a test confirms. They read the published run only.
- The research totals and one documented list together took 18 ms on MariaDB, so no extra cache was added. Results are tied to the published run, so a new run takes effect at once.
- Staff notes and research requests appear only in admin.
- No personal contact details from CDA's pages were stored.

## AI

No new provider or agent was added, and Gemini stays the only active one. The Investment Analyst and Global Property Data Manager read this data through their existing tools, including the awaiting-a-check labels. **I did not use the AI for this research round:** the facts were read and checked by hand, and the free tier has a low daily limit. No score uses AI.

## Files

- **Views:** `rankings/partials/index-boards`, `rankings/partials/insufficient`, `rankings/index`, `rankings/group`, `investment-index/home`, `investment-index/category`, `investment-index/project`, `investment-index/methodology`, `admin/investment-index/research` (new), `admin/investment-index/verification`, `admin/investment-index/partials/nav`.
- **Code:** `IndexEngine`, `ProjectAssessor`, `IndexBrowser`, `ResearchImport`, `ProjectDiscovery`, `InvestmentIndexController`, `Admin/InvestmentCoverageController`, `AdvisorGateway`, `Project`, `config/investment_index.php`, `routes/web.php`.
- **Data:** `market_research.php`, `sbca_building_nocs.php`, `cda_scheme_details.php`.
