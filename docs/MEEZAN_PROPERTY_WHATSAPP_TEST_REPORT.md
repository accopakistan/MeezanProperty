# Meezan Property: WhatsApp Test Report

Date: 5 November 2026. Every result below was actually run. **No real WhatsApp message was sent, and Gemini was not called.**

## 1. Results by kind of test

| Kind | Result |
|---|---|
| Automated tests (full suite, SQLite) | **900 passed, 5,331 assertions, 0 failed** |
| New WhatsApp tests, using mocked Meta responses | **72 passed** (263 assertions) |
| Existing WhatsApp tests | 39 passed (one assertion corrected; see section 3) |
| Mock pipeline on the real MariaDB, rolled back | Passed; row counts unchanged |
| Admin pages rendered on MariaDB | 4 pages returned 200 with no secrets |
| Local webhook route, called over HTTP | GET with wrong token 403; unsigned POST 503 (not configured yet) |
| Layout check at 375 px and 1366 px | No horizontal overflow on the three screens |
| `vendor/bin/pint --test` | Passed |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `route:cache`, `view:cache` | Worked, then cleared |
| `composer audit` | Clean, after the Symfony update (section 5) |
| **Live Meta test** | **Pending:** needs your token and approval |
| **Live webhook from Meta** | **Pending:** needs a public HTTPS address |
| **Live Gemini test** | **Pending:** not run, to protect the free quota |

## 2. What the new tests cover

| Area | Checked |
|---|---|
| Configuration | Test mode is the default; any unknown mode means test; production never uses test ids; older setting names still work; missing token, secret or phone id gives a warning and no invented value; production stays off without its account id |
| Outbound | `hello_world` in Meta's documented shape, to the right URL; text, template with parameters, list and media; refused unsafe media link; invalid recipient blocked without calling Meta; expired token explained and alerted; rate limit retried, including as HTTP 400; configured timeout and address; no double send; token never stored |
| Test mode | Sends only to the verified recipient; refuses all if none is set; owner test refused in production |
| Webhook | Verification at the new address; wrong token; valid and invalid signature; older address still works; oversized and malformed bodies; duplicates; text, image, caption, list reply and reaction; sent, delivered, read and failed; status moves forward only; no message content in event records |
| AI | 10 phrasings reach the right registered, active agent; property search stays with the assistant; website routing unchanged; Gemini places an unmatched message through the existing provider; a model answer naming an unreachable agent is ignored; safe reply when Gemini returns 503; fallback when an agent is paused; Index shows "Insufficient Verified Data" |
| Unauthorized actions | Asking for revenue, pending dealers, system health or the token reaches no staff tool; staff tool refused on a customer task; delegation refused; model tool mode can't choose tools; a payment issue reads no financial record |
| Handoff | Requested, AI stops, one alert; STOP still works; assign, reply, resolve, resume; plain resolve doesn't resume; only administrators can be assigned; taking over assigns the chat |
| Admin | IDs shown and secrets never shown; missing settings listed; test button absent in production; sends only when pressed; admin only; search and filters; AI or staff labels; note never sent to the customer |
| Command | `--check` sends nothing and prints no secret; refuses the mock provider; answering no sends nothing; answering yes sends once |

## 3. An existing test changed

`WhatsAppIntegrationTest`, "applies delivery statuses from the provider only forwards":

- **Before:** after a late "sent" status, it expected the conversation message to say `sent`.
- **Now:** it expects `read`.

The old assertion recorded the bug this work fixed, where a late status moved the conversation copy backwards. The outbound row's own checks are unchanged. No other existing test was edited.

`phpunit.xml` gained pinned WhatsApp settings so a local `.env` can't change results. It runs the suite in production mode, because the existing tests describe production rules. Test-mode cases switch it on themselves.

## 4. Pipeline on real MariaDB

Mock provider, faked HTTP, model off, in one transaction that was rolled back:

