# Meezan Property — Phase 7: AI Property Intelligence — Completion Report

_Local development (XAMPP, Laravel 12, MariaDB), 31 October 2026. No Git, GitHub or Hostinger actions. No destructive database commands._

Status labels used throughout:

- **COMPLETE AND VERIFIED** — built, covered by automated tests, and (where visible) checked in the browser.
- **COMPLETE BUT CREDENTIAL-BLOCKED** — built and tested with test doubles; needs external credentials to run live.
- **PARTIALLY IMPLEMENTED**
- **NOT IMPLEMENTED**

---

## 1. Executive summary

The AI Property Finder is now a conversation, not a one-shot parser.

**How it works:**

- One channel-agnostic `ConversationAssistant` serves the website and (once configured) WhatsApp.
- It remembers the requirement across messages ("house in Lahore" … "10 marla, around 4 crore").
- It reads English, Roman Urdu and Urdu script.
- It uses the 1,238-location database with the city rules from the expansion: it asks "which Garden Town?" and refuses Rawalpindi politely.
- It ranks real published listings, with honest budget labels and one-line explanations.
- It saves one requirement per conversation.

**Leads:** a lead is created only on an explicit, consented request, through the existing `LeadRouter`:

- at most 3 dealers
- city and area coverage enforced
- deduplicated
- audited

**WhatsApp:** the webhook now runs the same assistant, with:

- payload validation
- event-level idempotency
- failure logging and admin alerts
- YES/STOP consent handling

**What isn't live:**

- WhatsApp sending and the LLM driver need credentials, so they are not live.
- Email delivery needs a mail provider (still `log`).

**Tests:** 339 passed (1,420 assertions), 0 failed. That is the 291 tests from before this phase, all still passing, plus 48 new ones.

## 2. Existing AI audit

| Item | Before this phase | Reused / changed |
|---|---|---|
| `RulesRequirementParser` | English + Roman Urdu, all 7 cities via `LocationExtractor` | Kept as the engine; extended (§6–9) |
| `LlmRequirementParser` | Optional OpenAI-compatible parser, rules fallback, `MEEZAN_AI_DRIVER` | Kept; place names from the model only accepted within the rules-detected city |
| `AiFinderController` | Each message parsed in isolation; no memory | Rewritten around the assistant; no-JS GET search kept |
| `conversations` / `conversation_messages` tables | WhatsApp only: idempotent by message id, bot/human mode | Extended for all channels (no new conversation tables) |
| WhatsApp webhook + client | Signature check, handshake, guarded sending | Kept; now runs the assistant; validation, event log, failure alerts added |
| `PropertyMatcher` | Hard filters + scored reasons; strict budget cap | Budget tolerance, area-first ranking, explanations, furnishing, better zero-result hints |
| `LeadRouter::routeRequirement` | Consent required, dedupe key, ≤ 3 dealers, city + area coverage, audit | Reused unchanged |
| Notifications | `QueuedMailNotification` (in-app + queued mail), `LeadAssigned` | 3 new notifications on the same base |
| Admin conversations | WhatsApp list and transcript | Turned into AI assistant management (§22) |

## 3. Conversation architecture

```
Website (AiFinderController) ─┐
WhatsApp (WebhookProcessor) ──┼─► ConversationAssistant::respond()
Future app ───────────────────┘        │
     store user message ◄──────────────┤
     IntentDetector (stop / human / reset / sell / dealer / save / yes / no / greeting / thanks / requirement)
     RequirementParser (rules, or LLM with rules fallback) + UrduText
     RequirementState (remembered requirement, pending choices, questions asked)
     ├─ clarify location · unsupported city · ask city · follow-up question
     └─ PropertyMatcher ─► AssistantReply (text, matches, follow-up, quick replies, actions, summary)
     RequirementSaver ─► BuyerRequirement / ContactConsent / LeadRouter
     store assistant message ─► channel renders: website JSON + cards, WhatsApp plain text via WhatsAppClient
```

The business logic is written once. Channels only differ in how they deliver the reply and capture consent: a website button, or a WhatsApp YES.

