# Performance recommendations

## What the live time is made of (measured 28 November 2026)

External requests from this machine to `https://meezanproperty.com` (one run each). `x-hcdn-upstream-rt` is the Hostinger CDN's own measure of how long the origin server took.

| URL | TCP connect | TLS done | First byte | Origin time (CDN header) | CDN cache |
|---|---|---|---|---|---|
| `/` | 0.73 s | 1.25 s | 1.84 s | 0.35 s | DYNAMIC |
| `/lahore` | 0.25 s | 0.74 s | 1.30 s | 0.33 s | DYNAMIC |
| `/areas/dha-lahore` | 0.26 s | 0.78 s | 1.35 s | 0.32 s | DYNAMIC |
| `/projects/dha-lahore` | 0.24 s | 1.27 s | 1.82 s | 0.32 s | DYNAMIC |
| `/about` | 0.24 s | 0.72 s | 1.25 s | 0.29 s | DYNAMIC |
| `/investment-rankings` | 0.26 s | 0.78 s | 3.09 s | **2.06 s** | DYNAMIC |
| `/investment-rankings/housing-societies` | 0.25 s | 0.72 s | 4.09 s | **3.14 s** | DYNAMIC |
| `/robots.txt` (static file) | 0.26 s | 0.76 s | 1.24 s | 0.23 s | REVALIDATED |
| `/public/favicon.svg` (static file) | 0.25 s | 0.74 s | 1.21 s | 0.23 s | MISS |

On the server itself (read-only script):

| Item | Time |
|---|---|
| Composer autoload | 24–32 ms |
| Laravel bootstrap (config not cached, routes not cached) | 158–162 ms |
| Database connect | 8 ms |
| Database round trip (`select 1`) | 0.1–0.2 ms |
| OPcache | on |
| Cache / session drivers | file / database |
| Server load average | about 33 on a 64-core shared host |

## Findings

1. **Most of the 1.2–1.8 s first byte is network and TLS between the visitor and the CDN edge.** A static file has the same first byte as a dynamic page. These measurements were taken from outside Pakistan, through the Kuala Lumpur edge, so visitors in Pakistan may see different numbers; PageSpeed Insights field data will show the real figure.
2. **Normal pages take about 0.3 s at the origin.** The database is fast. Laravel boot (about 0.16 s) is the largest server-side part.
3. **The investment ranking pages take 2–3 s at the origin.** That is the `marketBoard` sorting problem already fixed locally (5–9 s → about 1.2–1.4 s locally). Deploying the fix is the biggest available gain.

## Recommendations, in order

| # | Action | Expected gain | Risk | Approval |
|---|---|---|---|---|
| 1 | Deploy the SEO release (includes the ranking speed fix) | Ranking pages' origin time: 2–3 s down to roughly 0.5–1 s | Low | Needed (deployment) |
| 2 | `php artisan route:cache` and `view:cache` in the deploy steps | Part of the 0.16 s boot | Low; any route closure would break route caching, so test first | Needed |
| 3 | `config:cache` | More of the boot time | Medium: the live `.env` is edited by hand and the project notes say to keep config uncached; every `.env` edit would need a re-cache | Owner decision |
| 4 | Full-page cache for signed-out visitors | At most about 0.3 s per page (the origin time); network/TLS unchanged | Medium–high (see below) | Not recommended now |
| 5 | Ask Hostinger about the CDN edge used for Pakistani visitors, HTTP/3 (already advertised) and TLS session reuse | Possibly the largest part of TTFB | None to the code | Owner (hosting) |
| 6 | Convert `images/places/home.jpg` (272 KB) to WebP/AVIF; self-host fonts; icon sprite | LCP and transfer size | Low–medium; must stay visually identical | Needed |

## Full-page cache: why it is not recommended yet, and how to do it safely later

The gain is small (origin time is already about 0.3 s) and the risks are real:

- **CSRF tokens:** public pages contain forms (inquiry, save, search, newsletter, login link states). A cached page would serve one visitor's CSRF token to everyone, and form posts would fail with 419. A safe version must either render forms without a token and fetch it by script, or exclude every page with a form.
- **Sessions:** the session cookie is started on every response. A cache must skip any request with a session or auth cookie and must never store `Set-Cookie` responses.
- **User-specific content:** the header shows "Saved" counts and sign-in state; flash messages appear after actions. All of these vary per visitor.
- **Invalidation:** listings, projects, areas and dealer profiles change through dealer and admin actions. Every save would have to clear the related pages.

A safe plan, if it is wanted later:

1. Cache only `GET` requests from visitors with no session or auth cookie, and only status 200.
2. Use an allow-list of templates with no forms: area guides, project profiles, the Index methodology, About, How it works.
3. Never cache admin, dealer, account, auth, compare, map or search-result pages.
4. Use a short TTL (5–10 minutes), plus model events that clear the affected URLs.
5. Add a `Vary: Cookie` header, and test the full checklist (registration, login, logout, inquiry forms, dealer forms, CSRF, filters, admin) before switching it on.

Nothing in this file has been implemented except the ranking speed fix, which is in the pending release.
