# Meezan Property: Authorized Listing Acquisition Plan

**Document Reference:** `docs/AUTHORIZED_LISTING_ACQUISITION_PLAN.md`  
**Date:** 7 November 2026  
**Implementation Tool:** `php artisan meezan:listings-import`  
**Template Location:** `public/templates/authorized_listings_template.csv`  

---

## 1. Principles of Authorized Inventory Acquisition

Meezan Property prohibits unverified scraping, unauthorized third-party copying, and fabricated advertisements. Every single property listing published on the platform must possess an explicit, documented publication basis.

### 1.1 The Three Statutory Publication Bases
Under migration `000062_add_publication_basis_to_properties`, every published property record must declare one of the following:

1. **`authorized_feed`:** Direct authorized electronic data feed (CSV / REST / XML) provided by an agency under an executed syndication agreement.
2. **`licence`:** Formal written intellectual property license authorizing publication of the broker's listings and descriptive text.
3. **`partnership`:** Corporate joint venture, developer sales distribution agreement, or institutional partnership.
4. **`dealer_submission`:** Native listing created directly by an authenticated, KYC-verified estate agent through their dealer dashboard.
5. **`direct_owner`:** Verified private owner listing with mobile OTP confirmation and CNIC verification.

*An advertisement seen on another property portal is **never** a basis for publishing on Meezan Property.*

---

## 2. Authorized Importer Architecture & Validation Engine

The authorized listing importer (`app/Console/Commands/ListingsImport.php`) implements strict validation controls:

```bash
php artisan meezan:listings-import <path-to-csv> \
    --lister=<agency-email> \
    --basis=<authorized_feed|licence|partnership> \
    --source="<Agency or Partner Name>" \
    [--commit]
```

### 2.1 Core Safeguards
* **Dry Run by Default:** Running without `--commit` performs a complete parse and validation report without saving any database rows.
* **Real Dealer Requirement:** The `--lister` argument must belong to an active, real user account with role `dealer` whose profile is **not** flagged as demo data (`is_demo = false`).
* **Moderation Queue:** Every newly imported listing enters `status = 'pending'`, requiring admin or moderation approval before becoming publicly visible.
* **Zero Scraped Photos:** Photos require separate copyright and privacy permissions. The importer leaves photo paths empty so the authenticated agency can review and upload verified, un-watermarked high-resolution images.
* **Strict Location Matching:** Every row's `city` and `area` are checked against Meezan's spatial database (`areas` table). Unknown areas or city mismatches are immediately reported and rejected.
* **Anti-Spam & Placeholder Filter:** Descriptions with placeholder text, lorem ipsum, or fewer than 50 characters fail validation.
* **Deduplication:** Repeated source references (`source_reference`) and URLs (`source_url`) are automatically skipped.

---

## 3. CSV / Feed Template Specification

The standardized acquisition template is available at `public/templates/authorized_listings_template.csv`.

| Field Name | Type | Constraint / Validation Rule | Description |
|---|---|---|---|
| `source_reference` | String | Required, unique per source, max 120 chars | Agency's internal listing reference code. |
| `source_url` | URL | Required, valid HTTP/HTTPS URL, max 2048 chars | Direct URL to original listing on agency's official website. |
| `title` | String | Required, 10 to 160 characters | Descriptive title (e.g. "Brand New 1 Kanal House for Sale in DHA Phase 6"). |
| `purpose` | Enum | Required: `sale` or `rent` | Transaction type. |
| `type` | Enum | Required: `house`, `flat`, `upper_portion`, `lower_portion`, `farm_house`, `penthouse`, `room`, `residential_plot`, `commercial_plot`, `agricultural_land`, `industrial_land`, `plot_file`, `plot_form`, `office`, `shop`, `warehouse`, `factory`, `building`, `other` | Property classification. |
| `city` | Enum | Required: `Lahore`, `Karachi`, `Islamabad`, `Rawalpindi`, `Multan`, `Faisalabad`, `Peshawar`, `Quetta` | Launch city jurisdiction. |
| `area` | String | Required: Must resolve to active `Area` record in `city` | Name of neighborhood, sector, or phase. |
| `address` | String | Optional: max 255 chars | Street address, block number, or road name. |
| `price` | Integer | Required: PKR 1,000 to 50,000,000,000 | Total asking price (sale) or monthly rent (rent) in PKR. |
| `area_size` | Float | Required: 0.25 to 1,000,000 | Numeric surface area. |
| `area_unit` | Enum | Required: `marla`, `kanal`, `sqft`, `sqyd` | Unit of spatial measurement. |
| `bedrooms` | Integer | Required for residential homes/flats: 0 to 30 | Number of bedrooms. |
| `bathrooms` | Integer | Optional: 0 to 30 | Number of bathrooms. |
| `furnishing` | Enum | Optional: `unfurnished`, `semi-furnished`, `furnished` | Furnishing condition. |
| `completion_status` | Enum | Optional: `ready`, `under_construction`, `off_plan` | Construction stage. |
| `authorization_type` | Enum | Optional: `authorized_agent`, `mandate`, `direct_owner` | Basis of agency authority. |
| `amenities` | String | Optional: semicolon-separated codes (e.g. `electricity;water_supply;gas;parking`) | Valid Meezan amenity identifiers. |
| `description` | Text | Required: 50 to 5,000 characters. No lorem ipsum. | Complete factual description of property features. |
| `agency_name` | String | Metadata: Full corporate entity name | Managing brokerage entity. |
| `contact_phone` | String | Metadata: Valid Pakistani phone/WhatsApp number | Verified dealer contact number. |
| `contact_email` | Email | Metadata: Official agency email address | Registered listing administrator. |
| `authorized_images`| String | Metadata: Semicolon-separated authorized image URLs | Original image files authorized for publication. |
| `checked_on` | Date | Optional: YYYY-MM-DD (&le; today) | Date listing details and availability were last verified. |
| `availability_confirmed` | Enum | Required: `yes` | Explicit confirmation that property is currently on the market. |

---

## 4. Agency Onboarding Protocol

1. **Brokerage Application:** Agency registers via `/dealer/register` submitting agency name, office address, tax NTN / SECP registration number, and operating license.
2. **KYC & Verification:** Staff examine verification documents in Admin (`/admin/dealers/{dealer}`). Once verified, dealer status transitions to `active`.
3. **Data Agreement Execution:** Agency executes standard Meezan Property Authorized Listing Syndication Agreement, selecting authorization basis (`authorized_feed` or `licence`).
4. **Initial Feed Test:** Feed is checked using `php artisan meezan:listings-import feed.csv --lister=dealer@domain.pk --basis=authorized_feed --source="Agency Name"` (dry-run).
5. **Batch Import & Moderation:** Upon passing validation, import runs with `--commit`. Listings populate moderation queue for final photo and content clearance.
6. **Zero Fabrication Enforcement:** Any account attempting to import fictional listings or unverified contact data is suspended and archived.
