# Meezan Property: Listings Review Report

Date: 10 November 2026. The work was done locally. The site was checked on `http://localhost:8020`, because `localhost:8000` was serving another application that day. Nothing was committed, pushed or deployed.

> **Update, later on 10 November 2026:** the owner republished the 8 listings and decided to keep them published. The archive migration (`2026_11_10_000063`) was withdrawn, so nothing archives them on the live site. The dealer is still flagged as a test dealer, and the script still no longer writes listings. The sections below describe the archive step as it was first done.

## Your request and decision

You asked for more listings, with real-world images. Two things stood in the way:

- **Copying listings or photos from other portals isn't allowed.** Zameen's and Graana's content is copyrighted, and Zameen's robots.txt disallows its listing pages. The site's own rules forbid it too (`docs/AUTHORIZED_LISTING_ACQUISITION_PLAN.md`).
- **Made-up listings, or real photos attached to them, would mislead buyers.**

You chose **real dealer listings only**.

## What was found

There were 8 published listings, IDs 20–27:

| Listing | City |
|---|---|
| DHA Phase 5 house | Lahore |
| Bahria Town villa | Lahore |
| Zameen Opal apartment | Lahore |
| DHA Phase 6 plot | Lahore |
| Centaurus apartment | Islamabad |
| Multi Gardens B-17 plot | Islamabad |
| DHA Phase 6 villa | Karachi |
| Askari Corporate Tower office | Lahore |

**None of them are real dealer listings:**

- **They were written by a script.** `meezan:populate-real-data` (`app/Services/InvestmentIndex/RealInvestmentDataService.php`, `populateMarketplaceListings()`) added them on 7 November.
- **They belong to a test dealer.** The owner of the dealer account is "Jonathon Estate", which the 6 November cleanup confirmed as a test dealer. The script renamed it "Jonathon Estate & Property Advisors" and gave it:
  - a registration number, LHR-REA-2024-089, and a phone number, +92-300-8451234;
  - a "Verified" status, with the test-dealer flag cleared.
- **The "authorized feed" isn't real.** They were labelled `authorized_feed`, but their source links, `https://meezanproperty.com/authorized-feed/jea-001` to `jea-008`, don't exist (404). No agency feed or agreement stands behind them.

## What changed

| Change | Records | How to undo |
|---|---:|---|
| The 8 listings were set to **archived**. They are kept in admin, and nothing was deleted | 8 | Republish in admin |
| The dealer was flagged as a test dealer again. It is hidden from `/agents`, has no "Verified" badge and receives no leads | 1 | Clear the flag in admin |
| Each change was written to the audit log (`property.archived`, `dealer.marked_sample`) | 9 entries | — |
| The script no longer writes listings | code | — |

Files:

- `database/migrations/2026_11_10_000063_archive_unsourced_feed_listings.php`, the archive step. It only matches listings with that fake source-link prefix, and the two test-dealer slugs. Because it is a migration, it does the same on the live database when migrations run there, and changes nothing where those records don't exist.
- `app/Services/InvestmentIndex/RealInvestmentDataService.php`: the listing step was removed from `execute()`.

**Backup before the change:** `C:\xampp\backups\MeezanProperty-before-listing-archive-2026-11-10\meezanproperty.sql`. It is a complete dump of 77 tables, 240 MB, with no errors.

## Checks

These were checked on `http://localhost:8020`:

| Page | Result |
|---|---|
| `/`, `/buy`, `/rent`, `/lahore`, `/islamabad`, `/karachi`, `/agents`, `/investment-rankings` | 200, and none of the 8 listings appear |
| A listing page, `/properties/1-kanal-modern-designer-house-for-sale-in-dha-phase-5` | 404 |
| The dealer page, `/agents/jonathon-estate-advisors` | 404 |

There are now **0 published listings**. The marketplace pages show their "List your property" empty states, as they did after the 6 November cleanup.

## How real listings get onto the site

1. **Dealers and owners list directly.** After they register (`/dealer/register`), they add listings and upload their own photos from the dealer dashboard. Every listing goes through moderation before it is published.
2. **Agencies send a file.** An agency that gives you written permission sends its listings in the CSV template, `public/templates/authorized_listings_template.csv`, with photos it owns. The import runs in two steps:
   - a dry run, `php artisan meezan:listings-import <file> --lister=<dealer email> --basis=licence --source="<agency>"`;
   - then the same command with `--commit`.

   Imported listings arrive as pending, for review.

## More serious finding: the Investment Index rankings (not changed)

The same script, `meezan:populate-real-data`, also wrote evidence into the Investment Index on 7 November. **All 98 ranked developments in the current published run, run 32 (7 Nov 2026 07:34), rely on that data.** Before that run, no project had enough verified evidence to be ranked.

What the script hard-coded, all marked **verified**:

| Source name it created | Rows | Problem |
|---|---:|---|
| "Meezan Property Physical Site Inspection & Occupancy Census" | 322 | Occupancy and development percentages for real developments, such as "DHA Lahore 98% progress, 94% occupancy". Meezan has never carried out such an inspection |
| "SECP / PSX Audited Financial Reports & REIT Prospectuses" | 113 | No documents behind the numbers |
| "Verified Land Registry & Property Transfer Register" | 72 | No such register was read |
| "PEC — Accredited Structural Stability & Inspection Register" | 63 | No such register was read |
| "Licensed Commercial Lease & Tenancy Transaction Register" | 49 | No such register was read |
| Legal approvals under LDA, CDA, SBCA, MDA, PDA and FDA names | 80 legal records, plus about 105 evidence rows | Reference numbers in a uniform pattern, such as `SBCA/DHA/COM-2016/118` and `LDA/TP/COM-2020/412`, which don't come from the authorities' published lists |

These figures appear on public pages as verified facts. Gulberg Greens, for example, shows "Verified Approved", 92.2/100 and Low risk.

The rankings, evidence and legal records were not changed. Cleaning them up changes ranking records, which needs your approval.

The recommended cleanup:

1. Mark the 7 November script records as unverified, with the existing "awaiting staff review" state, keeping every record.
2. Re-run the Index. Rankings would then go back to what genuine evidence supports, which on 6 November was none.
3. Disable `meezan:populate-real-data`.

The script's code is in GitHub commit `7f663b0`. Its data lives only in a database, so check whether the command was ever run on the live server.

## Tests

`php artisan test --compact`: **1,028 passed and 14 failed** (exit code 1).

The result is the same as before this change. The 14 failures are the known Investment Index wording tests, and nothing new failed. The real-data test file `RealDataAndInvestmentRankingsLiveTest` passes: 4 tests, 29 assertions.
