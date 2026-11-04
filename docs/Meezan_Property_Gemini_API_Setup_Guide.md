# Meezan Property: Gemini API Setup Guide

How to connect the Central AI Brain and all 16 agents to Google Gemini, test it, and keep it within your quota. Written 4 November 2026 against Google's documentation at aistudio.google.com/docs. Google moves pages often, so check there if anything below has changed.

> **Status (4 Nov 2026):** live. Your key is accepted, the connection is verified on `gemini-3.6-flash`, and all 16 agents completed real tasks through Gemini. `gemini-3.8-flash` was returning "high demand" (503) at the time, and the free-tier daily quota (about 20 requests per model) was reached during testing. See the Completion Report §22.

---

## 1. Get a Gemini API key

1. Open **Google AI Studio → API Keys**: https://aistudio.google.com/apikey. Sign in with the Google account that should own the AI usage.
2. Click **Create API key**. New users get a default project; existing Google Cloud users can import a project first.
3. Choose **Restrict to Gemini API only** if offered, and copy the key.

**Key safety:**
- Treat the key like a password. Never put it in source code, Git, chat messages, e-mails or screenshots.
- A key pasted into a chat or document should be considered exposed. Create a new one and delete the old one in AI Studio.
- A Gemini app subscription (Gemini Advanced and similar) does **not** give API access. Only an AI Studio API key works.

## 2. The environment variable

| Variable | Value |
|---|---|
| `GEMINI_API_KEY` | your key (required) |
| `GEMINI_MODEL` | leave empty to use `gemini-3.8-flash`, or set another model from your list |
| `GEMINI_BASE_URL` | `https://generativelanguage.googleapis.com/v1beta` (leave as is) |
| `GEMINI_TIMEOUT` | `60` (seconds) |

Optional settings are in `.env.example`, for example `GEMINI_THINKING_LEVEL`, `GEMINI_BILLING_TIER` and `GEMINI_MODEL_ROUTINE`.

## 3. Add the key locally (XAMPP)

**Option A: the environment file.**
1. Open `C:\xampp\htdocs\MeezanProperty\.env` in a text editor. The migration already added an empty `GEMINI_API_KEY=` line at the end.
2. Paste the key after the `=`, with no spaces or quotes, for example `GEMINI_API_KEY=AIza...`. Save the file.
3. Clear the cached configuration:
   ```bash
   php artisan config:clear
   ```

**Option B: the admin panel.** Open **Admin → AI Command Center → Gemini Settings**, paste the key into **API key**, and click **Save Gemini settings**.
- The key is saved encrypted with your `APP_KEY` and never shown again.
- A key saved here takes priority over `GEMINI_API_KEY`.
- If `APP_KEY` ever changes, enter the key again.

## 4. Configure it on Hostinger (later, when you deploy)