**COMPLETE AND VERIFIED.**

## 4. Database changes

Four additive migrations. I listed them and scanned the generated SQL for drops before running them; none were found. Existing data was unchanged afterwards: 17 properties, 4 users.

| Migration | Change |
|---|---|
| `2026_10_31_000021_add_ai_memory_to_conversations` | `conversations`: `session_key`, `status`, `state` (JSON), `locale`, `escalated_at`, `escalation_reason`, index (`channel`, `last_message_at`). `conversation_messages`: `role`, `intent`, `extracted` (JSON), index (`conversation_id`, `id`) |
| `2026_10_31_000022_add_ai_fields_to_buyer_requirements` | `buyer_requirements`: `conversation_id`, `preferences` (JSON), `match_status`, `last_match_count`, `matched_at`, `contact_name`, `contact_phone`. `conversations`: `buyer_requirement_id` |
| `2026_10_31_000023_create_contact_consents_table` | **New table** `contact_consents` |
| `2026_10_31_000024_create_webhook_events_table` | **New table** `webhook_events` (unique provider + payload hash) |

## 5. Conversation memory implementation

- **Storage:** every message is stored with its role, timestamp, intent and the structured requirement at that point.
- **State:** the conversation's `state` holds the remembered requirement, any pending location choices, the questions already asked, and a failure counter.
- **Merging:** each new message adds to the state. Naming a new city replaces the old one and clears its areas, so cities never mix.
- **Ownership:**
  - Website: one active conversation per browser session, found through a random 48-character token stored in the session (it survives sign-in).
  - Signed-in users are linked by `user_id`.
  - No request can name another conversation.
  - WhatsApp: one conversation per phone number.
- **Bounded:** the assistant works from the structured state, not the transcript. The page shows the last 50 messages; admins see the last 200.
- **Privacy:** WhatsApp messages keep only the envelope (id, from, timestamp, type), not profile or device data.
- **Retention:**
  - `meezan:prune-conversations` runs daily.
  - It deletes nothing until `MEEZAN_AI_RETENTION_DAYS` is set.
  - Conversations that produced a requirement or lead are always kept.

**COMPLETE AND VERIFIED**: tests cover memory, reload history, a new conversation, session isolation, user linking, and retention at 0 and at 365 days.

## 6. Requirement extraction

Supported fields:

| Group | Fields |
|---|---|
| Location | city, area / society / phase / sector / block (from the location database) |
| Purpose | buy / rent; sell enquiries are sent to the listing flow |
| Type | house (also bungalow, villa, kothi), flat / apartment, plot, commercial plot, shop, office, warehouse and the other existing types |
| Size | marla, kanal, sq ft, sq yards, "gaz"; half / aadha, derh (1½), dhai (2½) |
| Budget | thousand, lakh, crore, million, ranges ("2 to 3 crore"), "around"/"lagbhag", plain PKR/Rs amounts, comma-grouped numbers |
| Other | bedrooms, bathrooms, amenities (parking etc.), furnishing, stated reasons (investment, family, commercial use) |

- **Stated reasons** are stored with the requirement but never scored or used to make claims.
- **Purpose** is assumed to be "buy" only when that's unambiguous: plots, or budgets of at least 50 lakh. It's shown as "Buy (assumed)"; otherwise the assistant asks.

**COMPLETE AND VERIFIED** (14 size/budget cases plus furnishing and purpose tests).

**PARTIALLY IMPLEMENTED:** property age ("new", "5 years old") is not extracted; listings have no reliable age field to match against.

## 7. English support

**COMPLETE AND VERIFIED.** The brief's example ("I need a 10 marla house in DHA Lahore. My budget is around 4 crore.") gives:

- city Lahore
- DHA Lahore
- house
- 2,250 sq ft
- PKR 40,000,000, marked approximate

## 8. Roman Urdu support

**COMPLETE AND VERIFIED.** "Mujhe DHA Lahore mein 10 marla ka ghar chahiye" parses identically to the English version.

