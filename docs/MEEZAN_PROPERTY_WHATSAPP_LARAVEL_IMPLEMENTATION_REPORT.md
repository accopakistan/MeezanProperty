# Meezan Property: WhatsApp Laravel Implementation Report

Date: 5 November 2026. Local only: nothing is committed or deployed, no real WhatsApp message has been sent, and no secret has been entered.

## Correction, 5 November 2026 (later)

The owner's admin correction added these. Everything else in this report still applies.

- **Mock mode:** `WHATSAPP_MOCK_MODE` now decides the driver, ahead of `MEEZAN_WHATSAPP_DRIVER`. The admin switch can still force mock on. Nothing turns it off automatically.
- **Indicators:** Mock Mode, Meta Test API and Production API, plus what outgoing messages are right now. Each outgoing message records whether it was a mock, a real Meta test or a production message (new column `outbound_messages.api_mode`, migration `000056`).
- **Numbers:** the admin page separates the customer-facing number (`MEEZAN_WHATSAPP_NUMBER`), Meta test sender, Phone Number ID and verified test recipient. A check catches a phone number used as the Phone Number ID.
- **Outbound test:** needs only the access token. Test Meta connection is read-only and works in mock mode. Send test message needs mock off, Meta Test API ready, and a ticked approval box.
- **Incoming:** shown as working only with a public address, Meta's verification and a real message received.

## 1. Where it stands

WhatsApp is now connected to the Central AI Brain and set up for the Meta developer test account. It has only been tested with mocked Meta responses.

| Works now (tested with mocks) | Needs your credentials | Needs a public HTTPS address |
|---|---|---|
| Configuration, outbound messages, webhook, AI routing, handoff, admin | The live `hello_world` test, connection test | Real incoming messages, delivered and read updates, AI replies to real people |

The steps for you are in `MEEZAN_PROPERTY_WHATSAPP_SETUP_GUIDE.md`.

## 2. Files

**New**

| File | Purpose |
|---|---|
| `routes/api.php` | `/api/whatsapp/webhook`, with no session or CSRF |
| `app/Services/WhatsApp/HumanHandoff.php` | Pending, assigned and resolved handoff |
| `app/Console/Commands/WhatsAppTestSend.php` | `meezan:whatsapp-test-send`, the owner's check and live test |
| `database/migrations/2026_11_05_000055_add_whatsapp_routing_and_handoff.php` | See section 3 |
| `tests/Feature/WhatsAppMetaIntegrationTest.php` | 72 tests |
| `docs/MEEZAN_PROPERTY_WHATSAPP_*.md` | The four reports |

**Changed**

| Area | Files |
|---|---|
| Configuration | `config/services.php`, `config/meezan.php`, `.env.example`, `phpunit.xml`, `bootstrap/app.php`, `routes/web.php` |
| WhatsApp | `WhatsAppClient`, `MessagePolicy`, `OutboundMessenger`, `WebhookProcessor`, `WhatsAppFormatter`, `WhatsAppSettings`, `Providers/MetaCloudProvider`, `WhatsAppWebhookController`, `ProcessInboundWhatsAppMessage` |
| Brain | `AgentChatRouter`, `CentralAiOrchestrator`, `IntentRouter`, `ToolRunner`, `ToolRegistry`, `AgentContext`, `Report`, `AgentCatalog` |
| Agents (one customer workflow added to each) | `Agent` base, `DealerManagerAgent`, `SubscriptionManagerAgent`, `FinanceManagerAgent`, `TechnicalOperationsAgent`, `PropertyManagerAgent`, `InvestmentAnalystAgent`. `CustomerSupportAgent`: a complaint now also hands the chat to staff |
| Assistant | `ConversationAssistant`, `IntentDetector` |
| Models | `Conversation`, `ConversationMessage`, `OutboundMessage`, `WebhookEvent`; `AssistantNeedsAttention` |
| Admin | `ConversationController`, `WhatsAppController`, `SettingsController`, and the views `admin/whatsapp/index`, `admin/conversations/index`, `admin/conversations/show` |
| Built assets | `public/build` rebuilt for the new classes |
| `.env` | 18 settings appended, with the 3 secrets and the production values left empty. Existing lines confirmed unchanged. Copy kept in the backup folder |

