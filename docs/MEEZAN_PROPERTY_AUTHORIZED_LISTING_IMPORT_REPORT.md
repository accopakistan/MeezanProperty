# Meezan Property: Authorized Listing Import Report

Date: 6 November 2026.

## Result

**0 listings imported, because no authorised source exists yet.** The import system is built and tested, ready for the first agency feed or licence.

## Publication basis

New columns on `properties` (migration `2026_11_06_000062`, additive): `publication_basis`, `source_name`, `source_url`, `source_reference`, `source_verified_at`.

| Basis | Meaning |
|---|---|
| `direct_submission` | The dealer or owner submitted it on Meezan Property. Set automatically, and backfilled for existing real listings |
| `authorized_feed` | The listing agency's own feed |
| `licence` | Licensed data |
| `partnership` | A data partnership |

**A listing with no basis can't be published.** Admin approval refuses it with a clear message, and the model blocks publication by any other route (renewal, script). An advert seen on another portal is not a basis. Admin listing pages show the basis and the source.

## The importer

```
php artisan meezan:listings-import feed.csv --lister=agency@example.com --basis=authorized_feed --source="Agency name"
php artisan meezan:listings-import feed.csv --lister=agency@example.com --basis=authorized_feed --source="Agency name" --commit
```

| Rule | Behaviour |
|---|---|
| Dry run first | Nothing is saved without `--commit` |
| Basis | Must be `authorized_feed`, `licence` or `partnership` |
| Source | Must be named |
| Lister | Must be an active real dealer account, not a sample. The listings belong to that dealer |
| Required columns | `source_reference`, `source_url`, `title`, `purpose`, `type`, `city`, `area`, `area_size`, `area_unit`, `price`, `description` |
| Optional columns | `address`, `bedrooms`, `bathrooms`, `furnishing`, `completion_status`, `amenities` (separated by `;`), `authorization_type`, `checked_on` |
| Validation | The same rules as a dealer's form, including the placeholder-text rule |
| Area | Must match an area in that city, otherwise the row is rejected |
| Duplicates | Same source and reference, or same source URL, already imported or repeated in the file. Similar price and size alone never merge two properties |
| Publication | **Never direct.** Rows enter the moderation queue as "pending review" |
| Photos | Not imported. They need their own permission and are added by the lister |
| History | An audit entry per listing and one per import run |

## Tested

- A dry run saves nothing.
- A commit imports valid rows as pending.
- Duplicates, placeholder text, an unknown area and a missing reference are each rejected with a reason.
- A second run imports nothing new.
- A portal-advert basis, a missing source and a sample lister are each refused.
- An unpublished import isn't public.
- Publishing without a basis is blocked.

## Records

None imported, so there's no record table. The first import will list each record's ID, name, city, category, source URL, basis and status here.
