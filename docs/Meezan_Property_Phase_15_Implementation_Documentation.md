# Meezan Property — Phase 15 Implementation Documentation

Central AI Brain and 16 agents, as actually built. Written 3 November 2026, after the continuation work. Results and status are in `Meezan_Property_Phase_15_Completion_Report.md`; how the work was recovered is in `Meezan_Property_Phase_15_Continuation_Audit.md`.

**Read this first:** agents are deterministic workflows that call tools against the real database. The language model is **Google Gemini** (since 4 Nov 2026; section 14). When a key is configured, it chooses a route or workflow and words the final summary; agents switched to "Model tool calls" may also call their own permitted tools through Gemini function calling. No Gemini key is configured yet, so everything has run in **rules mode** locally, and Gemini itself only against faked responses.

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

Google Gemini, behind one manager (`AiProvider`). Section 14 has the full design.

- **Where it is used, when configured:**
  1. routing a request to an agent;
  2. choosing a workflow as structured JSON, only when keyword rules find nothing, validated against that worker's list;
  3. wording the final report's summary.
- **Where it is not used by default:** deciding which tools to call. Workflows do that; an agent switched to "Model tool calls" may also call its own tools through Gemini function calling (section 14).
- **Grounding:** a reply is discarded if it contains a figure not in the data, compared whole (`Support/Grounding`).
- **Cost control:** subtasks are not worded, so a CEO report pays for one summary. Task, per-agent and overall daily limits. Reaching one falls back to data-only and alerts admins once a day.
- **Cost figures:** from Google-reported tokens and prices entered in Usage & Cost, else Google's published prices (dated, in config). Null for a model with no known price. On the free tier the figure is the paid-tier equivalent.

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
| `GEMINI_API_KEY`, `GEMINI_MODEL` (empty = `gemini-3.8-flash`), `GEMINI_BASE_URL`, `GEMINI_TIMEOUT` (60) | Gemini access. A key saved in Gemini Settings wins |
| `GEMINI_MAX_RETRIES` (2), `GEMINI_THINKING_LEVEL` (low), `GEMINI_MIN_OUTPUT_TOKENS` (512), `GEMINI_BILLING_TIER` (free) | Client behaviour and cost labelling |
| `GEMINI_MODEL_ROUTINE`, `_EXTRACTION`, `_SUPPORT`, `_RESEARCH`, `_REASONING` | Model per workload tier |
| `MEEZAN_AI_PROVIDER` (gemini), `MEEZAN_AI_REDACT_PERSONAL_DATA` (true), `MEEZAN_AGENT_MAX_MODEL_TOOL_ROUNDS` (4) | Provider, personal-data masking, function-calling rounds |
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

**Provider files (since the Gemini migration):** `Services/Agents/Providers/` (`ProviderAdapter`, `ProviderConfig`, `HttpAdapter`, `GeminiAdapter`), `Services/Agents/ToolDeclarations.php`, `Services/Agents/PersonalDataMask.php`, migration `2026_11_04_000053_move_ai_agents_to_gemini.php`, tests `tests/Feature/GeminiProviderTest.php`. The OpenAI and Anthropic adapters were removed (section 14, History).

**Changed for the two providers:** `Services/Agents/AiProvider` (now the manager; same class, no second manager), `BudgetGuard`, `CentralAiOrchestrator`, `IntentRouter`, `OutputGuard`, `ConnectorStatus`, `AgentReadiness`, `AgentAlerts`, `ToolRegistry`, `Workers/FinanceManagerAgent`; `Models/AiAgent`; `Services/Ai/LlmRequirementParser`; `Services/Assistant/ConversationAssistant`; `Services/Investment/InvestmentInsights`; `AppServiceProvider`; Command Center controller and views; `admin/conversations/index`; `routes/web.php`; `config/services.php`, `config/meezan.php`; `.env.example`; `phpunit.xml`.

Phases 13, 14 and 15 were committed together (`3b7a9a0`) and pushed at the owner's request on 3 November 2026. The commit is on both `main` and `phases-13-15-ai-providers`.

## 14. Google Gemini (the AI provider since 4 November 2026)

Google Gemini replaced OpenAI and Anthropic on 4 November 2026. Every language-model call in the application now goes to Gemini over HTTPS from the server. The application needs nothing installed locally (no Ollama, no local model), so it runs the same on Hostinger. The setup steps are in `Meezan_Property_Gemini_API_Setup_Guide.md`.

### Architecture

```
Agents (16), IntentRouter, website requirement parser, WhatsApp chat, investment wording
        |
   AiProvider (the one manager): route -> personal-data mask -> quota pause -> budgets
        |                        -> GeminiAdapter -> usage record -> quota/auth stop
   BudgetGuard        ToolDeclarations (agent tools -> Gemini functions)        PersonalDataMask
```

**One adapter.** `meezan.agents.adapters` registers `gemini => GeminiAdapter`. Another provider can be added there without changing any agent. A test proves that fallback to a second registered adapter still works. No second provider is registered, so fallback is never used.