**Ways to set the key:**
- **hPanel environment variables** (if your plan offers them): add `GEMINI_API_KEY`, and optionally `GEMINI_MODEL`, under the website's environment settings.
- **The server's `.env`:** open it in hPanel's File Manager (it's outside `public_html` if you deploy the Laravel root there) and add the same lines as in step 2. Keep the file's permissions private, at 600 or 640.
- **The admin panel:** Gemini Settings, as in option B above.

**After setting the key on the server:**
1. Run the migrations, which include the Gemini migration:
   ```bash
   php artisan migrate --force
   ```
2. Rebuild the cached configuration:
   ```bash
   php artisan config:cache
   ```
3. Run the connection test in step 5 on the live site.

All requests go from the Hostinger server to Google over HTTPS. Nothing needs installing on your computer.

**Hostinger notes:**
- If the key is restricted by IP address in Google Cloud, add the Hostinger server's outgoing IP.
- Agent tasks and WhatsApp replies run on the queue. On shared hosting, set `MEEZAN_QUEUE_VIA_SCHEDULER=true` and keep the cron job running `php artisan schedule:run` every minute.

## 5. Test the connection, then the 16 agents

**1. Test the connection.** Open **Gemini Settings** and click **Test Gemini connection**. It does two things:
- reads your key's model list, which is free;
- sends one tiny real request ("Reply with the single word OK") to the active model.

It reports **Connection verified** only if Google actually answered. The answer, its tokens and its cost estimate appear in Usage & Cost under the purpose `connection_test`.

**2. Run the agents.** Open **AI Command Center → Agents**. For each of the 16 agents, open it, type a request in **Run a task** (or use **Tasks → New task**), and run it. Suggested requests:

| Agent | Request |
|---|---|
| AI CEO | Give me the executive overview |
| Property Management | Which listings need attention? |
| Dealer Management | Which dealers are waiting for verification? |
| Customer Support | How do I register as a dealer? |
| Investment Analyst | Which shopping malls lead the Investment Index? |
| Sales Manager | Show the lead pipeline |
| Marketing Director | How is the funnel doing this month? |
| SEO Manager | Run an SEO audit |
| Social Media Manager | Draft a post about new listings in Lahore |
| Finance Manager | Revenue this month |
| Subscription Manager | Which subscriptions expire soon? |
| Competitor Research | Competitor overview |
| Technical Operations | System health check |
| HR & Administration | Open staff tasks |
| Business Development | Partnership opportunities |
| Global Property Data | Location data coverage per city |

**What a working task looks like:**
- the mode shows **data + model wording** (or **data + model tool calls** for agents switched to that mode);
- the summary is written in sentences;
- a Gemini usage row is recorded.

If a task says **Written from data only**, the reason follows: no key, a budget limit, a quota pause or a Google error.

**3. Test the website AI.** Open `/ai-property-finder` and ask, for example, "3 marla house in Johar Town under 1.5 crore". Ask the same in Urdu and Roman Urdu. Requirement parsing uses Gemini only when `MEEZAN_AI_DRIVER=llm`; the rules parser is the default.

**4. WhatsApp AI** uses the same Brain. It works only after WhatsApp itself is set up and tested in **Admin → WhatsApp Control**. It isn't live until then.

## 6. Choose the active model

- **Where:** in **Gemini Settings**, type or pick a model in **Active model**, then **Save** and **Test** again. The list shows only models your key can use to generate content.
- **Recommended:** `gemini-3.8-flash`. It's Google's suggested Flash model for new projects, with a free tier.
- **Cheaper:** `gemini-3.5-flash-lite` and `gemini-3.1-flash-lite`.
- **Avoid:**
  - `gemini-2.5-flash` and `gemini-2.5-pro` are deprecated, and Google reroutes them from 1 December 2026;
  - preview models (with `-preview` in the name) can change at two weeks' notice.
- **Per agent:** use the model override on that agent's page.
- **Per workload tier:** use `GEMINI_MODEL_ROUTINE`, `_EXTRACTION`, `_SUPPORT`, `_RESEARCH` and `_REASONING`.
- **Per channel:** the Website AI and WhatsApp AI fields in Gemini Settings.

## 7. Check usage and quotas

**In Meezan Property (Admin → AI Command Center → Usage & Cost):**
- **Totals:** requests, successes and failures, and input, output and total tokens, for today and this month.
- **Breakdowns:** per agent, per model, the most expensive tasks, and daily use.
- **Estimated cost:** from Google's published prices, unless you enter your own. On the free tier, Google doesn't charge; the estimate shows what it would cost on the paid tier.
- **Limits:** daily request limit, monthly spending limit, maximum output tokens per request, and the **emergency AI stop**. Gemini Settings has its own monthly limit, and each agent page has per-agent limits.

**In Google AI Studio:**
- Your real limits per model (requests per minute, tokens per minute, requests per day): https://aistudio.google.com/rate-limit
- Billing and tiers: link a billing account to move from the free tier to Tier 1.

**How far the free tier goes:** in testing on 4 Nov 2026, the free tier allowed about **20 requests per model per day**. An agent task uses 1–2 requests, so that's roughly 10–20 AI-worded tasks a day. For regular use, enable billing (Tier 1). At the measured volumes a task costs about USD 0.001–0.002. You can also give routine work to a second model, since each model has its own daily quota: set `GEMINI_MODEL_ROUTINE=gemini-3.5-flash-lite`.

**When the free quota runs out:**
- **What happens:** Google answers 429 with a per-day quota. Meezan Property then stops sending requests until **midnight Pacific time**, when Google resets daily quotas (1 pm Pakistan time, or 12 pm during US daylight saving).
- **What you see:** Gemini Settings and Usage & Cost show "AI requests paused", and administrators are notified.
- **While paused:** agents keep working from data only. No paid provider is used instead, and no answer is made up.
- **Resuming early:** after upgrading billing, click **Test Gemini connection**.

## 8. Troubleshooting

| Message | Meaning | What to do |
|---|---|---|
| Gemini API key not configured | No key in `GEMINI_API_KEY` or Gemini Settings | Step 3, then `php artisan config:clear` |
| Google rejected the API key | Key wrong, deleted or mistyped (HTTP 400 `API_KEY_INVALID` or 401) | Create a new key in AI Studio and enter it again |
| Google refused access for this key | Restricted to other APIs, blocked, or reported as leaked (403) | Create a new key restricted to the Gemini API |
| … unmet account requirement, usually billing | `FAILED_PRECONDITION`: the project isn't eligible for the free tier | Link a billing account in AI Studio; a new key in the same project won't help |
| Gemini daily quota exhausted | Free-tier requests per day used up | Wait for midnight Pacific, or upgrade the tier; requests resume by themselves |
| Gemini rate limit reached | Too many requests per minute | Retried automatically; lower the request volume or raise the tier |
| Model not found | The model isn't available to this key | Test the connection and choose a listed model |
| … is not in the model list this key returned | Active model not available to the key | Choose a listed model |
| Google Gemini is temporarily unavailable … high demand | Google overload on that model (503), retried | Wait, or choose another model (for example `gemini-3.6-flash`) in Gemini Settings and test again |
| Written from data only: … | The model wasn't used for this task (the reason follows) | Fix the reason shown; the data report is still correct |
| The saved key can no longer be read | `APP_KEY` changed since the key was saved | Enter the key again in Gemini Settings |

**Logs.** Admin → AI Command Center → **Activity & Errors** lists provider errors and tool problems. The Laravel log is in `storage/logs/laravel.log`. Keys are never written to either.

**Data handling.** On the free tier Google may use request content to improve its products. Meezan Property masks phone numbers, e-mail addresses, CNIC and card numbers before sending. For sensitive production workflows, review Google's terms and use a paid tier.
