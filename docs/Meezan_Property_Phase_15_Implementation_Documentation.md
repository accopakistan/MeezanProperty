# Meezan Property — Phase 15 Implementation Documentation

Central AI Brain and 16 agents, as actually built. Written 3 November 2026, after the continuation work. Results and status are in `Meezan_Property_Phase_15_Completion_Report.md`; how the work was recovered is in `Meezan_Property_Phase_15_Continuation_Audit.md`.

**Read this first:** agents are deterministic workflows that call tools against the real database. A language model is optional. When configured, it only chooses a route or workflow and words the final summary. No API key is configured today, so everything below has run in **rules mode** only.

---

## 1. Architecture

```
Website chat ─┐
WhatsApp ─────┼─► ConversationAssistant ─► AgentChatRouter (narrow patterns only)
              │                                   │
Admin form ───┤                                   ▼
Schedule ─────┤                      CentralAiOrchestrator ◄── AgentEvents (model events)
Events ───────┘                       │ submit → route → execute
                                      │
       IntentRouter ◄─────────────────┤  picks the agent (rules; model optional)
       AgentCatalog / AiAgent ◄───────┤  registry and permissions
       Workers\*Agent ◄───────────────┤  picks a workflow, builds a Report
       ToolRunner → ToolRegistry ◄────┤  every data access: permission, limits, validation, audit
       MemoryStore, KnowledgeBase ◄───┤  controlled context
       ApprovalExecutor ◄─────────────┤  after a human decision only
       AiProvider + BudgetGuard ◄─────┤  optional model call, within budget
       Grounding, OutputGuard ◄───────┘  no invented figures, no secrets in output
```

**Rules the structure enforces**

- Agents reach each other only through `CentralAiOrchestrator::delegate()`. Only the AI CEO may delegate; depth is capped at 2 and subtasks at 8 per root task.
- Workers never touch models directly; they call tools through `AgentContext`.
- Agents have no SQL, shell or file tools. Prohibited tools exist only so the request is recognised and refused.

## 2. Services and files

| Class | Responsibility |
|---|---|
| `Services/Agents/CentralAiOrchestrator` | Submit (idempotent), atomic claim, execute, delegate, retry, cancel, optional model wording and workflow choice, failure handling |
| `IntentRouter` | Chooses the agent. Broad requests go to the CEO; otherwise the best keyword score; Customer Support loses ties |
| `AgentCatalog` | The 16 definitions (seed for the registry), base rules, connector needs |
| `AgentContext` | One task's view: tools, memory, delegation, counters |
| `ToolRegistry`, `ToolDefinition`, `ToolRunner` | 50 tools; validation, permissions, limits, audit |
| `ApprovalExecutor` | Carries out an approved action once; admin only |
| `MemoryStore`, `KnowledgeBase` | Permission-aware memory; keyword search over approved documents |
| `AiProvider`, `BudgetGuard` | The single model client; token and cost limits |
| `AgentChatRouter`, `AgentEvents` | Website/WhatsApp entry; business events |
| `AgentReadiness` | Operational status from evidence (added in continuation) |
| `AgentAlerts`, `Notifications/AgentNeedsAttention` | Tells admins about approvals, tickets, failures, budgets (added) |
| `ConnectorStatus` | Integration status; "verified" only after a real check |
| `OutputGuard` | Removes secrets, tokens, emails and phone numbers from output |
| `Report` | Structured result: findings, sections, recommendations, missing, limitations, sources |
| `Support/Grounding` | Whole-figure check on model wording (added; also used by Phase 14 insights) |
| `Workers/Agent` + 16 workers | Workflows per agent |
| `Jobs/RunAiTask` | Queued execution on the `agents` queue |
| `Console/Commands/AgentsScheduled` | `meezan:agents health / subscriptions / ceo-weekly / maintenance` |
| `Http/Controllers/Admin/AiCommandCenterController` | All Command Center pages |

## 3. Database

All additive. **Migrations:** `2026_11_03_000043_create_ai_agent_tables`, `…000044_seed_ai_agents_and_knowledge`, `…000045_grant_new_agent_tools`. The last one changes no schema; it adds four tools to existing agents and records a new agent version, keeping any admin edits.

