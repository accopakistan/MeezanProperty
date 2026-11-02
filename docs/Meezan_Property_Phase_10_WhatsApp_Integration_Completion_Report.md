# Meezan Property — Phase 10 Completion Report
## Live WhatsApp Business Integration, AI Automation & Dealer Lead Workflow

Date: 2 November 2026 · Environment: localhost / XAMPP (Laravel 12.70, PHP 8.2, MariaDB) · No Git commits, no GitHub push, no deployment in this phase.

Status key: **COMPLETE AND VERIFIED** · **COMPLETE BUT CREDENTIAL-BLOCKED** · **PARTIALLY IMPLEMENTED** · **NOT IMPLEMENTED**

---

## 1. Executive summary

The existing WhatsApp foundation (Phase 7) is now a full integration layer:

- **Provider abstraction:** `WhatsAppProvider` with a **Meta Cloud API** provider and a **TEST / MOCK** provider, chosen by `MEEZAN_WHATSAPP_DRIVER` (or by test mode).
- **Queued pipeline:**

  ```
  webhook → signature + shape + idempotency → normalisation → stored once
          → ProcessInboundWhatsAppMessage job → the same ConversationAssistant as the website
          → WhatsAppFormatter → OutboundMessenger → MessagePolicy → SendWhatsAppMessage job
          → provider → status
  ```

- **Message policy:** separates transactional, notification and promotional messages. It enforces opt-out (STOP), consent for notifications, Meta's 24-hour customer-service window (templates outside it), rate limits and the admin automation switches.
- **Outbound log:** `outbound_messages` records every outgoing message (category, purpose, related conversation and lead, status, provider reference). It has dedupe keys, bounded retries for temporary errors, staff retry, and delivery statuses taken only from the provider.
- **Dealer lead alerts:** sent through the existing `LeadAssigned` notification via a WhatsApp channel (no duplicate notification records). They are explicitly opt-in and contain no buyer contact details. Outside the 24-hour window they need an approved template.
- **Admin WhatsApp control center:**
  - integration checklist that never shows secret values;
  - live counts and the three switches (automation, AI auto-reply, test mode);
  - connection test, and the outbound log with retry;
  - a local-only mock console;
  - staff replies from the conversation screen.
- **Account linking:** a buyer links a WhatsApp chat to their account with a one-time code (`LINK 123456`), never by phone number alone.

**Results:** 438 tests pass (2,215 assertions, 0 failures), including 33 new WhatsApp tests. The 11 existing WhatsApp tests and 43 assistant tests pass unchanged. Pint, the build, both audits and the route cache are clean.

**Not live yet:** no real WhatsApp message has been sent or received. That needs Meta credentials and a public HTTPS address (§37–38). All manual testing used the mock provider.

---

## 2. Initial WhatsApp architecture audit

| Component | Before Phase 10 | Status found |
|---|---|---|
| Webhook verify + receive (`WhatsAppWebhookController`) | Handshake; signature (`X-Hub-Signature-256`); shape; idempotent `webhook_events`; 500 + admin alert on failure | Implemented, tested |
| `WebhookProcessor` | Stored inbound once per WhatsApp id; ran `ConversationAssistant` **synchronously**; sent the reply in the same request | Implemented; no queue, no outbound record |
| `WhatsAppClient` | One Meta text call; sends only with `WHATSAPP_SEND_ENABLED=true` | Placeholder-level: no abstraction, retries, policy or log |
| `ConversationAssistant` + parser + matcher + `LocationResolver` | Shared by website and WhatsApp: memory, English/Roman Urdu/Urdu, seven cities, ambiguity, real listings | Implemented, tested (reused unchanged) |
| `RequirementSaver` + `LeadRouter` | Lead only after explicit YES; at most 3 dealers; Phase 9 package limits | Implemented, tested (reused) |
| `ContactConsent` | Hashed contact; STOP revokes all | Implemented (reused, one purpose added) |
| Notifications | `LeadAssigned` in-app + mail | No WhatsApp channel |
| Admin | Conversations list/show, masked numbers, audited reveal; "manual replies not built yet" | Partial |
| Queue | `QUEUE_CONNECTION=database`, **no worker running locally**: 7 notification jobs from earlier QA were waiting | Documented and handled (§22) |
| Needs real Meta credentials | Live sending, real webhooks, connection test | Credential-blocked |

## 3. Existing functionality reused