Also handled:

- kiraye / kiraya (rent), khareedna (buy)
- tak (up to), lagbhag (around), derh / dhai
- "dealer se baat karwa dein"
- haan / ji / nahi (yes / no)

## 9. Urdu support

**COMPLETE AND VERIFIED.** A dictionary normaliser (`App\Support\UrduText`) rewrites Urdu script into the Roman forms the parser understands. It covers:

- cities and common societies
- property types and units
- money words (کروڑ, لاکھ, ہزار)
- Urdu number words and Urdu/Arabic-Indic digits
- rooms, intent words and connectors

Unknown Urdu words are dropped from the parsed copy rather than guessed, and the original text is always stored. "مجھے ڈی ایچ اے لاہور میں دس مرلہ گھر چاہیے" gives the same result as the English sentence.

**Limitation:** coverage is dictionary-based. Area names not in the dictionary aren't recognised in Urdu script, so the assistant asks instead.

## 10. Seven-city location intelligence

**COMPLETE AND VERIFIED.**

- All 7 cities and their short forms are recognised (tested city by city).
- Location matching uses the 1,238 locations and 267 aliases, with roman/arabic phase numbers ("DHA Ph V" → DHA Phase 5) and sector codes ("F7" → F-7).
- **Ambiguity:** "Garden Town" offers Lahore, Multan and Quetta as quick replies. Answering "Quetta" or "1" resolves it and keeps the rest of the message (verified in the browser with Urdu input).
- **Unsupported city:** "Rawalpindi" gets "Meezan Property does not cover Rawalpindi yet. We currently list property in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar…" and the city is left unset.
- **Cross-city:** results are filtered to the requirement's city (tested).
- **Performance:** the location dictionary is built once and cached until locations change.

## 11. Property matching logic

**COMPLETE AND VERIFIED.**

- **Hard filters:** city, purpose, type (or category), published status, and a budget ceiling of +25% (§13).
- **Scored:** area, budget fit, size, bedrooms, bathrooms, requested features, furnishing, verified dealer.
- **Explanation:** every match gets a factual sentence built from the listing's own data, e.g. "Good match: located in Valencia Town, Lahore · 8 Marla · House for Sale · 4 bedrooms · within your PKR 4 Crore budget." Tests assert that no investment, guarantee or appreciation wording appears.

## 12. Ranking system

**COMPLETE AND VERIFIED.** Sort order:

1. in the requested area
2. budget fit: within, then close, then above
3. score
4. newest

Labels:

- **Strong match:** score ≥ 85 with no caveats
- **Good match:** score ≥ 65
- **Partial match:** below that
- **Partial match — outside your area**
- **Close match — slightly above budget**
- **Alternative — above budget**

## 13. Budget handling

**COMPLETE AND VERIFIED.**

| Range | Label | Shown? |
|---|---|---|
| At or under budget | within | Always |
| Up to +10% over (`MEEZAN_AI_BUDGET_CLOSE_MARGIN`) | close match, with the exact percentage | Always |
| Up to +25% over (`MEEZAN_AI_BUDGET_ALTERNATIVE_MARGIN`) | alternative, above budget | Only when in the requested area and of the requested type |
| More than +25% over | — | Never (the existing "80M for a 50M budget" test still passes) |

A listing above budget is never described as "within your budget" (tested).

## 14. Zero-results flow

**COMPLETE AND VERIFIED.** Instead of "No properties found", the reply says:

1. "No exact match is available right now for: …"
2. Factual hints with real counts:
   - the cheapest matching price
   - other types available
   - "N matching listings are for rent instead"
3. Offers to save the requirement for new-listing alerts, or to connect with up to 3 verified dealers.

The card area shows the same, with Browse and Save buttons.

## 15. Requirement saving

**COMPLETE AND VERIFIED.**

- **Who can save:** signed-in buyers only (guests are asked to sign in and keep their search).
- **What is saved:** one `BuyerRequirement` per conversation, with:
  - source `ai` or `whatsapp`
  - conversation link
  - preferences (bathrooms, amenities, furnishing, reasons, approximate flag, assumed purpose)
  - match status and count
