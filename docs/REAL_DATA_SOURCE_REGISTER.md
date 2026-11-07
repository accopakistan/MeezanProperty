# Meezan Property: Real-World Data Source Register (Phase 1)

**Document Reference:** `docs/REAL_DATA_SOURCE_REGISTER.md`  
**Date:** 7 November 2026  
**Status:** Live Audited & Operational  
**Database Provenance:** Verified against `market_sources` table in MariaDB  

---

## 1. Statutory Authority Hierarchy & Reliability Framework

Meezan Property enforces a strict, hierarchical data provenance architecture. Public websites, marketing portals, and dealer claims **cannot** establish legal approval status. Only primary regulatory authorities and statutory oversight bodies establish verified legal standing.

| Tier | Source Classification | Authority Level | Permitted Usage in Meezan Engine |
|---|---|---|---|
| **Tier 1** | **Official Regulatory Records** | **1.0 (Maximum)** | Determines legal approval status, NOC validity, layout plan (LOP) sanctions, and statutory area/plot counts. |
| **Tier 2** | **Audited / Licensed Documents** | **0.8 – 0.9** | Verified occupancy rolls, audited REIT financial statements, certified structural safety inspections. |
| **Tier 3** | **Official Developer / Owner Disclosures** | **0.6** | Descriptive project metadata, stated launch dates, specifications (marked as developer-stated). |
| **Tier 4** | **Authorized Valuer / Research Reports** | **0.7** | Historical market trends and independent demand assessments (must be corroborated). |
| **Tier 5** | **Secondary References & News** | **0.4** | Informational context only; strictly zero weight in investment scoring or legal standing. |
| **Untrusted** | **Public Asking Prices & Marketing Ads** | **0.0 (Zero)** | Marketplace search only. **Never** treated as transactions, historical yields, or legal approvals. |

---

## 2. Core Official Sources Register

### 2.1 Lahore Development Authority (LDA)
* **Authority:** Lahore Development Authority, Government of the Punjab
* **Jurisdiction:** Lahore Metropolitan Area, Tehsil Raiwind, Cantt, Shalimar, City
* **Official Portal Addresses:**
  * Approved Schemes: `https://lda.gop.pk/approved-schemes` (and scheme details e.g. `?scheme_id=4`)
  * Illegal / Unapproved Schemes: `https://lda.gop.pk/illegal-schemes`
  * Schemes Developed by LDA: `https://lda.gop.pk/regular-schemes`
  * Digital Plan Archives: `https://lda.gop.pk/private_schemes/plu/img/schemes/`
* **Access Method & Technical Protocol:** Inertia.js application embedding JSON state inside `data-page` HTML attribute. Automated collection respects `robots.txt`, operates with `MeezanPropertyBot/1.0`, and enforces 1,000ms polite request delays.
* **Statutory Facts Extracted:**
  * Official Scheme Name and Scheme Key
  * Mauza (Revenue Village) & Tehsil Jurisdiction
  * Exact Approval Stage (e.g., "Technically Approved", "Finally Approved", "TMA Approved", "Final (Revised Plan)")
  * Date of Statutory Approval / Sanction
  * Approved Gross Area in Kanals
  * Sanctioned Residential Plots count
  * Sanctioned Commercial Plots count
  * Approved Blocks / Phase designations
  * Mortgaged Plots Schedule & Performance Guarantee Execution
  * Direct Link to Digitized Sanctioned Layout Plan Drawing
* **Legal Interpretation Rule:** "Technically Approved" schemes are classified as `partially_approved` (layout plan approved, final NOC pending execution of mortgage deed and completion of terms). Schemes marked "Finally approved" or "TMA Approved" with executed mortgage deeds are classified as `approved`.
* **Last Audited:** 7 November 2026. Total entries indexed: 401 approved schemes, 382 illegal listings.

---

### 2.2 Capital Development Authority (CDA)
* **Authority:** Capital Development Authority, Islamabad Capital Territory (ICT)
* **Jurisdiction:** Islamabad Capital Territory (Zones 1, 2, 3, 4, 5)
* **Official Portal Addresses:**
  * Private Housing Schemes: `https://www.cda.gov.pk/housing-schemes?letter=all`
  * Illegal Housing Schemes: `https://www.cda.gov.pk/illegal-housing-schemes`
  * Private Commercial Schemes: `https://www.cda.gov.pk/commercial-schemes?letter=all`
* **Access Method & Technical Protocol:** Card-based DOM and HTML list items. Each scheme page documents independent layout plan and NOC stages.
* **Statutory Facts Extracted:**
  * Scheme Title and Unique Scheme Slug
  * CDA Planning Zone (Zone 2, Zone 4, Zone 5)
  * Access Road / Master Plan Alignment (e.g. GT Road, Islamabad Highway, Simly Dam Road)
  * Gross Planning Area in Kanals
  * Developer / Sponsor Entity (Corporate sponsor or cooperative society)
  * Layout Plan (LOP) Approval Status & Date
  * No Objection Certificate (NOC) Status & Date
  * Latest Regulatory Notice / Warning Date