- **Reused, not duplicated:** the webhook controller and `webhook_events` idempotency; `WebhookProcessor` (validation, storage, idempotency); `ConversationAssistant` (all AI, memory, language, location, matching and lead logic); `RequirementSaver`; `LeadRouter` (verification, city/area coverage, consent, max dealers, package lead allowance); `ContactConsent`; `AssistantNeedsAttention`; `AuditLogger`; `QueuedMailNotification`.
- **No second AI engine or matcher:** WhatsApp and the website call the same `ConversationAssistant::respond()`.
- **Dealer fields reused:** `dealer_profiles.whatsapp_number` and `users.notify_whatsapp` already existed and are now used for opt-in alerts.

## 4. Provider architecture — COMPLETE AND VERIFIED

- **`App\Services\WhatsApp\Providers\WhatsAppProvider`** defines: `key`, `label`, `canSend`, `isTest`, `send(OutboundMessage): SendResult`, `configurationChecks`, `testConnection`.
- **`MetaCloudProvider`:**
  - sends text, interactive reply-button and template messages to `graph.facebook.com/{version}/{phone_number_id}/messages`;
  - classifies HTTP 429/5xx and connection errors as retryable;
  - logs only the HTTP status and Meta error code.
- **`MockProvider`:**
  - TEST only: ids start with `mock.`, every message is flagged `is_test`, nothing leaves the server;
  - works only when `APP_ENV` is local or testing;
  - can be told to fail the next send (permanent or temporary) to exercise failure paths.
- **`WhatsAppClient` is now the single entry point:** it picks the provider (`MEEZAN_WHATSAPP_DRIVER=meta|mock`, or mock while test mode is on) and reports `isConfigured` (webhooks), `canSend` and `isLive`.
- **"Live" is never claimed for the mock:** `isLive()` (a real provider that can send) is now used wherever the UI says "live" (admin dashboard, settings, buyer and dealer preference text), so the mock is never presented as live.
- **No direct Meta calls elsewhere:** business code reaches WhatsApp only through `OutboundMessenger`.

## 5. Meta Cloud API integration status — COMPLETE BUT CREDENTIAL-BLOCKED

| Area | Status |
|---|---|
| Code paths for text, interactive buttons and templates | Written |
| HTTP behaviour and the connection test | Tested against a faked Graph API |
| A real Meta account | **Not tested** (no credentials exist) |
| Interactive buttons | Off by default (`MEEZAN_WHATSAPP_INTERACTIVE=false`) until tested with a live number; with them off, replies say "Reply 'dealer' …" in text |
| Templates | Supported. The dealer lead alert needs a template approved in WhatsApp Manager (§38) |

## 6. Environment variables required

Secrets go in the server's `.env` only. They are never in source code or `.env.example` values, and never sent to the browser.

| Variable | Purpose | Secret |
|---|---|---|
| `WHATSAPP_PHONE_NUMBER_ID` | Business phone number id (Meta App → WhatsApp → API Setup) | No |
| `WHATSAPP_ACCESS_TOKEN` | Permanent System User token with `whatsapp_business_messaging` | **Yes** |
| `WHATSAPP_APP_SECRET` | Meta App secret (App settings → Basic), for webhook signatures | **Yes** |
| `WHATSAPP_VERIFY_TOKEN` | A random string you choose, entered in Meta's webhook setup | **Yes** |
| `WHATSAPP_GRAPH_VERSION` | Graph API version (default `v21.0`) | No |
| `WHATSAPP_SEND_ENABLED` | `true` to actually send | No |
| `MEEZAN_WHATSAPP_DRIVER` | `meta` (live) or `mock` (local testing) | No |
| `MEEZAN_WHATSAPP_QUEUE` | Queue name (default `whatsapp`) | No |
| `MEEZAN_WHATSAPP_INTERACTIVE` | Reply buttons on/off | No |
| `MEEZAN_WHATSAPP_TEMPLATE_DEALER_LEAD` / `_LANGUAGE` | Approved template name and language for dealer lead alerts | No |
| `MEEZAN_WHATSAPP_MAX_INBOUND_PER_MINUTE`, `MEEZAN_WHATSAPP_MAX_OUTBOUND_PER_HOUR` | Abuse limits (defaults 10 and 15) | No |

Your local `.env` now has `MEEZAN_WHATSAPP_DRIVER=mock` for testing. **Use `meta` on the live server.** `phpunit.xml` pins `MEEZAN_WHATSAPP_DRIVER=meta` and `MEEZAN_MOCK_PAYMENTS=false`, so a local `.env` can't change test results.

## 7. Webhook verification — COMPLETE AND VERIFIED

`GET /webhooks/whatsapp` echoes `hub.challenge` only when all three hold:
- `hub.mode=subscribe`;
- the verify token matches (constant-time comparison);
- the challenge is a plain token (no markup can be reflected).