- **Updates:** saving again updates the same record; so does changing the search later ("make it 6 crore"). A buyer saving the same city, purpose and type from another conversation also updates rather than duplicating.
- **WhatsApp buyers** (no account) have the phone number they wrote from stored as the requirement's contact, with a `whatsapp_replies` consent record.

## 16. Lead creation

**COMPLETE AND VERIFIED.** A lead is created only when all of these hold:

- the buyer explicitly asks to contact dealers
- the requirement is substantially complete: city, purpose, and a type, area or budget
- there is recorded consent: the website checkbox and button, or a WhatsApp YES after the assistant explains what will be shared

Greetings and small talk never create a requirement or lead (tested). Repeated requests reuse the same lead (dedupe key per requirement).

| Brief's states | Existing statuses used |
|---|---|
| Draft | conversation status `active` |
| Qualified | conversation `qualified` (requirement saved) |
| Matched / Assigned | lead `assigned` |
| Contacted | lead `contacted` |
| In progress | lead `qualified` |
| Closed | lead `closed` |
| Not interested | lead `lost` |
| No eligible dealer | lead `unrouted` (shown to admins as "Needs review") |

The existing lead enum and pages were not changed.

## 17. Dealer matching

**COMPLETE AND VERIFIED** (through the existing `LeadRouter`). The requirement goes to verified, active, lead-accepting dealers who meet all of these:

- they serve the requirement's city (multi-city coverage)
- they cover a requested area or its parent or child location
- they are under their lead capacity

Dealers are ranked by area overlap and relevant listings, and capped at `meezan.leads.max_dealers_per_requirement` (3).

Tested:

- 5 eligible Lahore dealers → exactly 3 assigned
- the Karachi-only dealer excluded
- one lead after two requests
- 3 `lead.assigned` audit entries

Dealers see the buyer's account details, or (WhatsApp) the consented WhatsApp number, on their Leads page.

## 18. Website AI interface

**COMPLETE AND VERIFIED** (browser at desktop width, plus tests).

The existing page and its layout from the `public/Refrence/` designs are kept. The brand palette is unchanged; the brief's hex values were not applied, per your earlier decision. Additions:

- server-rendered history (survives reloads)
- an animated typing indicator
- assistant follow-up questions as separate bubbles
- quick-reply chips (location choices, buy/rent, cities)
- a "Your requirement so far" panel
- match cards with label, explanation, reasons and caveats; amber labels for above-budget
- Save requirement / Contact dealers (with an inline consent box) / New search
- suggested prompts in English, Roman Urdu and Urdu
- right-to-left handling for Urdu input (`dir="auto"`)

Checked in the browser at 375 px (phone), 768 px (tablet) and 1366 px (desktop):

- no horizontal scrolling at any width
- quick replies and buttons wrap cleanly
- Urdu is laid out right to left

Three fixes came out of the phone check:

- **Chat first:** below 1280 px the chat comes before the results, so results follow the conversation.
- **Bounded thread:** the chat thread is limited to 60% of the screen height, so the message box stays in reach.
- **Shorter placeholder:** "English, اردو or Roman Urdu…" fits on one line.

Replies with results now add a **"View N matches ↓"** button that scrolls to the cards; it replaced a scroll-to-results that never fired. The desktop two-column layout is unchanged.

## 19. WhatsApp architecture

**COMPLETE BUT CREDENTIAL-BLOCKED.** Built and tested with test doubles; no live Meta account was used.

Pipeline: WhatsApp → `WhatsAppWebhookController` → `WebhookProcessor` (channel adapter) → `ConversationAssistant` → `WhatsAppClient`.

