# Meezan Property: Sample Data Cleanup Report

Date: 6 November 2026. **Nothing was deleted.** Every record is still in the database and in admin.

## What was found

I inspected the records on the local copy of the database, which holds the same 18 listings and 2 dealers the QA report saw on the live site.

| Record | What it is | Evidence |
|---|---|---|
| Listings 1–16 | Sample listings from "Sample Realty" | Flagged `is_demo`. Every description starts "Sample listing for platform pr…" |
| Listing 18, "10 Marla House in DHA Lahore" (PKR 1.7 Crore) | Test listing by "Jonathon Estate" | The description is placeholder text ("a nonsensical, pseudo-Latin placeholder text…"). The price is implausible for 10 Marla in DHA |
| Listing 17 | QA draft ("safe to remove") | Already a draft, never public. Left as it was |
| Dealer 1, "Sample Realty" (`demo-realty-sample-data`) | Sample dealer | Bio "Sample dealer profile used for local development"; user `dealer@meezanproperty.test`; marked Verified |
| Dealer 2, "Jonathon Estate" (`jonathon-estate`) | Test dealer, as confirmed in your brief | Marked Verified |

## What changed

Migration `2026_11_06_000060_archive_sample_and_test_content`:

| Change | Records | Reversible |
|---|---:|---|
| New column `dealer_profiles.is_demo` (default false) | — | Additive |
| Dealers marked sample or test (`is_demo = true`). Their verification records are kept, and the badge no longer shows | 2 | Clear the flag |
| Published sample listings set to **archived** | 16 | Republish in admin |
| Published test listing with placeholder text set to **archived** | 1 | Republish in admin |

Each change is written to the audit log (`dealer.marked_sample`, `property.archived`, with the reason).

The migration matches records by specific signals only:

- **Dealers:** a "Sample dealer profile" bio, an `@meezanproperty.test` email, or the confirmed slug `jonathon-estate`.
- **Listings:** the `is_demo` flag, or the phrases "lorem ipsum" or "pseudo-Latin placeholder text" in the description.

On the live database it will archive the same records, and nothing else.

## How sample content is kept off public pages

These are code rules, not just a data change, so a sample record left published by mistake still can't appear.

| Rule | Where | Effect |
|---|---|---|
| `Property::published()` excludes `is_demo` | `app/Models/Property.php` | Every public list, count, map, city, area, similar-listings and sitemap query uses this scope |
| `Property::isPublic()` is false for sample listings | Same | A sample listing's page returns 404 to visitors |
| The dealer directory excludes `is_demo` dealers | `DealerDirectoryController` | Hidden from `/agents`, and their profile page returns 404 |
| `DealerProfile::isVerified()` is false for sample dealers | `app/Models/DealerProfile.php` | No "Verified" badge anywhere |
| Lead routing excludes sample dealers | `Leads/LeadRouter.php` | Real buyer requirements never reach a test account |
| Verified-dealer counts exclude sample dealers | AI finder, city directory, search filter, agent tools | No inflated counts |
| Placeholder text is rejected at submission | `PropertyRequest` | Descriptions containing "lorem ipsum", "dolor sit amet" or "placeholder text" fail validation |

## Records preserved

All 18 listings, both dealers, their users, documents, verification history, images and audit history are kept. No genuine dealer exists yet, so no genuine verification record was touched.

## Result on public pages

Checked locally after the change. There are **0 published listings and 0 public dealers**, so the homepage, search, city and area pages show their empty states and invite dealers to list. No fake inventory was created to fill them.

## Not done here

- The **price-outlier check** suggested by the QA report (flagging, for example, PKR 1.7 Crore for 10 Marla in DHA) isn't implemented. It needs a price reference you trust, and verified market prices don't exist yet.
- The admin account is still named **"Meezan Admin (Demo)"**. Renaming it is your decision.

## On the live site

The cleanup runs when `php artisan migrate --force` is run after deployment. Take a database backup first.