Otherwise it returns 403. Each successful verification is recorded and shown in Admin → WhatsApp ("Meta verified the webhook").

**Callback URL to enter in Meta later:** `https://<your-domain>/webhooks/whatsapp`. With the current hosting layout that is the URL the app generates from `APP_URL`, and the admin page shows it exactly.

## 8. Webhook security — COMPLETE AND VERIFIED

Incoming webhooks are checked in this order:
1. Credentials present (else 503).
2. HMAC-SHA256 signature with the app secret (else 401).
3. Payload shape (else 422, recorded).
4. Duplicate delivery (`webhook_events`).
5. Events for a different business phone-number id are ignored.

Each WhatsApp message id is stored once. Repeats create no extra conversation, lead, reply or lead-allowance use (tested). Tokens, secrets, signatures and phone numbers are never logged (tested by listening to every log message).

## 9. Incoming message processing — COMPLETE AND VERIFIED

Normalisation:
- text, interactive button/list replies (mapped to the text they stand for), and template quick-reply buttons are read;
- other types (image, audio, location, document) are stored with their type and answered with "text only".

Stored per message: provider message id, sender, channel, type, text, timestamp envelope, `processing_status` and `processed_at`.

Processing statuses:

| Status | Meaning |
|---|---|
| queued / processing / processed | Normal path |
| held | AI auto-reply is off |
| rate_limited | Too many messages in a minute |
| duplicate | Same text within 60 s |
| skipped | A person is handling the chat |
| failed | Processing error |

Nothing is discarded.

## 10. Conversation integration — COMPLETE AND VERIFIED

- **One conversation per WhatsApp contact** (unique channel + number), reopened if the buyer writes after STOP.
- **No accounts are created.** A conversation works without an account.
- **Linking to an account:** **Account → Settings → "Link your WhatsApp chat"** gives a 15-minute one-time code. Sending `LINK 123456` from WhatsApp links the chat, and its saved requirement, to that account.
  - Phone numbers on accounts aren't verified, so linking by number alone was rejected for privacy.
  - Wrong, expired and reused codes are refused (tested).

## 11. AI integration — COMPLETE AND VERIFIED

`ProcessInboundWhatsAppMessage` calls the shared `ConversationAssistant`, which provides requirement memory, clarifying questions, real matching and consent-gated leads. Each message is claimed and processed **once** and never retried automatically, because the assistant changes conversation state.

On failure:
- the buyer gets a short apology with no technical detail;
- the message is marked failed;
- admins are alerted (tested with a forced exception).

## 12. Language support — COMPLETE AND VERIFIED

The shared parser handles all three languages. The same request in English, Roman Urdu and Urdu produced **identical** requirement state (city Lahore, DHA Lahore, house, 2,250 sq ft), in tests and in manual QA.

## 13. Seven-city location intelligence — COMPLETE AND VERIFIED

The shared researched location system handles aliases, phases, sectors and blocks.
- **Ambiguity:** "Garden Town" lists Lahore, Multan and Quetta and asks which is meant, instead of guessing; a reply of "2" or a city name settles it.
- **Unsupported cities:** "Rawalpindi" is refused politely, with the seven launch cities listed.
- **No mixing:** a test runs all seven cities, and each returns only its own listings.

## 14. Property matching — COMPLETE AND VERIFIED

Matching uses the existing `PropertyMatcher` against published listings. Prices, titles, sizes, bedrooms and links all come from the database.
- Zero results are explained with real price data ("Matching listings start from PKR 2.15 Crore"), with save and dealer options offered.
- Nothing is invented (tested).

## 15. Property response format — COMPLETE AND VERIFIED (text) · interactive buttons: COMPLETE BUT CREDENTIAL-BLOCKED

Example from manual QA:

```
🏠 3 properties found

1. 10 Marla House with Basement in DHA Phase 5
🏷 Strong match
💰 PKR 5.8 Crore
📍 DHA Phase 5, Lahore
🛏 4 Bedrooms
📐 10 Marla
🔗 http://localhost:8000/properties/10-marla-house-with-basement-in-dha-phase-5
…
Are you looking to buy or to rent?
```

- **Size:** at most 3 listings, capped at 1,500 characters.
- **Labels:** each listing shows the same match label as the website ("Partial match — outside your area", "(above budget)").
  - Manual QA caught the WhatsApp format omitting these labels and claiming "matching" for out-of-area results; both were fixed.
- **Links:** from `route()`, so they follow `APP_URL` (localhost now, the real domain later).
- **Buttons:** Talk to dealer / Save search / Change search, when enabled.

