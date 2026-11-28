# Visual regression report

## Method

1. **Markup comparison.** 40 public pages were rendered as a signed-out visitor twice against the same local database: once with the original files from commit `2600d66` (taken from the live checkout and verified by hash), once with the SEO changes. The `<body>` markup of each pair was compared after removing values that change on every request (CSRF token, script nonce, asset file hashes) and the JSON-LD blocks, which are not visible.
2. **Stylesheet comparison.** The live CSS (`app-CzCrREAc.css`) was compared rule by rule with the new build.
3. **Browser check.** The new FAQ card was viewed at desktop width and at 375 px (phone), with a check for horizontal scrolling.

## Results

### Stylesheet

2,118 rules before, 2,120 after: **2 added, 0 removed, 0 changed**. The two added rules belong to the FAQ card only: `.first:pt-0:first-child` and `.last:pb-0:last-child`. No existing colour, typography, spacing or component rule changed.

### Page markup

| Page | Visible difference |
|---|---|
| Home `/` | none |
| Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta, Peshawar | new "Questions about property in {city}" card, placed before "Property in other cities"; nothing else |
| Area guides (DHA Lahore, Etihad Town, F-7, Gulberg) | new "Questions about {area}" card at the end of the page; nothing else |
| Area guide index `/areas` | none |
| Project directory `/projects`, `/projects?page=5` | none (only head metadata changed) |
| Project profiles (DHA Lahore, Galleria Residences, Mall of Lahore) | none (only robots meta on the name-only profile) |
| Dealer directory and a dealer profile | none (only meta description and canonical) |
| Listings (4 checked) | none (only JSON-LD) |
| `/buy`, `/rent`, `/lahore/property-for-sale` | none |
| `/properties` | whitespace only inside three text nodes (no rendered difference) |
| Investment Index home, housing-societies leaderboard, Lahore dashboard, Overall, both methodologies | none (only robots meta on unranked pages) |
| About | three more items in the existing "What we offer" list |
| Contact, How it works, Sell, Login, Register | none |

Not compared in the browser: admin and dealer dashboards. Their templates and layout were not modified; the shared `<head>` component only changed which title suffix and verification tags are printed.

### The FAQ card

- It uses existing components and tokens only: `card card-body`, a `font-serif text-xl font-bold` heading (the same as the "About this location" card) and `text-muted` body text.
- It has no new colours, fonts or icons.
- At 375 px the area guide has no horizontal scroll and the card wraps cleanly. The floating WhatsApp button sits over the card's corner exactly as it does over other cards.
- It adds roughly 1–4 KB of HTML per city or area page.

## Conclusion

Apart from the FAQ cards on city and area pages and the three About list items, which were both intended, the design, colours, typography, navigation, spacing and components are unchanged. No other visual change was made in this review.