| Concern | Implementation |
|---|---|
| Handshake | `GET /webhooks/whatsapp` hub challenge (existing) |
| Not configured | 503, nothing stored |
| Signature | HMAC-SHA256 with `WHATSAPP_APP_SECRET`; invalid → 401 |
| Validation | Wrong shape → 422, recorded as `rejected` |
| Idempotency | Repeated delivery (same body hash) → `duplicate`, nothing processed; per-message id uniqueness (existing) |
| Logging | Every delivery in `webhook_events` (processed / duplicate / rejected / failed) |
| Errors | Failure → `failed` with the error, `Log::error`, admin alert, HTTP 500 so Meta retries safely |
| Human hand-over | "human / person / team" → mode human, no bot replies (existing behaviour kept) |
| Non-text messages | Polite "text only" reply |
| Sending | Only when `WHATSAPP_SEND_ENABLED=true` and all credentials are set; otherwise replies are stored as `not_sent` |

The test flow:

1. "Assalamualaikum, mujhe Gulberg Lahore mein 10 marla ka ghar chahiye 5 crore tak"
2. "dealer se baat karwa dein" — the assistant asks for YES
3. "YES" — requirement with phone, consent and a lead routed to the matching dealer

## 20. Consent architecture

**COMPLETE AND VERIFIED.** `contact_consents` records:

- user or conversation
- channel
- a SHA-256 hash of the phone or email (never the raw value)
- purpose (`share_with_dealers`, `whatsapp_replies`, `listing_alerts`)
- source and IP
- grant and revoke times

Recorded on:

- the website consent button
- WhatsApp YES
- WhatsApp save

WhatsApp STOP revokes all consents for that number and closes the chat (tested).

No promotional WhatsApp messages are sent anywhere. Proactive WhatsApp would need approved templates and a `listing_alerts` opt-in flow — **NOT IMPLEMENTED** (by design, pending templates).

## 21. Notifications

| Recipient | Notification | Status |
|---|---|---|
| Buyer | New matching listing for an AI-saved requirement (`meezan:requirement-matches`, daily 08:30) | COMPLETE AND VERIFIED (in-app); email credential-blocked (mail is `log`) |
| Buyer | Requirement shared with N dealers | COMPLETE AND VERIFIED (in-app) |
| Buyer | Requirement update | PARTIALLY: shown in chat and on My requirements; no separate notification |
| Dealer | New qualified lead / lead assignment | COMPLETE AND VERIFIED (existing `LeadAssigned`, reused) |
| Dealer | Lead update | NOT IMPLEMENTED |
| Admin | AI escalation (asked for a person; 3 messages not understood) | COMPLETE AND VERIFIED |
| Admin | Failed webhook | COMPLETE AND VERIFIED |
| Admin | Suspicious repeated requests (more than 20 messages a minute in a conversation, flagged once) | COMPLETE AND VERIFIED |

All use the existing queued in-app + mail base. Email delivery is not claimed: `MAIL_MAILER` is `log`.

## 22. Admin AI management

**COMPLETE AND VERIFIED by automated tests.** Not checked in the browser: the admin password was changed by you and was not reset.

**`/admin/conversations` (menu "AI & WhatsApp"):**

- WhatsApp status and parser in use (rules or LLM)
- counters: needs a person, not understood, AI requirements, AI leads, failed webhooks in the last 7 days
- filters: channel, status, bot/human mode
- each row shows the requirement summary and the lead's status
- phone numbers masked (`•••• 2233`)

**Conversation page:**

- the transcript with intents
- "What the assistant understood"
- requirement, lead and assigned dealers
- Mark handled (audited)
- Show number (audited as `conversation.contact_revealed`)
- bot / human / close controls

Admin role required (non-admins get 403, tested).

## 23. LLM integration status

**COMPLETE BUT CREDENTIAL-BLOCKED. The LLM has not been tested against a live API.**

- Driver selection: `MEEZAN_AI_DRIVER=rules` (default) or `llm`. `llm` is used only when `OPENAI_API_KEY` is set.
- Fallback is tested three ways:
  - no key → rules
  - API HTTP 500 → rules results
  - parser exception → rules, with no technical error shown to the buyer
- Place names the model returns are accepted only if they resolve within the rules-detected city.
- Keys stay server-side; nothing is exposed to JavaScript.