**One file talks to Google.** A test fails if any file in `app/` other than `GeminiAdapter.php` contains a model-API address.

**Every feature uses Gemini.** The 16 agents, the routing, the website requirement parser, WhatsApp chat (through the same Brain) and investment wording all call `AiProvider`. None has its own integration.

### API format (Google's documentation, checked 4 Nov 2026)

Google's documentation moved from ai.google.dev to aistudio.google.com/docs. `generateContent` is still a current method. The newer Interactions API was not needed: `generateContent` covers system instructions, function calling, JSON output and streaming.

| Item | Implementation |
|---|---|
| Endpoint | `POST {GEMINI_BASE_URL}/models/{model}:generateContent`; streaming `:streamGenerateContent?alt=sse`; model list `GET /models` (paged) |
| Authentication | `x-goog-api-key` header only. Never in a URL, log, view or browser response |
| Messages | `system` messages become `systemInstruction`. `assistant` becomes role `model`. Consecutive turns of the same role are merged |
| Output limit | `generationConfig.maxOutputTokens`, at least `GEMINI_MIN_OUTPUT_TOKENS` (512); the agent and admin limits always win |
| Temperature | Sent only to models before 3.6. Google's docs say 3.6 and later ignore it |
| Thinking | `thinkingConfig.thinkingLevel` (`GEMINI_THINKING_LEVEL`, default LOW), only for Gemini 3 and later, which accept it. Thinking tokens are counted as output (billed that way) |
| JSON | `responseMimeType: application/json`, plus `responseJsonSchema` when a schema is given |
| Usage | `promptTokenCount` as input; `candidatesTokenCount + thoughtsTokenCount` as output |
| Refusals | `promptFeedback.blockReason` and finish reasons SAFETY, PROHIBITED_CONTENT, BLOCKLIST, SPII, RECITATION are classed "safety". MAX_TOKENS with no text is "truncated". Malformed tool calls are "malformed" |

### Model selection

| Setting | Source |
|---|---|
| Default | `gemini-3.8-flash`. Google's models page recommends it for new projects, and it has a free tier |
| Override | `GEMINI_MODEL`, or "Active model" in Gemini Settings, which wins |
| Per workload tier | `GEMINI_MODEL_ROUTINE`, `_EXTRACTION`, `_SUPPORT`, `_RESEARCH`, `_REASONING` |
| Per agent | Model override on the agent's page |
| Per channel | Gemini model for website AI or WhatsApp AI, in Gemini Settings |

**Checking the model.** "Test Gemini connection" reads the key's own model list. Only models that support `generateContent` are kept. The test refuses a model the list doesn't contain, then sends one tiny real request. Other current options on 4 Nov 2026: `gemini-3.6-flash` (stable Flash), `gemini-3.5-flash-lite` and `gemini-3.1-flash-lite` (cheaper). Google routes `gemini-2.5-flash` to `gemini-3.5-flash-lite` from 1 December 2026.

### Errors, retries and quota

| Gemini response | Class | What happens |
|---|---|---|
| 400 `API_KEY_INVALID`, 401 | auth | No retry. Requests pause until an admin tests again; admins are alerted once |
| 403 `PERMISSION_DENIED` | auth | As above. The key is restricted, blocked or reported as leaked |
| 400 `FAILED_PRECONDITION`, 402 | quota | No retry. Billing needed (free tier not available to the project) or prepaid credit used up. Paused until a successful test |
| 429 with a per-day quota (`...PerDay...`) | quota | No retry. **Paused until midnight Pacific time**, when Google resets daily quotas. Admins are alerted, and Gemini Settings and Usage & Cost show the pause |
| 429 per-minute rate limit | temporary | Retried with Google's `RetryInfo.retryDelay`, else exponential backoff with jitter (0.5 s, 1 s, …, at most 20 s) |
| 500, 503, 504, 408, connection or timeout | temporary | Retried the same way (`GEMINI_MAX_RETRIES`, default 2) |
| 404 | model | No retry. Choose a listed model |
| Other 400s | invalid | No retry. Google's message is shown, cleaned of secrets; key errors never echo Google's text |

**During a pause, nothing reaches Google.** Agents keep producing reports from data, website answers keep working, and no answer is made up. Nothing switches to a paid provider. Changing the key or the model clears the pause.

### Function calling

**The default is unchanged.** Fixed workflows choose the tools, as decided on 3 Nov 2026, and the model only routes and words summaries.

**The new opt-in mode, "Model tool calls".** It's set per agent on the agent's page, is saved as a new agent version, and is off for all 16 agents.

