# Structured data validation

Checked locally with `scratch/ld-check.php`: every JSON-LD block on the page must parse, carry `@context: https://schema.org` and a `@type`, contain no empty values, use absolute URLs, and every FAQ answer must appear word for word in the visible page text.

| Page | Types | Result |
|---|---|---|
| `/` | WebSite (with SearchAction), Organization | pass |
| `/about` | Organization, BreadcrumbList | pass (Organization newly added here) |
| `/lahore`, `/karachi` (all 7 cities) | CollectionPage (City), BreadcrumbList, FAQPage | pass (FAQPage new) |
| `/areas/dha-lahore`, `/areas/etihad-town-lahore` | BreadcrumbList, FAQPage | pass (FAQPage new) |
| `/lahore/property-for-sale` | CollectionPage, ItemList, BreadcrumbList | pass |
| `/projects/dha-lahore` | BreadcrumbList | pass |
| `/investment-rankings`, `/buy` | BreadcrumbList | pass |
| All 9 local listings | RealEstateListing, BreadcrumbList | pass |

## Changes

- **RealEstateListing** gains `about` for homes only: `SingleFamilyResidence` (house, farmhouse), `Apartment` (apartment, penthouse) or `Accommodation` (portions), with `numberOfBedrooms`, `numberOfBathroomsTotal` and `floorSize` from the covered area (sq ft, `FTK`). Plots and commercial units get no `about`, since they have no rooms.
- **Offer** gains `seller` (`RealEstateAgent`, name and profile URL) only when the listing's dealer is a public dealer profile. Private owners are never named.
- **Organization** gains `description` and `sameAs` (from `MEEZAN_SAME_AS`; empty until official profiles exist).
- **FAQPage** on city pages and area guides, generated from the same array as the visible text.

No reviews, ratings, aggregate ratings or hidden content were added. Dates (`datePosted`, `dateModified`, sitemap `lastmod`) come from the records' own timestamps.

## Still to run after deployment (needs the live URL)

- Google Rich Results Test on one listing, one city page and one area guide.
- Schema.org Validator on the same pages and the home page.

FAQ rich results are not promised. Google currently shows them only for a small set of authoritative government and health sites, but the markup still describes the page accurately for other consumers.
