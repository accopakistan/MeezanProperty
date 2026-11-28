# Production deployment checklist

Deployment is **not approved yet**. Do nothing in this file until the owner says so.

## Before deploying

- [ ] Owner approval received in writing.
- [ ] Backups taken: the live database (mysqldump to `~/backups/<date>-seo-release/`) and the current `.env`. No migrations are in this release, so the database is not changed, but the backup rule still applies.
- [ ] Commit contains only the files in `FILES_MODIFIED.md`, plus `public/build/manifest.json` and the new CSS asset. It must exclude `scratch/`, `storage/`, `.claude/worktrees`, `*.sql` and `*.zip`.
- [ ] Push to `main`; Hostinger auto-pulls. Confirm the live HEAD matches the new commit.

## Backup instructions (run on the server before pushing)

No credentials are written here. The mysqldump reads them from a protected option file created just for the backup and deleted afterwards. The database name comes from the live `.env`.

```bash
cd ~/domains/meezanproperty.com/public_html
B=~/backups/$(date +%F)-seo-release && mkdir -p "$B" && chmod 700 "$B"
git rev-parse HEAD > "$B/previous-commit.txt"          # expected: 2600d66…
cp .env "$B/.env.backup" && chmod 600 "$B/.env.backup"
# DB_* values from .env go into a temporary option file (mode 600), never on the command line:
php -r '$e=parse_ini_file(".env"); file_put_contents(getenv("HOME")."/.my.seo.cnf", "[client]\nuser={$e["DB_USERNAME"]}\npassword=\"{$e["DB_PASSWORD"]}\"\nhost={$e["DB_HOST"]}\n"); chmod(getenv("HOME")."/.my.seo.cnf", 0600);'
mysqldump --defaults-extra-file=~/.my.seo.cnf --single-transaction --no-tablespaces "$(grep ^DB_DATABASE= .env | cut -d= -f2)" | gzip > "$B/db.sql.gz"
rm -f ~/.my.seo.cnf
ls -la "$B"
```

## Git commit summary (proposed)

```
SEO, AEO and GEO release: sitemap index, indexing rules, data-based FAQs, llms.txt

- Sitemap index (pages, research, locations, agents, projects-N, properties-N); only indexable URLs
- Unranked Investment Index pages "noindex, follow"; name-only project profiles noindex, follow
- Project directory and dealer pagination self-canonical; filtered directory views noindex, follow
- Faster ranking pages: market board sort keys computed once (same order) and cached
- FAQ blocks on city pages and area guides from site data only; sources labelled official or secondary
- /llms.txt; Organization description and sameAs; listing schema size, rooms and public dealer
- Title suffix rule; description fixes; Search Console and Bing verification settings
- Tests: SeoIndexingTest; sitemap tests read the child files
```

## Deployment instructions

1. Build the commit from the clean file list in `FILES_MODIFIED.md`, using the same plumbing method as earlier releases, because the local folder is not a git checkout. Include `public/build/manifest.json`, add `public/build/assets/app-DySRoiEN.css` and remove `public/build/assets/app-CzCrREAc.css`.
2. Push to `main`. Hostinger auto-pulls; wait until `git rev-parse --short HEAD` on the server shows the new commit.
3. Run the cache steps below, then the live verification.

## Immediately after deploying (on the server)

- [ ] `php artisan view:clear`
- [ ] `php artisan cache:clear` — needed: sitemap files and `llms.txt` are cached for an hour.
- [ ] Optional, with approval: `php artisan route:cache` (no route closures in `routes/web.php`).

## Live SEO verification

