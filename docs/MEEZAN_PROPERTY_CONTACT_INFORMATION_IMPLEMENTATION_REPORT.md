# Meezan Property: Contact Information Implementation Report

Date: 6 November 2026.

## The official details

| | Value | Link |
|---|---|---|
| Email | support@meezanproperty.com | `mailto:support@meezanproperty.com` |
| Office phone | +92 300 2358853 | `tel:+923002358853` |
| WhatsApp | +92 311 1749849 | `https://wa.me/923111749849?text=…` |
| Office | Office 2, 3rd Floor, Bigcity Plaza, Gulberg-III, Lahore, Pakistan | — |

The office phone and the WhatsApp number are separate settings and separate links, so they can't be swapped by mistake. No other address, number, email or social link was added.

## Where the details come from

- **Config:** `config/meezan.php`, under `contact`. The confirmed values are the defaults, so the live site shows them even though its `.env` doesn't set them.
- **Override:** a value in `.env` (`MEEZAN_CONTACT_EMAIL`, `MEEZAN_CONTACT_PHONE`, `MEEZAN_WHATSAPP_NUMBER`, `MEEZAN_CONTACT_ADDRESS`) takes precedence. **Check that the live `.env` doesn't set these to anything else.**
- **Helper:** `App\Support\Contact` builds every link: phone in international form, display format "+92 300 2358853", and `wa.me` links with a prefilled message.

## Where they appear

| Place | What |
|---|---|
| Header, desktop | Office phone (call link) and WhatsApp link |
| Header, mobile menu | "Call us" and "WhatsApp" buttons |
| Footer | Address, office phone, WhatsApp, email, contact page link, plus Disclaimer and Contact in Support |
| Floating button, every public page | "WhatsApp us" opens a chat with the team. It's not shown on dashboards |
| Contact page | Full list, "Call the office" and "WhatsApp us" buttons, and the existing form |
| About page | New "Our office" and "What we offer" sections |
| Homepage | The "WhatsApp Support" tiles now open WhatsApp and read "Chat with our team" |
| AI Property Finder | The WhatsApp card says it messages the team and that the automated assistant isn't live yet. The "24/7 on WhatsApp" claim was removed |
| Property pages | Dealer call and WhatsApp buttons still appear only for verified dealers with public numbers. A "WhatsApp Meezan Property" link with the listing's address was added below them |
| Search structured data | Organization schema gains the office address beside the phone and email |
| AI assistant knowledge | A "contact us" document with the same details |

## WhatsApp Cloud API stays separate

Every link here is a plain `wa.me` chat. The Cloud API integration is unchanged and stays in test and mock mode. **No public text says the automated assistant is live, and no WhatsApp message was sent during this work.**

## Forms and validation

| Change | Where |
|---|---|
| New `App\Rules\PhoneNumber` rule. Letters are rejected. 10–15 digits with an optional `+`. A Pakistani mobile must be complete (03XX XXXXXXX or +92 3XX XXXXXXX) | Inquiry, contact, buyer, owner and dealer registration, and both profile forms |
| Phone inputs open the numeric keypad (`inputmode="tel"`) | Field component |
| Consent and registration checkboxes are marked `required` and are still enforced on the server | Listing inquiry, buyer, owner and dealer registration |
| While the Terms aren't approved, the registration checkbox asks people to confirm their details and that they've read how the platform works. They're not asked to accept unapproved terms | `auth/partials/terms-consent` |
| Already in place and kept: CSRF tokens, honeypot field, rate limits, server validation and messages | — |

## Links tested

| Check | Result |
|---|---|
| `tel:`, `mailto:` and `wa.me` links on public pages at 1440 px | Browser sweep; see the final regression report |
| The office number is never used for WhatsApp, or the reverse | Automated test |
| Prefilled WhatsApp text is URL-encoded | Automated test |

The links were checked for their targets only. No call was placed, no email sent and no WhatsApp chat opened.