* **Legal Interpretation Rule:** CDA enforces a strict two-stage statutory framework:
  1. **Layout Plan (LOP) Approval:** Authorizes planning layout only; does **not** authorize marketing, sales, or plot transfers.
  2. **No Objection Certificate (NOC):** Authorizes public sale and transfers.
  * Schemes with LOP Approved but NOC Not Issued are categorized strictly as `partially_approved`.
  * Schemes where either LOP or NOC has been revoked are categorized as `cancelled` and immediately excluded from all investment recommendations.
* **Last Audited:** 7 November 2026. Total entries indexed: 70 private schemes, 166 illegal scheme notices.

---

### 2.3 National Accountability Bureau (NAB) — Property Verification Portal
* **Authority:** National Accountability Bureau (NAB), Government of Pakistan, in collaboration with Propsure / Propmax
* **Jurisdiction:** National (Federal, Punjab, Sindh, Khyber Pakhtunkhwa, Balochistan)
* **Official Portal Address:** `https://www.property.nab.gov.pk/housing-scheme-verification`
* **Backend Verification Endpoint:** `https://property.nab.gov.pk/be/api/societies`
* **Access Method & Technical Protocol:** REST API providing authoritative statutory verification status, authority attribution, and official downloadable verification report PDFs (`/be/attachment/file/.../view`).
* **Statutory Facts Extracted:**
  * Scheme Verification ID
  * Approved Scheme Title
  * Regulatory Authority Code (`CDA`, `LDA`, `FDA`, `RDA`, `SBCA`, `KDA`, `GDA`, `PDA`)
  * Official Verification Report PDF Link
  * Statutory Clearance & Legal Investigation Status
* **Meezan Engine Registration:** Registered as `MarketSource` #63: "NAB — Housing Scheme Verification Portal" with `reliability = 'high'` and `authority_level = 1.0`.
* **Last Audited:** 7 November 2026. Total entries indexed: 1,060 national verification records (including 408 LDA records, 62 CDA records, and 188 FDA records).

---

### 2.4 Faisalabad Development Authority (FDA)
* **Authority:** Faisalabad Development Authority, Government of the Punjab
* **Jurisdiction:** Faisalabad Metropolitan Corporation and District
* **Official Portal Addresses:**
  * Approved Private Schemes: `https://fda.gov.pk/schemes_private`
  * Schemes Developed by FDA: `https://fda.gov.pk/schemes`
* **Access Method & Technical Protocol:** HTML tabular dataset. Requires polite SSL configuration (`CURLOPT_SSL_VERIFYPEER` managed gracefully for legacy Punjab government SSL chains).
* **Statutory Facts Extracted:**
  * Scheme Name & Serial Number
  * Mauza / Location Alignment
  * Approved Area in Acres / Kanals
  * Statutory Sanction Reference Number & Date
* **Last Audited:** 7 November 2026. Total entries indexed: 188 approved private schemes, 19 authority-developed schemes.

---

### 2.5 Regional Civic Authorities (MDA, PDA, QDA, SBCA)
* **Multan Development Authority (MDA):** `https://mda.punjab.gov.pk/housing-schemes` and `approved.pdf` (61 sanctioned private housing schemes, 11 authority schemes).
* **Peshawar Development Authority (PDA):** `https://www.pda.kp.gov.pk/illegalsocieties` (Approved and unapproved private housing societies).
* **Quetta Development Authority (QDA):** `https://qda.gob.pk/img/scheme_lists/approved%20scheme-2024.pdf` (45 approved private housing schemes).
* **Sindh Building Control Authority (SBCA):** `https://sbca.gos.pk/home/public_sale_projects` and `/housing-Year-2025` (218 public sale project NOCs and architectural approvals for Karachi).

---

## 3. Data Integrity & Verification Constraints

1. **No Inferred Values:** Missing data remains explicitly empty (`null`) or labeled as "Not stated in official records".
2. **Area vs Date Distinction:** Areas are strictly quantified in Kanals or Acres. Approval dates are parsed into ISO-8601 timestamps and never confused with spatial measurements.
3. **LOP vs NOC Separation:** Layout Plan approvals are never recorded as formal NOCs.
4. **Adverse Notice Enforcement:** Any project appearing on an official illegal, cancellation, or revocation list is flagged with `severity = 'serious'`, setting `approval_status = 'cancelled'` or `'illegal'` and excluding the asset from investment index calculations.
5. **No Commercial or Marketing Bias:** Portals and classified listings cannot verify legal status. All records carry an immutable content hash, collection timestamp, and collector signature.
