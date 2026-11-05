# Meezan Property: WhatsApp Setup Guide

For the owner. You stay in control: nothing here is sent or switched on until you do it.

**Never paste these values into a chat, a report or source code.** Enter them only in `C:\xampp\htdocs\MeezanProperty\.env`.

## 1. Add the three secrets

The non-secret test settings are already in `.env`. These three lines are there but empty. **For an outbound test you only need the access token.** The other two are for incoming webhooks.

| Line | Where it comes from |
|---|---|
| `WHATSAPP_ACCESS_TOKEN=` | Meta App → WhatsApp → API Setup. The temporary token lasts about a day; for longer use, create a System User token |
| `META_APP_SECRET=` | Meta App → App settings → Basic → App secret |
| `WHATSAPP_VERIFY_TOKEN=` | You make this up. Generate it with the command below |

Generate a verify token:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copy the result into `.env`, and keep it for step 5.

## 2. Turn off mock mode safely

Mock mode is ON, so nothing can reach a phone yet. Adding credentials never turns it off. Do this only when you're ready to test:

1. In `.env`, change `WHATSAPP_MOCK_MODE=true` to `WHATSAPP_MOCK_MODE=false`. You can ignore `MEEZAN_WHATSAPP_DRIVER`, because this setting takes priority.
2. In **Admin → WhatsApp Control**, turn the **Mock mode** switch off. It has been on since 2 November.

Both must be off. Mock Mode on the same page shows what is still holding it on, and **Outgoing messages right now** should then say "Real Meta test messages". In test mode only your verified recipient can receive a message, so nothing can reach a customer. Any AI or staff reply to that number will be real, though.

To go back, set either one on again.

## 3. Clear the configuration cache

Run this each time you change `.env`:

```bash
php artisan config:clear
```

## 4. Check, then send the test message

This shows your setup and sends nothing. Secrets appear only as yes or no.

```bash
php artisan meezan:whatsapp-test-send --check
```

To confirm Meta accepts the token without sending anything, press **Test Meta connection** in Admin → WhatsApp Control. This works even while mock mode is on.

When Meta Test API shows **Ready** and mock mode is off, send Meta's `hello_world` template to the verified test number, `923111749849`:

```bash
php artisan meezan:whatsapp-test-send
```

It asks you to confirm first. In Admin → WhatsApp Control, you can instead tick the approval box and press **Send test message**. Each send is one message, and only you can start it.

**Expected:** the message arrives on the phone, and the command prints a message id. Delivered and read updates will not show in the admin yet, because they need the public webhook (step 5).

| If you see | It means |
|---|---|
| `code 190` | The token expired or is wrong. Temporary tokens last about a day |
| `code 131030` | Meta does not list the number as a test recipient |
| `code 132001` | The template name or language doesn't match |

## 5. Webhook: only after the site is public

Meta can only call a **public HTTPS** address, so `localhost` can't receive real messages. Until the site is deployed, these stay pending: incoming messages, delivered and read updates, and AI replies to real messages.

Your Meta report adds a further limit: **while the Meta app is unpublished, Meta delivers only dashboard test webhooks.** Real incoming messages also need the app published (Live).

The admin page shows incoming messages as working only with evidence: a public address, Meta's verification, and a real message received through the webhook.

Once it is deployed:

1. Put the same three secrets in the live server's `.env`.
2. Set `WHATSAPP_WEBHOOK_URL=https://meezanproperty.com/api/whatsapp/webhook` (use the real address), and run `php artisan config:clear`.
3. In Meta App → WhatsApp → Configuration, enter that URL and your verify token, then press **Verify and save**.
4. Subscribe to the **messages** field.
5. Check that Admin → WhatsApp Control shows "Meta verified the webhook".
6. Make sure a queue worker runs, or replies will wait:

```bash
php artisan queue:work --queue=whatsapp,default,agents
```

On shared hosting, use `MEEZAN_QUEUE_VIA_SCHEDULER=true` with the `schedule:run` cron instead.

## 6. Testing before deployment

**Without any tunnel:** with mock mode on, the test console in Admin → WhatsApp Control runs messages through the real webhook code, AI routing, handoff and policy, and sends nothing.

**With a temporary tunnel (optional):** a tool such as Cloudflare Tunnel gives your local app a temporary HTTPS address for Meta to call. Use only test credentials, and close the tunnel when finished. This exposes your laptop to the internet while it is open, so it's your decision whether to do it. The app refuses hosts other than `APP_URL`'s with a `400`, so set `APP_URL` to the tunnel address while it is open, and run `php artisan config:clear` after setting it and after changing it back.

## 7. Moving to production later

Production is separate and stays off until you do all of this:

1. Register a real number for the Cloud API. Do not use the Meta test sender. Your Meta report notes that a number can't stay on the WhatsApp Business App at the same time unless it uses coexistence onboarding, so check that before choosing `+923111749849`.
2. Set `WHATSAPP_PROD_PHONE_NUMBER_ID` and `WHATSAPP_PROD_WABA_ID`.
3. Use a permanent System User token.
4. Set `WHATSAPP_MODE=production` and run `php artisan config:clear`.
5. Get your dealer alert template approved in WhatsApp Manager, and set `MEEZAN_WHATSAPP_TEMPLATE_DEALER_LEAD`.

Production never falls back to the test number. If its settings are missing, sending stays off.

## Which number does what

| Number | Role | Does a message to it reach the AI? |
|---|---|---|
| `+15551374226` | Meta's test sender. This is the number connected to the API | Yes, once the public webhook is set up, and only from Meta's verified test recipients |
| `+923111749849` | Your verified test recipient. It receives test messages from the API | **No.** It is an ordinary WhatsApp number, so messages arrive on that phone and don't reach the app |

`+923111749849` is your main customer-facing contact number. It is deliberately **not** configured as the API sender.

**Website buttons, changed 5 November 2026 at the owner's request.** These now open `+923111749849`, so customers reach that phone directly, without the AI:

| Button | Setting | Old value |
|---|---|---|
| Start WhatsApp Chat (AI Finder) | `MEEZAN_WHATSAPP_NUMBER` in `.env` | `+923002358853` |
| WhatsApp on listings and the dealer page | Sample Realty's WhatsApp number in Agency Profile | `923000000003` (fake test number) |

For customers' WhatsApp messages to reach the AI, a real number must be registered for the Cloud API (section 7), and these buttons pointed at it.

## Switches at a glance

| Setting | Effect |
|---|---|
| `WHATSAPP_ENABLED` | Master switch for real sending |
| `WHATSAPP_MODE=test` | Real messages go only to `WHATSAPP_TEST_RECIPIENT` |
| `WHATSAPP_MOCK_MODE=true`, or the Mock mode switch on | Everything is recorded and nothing is sent |
| `MEEZAN_WHATSAPP_NUMBER` | The number customers see on the website. It is not the API sender |
| Admin: WhatsApp automation off | No automatic messages; staff replies still work |
| Admin: AI auto-reply off | Messages are stored and held for staff |
