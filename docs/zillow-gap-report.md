# Meezan Property — Zillow-inspired gap report

_Phase A audit, 29 Oct 2026. Zillow is used as a product/UX reference only; no code, branding or content is copied._

## 1. How the reference was researched

- zillow.com served a "Press & Hold" bot check to the automated browser, so it was **not** browsed directly (bypassing it would be inappropriate).
- Features were confirmed from public sources: Zillow Group's own search guide (zillowgroup.com/news/how-to-search-zillow), the Zillow Pro search-tips page (zillow.com/pro/zillow-home-search-tips), and press/consumer coverage of Agent Finder and agent profiles (GeekWire 2015, Consumer Federation of America).
- Exact current menu labels and filter names were not verified and are not relied upon.

## 2. Zillow capabilities and their Pakistani equivalent

| Zillow capability | Relevance to Pakistan | Meezan adaptation |
|---|---|---|
| Buy / Rent / Sell top navigation | Direct | Buy, Rent, Plots, Commercial, Projects, Agents, Sell/List |
| Map + list split search, "search as I move the map" | Direct | Leaflet map, viewport (bounds) search on listings that have real coordinates |
| Draw a custom boundary | Useful | **Pending** — needs a drawing library; viewport search implemented first |
| Filters: price, beds, baths, home type, sq ft, lot size, days listed, keywords | Direct, with local units | Price (PKR), beds, baths, type, plot area (marla/kanal/sq ft), covered area (sq ft), completion status, furnishing, amenities |
| Saved homes | Direct | Saved properties |
| Saved searches with email/push alerts | Direct | Saved searches + in-app alerts; email only when a mail provider is configured and the user opts in |
| Listing page: gallery, facts & features, price history, map, similar homes, contact agent / request tour | Direct | All present; price history recorded by Meezan from asking-price changes only |
| Tax history, Zestimate, school ratings, HOA, mortgage pre-approval, MLS | **US-specific** | Not copied. Replaced by price per marla, possession / completion status, area guides from real listings, indicative loan calculator |
| Agent Finder, agent profiles, reviews, past sales | Relevant (dealers) | Dealer directory + public profiles with verified status and live listings. Reviews and "past sales" omitted — no reliable data source |
| Owner listing / "sell my home" | Relevant | Sell journey for private owners with ownership/authorisation declaration (not legal verification) |
| New construction | Relevant (housing societies) | New Projects directory managed by admins |
| Market research / home value index | Relevant later | Area guides showing **asking** prices only, only with ≥5 comparable listings |
| Report a listing | Relevant (fraud, duplicates) | Report listing + admin reports queue + duplicate detection |
| Compare homes | Useful | Compare up to 4 properties |

## 3. Current implementation vs. requirements (before this phase)

**Already functional:** reference-matched design system and pages; homepage; search with checkbox filters and counts; map page; property details; buy/rent/plots/commercial pages; buyer saved properties, requirements, inquiries; dealer registration, listings, moderation, leads; admin dashboard, moderation, verification, leads, audit log; deterministic AI matcher; consented area-based lead routing; WhatsApp webhook boundary.

**Gaps identified (priority order):**

1. Saved searches and alerts — missing.
2. Map viewport search — missing.
3. Covered area vs plot area, completion/possession status, amenities filter — missing.
4. Property comparison — missing.
5. Notification preferences — missing.
6. Sell journey for private owners; ownership/authorisation declaration — missing.
7. Listing reports and duplicate detection — missing.
8. Find-a-dealer directory and public dealer profiles — missing.
9. Dealer lead capacity — missing.
10. Asking-price history — missing.
11. New housing projects — missing.
12. Area guides with real statistics — missing.
13. Admin location management — missing.
14. AI parser: number words ("five bedrooms"), amenities, optional LLM provider behind the service layer — partial.
15. Draw-boundary search, dealer reviews, subscriptions/payments, SMS — not started (see "Pending").

## 4. Implementation status (end of this phase)