## 16. Lead qualification — COMPLETE AND VERIFIED

Unchanged rule, reused: a lead is created only when the requirement is substantial **and** the buyer asks for dealers **and** replies YES (consent recorded).
- Small talk and partial requirements create none.
- A second YES creates no second lead.

## 17. Dealer matching — COMPLETE AND VERIFIED

The existing `LeadRouter` applies the verified-dealer requirement, city/area coverage, the dealer accepting leads, Phase 9 package lead allowances and ranking.
- **Cap:** tested with 5 eligible dealers, exactly 3 are assigned (`max_dealers_per_requirement`).
- **Allowances used up:** when dealers have no allowance left, the lead is **held for staff**, not broadcast or lost.

## 18. Dealer notifications — in-app: COMPLETE AND VERIFIED · WhatsApp: COMPLETE BUT CREDENTIAL-BLOCKED

- **Opt-in:** dealer **Agency Profile → "Send new-lead alerts to this WhatsApp number"**. It stores `notify_whatsapp` and a `whatsapp_lead_alerts` consent for that number. Unticking, or changing the number, withdraws it.
- **Channel:** `LeadAssigned` adds a WhatsApp channel only for opted-in dealers. The in-app notification remains the one notification record; the WhatsApp copy is an `outbound_messages` row.
- **Message content:** "New qualified lead available" with requirement, location and budget, and a dashboard link. **No buyer name or number**; the dealer sees the lead, with the buyer's consented contact, in the dashboard (tested).
- **Templates:** outside the dealer's 24-hour window the alert is sent as the approved template `MEEZAN_WHATSAPP_TEMPLATE_DEALER_LEAD`, with 3 parameters. Without an approved template it is **blocked** (`outside_service_window`), never sent as free text (tested, and seen in manual QA).
- **Dashboard first:** the dealer dashboard stays the source of truth. Two-way dealer WhatsApp messaging was deliberately not built.

## 19. Consent and opt-out management — COMPLETE AND VERIFIED

- **Recorded consents:** `share_with_dealers` (YES), `whatsapp_replies` (save requirement) and `whatsapp_lead_alerts` (dealer opt-in), each with timestamp, channel and source; the contact is hashed.
- **STOP** (also "unsubscribe", "band karo"):
  - revokes every consent for the number and closes the chat;
  - sends one confirmation (`opt_out_confirmation`, the only message allowed after STOP);
  - blocks later automated messages (`opted_out`), and dealer alerts lose consent too.
- **Writing again:** a new message from the buyer reopens the chat. Under Meta's rules a user-initiated message may be answered.

## 20. Outgoing message tracking — COMPLETE AND VERIFIED

`outbound_messages` stores:
- provider, the recipient **encrypted** (needed to send and retry) with a hash and the last 4 digits;
- the related conversation, conversation message, lead and user;
- category, purpose, type, body, buttons or template parameters (no credentials);
- status, block reason, provider message id (unique), attempts, last error, dedupe key (unique), test flag and timestamps.

Rows can't be deleted. Admin views mask numbers (`•••• 5566`).

## 21. Delivery statuses — COMPLETE AND VERIFIED (mock and faked Meta) · live: CREDENTIAL-BLOCKED

| Status | Set by |
|---|---|
| queued, sending, sent, failed, blocked | Our system |
| delivered, read, failed (with Meta error code and title) | Only from status webhooks |

Progress only moves forward (sent → delivered → read); a late "sent" can't undo "read" (tested). Not every provider reports every status, and none is assumed.

## 22. Queue architecture — COMPLETE AND VERIFIED

- **What runs where:** webhooks only validate, store and dispatch. AI processing and sending run as jobs on the **`whatsapp`** queue (`ProcessInboundWhatsAppMessage`, `SendWhatsAppMessage`).
- **Local queue:** locally `QUEUE_CONNECTION=database`, so a worker must run:

  ```bash
  php artisan queue:work --queue=whatsapp,default
  ```

  The admin page shows waiting and failed job counts and this command when jobs are waiting.
- **Shared hosting:** the scheduler-driven worker (`MEEZAN_QUEUE_VIA_SCHEDULER=true`) previously drained only `default`. It now drains `whatsapp,default`.
- **Mock console:** offers "Process now" so local testing works without a worker.
- **Found during the audit:** in-app notifications were not being delivered locally because no worker ran. Running the worker during QA delivered the 7 waiting jobs. Mail goes to the log.

## 23. Failure handling — COMPLETE AND VERIFIED

