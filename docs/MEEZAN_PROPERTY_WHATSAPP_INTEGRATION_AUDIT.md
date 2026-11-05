# Meezan Property: WhatsApp Integration Audit

Date: 5 November 2026. Done before any code change. Backup taken first: `C:\xampp\backups\MeezanProperty-before-whatsapp-integration-2026-11-05\` (database dump, `.env` copy and row counts).

**Main finding: most of this already exists.** Phase 10 built the WhatsApp channel and Phase 15 connected it to the Central AI Brain. It has never run against Meta. This work extends it, and does not build a second system.

## 1. Existing WhatsApp files

| Area | Files | State |
|---|---|---|
| Webhook | `Http/Controllers/Webhooks/WhatsAppWebhookController`, route `/webhooks/whatsapp` | Handshake, HMAC signature, payload shape and duplicate checks work. Tested with faked data only |
| Inbound processing | `Services/WhatsApp/WebhookProcessor`, `Jobs/ProcessInboundWhatsAppMessage` | Stores each message once, queues the AI step |
| Sending | `Services/WhatsApp/OutboundMessenger`, `Providers/MetaCloudProvider`, `Providers/MockProvider`, `Jobs/SendWhatsAppMessage` | Text, reply buttons and templates. Retries temporary errors |
| Rules | `MessagePolicy`, `WhatsAppSettings`, `WhatsAppFormatter`, `AccountLinker`, `Support/WhatsAppNumber` | Opt-out, consent, 24-hour window, rate limits, switches |
| Admin | `Admin/WhatsAppController`, `Admin/ConversationController`, `admin/whatsapp/*`, `admin/conversations/*` | Status page, message log, mock console, staff replies |
| Notifications | `Notifications/Channels/WhatsAppChannel`, `LeadAssigned`, `AssistantNeedsAttention` | Dealer alerts and admin alerts |
| Other | `Console/Commands/WhatsAppSweep`, `Models/OutboundMessage`, `Models/ContactConsent`, `Models/WebhookEvent` | Working |

## 2. AI orchestration

- `CentralAiOrchestrator::submit()` is the only way in to the 16 agents. It checks that the agent is active, runs its workflow, and records the task.
- `ToolRunner` is the only way an agent reaches data. It checks the agent's permission and logs each call.
- `AgentChatRouter` sends a narrow set of chat questions to agents: Customer Support, Investment Analyst and a signed-in dealer's own data. Everything else goes to `ConversationAssistant`, the property search shared with the website.
- All 16 agents are registered and active in the database. They run fixed workflows (`planning = workflow`; none uses model tool calls).

## 3. Gemini

`AiProvider` and `GeminiAdapter` are the only provider layer. They apply the model settings, token tracking, budgets and per-channel routing. The key is saved encrypted in Gemini Settings. WhatsApp already reaches Gemini through `AiProvider::inChannel('whatsapp', …)`. No WhatsApp code names a model. Nothing here needs changing. The free tier allows about 20 requests per model per day, which limits how much AI WhatsApp can use.

## 4. Customers and dealers

- `users` holds accounts with roles buyer, dealer and admin. `dealer_profiles.whatsapp_number` and `users.notify_whatsapp` exist.
- WhatsApp **never creates accounts**, so it can't create duplicates. A chat links to an account only by a one-time code (`LINK 123456`). Phase 10 rejected matching by phone number because account numbers aren't verified. That decision stays.

## 5. Leads and CRM

`BuyerRequirement`, `Lead`, `LeadAssignment`, `RequirementSaver` and `LeadRouter` are reused as they are. A lead is created only after the customer replies YES.

`SupportTicket` already exists and has 0 rows. Agents open tickets through `support.create_ticket`.

## 6. Message tables

| Brief asked for | Already exists | Decision |
|---|---|---|
| WhatsApp conversations | `conversations` (channel `whatsapp`, 12 rows across channels) | Reuse. Add routing and handoff columns |
| WhatsApp messages | `conversation_messages`, `outbound_messages` | Reuse. Add agent and staff columns |
| Webhook events | `webhook_events` | Reuse. Add event type and processed time |
| Delivery status history | `sent_at`, `delivered_at`, `read_at`, `failed_at` on `outbound_messages` | Enough. No new table |
| Templates | Named in `config/meezan.php` | Enough for now. Meta is the source of truth for approved templates |
| Settings | `app_settings` (switches only, never credentials) and `.env` | Reuse |

## 7. Admin and permissions

`routes/web.php` has an admin group limited to the admin role. There is no separate staff role, so "assigned staff" means an active administrator. The nav already has "AI & WhatsApp" and "WhatsApp Control".

## 8. Notifications

`AssistantNeedsAttention` (in-app and queued email) goes to active admins. It is reused for handoff alerts.

## 9. Missing or wrong

| # | Gap | Why it matters |
|---|---|---|
| 1 | No test and production separation; setting names differ from the brief | The Meta test setup can't be configured safely |
| 2 | `hello_world` would be sent with an empty parameter list | Meta's documentation shows no `components` for a template without variables, so this may be rejected. It is the first live test |
| 3 | Graph URL hardcoded in two places; 10-second timeout; version `v21.0` | Brief asks for configurable values and `v25.0` |
| 4 | No list or media messages; inbound media metadata and captions discarded | Brief requires them |
| 5 | No `/api/whatsapp/webhook` route | Brief's expected address |
| 6 | Meta errors give only a code | Staff can't tell an expired token from a rate limit |
| 7 | **STOP is ignored while a person is handling the chat** | The opt-out never runs in human mode. Compliance bug |
| 8 | A late "sent" status can overwrite "read" on the conversation message | The outbound row is protected, but the conversation copy isn't |
| 9 | Only Customer Support and Investment Analyst take customer questions | Brief lists eight routes |
| 10 | Handoff has no assignment, status or timestamps | Brief requires them |
| 11 | Admin list has no search, handoff filter, assignment or last message | Brief requires them |

## 10. Risk in the brief's routing table

The brief routes payment issues to Finance, technical issues to Technical Operations, and dealer registration to Dealer Management. **Those agents' workflows are staff reports.** Finance's default workflow reports revenue, Dealer Management's lists pending dealers, and Technical Operations' reports system health. Sending a customer's message straight to them could expose internal data to anyone who messages the number.

So the routing is kept, with protection added:

- each of these agents gets a separate customer-safe workflow, chosen explicitly and never by keywords;
- customer tasks are marked `audience = customer`, and `ToolRunner` refuses any tool outside a short allow-list, whatever the agent's own permissions;
- customer tasks can't delegate, can't use model tool calls, and don't see internal notes;
- knowledge search is limited to public categories on customer tasks.

## 11. Reused without modification

`GeminiAdapter`, `AiProvider`, `BudgetGuard`, `RequirementSaver`, `LeadRouter`, `ContactConsent`, `AuditLogger`, `AccountLinker`, `WhatsAppSweep`, `PropertyMatcher`, `LocationResolver`, `RulesRequirementParser`.

## 12. Colours

The brief lists `#4B1592` and `#D6A13D`. On 30 October 2026 the owner chose to keep the Brand Style Guide palette (`#4B2E83`, `#D4AF37`) even when a brief quotes other values, so the existing tokens are used.
