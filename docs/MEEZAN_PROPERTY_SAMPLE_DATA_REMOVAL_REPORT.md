# Meezan Property: Sample Data Removal Report

Date: 6 November 2026. **Local database only.** The live database has not been touched; see the production deployment report.

## Records found and what happened to each

Each record was inspected individually. Nothing was deleted, and every change is in the audit log.

| ID | Record | Evidence it is sample or test | Before | Now |
|---|---|---|---|---|
| MP-000001 to MP-000016 | 16 listings by "Sample Realty" | Flagged `is_demo`; descriptions start "Sample listing for platform preview" | Published | **Archived**, sample |
| MP-000017 | "QA test listing - location picker check (draft, safe to remove)" | Title; account `dealer@meezanproperty.test` | Draft | Draft, flagged as sample |
| MP-000018 | "10 Marla House in DHA Lahore", Jonathon Estate | Description is placeholder text ("pseudo-Latin placeholder text…"); you confirmed the dealer is a test account | Published | **Archived**, flagged as sample |
| MP-000019 | "QA TEST 5 Marla House in Lake City (local test)" | Created today at 10:22 by `qa.dealer@meezanproperty.test`, apparently from your other account's QA session | Published | **Archived**, flagged as sample |
| Dealer 1 | Sample Realty | "Sample dealer profile used for local development"; `@meezanproperty.test` | Verified, public | Hidden, no badge, no leads |
| Dealer 2 | Jonathon Estate | Confirmed test dealer | Verified, public | Hidden, no badge, no leads |
| Dealer 3 | QA Test Realty | `qa.dealer@meezanproperty.test` | Pending, public through its listing | Hidden |

**Result:** 0 published listings and 0 public dealers. Verification records, documents, users and listing data are all kept.

- The sample dealers' contact details (Sample Realty had +923111749849, Meezan's own WhatsApp) no longer appear anywhere, because the profiles aren't public.
- MP-000018 is archived rather than sent back for correction, because its dealer is a confirmed test account. If Jonathon is a real dealer, an admin can request changes on it instead.

## The reusable mechanism

| Layer | What it does |
|---|---|
| `Property::published()` excludes `is_demo` | Every public query: home, search, Buy, Rent, categories, city and area pages, map, similar listings, sitemap, AI Property Finder, agent tools |
| `DealerProfile::isVerified()` is false for samples; the directory and lead routing skip them | No badge, no listing in `/agents`, no real buyer leads |
| **New:** `App\Support\SampleContent` | An account on a reserved test domain (`.test`, `.example`, `.invalid`, `.localhost`; these can never be a real customer's) has its dealer profile and listings created as samples automatically. MP-000019 would have been caught this way |
| Placeholder rule | Descriptions with "lorem ipsum", "dolor sit amet" or "placeholder text" are rejected at submission and at import |
| **New:** `php artisan meezan:flag-sample-content` | Finds unflagged test-account and placeholder records. A dry run by default; `--commit` flags them and archives published ones, with an audit entry for each |

A genuine listing is never flagged for an unusual price or missing details. The rules look only at test accounts and placeholder text.

## On the live site

Migration `2026_11_06_000060` archives the 17 records the QA report found. After deploying, run `php artisan meezan:flag-sample-content` as a dry run, then with `--commit` if the list is right.

## To reverse

- **A listing:** republish it in admin, after clearing `is_demo` for a genuine record.
- **A dealer:** set `is_demo` back to false.

The audit log shows who changed what, and why.