| Failure | Handling |
|---|---|
| AI processing fails | Buyer apology, message marked failed, admins alerted |
| Temporary send failure (HTTP 429/5xx, network) | Retried up to 3 times with growing delays (30 s, 2 min, 10 min); visible as "awaiting retry" |
| Permanent send failure | Marked failed with the provider code; admins alerted at most once an hour |
| Staff retry | Only for failed messages, and only after the policy is re-checked (e.g. the 24-hour window may have closed) |

`meezan:whatsapp-sweep` (every 5 minutes) re-dispatches messages a stopped worker left queued. Sends interrupted mid-request are marked "outcome unknown" for staff, **not** re-sent automatically, to avoid duplicates.

## 24. Admin WhatsApp control center — COMPLETE AND VERIFIED (automated) · browser QA not possible (admin password is yours)

**Admin → WhatsApp Control** (admins only):
- **Integration status:** provider; each credential shown as Set (hidden) or Missing; incoming webhooks accepted; whether the webhook is publicly reachable ("No: this is a local address"); last Meta verification; connection test; last webhook; queue; the exact callback URL.
- **Activity:** conversations, active (24 h), incoming, outgoing, failed, awaiting retry, blocked by policy, held for staff, processing failed, qualified WhatsApp leads, test messages, webhook failures (7 days).
- **Switches:** automation, AI auto-reply, test mode (audited).
- **Lists:** failed and blocked messages with Retry; messages not answered automatically.
- **Outgoing message log:** filters by status.
- **TEST / MOCK console:** only on local/testing with the mock provider.

The conversation screen now has a **staff reply** form (policy-checked; works while automation is off; blocked outside 24 hours or after STOP).

## 25. Automation safety controls — COMPLETE AND VERIFIED

| Switch | When off / on |
|---|---|
| Automation (off) | No automatic messages (assistant replies, dealer alerts) are sent; they're recorded as blocked; staff replies still work |
| AI auto-reply (off) | Incoming messages are stored and marked **held**; no AI processing, no reply; nothing discarded |
| Test mode (on) | Every message goes to the mock provider and is marked as a test, whatever the driver |

Promotional messages are always blocked in this version (`promotional_disabled`).

## 26. Rate limiting — COMPLETE AND VERIFIED

| Protection | Rule |
|---|---|
| Webhook route | 120 requests/minute |
| Inbound flood | Above 10 messages/minute from one contact: kept, not answered |
| Exact repeat | Same text within 60 s: kept, not answered |
| Outbound | 15 automated messages per recipient per hour (staff replies exempt) |
| Admin actions | Retry, simulate, connection test and link code are throttled |
| Burst escalation | Existing escalation to staff still applies |

All limits are configurable.

## 27. Mock/local testing — COMPLETE AND VERIFIED

- **Mock console** (Admin → WhatsApp, local only):
  - simulate an incoming message (through the real processor);
  - re-deliver the last one (duplicate webhook);
  - make the next send fail (permanent or temporary);
  - simulate delivered/read/failed statuses.

  Everything is labelled **TEST / MOCK MODE** and audited.
- **QA script:** used to drive the same pipeline from the command line during QA. It lives in the session scratchpad, not the project.

## 28. Localhost webhook limitations — documented

Meta delivers webhooks only to a **public HTTPS** address, so `http://localhost:8000/webhooks/whatsapp` cannot receive real messages. The admin page says so.

Options for a later, supervised test (not done; each needs your decision):
- a temporary HTTPS tunnel (e.g. Cloudflare Tunnel or ngrok) pointing at the local app, with test credentials and the tunnel closed afterwards;
- or a staging deployment in the deployment phase.

Don't put permanent production secrets on a tunnel-exposed laptop.

## 29. Data privacy — COMPLETE AND VERIFIED

- **Admin-only pages:** all WhatsApp pages, conversations, staff reply, retry, mock console and message log are admin-only (dealers and buyers get 403; guests go to login; tested).
- **Numbers:** masked everywhere in admin; a full number is revealed only by the existing audited "Show number". The outbound recipient is encrypted and hidden from serialisation.
- **Dealer messages:** no buyer contact details. The buyer's number appears only in the dealer dashboard after the buyer consented.
- **Logs:** never include numbers, message text, tokens or signatures.

## 30. Audit logging — COMPLETE AND VERIFIED

Recorded actions (actor and time):
- `whatsapp.setting_changed` (from/to), `whatsapp.connection_tested`, `whatsapp.message_retried`, `whatsapp.message_failed`, `whatsapp.simulator_used`;
- `conversation.staff_replied`, `conversation.linked_to_account`;
- `dealer.whatsapp_alerts_on/off`;
- existing: `conversation.opted_out`, `conversation.escalated`, `lead.assigned`, `lead.held`, `requirement.created_by_ai`.

