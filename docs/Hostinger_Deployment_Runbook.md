# Meezan Property — Hostinger Deployment Runbook

A step-by-step guide for the first deployment, and for updates after it, to Hostinger (hPanel shared/Business/Cloud hosting with SSH). Written 30 Oct 2026 against the local project; nothing here has been run on a server yet.

> **Order matters.** Never run `migrate:fresh`, `db:wipe` or `db:seed --class=DemoContentSeeder` on the server. The demo seeder refuses to run outside local and testing anyway.

---

## 0. Before you start (decisions and accounts)

| Item | Why | Setting |
|---|---|---|
| Domain + SSL for `meezanproperty.com` | HTTPS is required (secure cookies, HSTS) | hPanel → SSL |
| MySQL database and user | Application data | hPanel → Databases → `DB_*` |
| Mail provider (Hostinger mailbox SMTP, or Postmark/Resend/SES) | Verification links, inquiries, alerts | `MAIL_*` |
| Map tiles for production traffic | Public OpenStreetMap tiles are for light use only | `MEEZAN_MAP_TILE_URL`, `MEEZAN_MAP_ATTRIBUTION` |
| Overpass server for Nearby Places (optional) | The public server limits use, and blocked the development machine | `MEEZAN_OVERPASS_URL`, or `MEEZAN_NEARBY_ENABLED=false` |
| Company contact email, phone, WhatsApp | Shown on the site; receives contact-form mail | `MEEZAN_CONTACT_*` |
| Backups | Hostinger daily backups, plus your own database export before each release | hPanel → Backups |

**PHP:** 8.2 or newer (the project uses 8.2.12). Extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `exif`, `gd` (**with WebP**), `curl`, `tokenizer`, `xml`, `ctype`. In hPanel → Advanced → PHP Configuration, enable `exif` and `gd` if they are off.

---

## 1. Upload the code

Use version control when you are ready (deferred for now), or upload a zip. **Do not upload:**
- `.env`, `node_modules/`, `vendor/` (installed on the server)
- `storage/logs/*`, `storage/framework/{cache,sessions,views}/*`
- `storage/app/private/*` (verification documents stay out of transfers)
- `storage/app/public/*` (photos, unless you are migrating them on purpose)
- `.backups/`
- `public/Refrence/` (design references, not needed on the live site)

**Layout:** Hostinger serves `public_html/`. Recommended:
- the app goes in `~/meezanproperty/`
- the **domain's document root** points to `~/meezanproperty/public` (hPanel → Websites → Advanced → change the document root, or use a symlink)
- never put the whole project inside `public_html/`, because `.env` and `storage/` must not be web-reachable

**Front-end assets:** shared hosting usually has no Node. Run `npm ci && npm run build` **locally** and upload `public/build/`.

---

## 2. First-time setup (SSH, in `~/meezanproperty`)

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env            # then edit .env (section 3)
php artisan key:generate        # FIRST INSTALL ONLY: a new key logs everyone out and breaks encrypted data
php artisan migrate --force
php artisan db:seed --force     # reference data only (Lahore areas); safe to re-run
php artisan storage:link
php artisan meezan:geocode-areas   # approximate area centres from OpenStreetMap; set the 4 missing ones in Admin → Locations
```

Create the first admin with `php artisan tinker`: create a user and set `role` to `admin` with `forceFill`. Use a strong password, and never reuse the local demo accounts.

---

## 3. Production `.env` (names only, no secret values)

```
APP_NAME="Meezan Property"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://meezanproperty.com
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=…  DB_PORT=3306  DB_DATABASE=…  DB_USERNAME=…  DB_PASSWORD=…

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
MEEZAN_QUEUE_VIA_SCHEDULER=true       # shared hosting: the cron sends queued emails

MAIL_MAILER=smtp  MAIL_HOST=…  MAIL_PORT=465  MAIL_USERNAME=…  MAIL_PASSWORD=…  MAIL_SCHEME=smtps
MAIL_FROM_ADDRESS=no-reply@meezanproperty.com
MAIL_FROM_NAME="Meezan Property"

MEEZAN_CONTACT_EMAIL=…  MEEZAN_CONTACT_PHONE=…  MEEZAN_WHATSAPP_NUMBER=…
MEEZAN_REQUIRE_EMAIL_VERIFICATION=true   # only after a test email has arrived
MEEZAN_CSP_ENABLED=true
MEEZAN_MAP_TILE_URL=…                    # production tile provider
MEEZAN_OVERPASS_URL=…                    # or MEEZAN_NEARBY_ENABLED=false
TRUSTED_PROXIES=                         # "*" only if the site sits behind Cloudflare
# Optional, when ready: WHATSAPP_*, MEEZAN_AI_DRIVER=llm + OPENAI_API_KEY
```

---

## 4. Cron (hPanel → Advanced → Cron Jobs)

Every minute:
```
* * * * * cd /home/USER/meezanproperty && php artisan schedule:run >> /dev/null 2>&1
```
This runs:
- `meezan:listing-lifecycle` (07:00): expiry and reminders
- `meezan:send-search-alerts` (08:00)
- notification clean-up (03:00)
- with `MEEZAN_QUEUE_VIA_SCHEDULER=true`, a queue run every minute, so emails go out within about a minute

On a VPS or Cloud plan with Supervisor, run `php artisan queue:work --tries=3` as a service instead and set `MEEZAN_QUEUE_VIA_SCHEDULER=false`.

---

## 5. Cache and verify

```bash
php artisan optimize          # config, routes, views and events cached
php artisan meezan:production-check
```
`meezan:production-check` is read-only:
- it must report **0 FAIL**
- review every WARN: demo listings, `.test` accounts, map and Overpass providers, email enforcement
- it exits with code 1 while any FAIL remains

Then check in a browser:
1. Home, search, map, a listing, the Nearby section (if enabled).
2. Register a test buyer: the confirmation email arrives and the link works.
3. Dealer: create a draft with a photo and a pin; the photo appears as WebP.
4. Admin: settings page shows 0 queued or failed emails.
5. The response headers include `Content-Security-Policy` and `Strict-Transport-Security`.

---

## 6. Updating a live site

```bash
php artisan down --refresh=15            # shows the branded 503 page
# upload new code and public/build
composer install --no-dev --optimize-autoloader
php artisan migrate --force              # additive migrations only; back up the database first
php artisan optimize                     # re-cache; run again after every .env change
php artisan up
php artisan meezan:production-check
```

---

## 7. Data on day one

- The 16 demo listings and the `.test` accounts exist only in the **local** database. A fresh server database starts without them. Don't copy the local database across.
- If you do migrate data, remove demo listings (`is_demo = 1`) and demo accounts first, then run `php artisan meezan:optimize-images` for any real photos.
- Verification documents (`storage/app/private`) are personal data. Transfer them only over SFTP, and keep them out of backups that leave Hostinger, unless they're encrypted.

---

## 8. Known operational notes

- **Laravel Boost** (dev only) injects a small inline script locally that the Content-Security-Policy blocks; it isn't installed with `--no-dev`.
- **Lazy loading:** missed eager loads are logged in production (`Lazy-loaded relation` warnings) instead of failing. Check the log weekly in the first month.
- **Error pages** (403/404/419/429/500/503) are branded and never show debug details when `APP_DEBUG=false`.
