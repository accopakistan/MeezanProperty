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

**Front-end assets:** shared hosting usually has no Node, so the built assets in `public/build/` are committed to Git. After any change to CSS, JavaScript or Blade classes, run `npm run build` **locally and commit `public/build/` with the change**. Otherwise a deploy from GitHub serves new pages with an old stylesheet (3 Nov 2026: the header logo showed at full size because the live CSS had no `h-[60px]` class).

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
TRUSTED_PROXIES=                         # empty unless a proxy is confirmed; see below
# Optional, when ready: WHATSAPP_*, MEEZAN_AI_DRIVER=llm + OPENAI_API_KEY
```

**`APP_URL` decides which hosts the site answers.** Only its host and the `www` form of that host are accepted, and any other `Host` gets a plain `400 Bad Request`. This protects password-reset links from a faked `Host`. So:
- set `APP_URL` to the address visitors use. A temporary Hostinger domain needs its own `APP_URL` while you use it, or every page returns 400
- any other domain pointed at the site must redirect at the server or CDN before it reaches Laravel
- point uptime monitors at the domain, not the server's IP address
- run `php artisan optimize` after changing `APP_URL`

**`TRUSTED_PROXIES`: don't use `*`.** It trusts whichever address connects, so anyone who reaches the server directly can fake their IP, which the inquiry and registration limits rely on. Cloudflare alone doesn't stop this: a server's address can be found, and on shared hosting you can't firewall it to accept only Cloudflare. Choose as follows:

| Setup | Setting |
|---|---|
| No proxy in front of the site (check first, section 5) | leave empty |
| Behind Cloudflare | Cloudflare's published ranges, comma-separated, from cloudflare.com/ips. They change occasionally, so recheck them now and then |
| Behind another proxy or Hostinger's CDN | that provider's published addresses. Use `*` only if the provider confirms the server can't be reached except through it |

Whatever the setting, the app takes only the client IP and HTTPS from a proxy. It ignores `X-Forwarded-Host`, `-Port` and `-Prefix`, so links always use the real `Host`. A proxy must therefore pass the original `Host` through; Cloudflare does.

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

Then run the host checks from your own computer. **None of these has been run on Hostinger yet**, so don't skip them. Replace `SERVER_IP` with the address from hPanel:

```bash
curl -sI https://meezanproperty.com/ | grep -iE '^(HTTP|server|cf-ray|x-hcdn|x-cache|via)'
curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: evil.example' https://meezanproperty.com/
curl -s -H 'X-Forwarded-Host: evil.example' https://meezanproperty.com/ | grep -c 'evil.example'
curl -sk -o /dev/null -w '%{http_code}\n' -H 'Host: evil.example' https://SERVER_IP/
```

1. **Is there a proxy?** A header such as `cf-ray`, `x-hcdn-*` or `via` in the first result means a proxy is in front. Set `TRUSTED_PROXIES` as in section 3.
2. **Foreign host:** the second and fourth should print `400`, or another code if Hostinger or the CDN refuses first. They must never print `200`.
3. **Forwarded host:** the third should print `0`.
4. **HTTPS:** view the source of the home page. The canonical tag must start with `https://`. If it shows `http://` behind a proxy, `TRUSTED_PROXIES` is wrong or empty.
5. **Reset link:** request a password reset and check that the email's link is on `https://meezanproperty.com`.

---

## 6. Updating a live site

```bash
php artisan down --refresh=15            # shows the branded 503 page
# pull or upload the new code (public/build is in Git; rebuild locally first if assets changed)
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