Per-message events (webhook received, message processed, reply generated, sent, delivered) are in `webhook_events`, `conversation_messages` and `outbound_messages` rather than the audit log, so it isn't flooded.

## 31. Files changed

**New**

| Area | Files |
|---|---|
| Migrations | the three in §32 |
| Models | `AppSetting`, `OutboundMessage` |
| Providers | `Services/WhatsApp/Providers/{WhatsAppProvider, MetaCloudProvider, MockProvider, SendResult}` |
| Services | `Services/WhatsApp/{MessagePolicy, OutboundMessenger, WhatsAppFormatter, WhatsAppSettings, AccountLinker}`; `Support/WhatsAppNumber` |
| Jobs | `Jobs/ProcessInboundWhatsAppMessage`, `Jobs/SendWhatsAppMessage` |
| Notification channel | `Notifications/Channels/WhatsAppChannel` |
| Command | `WhatsAppSweep` |
| Controller | `Admin/WhatsAppController` |
| Views | `admin/whatsapp/{index, messages}` |
| Tests | `tests/Feature/WhatsAppIntegrationTest.php` (33) |
| Report | this file |

**Changed**

| File | Change |
|---|---|
| `Services/WhatsApp/WhatsAppClient.php` | Provider selection, `isLive` |
| `Services/WhatsApp/WebhookProcessor.php` | Normalisation, phone-number-id check, guards, queueing, statuses |
| `Http/Controllers/Webhooks/WhatsAppWebhookController.php` | Safe challenge, verification record |
| `Notifications/LeadAssigned.php` | WhatsApp channel and message |
| `Notifications/AssistantNeedsAttention.php` | 2 new reasons |
| `Models/ContactConsent.php` | `whatsapp_lead_alerts` purpose |
| `Services/Assistant/ConversationAssistant.php` | Grammar only: "1 dealer who covers" |
| `Admin/ConversationController` | Staff reply |
| `Dealer/ProfileController` + `dealer/profile` view | WhatsApp alert opt-in |
| `Account/ProfileController` + `account/settings` view | Link code |
| `admin/conversations/show` | Reply form, processing labels |
| Admin `Dashboard` / `Settings` controllers | `isLive` |
| `components/layouts/dashboard` | "WhatsApp Control" nav with failed badge |
| `config/meezan.php` | `whatsapp` section |
| `routes/web.php` | 8 routes |
| `routes/console.php` | Sweep schedule; worker drains `whatsapp` |
| `phpunit.xml` | Pinned test env |
| `.env.example` | Unchanged values |
| `.env` | Added `MEEZAN_WHATSAPP_DRIVER=mock` (local) |

## 32. Database changes — COMPLETE AND VERIFIED

Three additive migrations, run 2 Nov 2026:
- `2026_11_02_000033_create_outbound_messages_table`
- `2026_11_02_000034_create_app_settings_table`: runtime switches and health results, never credentials
- `2026_11_02_000035_add_processing_state_to_conversation_messages`: `processing_status`, `processed_at`

No table was dropped or rewritten.

## 33. Routes added (8)

| Route | Name |
|---|---|
| `GET admin/whatsapp` | `admin.whatsapp` |
| `GET admin/whatsapp/messages` | `admin.whatsapp.messages` |
| `PATCH admin/whatsapp/settings` | `admin.whatsapp.settings` |
| `POST admin/whatsapp/connection-test` | `admin.whatsapp.test` |
| `POST admin/whatsapp/messages/{message}/retry` | `admin.whatsapp.retry` |
| `POST admin/whatsapp/simulate` | `admin.whatsapp.simulate` |
| `POST admin/conversations/{conversation}/reply` | `admin.conversations.reply` |
| `POST account/settings/whatsapp-link` | `account.whatsapp.link` |

## 34. Automated test results

| Command | Result |
|---|---|
| `php artisan test` | **438 passed, 2,215 assertions, 0 failed** |
| `vendor/bin/pint --test` | passed |
| `npm run build` | OK |
| `composer audit` | no advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `php artisan route:cache` | cached (then cleared for development) |