| # | Check | How | Expected |
|---|---|---|---|
| 1 | Sitemap index | `curl -s https://meezanproperty.com/sitemap.xml` | `<sitemapindex>` listing `pages`, `research`, `locations`, `agents`, `projects-1`, `properties-1` |
| 2 | Child files | fetch each child file | valid XML; about 21 / 4 / ~40 / 2 / 1,120 / 11 URLs |
| 3 | No noindex URL in any sitemap | for each sitemap URL, fetch and read `<meta name="robots">` | every URL `index`, status 200 (run `scratch/sitemap-verify.php` logic against live, or a crawler) |
| 4 | Canonicals | same pass | exactly one canonical per page, equal to the sitemap URL |
| 5 | Unranked ranking pages | `/investment-rankings/housing-societies`, `/investment-rankings/overall` | `noindex, follow`; not in `research.xml` |
| 6 | Name-only project profile | `/projects/mall-of-lahore` | `noindex, follow`, no canonical, page still 200 |
| 7 | Researched project profile | `/projects/dha-lahore` | `index`, canonical to itself, in `projects-1.xml` |
| 8 | Directory pagination | `/projects?page=5` | canonical `…/projects?page=5`; `/projects?city=Lahore` is `noindex, follow` |
| 9 | Dealer sitemap | `/sitemaps/agents.xml` | `arham-estate-builders`, `shoaib-bashir` (the public profiles) |
| 10 | Property sitemap | `/sitemaps/properties-1.xml` | the 11 live listings with `lastmod` |
| 11 | robots.txt | `curl /robots.txt` | includes `Allow: /dealer/register` and `Sitemap: https://meezanproperty.com/sitemap.xml` |
| 12 | llms.txt | `curl -i /llms.txt` | 200, `text/plain`, live counts, `https://meezanproperty.com/...` links |
| 13 | FAQ | `/lahore`, `/areas/etihad-town-lahore` | card visible; FAQPage JSON-LD text identical to the card |
| 14 | Structured data | Google Rich Results Test and validator.schema.org on `/`, `/about`, `/lahore`, an area guide, a listing | valid; no errors |
| 15 | Titles | view source on `/about`, a listing | `About Meezan Property` (no duplicate brand); listing title without suffix |
| 16 | Ranking speed | `curl -w "%{time_starttransfer}"` and the `x-hcdn-upstream-rt` header on `/investment-rankings/housing-societies` | origin time well below the previous 3.1 s |
| 17 | Error log | `tail storage/logs/laravel.log` after the checks | no new errors |

## Search engines (owner)

- [ ] Search Console: verify the domain, submit `https://meezanproperty.com/sitemap.xml`, and use URL Inspection on 3 sample pages. Do not report anything as "indexed" until Search Console says so.
- [ ] Bing Webmaster Tools: verify and submit the sitemap.
- [ ] Fill in `MEEZAN_SAME_AS` once official profiles exist, then run `cache:clear`.

## Core Web Vitals plan (after deploying)

Lab results (Lighthouse or PageSpeed Insights "Diagnose performance issues") and field results (the Chrome UX Report section at the top of PageSpeed Insights, and Search Console → Core Web Vitals) are different things. Report them separately. Field data needs enough real traffic and may say "insufficient data" for a new site.

| Page | Mobile | Desktop |
|---|---|---|
| `/` | PSI | PSI |
| `/lahore` | PSI | PSI |
| `/areas/dha-lahore` | PSI | PSI |
| `/projects/dha-lahore` | PSI | PSI |
| a listing with photos | PSI | PSI |
| `/investment-rankings` | PSI | PSI |

Record for each page and device: LCP (good ≤ 2.5 s), INP (good ≤ 200 ms, field only; lab shows TBT instead), CLS (good ≤ 0.1), TTFB, total transfer size and request count. Run each test 3 times and keep the median. Note the test location: PageSpeed Insights tests from Google's servers, not from Pakistan.

Local server timings in `PERFORMANCE_BEFORE_AFTER.md` are lab measurements of server render time only. They are not Core Web Vitals.

## Rollback

If anything breaks: revert the release commit on `main` (Hostinger auto-pulls the revert), then run `view:clear` and `cache:clear`. No database rollback is needed, because the release has no migrations or data changes.
