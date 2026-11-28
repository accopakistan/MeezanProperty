# AEO and GEO implementation

## AEO — question-and-answer content

`App\Support\Faq` builds short questions and answers for the 7 city pages and every area guide. It is shown with `<x-faq>` (same card style as the existing "About this location" card) and emits `FAQPage` JSON-LD whose text is identical to what is on the page.

Every answer is written only from Meezan Property's own records. No market prices, approvals, statistics or advice are invented.

| Page | Questions | Data used |
|---|---|---|
| City pages | How many properties are listed in {city}? | live, non-demo listings by purpose, with today's date |
| | Which areas of {city} have the most listings? | top 3 areas by live listings (shown only when there are listings) |
| | How can I check whether a housing society in {city} is approved? | count of housing schemes with an authority status, the authority that published most of them, the date checked and the source URL already stored on the area records; for cities with no recorded status (Karachi, Faisalabad, Peshawar) it says so |
| | How big is a Marla and a Kanal on Meezan Property? | the site's own unit setting (`MEEZAN_MARLA_SQFT`, 225 sq ft), with a note that other places and land records may differ |
| Area guides (housing schemes) | Is {area} approved? | the area's recorded approval status, authority, date checked, source note and source link; "not verified" when nothing is recorded |
| Area guides | How many properties are for sale or rent in {area}? | live listings |
| | What are asking prices in {area}? | only when the existing asking-price medians have enough listings (same rule as the page's price card) |

Every approval answer repeats that Meezan Property does not certify schemes and that buyers should confirm with the authority.

Not done, on purpose: the general buyer questions in the brief (how to buy property in Pakistan, documents to verify, file vs plot, transfer process, stamp duty, CDA verification steps, rental yield and capital appreciation explainers). Answering these correctly needs researched, dated and cited content (LDA, CDA, FBR, provincial revenue departments) and an accountable reviewer. Writing them without sources would risk publishing wrong legal or tax guidance. They are listed in `REMAINING_ISSUES.md` as content to commission.

## GEO — identity and machine-readable guides

| Item | Status |
|---|---|
| `/llms.txt` | Added (`LlmsTxtController`). Generated from routes and live counts: what Meezan Property is, the source rules (authority lists cited with date, secondary references labelled, no certification, asking ≠ sale price), main sections, the 7 cities, research and methodology links, how to read Index statuses, units (Marla, Kanal, crore, lakh) and contact. Cached 1 hour. |
| Organization JSON-LD | Adds a description using the positioning "Pakistan's property search, research and investment intelligence platform". `sameAs` is filled from `MEEZAN_SAME_AS` and is empty until the owner provides official profiles. Now also on the About page. |
| About page | Its existing "What we offer" list now also covers area guides, the project directory and the methodology. |
| AI crawlers | `robots.txt` reviewed: GPTBot, ClaudeBot, PerplexityBot, Google-Extended and Bingbot are not blocked. No change made. |
| Research transparency | Already present on project and area pages (source, basis label, date checked, "not verified" wording); now summarised in `llms.txt`. |
| Authors and reviewers | Not added. No named, qualified reviewer was provided, and inventing one is not allowed. |
| Meezan Bank disambiguation | Not added. A "not affiliated with Meezan Bank" statement must come from the owner (see `REMAINING_ISSUES.md`). |

Nothing here guarantees FAQ rich results, AI Overview inclusion or citations by ChatGPT, Gemini, Perplexity or Copilot. Visibility can only be judged from Search Console and real queries after deployment.