There were 405 tests after Phase 9; **33 new** tests cover:
- **Webhook:** verification (valid, invalid, malformed, wrong mode); unsigned, wrongly signed and malformed requests; other phone number; duplicate delivery with no extra message, reply, lead or allowance use.
- **Providers:** mock; missing credentials and sending off; Meta send with the token, then retry on 500 and failure on 400 plus admin alert; mock refused in production.
- **AI:** Roman Urdu memory across messages with real listings, links and length cap; English = Roman Urdu = Urdu; Garden Town ambiguity and answer; unsupported city; all seven cities without mixing; zero results invent nothing.
- **Leads:** consent before any lead; at most 3 of 5 dealers; held when allowances are used up.
- **Dealer alerts:** opt-in via profile; template outside the window; no buyer details; blocked without a template; no alert without opt-in; consent withdrawn on opt-out.
- **Outbound:** dedupe and no double send; status progression and failure codes; staff retry with policy re-check.
- **Abuse protection:** inbound flood, repeats and outbound hourly limit.
- **Opt-out and switches:** STOP confirmation then blocking, promotional blocked; auto-reply off (held), automation off (blocked, staff reply still allowed); test mode forces mock; staff reply blocked after 24 hours.
- **Other:** interactive buttons and tapped-button handling; account linking with valid, invalid and reused codes; processing failure (apology, alert, no stack trace).
- **Admin and privacy:** admin-only access, no secret values, masked numbers; connection test that never claims success it didn't get; mock console (process, duplicate, status, refused without mock); credentials, signatures and numbers never logged; sweep.

**Existing tests:** none weakened or edited. The only test-side change is pinning two env values in `phpunit.xml`, after my local `.env` change briefly made 3 existing WhatsApp tests use the mock driver.

## 35. Manual test results (mock provider, local database, real queue worker)

