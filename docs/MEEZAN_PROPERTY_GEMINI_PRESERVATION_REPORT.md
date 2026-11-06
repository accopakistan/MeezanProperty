# Meezan Property: Gemini Preservation Report

Date: 6 November 2026.

## Status

**Google Gemini is the active AI provider, and it answered a test request today.**

| Check | Result |
|---|---|
| Default provider (`config('meezan.ai.provider')`) | `gemini` |
| Gemini provider setting | Enabled; model `gemini-3.6-flash`; status verified |
| Connection test (the admin button's method, run once on 6 Nov 2026) | "Connected: gemini-3.6-flash answered a test request (8 tokens in, 73 out)" |
| OpenAI and Anthropic settings | Disabled, unchanged, and not used |
| AI agents | 16 active |
| Central AI Brain, agent workflows, tools | Not modified |
| AI Property Finder | Unchanged. It renders and parses requirements, and the test suite passes |

The test request used one call from the free-tier daily allowance. No other AI requests were sent during this work.

## What changed around the AI

Nothing in the provider layer, agents or orchestration changed. Only the knowledge the assistant may quote was updated, by migration `2026_11_06_000061_update_ai_knowledge_for_public_launch`:

| Document | Change |
|---|---|
| About Meezan Property and how to contact us | **New.** Services, the seven cities and the confirmed contact details. WhatsApp is described as the team, not a bot |
| How the Meezan Investment Index works | **New.** The five categories, weights, the 60% rule, research states, what "awaiting our check" and "Unknown" mean, and that asking prices aren't sales. It tells the assistant never to state a rank, score, approval or yield the Index doesn't show |
| Finding property on Meezan Property | **New.** Search, Buy, Plots, Commercial, map, compare, the AI finder and saved requirements. It tells the assistant never to describe an unpublished property |
| How investment rankings work | **Updated** to separate the Index from the MPIS market insight score |
| How Meezan Property reviews listings and dealers | **Updated.** Sample listings aren't shown, and sample accounts never show the badge |
| Dealer subscriptions and packages | **Updated.** Packages are being finalised and none can be bought. It tells the assistant never to quote a price that isn't published |

**The QA report's failing question now finds the right documents.** "How does the Meezan Investment Index work?" returns "How investment rankings work" and "How the Meezan Investment Index works" first. "What is your phone number and office address" returns the contact document.

## Safeguards already in place, kept

- **Personal data** is masked before text is sent to the provider.
- **The customer-facing agent** can read only the public knowledge categories.
- **Scores, ranks and legal statuses** come from the deterministic engines, never from the model.

## Future provider changes

The provider layer still supports several providers through one interface. Switching would be a settings change by the owner. Nothing here switches or prepares a switch.