## 3. Database

One additive migration, already run. Nothing was dropped or rewritten, and no table was added.

| Table | New columns |
|---|---|
| `conversations` | `assigned_agent`, `assigned_to`, `handoff_status`, `handoff_reason`, `handoff_requested_at`, `handoff_assigned_at`, `handoff_resolved_at`, `internal_note` |
| `conversation_messages` | `agent_slug`, `staff_id` |
| `webhook_events` | `event_type`, `processed_at` |
| `ai_agents` | Finance and Technical Operations gain `support.create_ticket`, each as a new recorded version |

The brief's other suggested tables already exist or weren't needed. See section 6 of the audit.

## 4. Routes added

| Route | Name |
|---|---|
| `GET /api/whatsapp/webhook` | `api.whatsapp.verify` |
| `POST /api/whatsapp/webhook` | `api.whatsapp.webhook` |
| `POST /admin/whatsapp/test-send` | `admin.whatsapp.test-send` |
| `POST /admin/conversations/{conversation}/handoff` | `admin.conversations.handoff` |
| `PATCH /admin/conversations/{conversation}/note` | `admin.conversations.note` |

`/webhooks/whatsapp` still works.

## 5. Services

The brief suggested `WhatsAppCloudApiService`, `WhatsAppMessageService` and `WhatsAppWebhookService`. The same jobs already exist, so these were extended rather than duplicated:

| Suggested | Existing |
|---|---|
| Cloud API service | `Providers/MetaCloudProvider` |
| Message service | `OutboundMessenger` and `MessagePolicy` |
| Webhook service | `WebhookProcessor` |

**Added to sending:** list and media messages; `hello_world` without an empty parameter list; a configurable address, version, timeout and retry count; recipient validation; plain descriptions of Meta's error codes; and retries for rate limits that Meta returns as HTTP 400. Meta's own error text isn't stored.

**Added to receiving:** a 3 MB size limit, media references and captions, event type, and processed time. Media files are not downloaded.

## 6. Gemini and agent routing

```
WhatsApp → webhook → WebhookProcessor → ProcessInboundWhatsAppMessage
  → ConversationAssistant → AgentChatRouter → CentralAiOrchestrator → agent
  → OutboundMessenger → MessagePolicy → Meta
```

There is no second chatbot and no second Gemini setup. No WhatsApp code names a model.

| Customer request | Agent | What it does |
|---|---|---|
| Find a property | Sales Manager, through the shared property assistant | Requirement memory, real listings, and a lead after YES |
| Register as a dealer | Dealer Management | Explains, gives the link, records interest once per chat |
| Investment advice or the Index | Investment Analyst | Published data only. Says "Insufficient Verified Data" when that is the position |
| Listing information | Property Management | Public help and the link |
| Subscription information | Subscription Manager | Public help and the pricing link. A linked dealer sees their own plan |
| Payment issue | Finance Manager | Reads no records, opens a ticket, hands the chat to staff |
| Technical issue | Technical Operations | Shares no diagnostics, opens a ticket |
| General help or a complaint | Customer Support | Approved answers. A complaint opens a ticket and hands the chat to staff |

**How a route is chosen:**

1. Fixed patterns in English, Roman Urdu and some Urdu.
2. Otherwise, property search.
3. If the message isn't a search either, Gemini may pick one of the customer routes. Its answer is rejected if it names anything else.
4. If there is no model, or it can't pick, the customer is asked for more and told how to reach a person.

**Honest limits of this routing:**

- Property search runs in the shared assistant. It is recorded under the Sales Manager, because that agent's own workflows are staff reports. The brief's "Sales Manager" route is therefore a label on the conversation, not that worker running.
- Roman Urdu is the reply language for Urdu messages on the new workflows, with text written in advance. Urdu script replies are not generated.

## 7. Permissions

Most agents' normal workflows are staff reports, so customer messages are kept away from them in four layers:

1. `AgentChatRouter::CUSTOMER_ROUTES` names the only workflows a customer can reach. These workflows have no trigger words, so keywords can't select them.
2. Every such task is marked `audience = customer`.
3. `ToolRunner::CUSTOMER_TOOLS` refuses anything else on those tasks, even a tool the agent normally has.
4. Customer tasks can't delegate, can't use Gemini tool calls, don't read internal notes, and search public knowledge only.

The AI can't approve payments, refunds, legal claims, subscriptions or commitments. Those requests become a ticket and a handoff.

**The same restrictions now apply to website chat questions** that reach agents, since they use the same router. Website routing patterns are unchanged, and its existing tests pass.

## 8. Human handoff

- **Starts when:** the customer asks ("human", "representative", "team se baat" and similar), reports a payment issue, or makes a complaint.
- **While open:** the AI stops replying, and messages are stored for staff. STOP still works.
- **Staff can:** assign the chat, reply (a reply to a waiting chat assigns it to whoever replied), resolve, or resolve and resume the AI.
- **Resuming:** only a person can resume the AI. Admins are alerted once per handoff.

"Assigned staff" means an active administrator, because the app has no separate staff role.

## 9. Admin dashboard

| Page | Added |
|---|---|
| WhatsApp Control | Meta configuration (IDs, environment, and yes or no for each secret), setup warnings, last incoming message, last message accepted by Meta, waiting count, Send test message |
| AI & WhatsApp list | Search by name or last digits, handoff and agent filters, last message, agent and assigned staff |
| Conversation | Handoff panel, AI or staff label on each reply, internal note |

The existing mock switch was relabelled **Mock mode**, so it isn't confused with Meta's test mode. Colours are the existing Brand Style Guide tokens (see section 12 of the audit).

## 10. Security

- Secrets stay in `.env` only. Tests confirm that they don't appear on admin pages, in command output or in stored messages.
- Signature: HMAC-SHA256 over the raw body, compared in constant time.
- Duplicates: payload hash and message id. Replays store nothing new.
- Webhook events keep no message content. Logs keep no numbers, text or tokens.
- Test mode can only send to the verified recipient, and refuses everything if none is set.
- Production never falls back to the test ids.
- A live send happens only when a person runs the command or presses the button.

## 11. Bugs found and fixed

| Bug | Fix |
|---|---|
| **STOP was ignored while staff handled a chat** | STOP now always runs and closes any handoff |
| A late "sent" status overwrote "read" on the conversation message | Status now moves forward only |
| `hello_world` would be sent with an empty parameter list | The list is left out when there are no parameters |

## 12. Remaining Meta configuration

Access token, app secret, verify token, webhook subscription to `messages`, and an approved dealer alert template. Production also needs a real number and a permanent token.

## 13. Remaining hosting requirements

Public HTTPS domain, deployment, a running queue worker or the scheduler, and a server `.env` with the same secrets.

## 14. Known limitations

1. **Nothing has been tested against Meta**, including list and media formats.
2. **Gemini was not called live.** The free tier allows about 20 requests per model per day, so a busy number would fall back to rules quickly.
3. **Timeframe is not collected** during lead qualification. The assistant asks about purpose, type, budget and size. Timeframe would change the shared website flow, so it was left out.
4. **Media isn't understood.** Images are stored by reference only, and a caption is read as text.
5. **An unclear message after a saved search** re-shows that search. It is harmless and leaks nothing, but it's not a good answer. This is existing behaviour.
6. **No automatic deletion of messages.** Retention needs a decision from you, because deleting customer records can't be undone.
7. **A resolved handoff stays with staff** unless someone chooses resume, and nothing reminds staff about a chat left waiting.
8. **Number matching to accounts stays off** by design. A chat links to an account by one-time code only.
9. **Admin pages were checked as rendered snapshots**, not by signing in, because the admin password is yours.
10. **Host header gap:** the app has no trusted-hosts list, so a faked `Host` header can reach password-reset links. It isn't caused by this work, and should be fixed before deployment. The 7 Symfony advisories found during this work are fixed (v7.4.23).