| # | Gap | Status |
|---|---|---|
| 1 | Saved searches + in-app alerts | Done — save from results/map, manage, "N new" counts, alerts page |
| 1b | Email alerts | Done, inactive until a real `MAIL_MAILER` is set and the buyer opts in (`meezan:send-search-alerts`, scheduled daily 08:00) |
| 2 | Map viewport search | Done — "Search as I move the map"; only listings with real coordinates can match, stated on screen |
| 3 | Covered area, completion status, amenities filter | Done (search, listing form, detail page) |
| 4 | Compare | Done — up to 4 listings, session-based |
| 5 | Notification preferences | Done — in-app / email / WhatsApp toggles; providers not live |
| 6 | Sell journey, owner accounts, authorisation declaration | Done — declaration required on every listing; ownership not legally verified |
| 7 | Listing reports, duplicate detection | Done — public report form, admin queue, duplicate review, dealer warning on submit |
| 8 | Dealer directory + profiles | Done — verified or actively listing agencies only; owners excluded |
| 9 | Dealer lead capacity | Done — admin-set limit respected by routing |
| 10 | Asking-price history | Done — recorded from now on for published listings |
| 11 | New projects | Done — admin-curated, public pages; approvals shown as "as stated by developer" |
| 12 | Area guides | Done — asking-price medians only with ≥5 comparable listings |
| 13 | Admin location management | Done — add/rename/order areas; cities via `config/meezan.php` |
| 14 | AI parser upgrades + LLM provider | Done — number words, bathrooms, amenities; optional OpenAI-compatible parser (`MEEZAN_AI_DRIVER=llm`) with rules fallback; not verified against a live API |
| 15 | Draw-boundary search | Done — freehand draw on the map page (`?poly=`), concave shapes supported, savable with searches |
| 16 | Dealer reviews, subscriptions/payments, SMS | Not started |
| 17 | Dealer listing & lead performance reports | Done — `/dealer/performance` (7/30/90 days, CSV export), daily views and response times tracked from 29 Oct 2026 (no backfill), price/marla vs area only with 5+ comparables; 30-day summary on admin dealer page |
| 18 | Zillow-style map pins | Done — purple price pins for exact locations (photo preview on hover, "New" badge); listings without a pin grouped per area at the area's OpenStreetMap centre, dashed and labelled approximate; map plots all results (up to 500), not just the current page; `meezan:geocode-areas` fills area centres (35/39 found; edit the rest in Admin → Locations) |
| 19 | Dealer location pin picker | Done — click/drag pin, typed coordinates, clear, use my location, Pakistan-only place search (moves map only); server-side pair + Pakistan-bounds validation; area centres never saved as exact; exact/approximate/not-provided labels on listing and admin pages. See `docs/Meezan_Property_Phase_1_Completion_Report.md` |
| 20 | Notifications & listing lifecycle | Done — queued service emails (inquiries, leads, moderation, expiry, contact form, verification), daily expiry + reminder job, dealer Renew, optional email-verification enforcement (`MEEZAN_REQUIRE_EMAIL_VERIFICATION`). Mail is `log` until a provider is configured. See `docs/Meezan_Property_Phase_2_Completion_Report.md` |
| 21 | Trust & media | Done — private dealer verification documents (admin-only viewing, audited), photo pipeline (EXIF-upright, ≤1600 px WebP + 640 px thumbnail, metadata/GPS stripped), cover choice and photo ordering, `meezan:optimize-images` for older photos. See `docs/Meezan_Property_Phase_3_Completion_Report.md` |
| 22 | Discovery & engagement | Done — in-app notification centre (all roles, bell + unread counts, daily pruning), "Listed within" and "Price reduced" filters with card badges, days on market, Nearby Places from OpenStreetMap (exact pins only; live data not reachable from the dev machine). See `docs/Meezan_Property_Phase_4_Completion_Report.md` |
| 23 | Production readiness (local) | Done — security headers + strict CSP (no inline script), branded error pages, `meezan:production-check`, cache-safe config, queue-via-scheduler for shared hosting, test log isolation, query budgets, Hostinger runbook. See `docs/Meezan_Property_Phase_5_Completion_Report.md` |
| 24 | Local QA (Phase 6) | Done — signed-in dealer/buyer QA; fixed saved-list "Remove" re-saving, CSP nonce (Boost dev logger), singular result headings, plain upload errors; admin browser QA blocked (admin password changed). See `docs/Meezan_Property_Phase_6_Completion_Report.md` |
| 25 | National multi-city expansion | Done — 7 launch cities, 1,238 researched locations with hierarchy, aliases and authority status (CDA/QDA/MDA official, LDA 2021 secondary), city landing pages, city-aware search/maps/listing form/AI/lead routing, multi-city dealers, admin location edit/merge. See `docs/Meezan_Property_National_Expansion_Completion_Report.md` |
| 26 | AI property intelligence (Phase 7) | Done — conversation memory (website; WhatsApp-ready), English/Roman Urdu/Urdu parsing, 7-city location intelligence with clarifying questions, budget-tolerant ranking with factual explanations, requirement saving, consented leads to ≤3 city-matched dealers, consent records, hardened WhatsApp webhook, admin AI management. WhatsApp sending and LLM credential-blocked. See `docs/Meezan_Property_Phase_7_AI_Completion_Report.md` |
| 27 | Marketplace trust (Phase 8) | Done — dealer verification lifecycle with per-document review and audit, private documents, location privacy (exact/approximate/area) applied to every public map and search, location-quality flags, completeness score, photo moderation, changes-requested/suspended listing states, explained duplicate detection, trust dashboard. See `docs/Meezan_Property_Phase_8_Marketplace_Trust_Completion_Report.md` |