| Table | Purpose |
|---|---|
| `ai_agents`, `ai_agent_versions` | Registry; every change versioned |
| `ai_tasks` | Task, parent and root, depth, source, idempotency key, status, attempts, result, mode, tokens, cost |
| `ai_tool_calls` | One audit row per call (input, status, summary, error, duration); immutable |
| `ai_approvals` | Action, payload, evidence, status, decider, execution result, expiry |
| `ai_memories` | Scope (task, agent, shared), source, status, version, approver, expiry |
| `ai_knowledge_documents` | Category, body, source, visible agents, status, version |
| `ai_usage_records` | Provider usage per model call; cost null without configured pricing |
| `ai_drafts` | Draft content: draft, approved, rejected, published |
| `support_tickets` | Escalations, `SUP-` reference: open, in_progress, resolved |
| `staff_tasks` | Internal tasks: open, done, cancelled; link to an approval |

Reused: `app_settings` (`ai.pricing`, `connectors.checks`), `audit_logs`, `jobs`, `notifications`.

## 4. The 16 agents

| Agent (slug) | Stage | Tier | Workflows | Assigned tools |
|---|---|---|---|---|
| AI CEO (`ceo`) | B | reasoning | business report, expansion (city-aware), question | city.statistics, leads.summary, payments.revenue, subscriptions.summary, properties.inventory, dealers.performance, system.health, ai.usage, staff.create_task, knowledge.search |
| Property Management (`property-manager`) | B | extraction | inventory, incomplete, duplicates, anomalies, stale, review | properties.search/inventory/incomplete/duplicates/price_anomalies/stale, listings.flag, knowledge.search |
| Dealer Management (`dealer-manager`) | B | extraction | pending, performance, inactive, quality, new dealer, verify | dealers.pending_verification/performance/inactive/listing_quality, subscriptions.utilization, staff.create_task, dealers.verify, knowledge.search |
| Customer Support (`customer-support`) | B | support | FAQ, find, escalate | knowledge.search (public categories only), properties.search, support.create_ticket |
| Sales Manager (`sales-manager`) | B | routine | leads, follow-ups, pipeline, patterns, own leads, new lead, reassign | leads.summary, crm.follow_ups_due/pipeline/outcomes, dealers.performance, dealer.self_summary, staff.create_task, leads.reassign, communications.send_bulk, knowledge.search |
| Investment Analyst (`investment-analyst`) | C | research | rankings, compare, yield, data status, methodology | investment.rankings/compare/yield, market_data.quality, investment.methodology_change, knowledge.search |
| Finance Manager (`finance-manager`) | C | extraction | revenue, outstanding, reconcile, AI cost, refund (refused) | payments.revenue/outstanding/reconciliation, subscriptions.summary, ai.usage |
| Subscription Manager (`subscription-manager`) | C | routine | summary, expiring, utilisation, own plan, change | subscriptions.summary/expiring/utilization, dealer.self_summary, content.save_draft, subscriptions.change, knowledge.search |
| Competitor Research (`competitor-research`) | C | research | summary from approved notes | knowledge.search, connectors.status, content.save_draft |
| Global Property Data (`global-data-manager`) | C | extraction | coverage, quality, review, markets | locations.coverage/review_queue, market_data.quality, investment.rankings, staff.create_task, knowledge.search |
| Marketing Director (`marketing-director`) | D | reasoning | strategy, funnel | marketing.funnel, city.statistics, leads.summary, content.save_draft, content.publish, knowledge.search |
| SEO Manager (`seo-manager`) | D | research | audit, brief | seo.audit, city.statistics, content.save_draft, connectors.status, knowledge.search |
| Social Media (`social-media-manager`) | D | support | posts, calendar, publish, engagement | content.save_draft, content.publish, city.statistics, connectors.status, knowledge.search |
| Technical Operations (`technical-operations`) | D | routine | health, integrations, remediate (refused, passed to staff) | system.health, connectors.status, ai.usage, staff.create_task |
| HR & Administration (`hr-admin`) | D | routine | workload, task, procedure, decision (declined) | staff.directory, staff.create_task, knowledge.search |
| Business Development (`business-development`) | D | reasoning | expansion, dealer acquisition, partnership | city.statistics, locations.coverage, marketing.funnel, content.save_draft, knowledge.search |

Each agent's instructions are in the registry and editable in the Command Center. `AgentCatalog::BASE_RULES` always applies. **Instructions are only sent to the model**, so in rules mode the behaviour comes from the worker code, not from editing the text. That matters when deciding what to change.

## 5. Tools and permissions

50 tools: **35 read, 4 low-risk, 6 approval, 5 prohibited.** The Command Center's Tools page is the live list.

`ToolRunner` checks in this order: known → not prohibited → assigned to this agent → within tool-call and time limits → valid input → run or request approval → audit.