## 24. External credentials required

| Feature | Settings |
|---|---|
| LLM parsing | `MEEZAN_AI_DRIVER=llm`, `OPENAI_API_KEY`; optionally `OPENAI_BASE_URL`, `OPENAI_MODEL` |
| WhatsApp receive | `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`, plus the webhook URL registered in Meta |
| WhatsApp send | The above, plus `WHATSAPP_SEND_ENABLED=true` |
| Proactive WhatsApp alerts | Approved message templates in Meta Business Manager (not built) |
| Email | A real `MAIL_MAILER` and `MAIL_FROM_ADDRESS` |
| Retention | Decide and set `MEEZAN_AI_RETENTION_DAYS` (0 keeps conversations indefinitely) |

## 25. Files changed

**New files:**

- **Migrations:** `database/migrations/2026_10_31_000021` – `000024`
- **Models:** `app/Models/ContactConsent.php`, `WebhookEvent.php`
- **Assistant:** `app/Services/Assistant/ConversationAssistant.php`, `IntentDetector.php`, `RequirementState.php`, `RequirementSaver.php`, `AssistantReply.php`
- **Support:** `app/Support/UrduText.php`
- **Notifications:** `app/Notifications/RequirementMatchedDealers.php`, `NewRequirementMatches.php`, `AssistantNeedsAttention.php`
- **Commands:** `app/Console/Commands/SendRequirementMatches.php`, `PruneConversations.php`
- **Views:** `resources/views/ai-finder/partials/requirement-summary.blade.php`
- **Tests:** `tests/Feature/AiAssistantTest.php`
- **Report:** this file

**Modified files:**

- **Models:**
  - `app/Models/Conversation.php`
  - `ConversationMessage.php`
  - `BuyerRequirement.php`
- **Parsing and matching:**
  - `app/Services/Ai/RulesRequirementParser.php`
  - `LlmRequirementParser.php`
  - `LocationExtractor.php`
  - `app/Services/Matching/MatchCriteria.php`
  - `PropertyMatcher.php`
  - `PropertyMatch.php`
- **WhatsApp:** `app/Services/WhatsApp/WebhookProcessor.php`
- **Controllers:**
  - `app/Http/Controllers/AiFinderController.php`
  - `Admin/ConversationController.php`
  - `Webhooks/WhatsAppWebhookController.php`
- **Config and routes:** `config/meezan.php`, `routes/web.php`, `routes/console.php`
- **Views:**
  - `resources/views/ai-finder/show.blade.php`
  - `ai-finder/partials/results.blade.php`
  - `admin/conversations/index.blade.php`
  - `admin/conversations/show.blade.php`
  - `dealer/leads.blade.php`
- **JavaScript:** `resources/js/ai-finder.js`
- **Formatting only:** `docs/research/build_dataset.php`

`public/Refrence/` was not touched.

## 26. Automated test results

| Command | Result |
|---|---|
| `php artisan test` | **339 passed, 1,420 assertions, 0 failed.** All 291 tests that existed before this phase still pass; 48 are new. |
| `npm run build` | Success |
| `composer audit` | No advisories |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `vendor/bin/pint --test` | Passed |
| `php artisan route:cache` | Routes cached successfully (then cleared, as in earlier phases) |

The new tests cover:

- conversations: creation, persistence, memory, new conversation, ownership/isolation, user link
- parsing: English, Roman Urdu, Urdu, 14 size and budget cases, furnishing and reasons, partial and updated requirements, no repeated questions, ambiguous location by city or number
- cities: all 7, Rawalpindi refusal, phase abbreviations and sector codes, no cross-city matches
- matching: budget labels, alternatives only when on target, area-first ranking, factual explanations, zero results
- requirements and leads: guest save blocked, one requirement per conversation that stays current, no lead for small talk, consent required, at most 3 dealers in the city, once, role restrictions
- LLM: no key, HTTP failure, parser exception
- WhatsApp: full Roman Urdu flow with YES consent, STOP opt-out, invalid payload, duplicate delivery, failed processing with admin alert
- escalation, burst flagging, admin masking and audited reveal, retention policy, the new-match command

