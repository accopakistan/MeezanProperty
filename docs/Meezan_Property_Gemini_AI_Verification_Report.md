# Meezan Property: Gemini AI Verification Report

Date: 4 November 2026. This was a read-only audit: no Gemini setting, key, model or agent was changed. The agent and website runs below were rolled back.

## 1. Test labels

| Label | Meaning | Status in this audit |
|---|---|---|
| **Live Gemini answer** | Gemini wrote a reply in this audit | **Not run.** The free daily quota is paused until 5 Nov 2026, 13:00 Pakistan time |
| **Earlier live test** | Previous session, 4 Nov 2026, recorded in Phase 15 Completion Report §22 | Passed, before the later changes listed in §4 |
| **Live model list** | Real free request in this audit | **Passed** |
| **Rules mode on MariaDB** | Real workflows and data, no model, rolled back | **Passed** |
| **Mock tests** | Automated with faked Gemini responses | **Passed** |

## 2. Gemini integration

| Item | Finding |
|---|---|
| Active provider | Gemini is the only adapter. All 16 agents route to it, with no secondary provider |
| API key | Configured, saved encrypted, and never printed in this audit. It is the key that was pasted in chat earlier, so **replacing it in Google AI Studio is still advised** |
| Authentication | `x-goog-api-key` header only, never in a URL |
| Model | `gemini-3.6-flash` is active, and the live model list includes it (46 models) |
| Request and response handling | `generateContent` through `GeminiAdapter`; token use recorded, with thinking counted as output |
| Errors and retries | Failures are classified. Backoff uses Google's retry delay. A daily quota, billing or key error is not retried |
| Quota | **Paused now**, as designed: `quotaBlock` says the free-tier daily quota is used up until midnight Pacific |
| Budgets | Emergency stop and budget limits apply before every request |
| Admin | Gemini Settings and Usage & Cost open without errors |
| History | OpenAI and Anthropic usage rows are kept and their adapters removed. Gemini has 23 recorded requests, 19 successful |

## 3. Central AI Brain and the 16 agents

Orchestrator, delegation, permissions, memory, knowledge base, logs, approvals and the AI Command Center were inspected and are covered by `AgentWorkforceTest`, `AgentContinuationTest` and `GeminiProviderTest`, all passing.

Each agent ran one real task in rules mode on MariaDB, rolled back. Every tool it has is registered.

| Agent | Version | Tools | Tool calls | Refused or failed | Status shown |
|---|---|---:|---:|---:|---|
| AI CEO | 2 | 10 | 1 | 0 | Active and verified |
| Property Management | 2 | 8 | 1 | 0 | Active and verified |
| Dealer Management | 3 | 8 | 1 | 0 | Active and verified |
| Customer Support | 2 | 3 | 1 | 0 | Active and verified |
| Sales Manager | 3 | 10 | 1 | 0 | Active and verified |
| Investment Analyst | 3 | 9 | 2 | 0 | Active and verified |
| Finance Manager | 3 | 5 | 2 | 0 | Active and verified |
| Subscription Manager | 2 | 7 | 1 | 0 | Active and verified |
| Competitor Research | 2 | 3 | 2 | 0 | **Waiting for credentials** |
| Global Property Data | 3 | 6 | 1 | 0 | Active and verified |
| Marketing Director | 2 | 6 | 3 | 0 | Active and verified |
| SEO Manager | 2 | 5 | 1 | 0 | Active and verified |
| Social Media Manager | 2 | 5 | 2 | 0 | Active and verified |
| Technical Operations | 2 | 4 | 1 | 0 | Active and verified |
| HR & Administration | 2 | 3 | 1 | 0 | Active and verified |
| Business Development | 2 | 5 | 2 | 0 | Active and verified |

**What "Active and verified" means:** the workflow finished on local data. It does not mean a model was used. Every agent's worker class exists, and all 16 are in the database as active on Gemini.

**Permissions:** a tool outside an agent's list is refused by `ToolRunner`. Refusals are covered by automated tests. This audit's normal tasks caused none.

**Limits:**
- **Competitor Research** has no approved notes or search connector, so it can't produce real competitor findings.
- **Planning:** all 16 use fixed workflows. Gemini tool calling is an opt-in mode that is off for all of them and has not been run live.

## 4. Investment Analyst and the restored rankings

| Check | Rules mode on MariaDB | Live Gemini |
|---|---|---|
| Index leaderboard question | Called `investment_index.leaderboard` and `investment.rankings`; reported 0 ranked | **Not tested.** This workflow was changed after the earlier live test |
| Best-mall question | Called `investment.advisor`; answered that no rankings are published | **Not tested** |
| Numbers | Scores come only from the deterministic engines. Gemini can only word a summary, and any figure not in the data is discarded | |

Fixtures in automated tests confirm that the analyst and advisor name real ranked projects once they exist.

## 5. Website and WhatsApp AI

| Check | Result |
|---|---|
| Website, dealer registration question | Rules mode: routed to Customer Support, correct answer |
| Website, "Where should I invest in Lahore?" | Rules mode: routed to Investment Analyst; says nothing is published |
| Website, property search | Rules mode: the existing assistant answered. It reported no exact match and suggested a higher budget |
| Website, "Which shopping malls rank best in the investment index?" | **Weak:** the assistant asked for a city instead of routing to the analyst. Chat routing patterns are narrow by design |
| Website requirement parsing | Stays on rules (`MEEZAN_AI_DRIVER=rules`). English parsing worked live earlier. Roman Urdu and Urdu have not been proven live |
| WhatsApp | **Not live.** The driver is `mock` and no Meta credentials are configured. The pipeline is covered by mock tests only |
| Human handoff, logging, errors | Covered by automated tests |

## 6. Still to do

1. **Live Gemini test after 5 Nov, 13:00 Pakistan time:** one Investment Analyst task and one general task. This uses about 2 to 4 of the roughly 20 free daily requests.
2. Replace the API key that was pasted in chat.
3. Add Meta credentials before WhatsApp can be tested for real.