| Class | Tools | Behaviour |
|---|---|---|
| Read | Reports and searches | Runs within permissions |
| Low-risk | support.create_ticket, staff.create_task, listings.flag, content.save_draft | Runs within permissions |
| Approval | content.publish, communications.send_bulk, leads.reassign, dealers.verify, subscriptions.change, investment.methodology_change | Only creates a request, and alerts admins |
| Prohibited | payments.refund, payments.transfer, records.delete, system.shell, system.deploy | Never runs; cannot be assigned |

**What an approval does afterwards.** Deliberately narrow:

- Verification, reassignment and package changes become a staff task. Staff make the change in the existing admin screens.
- A methodology change creates a **draft** version, never an active one.
- Publishing and bulk sending are recorded as **blocked**. Even with a verified connector nothing is posted automatically in this version; the draft is marked approved for staff to post by hand.

## 6. Memory and knowledge

| Scope | Written by | Read by |
|---|---|---|
| Task | The agent during the task (the CEO's subtask log uses this); 30-day expiry | That task only |
| Conversation | Existing conversation tables | Sent as context with model wording, where the agent's policy allows |
| Agent | Admin note, or agent proposal approved by an admin | That agent, where policy allows |
| Shared | Admin note or approved proposal | Agents whose policy allows |

Approved agent and shared notes appear in the agent's report under "Approved notes on file", with their source. Saving the same note name makes a new version and archives the old one. No workflow currently calls `propose()`, so in practice notes come from the admin form.

**Knowledge:** 6 approved documents, seeded and written from the application itself. Keyword scoring, no vector service. Customer Support is limited to the public categories in `AiKnowledgeDocument::PUBLIC_CATEGORIES`; marketing, brand, technical, procedures and competitor notes stay internal.

## 7. Language model

OpenAI (primary) and Anthropic Claude (secondary) sit behind one manager. Section 14 has the full design.

- **Where it is used, when configured:**
  1. routing a request to an agent;
  2. choosing a workflow as structured JSON, only when keyword rules find nothing, validated against that worker's list;
  3. wording the final report's summary.
- **Where it is not used:** deciding which tools to call. Workflows do that.
- **Grounding:** a reply is discarded if it contains a figure not in the data, compared whole (`Support/Grounding`).
- **Cost control:** subtasks are not worded, so a CEO report pays for one summary. Task, per-agent and overall daily limits. Reaching one falls back to data-only and alerts admins once a day.
- **Cost figures:** from provider-reported tokens and admin-entered prices. Null without prices. Never estimated.

## 8. Website and WhatsApp

`ConversationAssistant::respond()` tries `AgentChatRouter` for non-control messages. There is one conversation engine, shared with the website and WhatsApp.

| Message | Goes to |
|---|---|
| Registration, password, packages, how listings are reviewed (English, Roman Urdu, some Urdu script) | Customer Support |
| "Where should I invest", rankings, rental yield | Investment Analyst |
| Signed-in dealer: my leads | Sales Manager (own data only) |
| Signed-in dealer: my package | Subscription Manager (own data only) |
| Property searches, requirements, consent, handoff, STOP | The existing assistant, unchanged |

If an agent fails, the assistant answers as before. Complex business questions are **not** routed to the CEO from public channels, because its reports contain internal figures.

## 9. Events, schedule, queue

| Trigger | Agent | Type |
|---|---|---|
| Listing submitted for review | Property Management | Queued event |
| Dealer registered | Dealer Management | Queued event |
| Lead created | Sales Manager | Queued event |
| Rankings published | Investment Analyst | Queued event |
| Failed job or failed task in the last hour | Technical Operations | Hourly check, queued |
| Daily 06:00 / 06:15 | Technical Operations / Subscription Manager | Scheduled |
| Monday 06:30 | AI CEO | Scheduled |
| Hourly | Approval and memory expiry, stuck tasks | Scheduled |

"Subscription nearing expiry" is covered by the daily review rather than a per-record event.

**Local commands:**

```bash
php artisan queue:work --queue=whatsapp,default,agents
```

```bash
php artisan schedule:work
```

Shared hosting: `MEEZAN_QUEUE_VIA_SCHEDULER=true` and the every-minute cron. Set `MEEZAN_AGENT_EVENTS=false` to switch events off.

**Retries:** only `event` and `schedule` tasks retry by themselves, up to `MEEZAN_AGENT_MAX_ATTEMPTS`. Admins press Retry. Chat tasks don't retry, because the assistant has already replied.

## 10. Command Center (`/admin/ai`, admin only)

| Page | Contents |
|---|---|
| Overview | Central status, model and queue state, agent states, tasks, approvals, staff work, usage, connectors, run a task |
| Agents | Operational status, what stays off until configured, done/failed, last run, version |
| Agent detail | Status, usage and cost, refusals, instructions, tools, model, memory, versions, run a task |
| Tasks | History, filters, subtasks, tool calls, retry, cancel, Markdown export |
| Approvals | Pending and decided, evidence, approve or reject |
| Staff Work | Support tickets, internal tasks, drafts |
| AI Providers | Two provider cards, secure key replacement, test connection, fallback switch, website and WhatsApp routing, the 16 agents' routes |
| Usage & Cost | Per provider (requests, succeeded, failed, tokens, cost, fallback cost; today and this month), per agent, per task, daily; budgets, limits, emergency stop, pricing entry |
| Memory & Knowledge | Proposed notes, documents, add document, add note |
| Tools | Registry by risk, input, assigned agents, calls |
| Connectors | Status and check button |
| Activity & Errors | Failed tasks, refused calls, delegations, provider errors, log errors |

**Operational status** (`AgentReadiness`) comes from evidence: *Active and verified* means a task finished with no failed tool, on local data, since the agent's configuration last changed. It does not mean a model was used.

31 routes under `admin/ai`, behind `auth` and `role:admin`.

## 11. Configuration

All optional unless stated. Values go in the server's `.env`; `.env.example` lists names only.

| Setting | Purpose |
|---|---|
| `OPENAI_API_KEY`, `OPENAI_BASE_URL`, `OPENAI_DEFAULT_MODEL` (or the older `OPENAI_MODEL`) | OpenAI access. There is no built-in model: choose one your account lists |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_BASE_URL`, `ANTHROPIC_DEFAULT_MODEL`, `ANTHROPIC_VERSION`, `ANTHROPIC_EFFORT` | Anthropic access |
| `OPENAI_MODEL_ROUTINE`, `_EXTRACTION`, `_SUPPORT`, `_RESEARCH`, `_REASONING`, and the same five as `ANTHROPIC_MODEL_*` | Model per workload tier |
| `OPENAI_TIMEOUT`, `OPENAI_MAX_RETRIES`, `OPENAI_MIN_OUTPUT_TOKENS`, and the same three as `ANTHROPIC_*`; `OPENAI_REASONING_EFFORT` (low) | Client behaviour |
| `MEEZAN_AGENTS_USE_LLM`, `MEEZAN_AI_PROVIDER`, `MEEZAN_AI_FALLBACK` (off) | Use of a model at all, default provider, fallback at start |
| `MEEZAN_AGENT_MAX_TOOL_CALLS` (25), `_MAX_DEPTH` (2), `_MAX_SUBTASKS` (8), `_MAX_ATTEMPTS` (2), `_MAX_SECONDS` (60), `_MAX_TOKENS_PER_TASK` (8000), `_DAILY_TOKENS` (200000) | Limits |
| `MEEZAN_AGENTS_DAILY_TOKENS` (1000000), `MEEZAN_AGENTS_DAILY_COST_USD` | Overall budgets |
| `MEEZAN_AGENT_EVENTS`, `MEEZAN_AGENT_QUEUE` | Events and queue name |
| `GOOGLE_SEARCH_CONSOLE_*`, `GOOGLE_ANALYTICS_*`, `META_*`, `SMS_*`, `WEB_SEARCH_*` | Connector status only; **no code reads data from these services yet** |
| `WHATSAPP_*` | See the Phase 10 report |

Model prices, budgets and the emergency stop are entered in Usage & Cost. Keys, models and base URLs can also be set in AI Providers, and a value saved there takes priority over `.env`.

## 12. Testing

- `tests/Feature/AgentWorkforceTest.php`: 43 test definitions from the previous session, unchanged apart from automatic formatting.
- `tests/Feature/AgentContinuationTest.php`: 46 test definitions from this continuation.
- `tests/Feature/AiProviderTest.php`: 63 test definitions (80 cases with data sets). All provider traffic is faked with `Http::preventStrayRequests`.
- `phpunit.xml` pins both API keys empty and the base URLs, so a test run can never bill a real key.
- **MariaDB matters:** `crm.outcomes` passed on SQLite and crashed on MariaDB (`only_full_group_by` with an alias matching a real column). Phase 11 hit a similar gap. New queries need a run on MariaDB as well as the tests.

To repeat the scenarios, ask them in Overview → Ask the AI Brain. Each run adds task records, and marketing or calendar workflows add a draft.

## 13. Files

**Added before this continuation:** `app/Models/Ai*`, `StaffTask`, `SupportTicket`; `app/Services/Agents/*` and `Workers/*`; `app/Jobs/RunAiTask`; `app/Console/Commands/AgentsScheduled`; `Admin/AiCommandCenterController`; `resources/views/admin/ai/*`; migrations 000043 and 000044; `tests/Feature/AgentWorkforceTest.php`.

**Added in this continuation:** `Services/Agents/AgentReadiness.php`, `AgentAlerts.php`; `Notifications/AgentNeedsAttention.php`; `Support/Grounding.php`; `views/admin/ai/tools`, `logs`, `work`; migration 000045; `tests/Feature/AgentContinuationTest.php`; the three Phase 15 documents.

**Changed in this continuation:** orchestrator, router, tool registry and runner, budget guard, memory store, knowledge base, provider, chat router, events, catalog; 11 workers (CEO, Property, Dealer, Support, Sales, Investment, Finance, Global Data, Social, SEO, Technical Operations, plus Marketing keywords); `RunAiTask`; `AgentsScheduled`; `AiKnowledgeDocument`; Command Center controller and views; `routes/web.php`; `.env.example`; `public/build`.

**Outside Phase 15, changed deliberately:** `app/Services/Investment/InvestmentInsights.php` (Phase 14) now uses `Grounding`, because the same flaw let invented figures through there and made a Phase 14 test fail intermittently.

**Added for the two providers:** `Services/Agents/Providers/` (`ProviderAdapter`, `ProviderConfig`, `HttpAdapter`, `OpenAiAdapter`, `AnthropicAdapter`); `Models/AiProviderSetting.php`; `views/admin/ai/providers`; migration 000046; `tests/Feature/AiProviderTest.php`.

**Changed for the two providers:** `Services/Agents/AiProvider` (now the manager; same class, no second manager), `BudgetGuard`, `CentralAiOrchestrator`, `IntentRouter`, `OutputGuard`, `ConnectorStatus`, `AgentReadiness`, `AgentAlerts`, `ToolRegistry`, `Workers/FinanceManagerAgent`; `Models/AiAgent`; `Services/Ai/LlmRequirementParser`; `Services/Assistant/ConversationAssistant`; `Services/Investment/InvestmentInsights`; `AppServiceProvider`; Command Center controller and views; `admin/conversations/index`; `routes/web.php`; `config/services.php`, `config/meezan.php`; `.env.example`; `phpunit.xml`.

Phases 13, 14 and 15 were committed together (`3b7a9a0`) and pushed at the owner's request on 3 November 2026. The commit is on both `main` and `phases-13-15-ai-providers`.

## 14. AI providers

### Architecture

```
Agents, IntentRouter, website and WhatsApp parser, investment wording
        |
   AiProvider (the manager): route -> budget check -> adapter -> usage record -> fallback decision
        |                                   |
   BudgetGuard                  OpenAiAdapter    AnthropicAdapter    (ProviderAdapter interface)
```

Only the two adapters contain provider URLs, and a test fails if any other file in `app/` does. An agent can't call a provider itself: it holds tool permissions and goes through the manager.

| Piece | Role |
|---|---|
| `AiProvider::completeFor()` | A model call for an agent |
| `AiProvider::completeGeneral()` | A call with no agent: website parsing, investment wording |
| `AiProvider::route()` | Decides provider and model for an agent, tier and channel |
| `AiProvider::check()` | Lists models. Free, and proves the key |
| `AiProvider::testConnection()` | `check()`, then one very small paid request to the default model |
| `AiProvider::controls()` | Budgets, emergency stop, fallback, channels (stored as the `ai.controls` setting) |
| `ProviderConfig` | One provider's settings. Hides the key from debug output |
| `BudgetGuard::beforeRequest()` | The spending checks made before every request, including fallback requests |

**Unified response:** `ok, text, model, provider, tokens_in, tokens_out, error, failure`, and `fallback_from` when a fallback answered.

### Why Anthropic uses plain HTTP

The brief asked for the API key and required headers, and for no unnecessary packages. Both adapters use Laravel's `Http` client, so no SDK was added.

### Request details

| | OpenAI | Anthropic |
|---|---|---|
| Endpoint | `POST {base}/chat/completions` | `POST {base}/v1/messages` |
| Authentication | Bearer token | `x-api-key`, `anthropic-version: 2026-10-21` |
| Output limit | `max_completion_tokens` on api.openai.com, `max_tokens` on compatible servers | `max_tokens` |
| Temperature | Sent. If a model refuses it, one retry without it | **Never sent:** that API version rejects sampling settings, so an agent's temperature has no effect on Claude |
| Thinking depth | `reasoning_effort` (low) on api.openai.com. If a model refuses it, one retry without it | `output_config.effort` (low). If a model refuses it, one retry without it |
| JSON | `response_format` | Instruction in `system`; code fences removed from the reply |
| Model list | `GET {base}/models` | `GET {base}/v1/models` |
| Lowest output limit | 256 tokens | 1,024 tokens, because thinking is spent from the same limit |

Administrator limits on output tokens override those lowest values.

### Failure classes

| Class | Examples | Retried | Falls back |
|---|---|---|---|
| `temporary` | 429 rate limit, 5xx, 529, timeout | Yes | If allowed |
| `disabled`, `not_configured`, `no_model` | Primary can't be used yet | No | If allowed |
| `auth` | Key rejected | No | **Never** |
| `quota` | No API credit | No | **Never** |
| `model` | Model unavailable to this account | No | **Never** |
| `invalid` | Invalid request | No | **Never** |
| `safety` | Provider declined | No | **Never** |
| `truncated`, `malformed` | No usable answer | No | **Never** |
| `budget` | A limit would be exceeded, or the emergency stop is on | No | **Never** |

Every failure ends in a data-only report and the portal keeps working. A rejected key or missing credit alerts administrators once a day. Provider error text is never repeated on a rejected key, because it can echo part of the key.

### Model choice

For an agent: the channel's model if the task came from that channel, then the agent's own model, then the tier's model for that provider, then the provider default. No model IDs are built in. If none is set, the call stops with "No model is selected" and no request is sent.

**Server-side fallback is not used.** Anthropic offers a `fallbacks` request setting, which was left out because it would switch models silently.

### Fallback

Three switches must all allow it: global (`AI Providers`, off by default), the agent (`fallback_enabled`, on by default), and the channel, when the request came from one. **The global switch is off by default because fallback sends the same data to a second company at its prices.**

A fallback request passes the same budget checks. Both calls are recorded; the second has `fallback_from`. The task's limitations say which provider answered and why, and Activity & Errors lists fallbacks.

### Budgets

| Limit | Where | Applies to |
|---|---|---|
| Emergency stop | Usage & Cost | Every paid request |
| Monthly total, requests per day, max tokens per request | Usage & Cost | All AI |
| Monthly budget | AI Providers | One provider |
| Monthly budget, requests per day, max tokens | Agent page | One agent |
| Per-task and per-day token limits, daily cost | `.env` | As before |

Monthly budgets count the most the next request could cost, so a budget can't be overrun by that request. While a monthly budget is set, a model with no entered price is refused, because its spending can't be controlled. Prices come only from the admin's own entries; none are built in.

### Key handling

| Rule | How |
|---|---|
| Server side only | Read by adapters on the server; never in a view, script or API response |
| Saved encrypted | `api_key` uses Laravel's `encrypted` cast, with `APP_KEY`, and is `$hidden` |
| Never shown again | Password field, always empty; the page says only Yes or No and where the key came from |
| Empty field keeps the key | Only a typed key or the Remove checkbox changes it |
| Not flashed to the session | Form errors use `withInput($request->except('api_key'))` |
| Not logged | Audit entries record which fields changed and `api_key_changed`, never the value |
| Removed from output | `OutputGuard` knows the saved and `.env` keys and common key shapes |
| Key sent only over HTTPS | A plain HTTP base URL is refused, except localhost |
| Admin only | `role:admin`; dealer, buyer and guest tests |

If `APP_KEY` changes, saved keys can't be read. The page reports this and asks for the key again. Keep a copy of the key in your provider account, not in this project.

### Database (migration 000046, additive)

- New table `ai_provider_settings`: provider, enabled, api_key, base_url, default_model, monthly_budget_usd, connection_status, last_checked_at, last_success_at, last_error, last_error_at, available_models.
- `ai_agents`: primary_provider (openai), secondary_provider (anthropic), secondary_model, fallback_enabled (on), max_tokens, daily_request_limit, monthly_budget_usd.
- `ai_usage_records`: fallback_from, with an index.

The 16 agents start as OpenAI primary and Anthropic secondary, and each can be changed on its own page. Changing them creates a new agent version.