## 27. Manual test results

In the in-app browser on `localhost:8000`:

| Check | Result |
|---|---|
| "I want a house in Lahore" | 3 Lahore results, then "Are you looking to buy or to rent?" |
| "10 marla and around 4 crore" | Remembered Lahore; panel shows City Lahore · Buy (assumed) · House · From 10 Marla · Around PKR 4 Crore; 2 cards with explanations ("…within your PKR 4 Crore budget"); Save / Contact / New search shown |
| Reload | History restored |
| New search, then Urdu "مجھے گارڈن ٹاؤن میں دس مرلہ گھر چاہیے" | Chips: Garden Town, Lahore · Garden Town Multan, Multan · Garden Town, Quetta. Choosing Lahore kept "10 marla house" |
| Existing search (`/properties?location=Lahore`) | "16 Properties in Lahore" |
| Map (`/map?city=Lahore`) | 12 pins, tiles loaded, no console errors |
| AI finder requests | All 200. Four earlier 419 errors in the console came from fetch calls made from a stale page before a logout, not from the new code |
| Laravel log | No errors today (the last error is yesterday's geocoding timeout, already handled) |
| Phone (375 px) | No horizontal scroll; chat before results; thread bounded; "View 3 matches ↓" scrolls to the cards; placeholder fits one line |
| Tablet (768 px) | Garden Town chips in one row; no horizontal scroll |
| Desktop (1366 px) | Chat beside results as before |

**Not checked manually:**

- the admin AI pages (your admin sign-in is needed)
- Save / Contact dealers in the browser (covered by tests; skipped to avoid creating extra local requirement and lead records)
- a live WhatsApp exchange (no credentials)

The browser checks created a few website conversation records in the local database, as normal usage would.

## 28. Outstanding issues

1. **WhatsApp and the LLM are not live**; both need credentials (§24).
2. **Email delivery is not live** (`MAIL_MAILER=log`).
3. **Not extracted:** property age, and Urdu-script place names outside the dictionary.
4. **No "lead update" notifications for dealers**, and no proactive WhatsApp templates.
5. **When the purpose isn't stated yet,** the first results can mix sale and rent listings; the assistant asks buy-or-rent straight away.
6. **The size label isn't capped:** an 8-marla house can still be called "Good match" for a 10-marla request. The size shortfall is shown as a caveat, but the label doesn't cap it.
7. **The retention period is still a policy decision** (currently keep indefinitely).
8. **Admin pages need your QA.**

## 29. Recommended next phase

1. **Admin QA** of the AI assistant pages, and setting a retention period.
2. **Connect the WhatsApp Cloud API in a test number sandbox:**
   - register the webhook
   - verify receiving
   - then enable sending and create approved templates for opt-in listing alerts
3. **Optionally enable the LLM driver** with a key, and compare its results against the rules engine on a labelled set of real buyer messages before switching.
4. **Dealer lead-update notifications**, and buyer-side "dealer contacted you" tracking.
5. **Matching refinements:** make size shortfalls affect the label, and add a property-age field to the listing form.

---

## Totals

| Measure | Value |
|---|---|
| Tests passed | **339** |
| Assertions | **1,420** |
| Failed tests | **0** |
| New database tables | **2** (`contact_consents`, `webhook_events`), plus new columns on `conversations`, `conversation_messages` and `buyer_requirements` |
| New routes | **5**: `POST /ai-property-finder/reset`, `POST /ai-property-finder/save`, `POST /ai-property-finder/contact`, `POST /admin/conversations/{id}/resolve`, `POST /admin/conversations/{id}/reveal` |
| New scheduled commands | `meezan:requirement-matches` (daily), `meezan:prune-conversations` (daily; no-op until a retention period is set) |
| External credentials still required | OpenAI-compatible API key (LLM); WhatsApp phone number id, access token, app secret, verify token (+ sending flag); mail provider; WhatsApp message templates for proactive alerts |