| Message | Agent | Result |
|---|---|---|
| 10 marla house in DHA Lahore around 4 crore | Sales Manager | 3 real listings with match labels |
| What is the Meezan Investment Index? | Investment Analyst | Explanation, then the published position |
| How can I register as a dealer? | Dealer Management | Approved help text and link |
| What are your dealer packages? | Subscription Manager | Approved help text and link |
| How do I list my property? | Property Management | Approved help text and link |
| The website is not working | Technical Operations | Ticket reference; no diagnostics |
| I forgot my password | Customer Support | Approved help text |
| Show me the total revenue report | Sales Manager | Re-showed the saved search. No revenue data, but not a good answer (limitation 5 in the implementation report) |
| My payment failed, please refund me | Finance Manager | Ticket, handoff, AI stops |
| 5 marla plot in Lahore | None | Kept for staff, not answered |
| STOP | Customer Support | Unsubscribed, handoff closed |

Tools called: `investment_index.leaderboard` ×5, `investment.rankings`, `knowledge.search` ×4, `staff.create_task`, `support.create_ticket` ×2. All are on the customer list. No HTTP calls left the machine.

## 5. Security advisories (not from this work)

`composer audit` was clean on 4 November, then reported 7 advisories published since, in `symfony/http-foundation` (4), `symfony/http-kernel`, `symfony/routing` and `symfony/yaml`.

**Update, 5 November:** these four packages moved from v7.4.22 to v7.4.23 in a separate session, and the owner had the update brought into the main checkout (`composer.lock` only). `composer audit` is now clean. Rerun on the combined code: 900 tests passed (5,331 assertions), Pint passed, and the MariaDB pipeline check passed with row counts unchanged.

**Still open:** the app has no trusted-hosts list, so a faked `Host` header produced a password-reset link on that host on the local server. The Symfony update does not change this, and it should be fixed before deployment.

## 6. Existing data

Compared with the counts taken before any change:

| Table | Before | After |
|---|---|---|
| users | 4 | 4 |
| properties | 18 | 18 |
| leads | 2 | 2 |
| conversations | 12 | 12 |
| conversation_messages | 56 | 56 |
| outbound_messages | 19 | 19 |
| webhook_events | 17 | 17 |
| active agents | 16 | 16 |
| support_tickets | 0 | 0 |

- **Rankings:** `investment_rankings` holds 17,685 rows, and calculation #7 still has its 3,522.
- **`ai_tasks`:** rose from 186 to 194. All eight new tasks are one AI Command Center request ("check seo in google") at about 11:53 on 5 November, not from this work.

## 7. Blocked by design

During this work I started a command that included a real run of `meezan:whatsapp-test-send --yes`. The permission system stopped it before anything ran, and I removed that step. The live send path was tested only with mocks. Nothing was sent: `outbound_messages` stayed at 19.

## 8. Definition of done

| # | Item | Status |
|---|---|---|
| 1 | Project boots | Verified |
| 2 | Website functionality intact | Verified: full suite, home page 200 |
| 3 | Gemini integration intact | Verified by tests; not called live |
| 4 | 16 agents intact | Verified: 16 active. Two gained a tool, six gained a customer workflow |
| 5 | WhatsApp configuration loaded | Verified with `--check` |
| 6 | Valid Meta requests constructed | Verified against Meta's documented shape; **not confirmed by Meta** |
| 7 | Outbound passes automated tests | Verified |
| 8 | Webhook verification passes | Verified |
| 9 | Signature verification passes | Verified |
| 10 | Incoming messages with mocked events | Verified on SQLite and MariaDB |
| 11 | Messages reach the Central AI Brain | Verified |
| 12 | Correct agent selected | Verified |
| 13 | Human handoff | Verified |
| 14 | Admin can inspect conversations | Verified by tests and snapshots; not by signing in |
| 15 | Test and production isolated | Verified |
| 16 | No secrets exposed | Verified |
| 17 | No data or features lost | Verified |

**Still needs real credentials:** the live send and connection test.

**Still needs public HTTPS:** real incoming messages, delivery updates and AI replies to real people.