1. The workflow runs first, so the data-only report always exists.
2. `ToolDeclarations` turns the agent's permitted tools into Gemini `functionDeclarations`, with JSON Schemas derived from each tool's Laravel validation rules. Prohibited tools are never declared. Approval tools say they only create an approval request. Agents allowed to delegate (the CEO) also get `agents.delegate`.
3. `AiProvider::completeWithTools()` runs the exchange. Each model turn is sent back exactly as received, thought signatures included. Each call is answered with a `functionResponse` (`output`, or `error`), matched by call `id`.
4. Every tool call goes through `ToolRunner`: registered, not prohibited, permitted for this agent, input validated server-side, tool-call and time limits, approval requests for approval tools, and an `ai_tool_calls` log row. Delegation goes through the Brain's `delegate()`, with its depth and subtask caps.
5. Limits: `MEEZAN_AGENT_MAX_MODEL_TOOL_ROUNDS` (4) model turns, `MEEZAN_AGENT_MAX_TOOL_CALLS` (25), `MEEZAN_AGENT_MAX_DEPTH` (2), `MEEZAN_AGENT_MAX_SUBTASKS` (8). Every turn is a budget-checked, recorded request.
6. **Grounding:** the final answer is discarded if it has a figure that isn't in the workflow data or the tool results. The ordinary data-only wording is used instead.

There is no SQL, shell or file access: tools are fixed Laravel services.

### Investment Analyst, MPIS and the Investment Index

- **Scores come from the engines only.** MPIS (the earlier segment rankings) is calculated only by `RankingCalculator`, and the Meezan Investment Index only by `IndexEngine`. Gemini never assigns or estimates a score: its instructions say so, and grounding rejects figures not returned by the tools.
- **Two new read-only tools for the Investment Analyst** (added as its version 3):
  - `investment_index.leaderboard`: one category's published ranked projects, their scores, confidence, risk, legal status and last verification, plus how many have Insufficient Verified Data or are excluded;
  - `investment_index.project`: one development's profile, with each factor's verified value or why it's missing, risks and reasons.
- **Demo data stays labelled.** It is flagged in tool results ("demo": true) and in the advisor's answers. Demo mode is off by default.
- **Scenarios** (conservative, moderate, optimistic) come only from `GrowthScenarios` when enough verified history exists, and they are always presented as hypothetical.

### Usage and cost

- **Recording:** every request writes an `ai_usage_records` row: provider, model, purpose, agent, task, input and output tokens, estimated cost, success, and since this migration, the failure class.
- **Usage & Cost page:** requests, successes and failures; input, output and total tokens; per agent, per model, per task, daily (14 days) and monthly totals; failures by reason this month; and the quota pause. Earlier providers' history is shown as "OpenAI (no longer used)" and "Anthropic (no longer used)".
- **Estimated cost:**
  - **Sources:** prices entered in Usage & Cost, else Google's published paid-tier prices in `config/services.php`, which are dated: gemini-3.8-flash and 3.6-flash USD 0.75 / 3.75 per million until 31 Dec 2026, then 1.50 / 7.50; 3.5-flash-lite 0.30 / 2.50; 3.1-flash-lite 0.25 / 1.50. Checked 4 Nov 2026.
  - **Free tier:** Google doesn't charge on the free tier (`GEMINI_BILLING_TIER=free`), so the figure is the paid-tier equivalent.
- **Limits:**
  - daily requests in total and per agent;
  - a monthly spending limit in total, for Gemini, and per agent (the next request's maximum cost is counted, so a limit is never overrun);
  - tokens per task, per agent per day, and in total per day;
  - maximum output tokens per request and per agent;
  - the emergency AI stop.

### Security and data handling

- **Key handling:**
  - The key is read from `GEMINI_API_KEY`, or saved encrypted with `APP_KEY` in Gemini Settings.
  - It's never shown again, never in the session or a failed form, never in the audit log or application log, and never in browser responses. `OutputGuard` also removes `AIza…` and `AQ.…` key formats from agent output.
- **Personal data:** `PersonalDataMask` masks phone numbers, e-mail addresses, CNIC and card numbers in everything sent to Gemini. This is on by default (`MEEZAN_AI_REDACT_PERSONAL_DATA`). Prices, sizes and dates stay.
- **Free tier:** Google may use free-tier content to improve its products. Gemini Settings says so. Use a paid tier for sensitive production workflows.
- **Admin only:** Gemini settings, controls and tests (dealers, buyers and guests get 403 or a redirect), with rate-limited test requests.

### History: OpenAI and Anthropic (retired 4 Nov 2026)

**Removed:** the adapters, their configuration and the `OPENAI_*` / `ANTHROPIC_*` variables in `.env.example`. They were never live: neither account had API credit.

**Kept:**
- The two settings rows remain (disabled), with any saved key.
- Usage history (6 OpenAI and 3 Anthropic rows) is unchanged.
- Each agent's earlier definition stays in its version history.
- The old code is in Git commit `0e69d7b` and in `C:\xampp\backups\MeezanProperty-before-gemini-migration-2026-11-04\`.

**Database (migration 000053, additive):**
- adds `ai_usage_records.failure` and `ai_agents.planning` (default `workflow`);
- column defaults are now `primary_provider = gemini` and `secondary_provider = null`;
- inserts the `gemini` settings row;
- disables the OpenAI and Anthropic rows;
- gives each agent a new version moved to Gemini;
- clears saved routing that named a removed provider.
