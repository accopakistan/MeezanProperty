# Meezan Property: Production Deployment Report

Date: 6 November 2026.

## Status: not deployed

**The live site was not updated.** I have no access to the production server or its database, so I can't take the required production backup or run migrations there. You also asked me not to commit yet.

Pushing to `main` alone would be unsafe. The new code needs new database columns, so pages would fail until the migrations run on the server.

## What is waiting to go live

All of it is on the local copy, uncommitted, on top of `main` at `212e334`:

- **Public showcase:** contact details, WhatsApp button, honest legal and package pages, Investment Index wording, SEO fixes, structured-data fix
- **Mega menu**
- **Sample content:** the cleanup and the test-account rule
- **Rankings:** the 10-project leaderboard limit
- **Listings:** the publication basis and the authorised listing importer
- **AI:** knowledge updates

### Migrations

All additive or data-only, with no drops.

| Migration | Effect |
|---|---|
| `2026_11_06_000060_archive_sample_and_test_content` | Adds `dealer_profiles.is_demo`; archives the sample listings and marks sample dealers |
| `2026_11_06_000061_update_ai_knowledge_for_public_launch` | Adds and updates AI knowledge documents |
| `2026_11_06_000062_add_publication_basis_to_properties` | Adds the publication-basis columns; backfills `direct_submission` for real listings |

Earlier migrations `000055` to `000059` are already on `main`. If the server hasn't run them yet, they run in the same command.

## Deployment steps

1. **Commit and push**, when you approve. I can do this.
2. **On the server, back up the database:**
   ```
   mysqldump --single-transaction meezanproperty > backup-2026-11-06.sql
   ```
3. **Pull the code**, or let your GitHub deployment run. Leave `.env` untouched.
4. **Install dependencies:** `composer install --no-dev --optimize-autoloader`. `public/build` is committed, so npm isn't needed on the server.
5. **Migrate:** `php artisan migrate --force`
6. **Flag test content:**
   ```
   php artisan meezan:flag-sample-content
   php artisan meezan:flag-sample-content --commit
   ```
   Run the dry run first; commit only if its list is right.
7. **Load the Index research:** `php artisan meezan:investment-research-import`. It imports the bundled research files; nothing is copied from the local database.
8. **Cache:** `php artisan optimize`, then turn on OPcache.
9. **Check queue and scheduler:**
   - Cron runs `php artisan schedule:run` every minute.
   - On shared hosting, set `MEEZAN_QUEUE_VIA_SCHEDULER=true`.
10. **Check `.env`:**
    - `WHATSAPP_MOCK_MODE=true`.
    - The contact settings are empty or correct.
    - `APP_URL=https://meezanproperty.com`, so `www` redirects.
11. **Check the live site:**
    - home, `/agents` and `/properties` show no sample content
    - `/contact`
    - `/investment-rankings`
    - the mega menu at desktop and mobile widths

**Never copy the local database to production.**