| Scenario | Result |
|---|---|
| "Mujhe DHA Lahore mein 10 marla ghar chahiye" | Requirement understood; 3 real listings with labels and local links; asked buy or rent |
| "4 crore ke around" | Memory kept (Lahore, DHA Lahore, house, 10 marla), budget added, purpose inferred; honest partial matches (nothing in DHA under 4 crore) |
| English and Urdu versions | Identical requirement state |
| "house in Garden Town" | Asked: Lahore / Multan / Quetta |
| "house in Rawalpindi" | Politely refused; 7 cities listed |
| 2 kanal under 50 lakh | No-result explanation with real price data; nothing invented |
| Duplicate webhook | Ignored (stored=0) |
| Forced send failure | Message marked failed |
| Status updates | delivered → read applied |
| Gulberg requirement → "dealer se baat karwa dein" → YES | Consent asked; lead created; assigned to the demo dealer only (Gulberg chosen so your other dealer account wasn't involved); in-app notification created; lead visible in the dealer's Requirement leads tab |
| Dealer WhatsApp alert | Demo dealer opted in (fake test number 0300 0000003); alert generated with requirement, location and budget only; correctly **blocked**: no approved template and no message from the dealer in 24 hours |
| STOP | Confirmation sent; later automated message blocked (`opted_out`) |
| Account linking | Code shown in buyer settings; `LINK` code from WhatsApp linked the chat and requirement; "1 WhatsApp chat linked"; requirement under My requirements |
| Browser | Dealer profile opt-in, dealer leads, buyer settings and requirements at 375 and 1366 px: no overflow, no console errors |
| Laravel log | No new errors (only the earlier Phase 9 migration error) |

Fixes from manual QA:
- match labels added to WhatsApp listings;
- neutral headline instead of "matching";
- "For For Sale" wording in the dealer alert;
- "1 dealer who covers" grammar.

**Admin pages were not opened in the browser** (they need your admin sign-in); automated tests render them with data.

**Test data left locally:**
- 8 test conversations (numbers 92000000000x, name "QA Test Buyer");
- 2 test leads and 2 requirements (Gulberg, assigned to the demo dealer);
- 18 test outbound messages;
- the demo dealer's fake WhatsApp number and opt-in;
- the demo buyer is linked to one test chat.

Nothing was deleted. Your other dealer account received nothing.

## 36. Known limitations

1. **Not live:** no real Meta number, token or public webhook exists, so live send and receive, interactive buttons and templates are untested with Meta.
2. **Dealer alerts need an approved template**, because dealers rarely write in first; until then they are blocked and recorded.
3. **No buyer proactive WhatsApp alerts** (e.g. new matching listings): this needs a marketing/utility template and explicit alert consent. Subscription notices stay in-app and email.
4. **No two-way dealer WhatsApp CRM** (by design); staff can reply to buyers from the admin screen.
5. **Media messages** (images, voice notes, locations) are stored but not interpreted.
6. **Queued processing needs a running worker** (or the scheduler option on shared hosting); without one, replies wait.
7. **Account linking is buyer-initiated;** there is no automatic matching by phone, by design.
8. **The 24-hour window** is measured from our stored inbound messages; if webhooks were missed, the window may be shorter than Meta's view, which errs on the side of not sending.
9. **Test conversations aren't flagged:** they are ordinary rows with recognisable test numbers. Their outgoing messages are flagged `is_test`.

## 37. Required external credentials

| Item | From |
|---|---|
| Meta Business account (verified) and WhatsApp Business Account | business.facebook.com |
| A phone number registered for the Cloud API (not one used in the WhatsApp app) | WhatsApp Manager |
| Meta App with the WhatsApp product; **Phone number ID** | Meta App → WhatsApp → API Setup |
| **Permanent access token** (System User with `whatsapp_business_messaging`, `whatsapp_business_management`) | Business Settings → System Users |
| **App secret** | Meta App → App settings → Basic |
| **Verify token** | You choose a long random string |
| Approved **message template** for dealer lead alerts (3 body variables) | WhatsApp Manager → Message templates |
| Public **HTTPS** URL for the webhook | Deployment phase |

## 38. Exact next steps for live Meta setup

1. **In Meta**, complete the Business account, WhatsApp Business Account, phone number and app (§37).
2. **On the live server's `.env`** (never in code), set:
   - `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`
   - `MEEZAN_WHATSAPP_DRIVER=meta`
   - `WHATSAPP_SEND_ENABLED=false` (keep sending off for now)

   Then run `php artisan config:clear`.
3. **Admin → WhatsApp Control → Test connection.** Continue only when it says "Connected to …".
4. **Webhook:**
   1. In the Meta App, go to WhatsApp → Configuration → Webhook.
   2. Set the Callback URL to the address shown on the admin page (`https://<domain>/webhooks/whatsapp`) and the Verify token to your `WHATSAPP_VERIFY_TOKEN`.
   3. Subscribe to the **messages** field.
   4. Check that the admin page shows "Meta verified the webhook".
5. **Make sure a queue worker runs:** `php artisan queue:work --queue=whatsapp,default` under a supervisor, or `MEEZAN_QUEUE_VIA_SCHEDULER=true` plus the cron for `schedule:run`.
6. **Receiving test:** message the business number from a staff phone, and confirm the conversation and a *blocked* reply ("sending_disabled") appear.
7. **Sending test:** set `WHATSAPP_SEND_ENABLED=true`, then message again; the reply should arrive and its status move sent → delivered → read.
8. **Dealer alerts:**
   1. Create the dealer lead template in WhatsApp Manager. A suggested body (Utility category): *"New qualified lead available. Requirement: {{1}}. Location: {{2}}. Budget: {{3}}. Open your Meezan Property dashboard to review it."*
   2. After approval, set `MEEZAN_WHATSAPP_TEMPLATE_DEALER_LEAD=<template name>` and `MEEZAN_WHATSAPP_TEMPLATE_LANGUAGE=<code>`.
9. **Buttons (optional):** after testing with a staff phone, set `MEEZAN_WHATSAPP_INTERACTIVE=true`.
10. **Keep the mock away from live:** leave test mode off and never set `MEEZAN_WHATSAPP_DRIVER=mock` or `MEEZAN_MOCK_PAYMENTS=true` on the live server.

---

### Final figures

| Item | Value |
|---|---|
| Tests passed | **438** |
| Assertions | **2,215** |
| Failures | **0** |
| New database tables | **2** (`outbound_messages`, `app_settings`) + 2 new columns on `conversation_messages` |
| New routes | **8** |
| New jobs / queues | **2 jobs** (`ProcessInboundWhatsAppMessage`, `SendWhatsAppMessage`) on the **`whatsapp`** queue; **1 scheduled command** (`meezan:whatsapp-sweep`, every 5 minutes) |
| Existing data before → after | users 4→4 · dealers 2→2 (verified 2→2) · properties 17→17 (published 16→16) · images 42→42 · subscriptions 1→1 · invoices 1→1 · payments 2→2 · areas 1,238→1,238 · conversations 2→10 (+8 test) · leads 0→2 (test) · requirements 0→2 (test) |
| Environment variables required | `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_SEND_ENABLED`, `MEEZAN_WHATSAPP_DRIVER`; optional `MEEZAN_WHATSAPP_TEMPLATE_DEALER_LEAD`, `MEEZAN_WHATSAPP_TEMPLATE_LANGUAGE`, `MEEZAN_WHATSAPP_INTERACTIVE`, `MEEZAN_WHATSAPP_QUEUE`, limits |
| Features requiring real Meta credentials | Live sending; receiving real webhooks; connection test; delivery statuses from Meta; interactive buttons; dealer lead alert template |
