<?php

use App\Services\Agents\Providers\GeminiAdapter;

return [

    /*
    |--------------------------------------------------------------------------
    | Market
    |--------------------------------------------------------------------------
    |
    | Cities that are open for listings, in display order. Each needs a row in
    | the `cities` table (added by migration) and an entry in `city_details`.
    | Rawalpindi is deliberately not a launch city: societies marketed as
    | "Islamabad" but located in Rawalpindi District are not imported.
    |
    */

    'cities' => ['Lahore', 'Karachi', 'Islamabad', 'Multan', 'Faisalabad', 'Quetta', 'Peshawar'],

    'default_city' => 'Lahore',

    // Times are stored in UTC (app.timezone). Times people type and read in
    // the dealer CRM (follow-ups, viewings, "today") use this local zone.
    'timezone' => env('MEEZAN_TIMEZONE', 'Asia/Karachi'),

    // URL slug (/{slug}) and province per city. Slugs are fixed here so the
    // landing-page route stays cacheable.
    'city_details' => [
        'Lahore' => ['slug' => 'lahore', 'province' => 'Punjab'],
        'Karachi' => ['slug' => 'karachi', 'province' => 'Sindh'],
        'Islamabad' => ['slug' => 'islamabad', 'province' => 'Islamabad Capital Territory'],
        'Multan' => ['slug' => 'multan', 'province' => 'Punjab'],
        'Faisalabad' => ['slug' => 'faisalabad', 'province' => 'Punjab'],
        'Quetta' => ['slug' => 'quetta', 'province' => 'Balochistan'],
        'Peshawar' => ['slug' => 'peshawar', 'province' => 'Khyber Pakhtunkhwa'],
    ],

    'marla_sqft' => (int) env('MEEZAN_MARLA_SQFT', 225),

    /*
    |--------------------------------------------------------------------------
    | Public contact details
    |--------------------------------------------------------------------------
    |
    | Only verified, company-owned contact details belong here. The defaults
    | are the owner-confirmed details (6 Nov 2026); .env can override them.
    | The office phone and the WhatsApp number are different lines.
    |
    */

    /*
    | Legal pages count as final only with an approval date here AND approved text in
    | resources/views/pages/legal/approved/{terms,privacy,disclaimer}.blade.php.
    | Until then each page shows a summary labelled "Not yet final" and is noindex.
    */
    'legal' => [
        'terms' => ['approved_on' => env('MEEZAN_TERMS_APPROVED_ON')],
        'privacy' => ['approved_on' => env('MEEZAN_PRIVACY_APPROVED_ON')],
        'disclaimer' => ['approved_on' => env('MEEZAN_DISCLAIMER_APPROVED_ON')],
    ],

    'contact' => [
        'email' => env('MEEZAN_CONTACT_EMAIL') ?: 'support@meezanproperty.com',
        'phone' => env('MEEZAN_CONTACT_PHONE') ?: '+923002358853',
        'whatsapp' => env('MEEZAN_WHATSAPP_NUMBER') ?: '+923111749849',
        'address' => env('MEEZAN_CONTACT_ADDRESS') ?: 'Office 2, 3rd Floor, Bigcity Plaza, Gulberg-III, Lahore, Pakistan',
        'whatsapp_message' => 'Assalam o Alaikum, I would like some help with a property on Meezan Property.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Listings
    |--------------------------------------------------------------------------
    */

    'listing' => [
        'max_images' => 15,
        'max_image_kb' => 5120,
        'lifetime_days' => 90,
        'per_page' => 12,
        // Expiry lifecycle (meezan:listing-lifecycle, daily). Dealers are
        // reminded this many days before expiry, may renew a live listing
        // this close to expiry, and may renew an expired one without a new
        // review for this many days after it expired.
        'reminder_days' => 7,
        'renew_window_days' => 14,
        'renew_grace_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Accounts
    |--------------------------------------------------------------------------
    |
    | When true, buyers, owners and dealers must confirm their email address
    | before sharing requirements with dealers or creating listings. Leave
    | false until outgoing mail works, or nobody can receive the link.
    |
    */

    'require_verified_email' => (bool) env('MEEZAN_REQUIRE_EMAIL_VERIFICATION', false),

    /*
    |--------------------------------------------------------------------------
    | Dealer verification documents
    |--------------------------------------------------------------------------
    |
    | Stored on the private "local" disk (storage/app/private) and streamed
    | only to the dealer who uploaded them and to admins.
    |
    */

    'verification' => [
        'max_document_kb' => 5120,
        // Images must decode as real images (integrity) and stay within the
        // maximum size. A minimum side length can be required; off by default.
        'min_image_px' => env('MEEZAN_DOCUMENT_MIN_IMAGE_PX') ? (int) env('MEEZAN_DOCUMENT_MIN_IMAGE_PX') : null,
        'max_image_px' => 10000,
        // Documents that must be approved before a dealer can be verified,
        // by business type. Other types (office proof, etc.) are optional.
        'required_documents' => [
            'individual' => ['cnic_front', 'cnic_back'],
            'agency' => ['cnic_front', 'cnic_back', 'agency_registration'],
            'owner' => ['cnic_front', 'cnic_back'],
        ],
        // Months a verification stays valid (null = no expiry). Expired
        // dealers lose the badge until they are verified again.
        'valid_months' => env('MEEZAN_VERIFICATION_VALID_MONTHS') ? (int) env('MEEZAN_VERIFICATION_VALID_MONTHS') : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Nearby places
    |--------------------------------------------------------------------------
    |
    | Schools, hospitals, mosques, shopping and parks around listings with an
    | exact pin, from OpenStreetMap via the Overpass API (cached for 30 days
    | per location). The public Overpass server suits light use; point
    | MEEZAN_OVERPASS_URL at a self-hosted or commercial instance for launch.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Security and operations
    |--------------------------------------------------------------------------
    |
    | csp: send a strict Content-Security-Policy (see SecurityHeaders).
    | queue_via_scheduler: for shared hosting without a long-running worker,
    | the scheduler drains the queue every minute instead.
    |
    */

    'security' => [
        'csp' => (bool) env('MEEZAN_CSP_ENABLED', true),
        // Behind a proxy or CDN (e.g. Cloudflare): a comma-separated list of its
        // IPs or ranges, so HTTPS and client IPs are detected correctly. Empty:
        // none. "*" lets any client fake its IP, so use it only if the origin
        // cannot be reached except through the proxy.
        'trusted_proxies' => env('TRUSTED_PROXIES'),
    ],

    'queue_via_scheduler' => (bool) env('MEEZAN_QUEUE_VIA_SCHEDULER', false),

    'nearby' => [
        'enabled' => (bool) env('MEEZAN_NEARBY_ENABLED', true),
        'endpoint' => env('MEEZAN_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'radius_m' => 1500,
        'per_category' => 3,
    ],

    'amenities' => [
        'parking' => 'Parking',
        'gas' => 'Sui gas',
        'electricity_backup' => 'Electricity backup',
        'water_supply' => 'Water supply',
        'security' => '24/7 security',
        'cctv' => 'CCTV',
        'lift' => 'Lift',
        'servant_quarter' => 'Servant quarter',
        'lawn' => 'Lawn / garden',
        'basement' => 'Basement',
        'drawing_room' => 'Drawing room',
        'store_room' => 'Store room',
        'corner' => 'Corner',
        'park_facing' => 'Park facing',
        'main_boulevard' => 'Main boulevard',
        'mosque_nearby' => 'Mosque nearby',
        'school_nearby' => 'School nearby',
        'hospital_nearby' => 'Hospital nearby',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maps
    |--------------------------------------------------------------------------
    |
    | Map search (/map) uses MapLibre GL JS with a vector style (style_url).
    | The default is OpenFreeMap's "liberty" style: OpenStreetMap data, no
    | API key, free to use but run on donations with no service guarantee.
    | Leave MEEZAN_MAP_STYLE_URL empty to draw the raster tile_url instead.
    | Smaller maps (listing page, dealer pin picker) use Leaflet with the
    | raster tile_url. OpenStreetMap's own tile server suits development and
    | light traffic only; configure a provider before high-traffic launch.
    |
    */

    'map' => [
        'enabled' => (bool) env('MEEZAN_MAP_ENABLED', true),
        'tile_url' => env('MEEZAN_MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        // MapLibre style JSON for map search; empty = raster tile_url. If the
        // style fails to load, the page falls back to tile_url by itself.
        'style_url' => env('MEEZAN_MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/liberty'),
        // Above this many located listings in view, map search shows counted
        // grid cells instead of individual pins (zoom in to see pins).
        'max_points' => 500,
        // Published society/area boundaries are drawn from this zoom level.
        'boundary_min_zoom' => 10,
        'attribution' => env('MEEZAN_MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
        // Optional satellite imagery for the investment map's Map/Satellite switch: an XYZ raster
        // tile URL from a provider you hold a licence or key for (for example MapTiler Satellite).
        // Empty = no Satellite option is shown.
        'satellite_url' => env('MEEZAN_MAP_SATELLITE_URL'),
        'satellite_attribution' => env('MEEZAN_MAP_SATELLITE_ATTRIBUTION', ''),
        'center' => [31.5204, 74.3587],
        'zoom' => 11,
        // Used to reject area look-ups that land outside the city.
        'city_centres' => [
            'Lahore' => [31.5204, 74.3587],
        ],
        // Listing coordinates must fall inside this box around Pakistan
        // (approx. 23.6°–37.1° N, 60.9°–77.8° E, with a small margin). A box
        // is a sanity check, not a border test: it also covers slivers of
        // neighbouring countries.
        'pakistan_bounds' => [
            'south' => 23.5,
            'north' => 37.2,
            'west' => 60.5,
            'east' => 78.0,
        ],
        // Warn the dealer (not block) when a pin is this far from the
        // chosen area's centre.
        'area_warning_km' => 6,
        // Pins further than this from the stated area centre, or from the
        // city centre, are flagged for review (never rejected: precise city
        // boundaries are not available).
        'area_flag_km' => 10,
        'city_radius_km' => ['Karachi' => 65, 'default' => 40],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI matching
    |--------------------------------------------------------------------------
    |
    | "rules" is the built-in deterministic parser. "llm" adds the Gemini
    | model (config/services.php "gemini", through AiProvider) for parsing
    | only; matching always runs against the database.
    |
    */

    'ai' => [
        'driver' => env('MEEZAN_AI_DRIVER', 'rules'),
        'max_results' => 3,
        // Listings up to "close" above budget are close matches; up to
        // "alternative" above, they are shown only when they are otherwise on
        // target and are always labelled as above budget.
        'budget' => [
            'close_margin' => (float) env('MEEZAN_AI_BUDGET_CLOSE_MARGIN', 0.10),
            'alternative_margin' => (float) env('MEEZAN_AI_BUDGET_ALTERNATIVE_MARGIN', 0.25),
        ],
        // Without "rent" words, a requirement is read as a purchase for plots,
        // or when the budget is at least this much (rents are monthly).
        'assume_sale_from' => 5_000_000,
        // Days after the last message before website conversations without a
        // saved requirement or lead are deleted by meezan:prune-conversations.
        // 0 keeps them indefinitely (the default until a policy is agreed).
        'retention_days' => (int) env('MEEZAN_AI_RETENTION_DAYS', 0),
        // Messages per conversation per minute before it is flagged as suspicious.
        'burst_limit' => 20,
        // Unparsed messages in a row before the conversation is escalated to a person.
        'escalate_after_failures' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead routing
    |--------------------------------------------------------------------------
    */

    'leads' => [
        'max_dealers_per_requirement' => 3,
        'dedupe_window_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    |
    | Category landing pages per city (/lahore/houses-for-sale) and per area
    | (/areas/dha-phase-6/houses-for-sale). Every page works for visitors,
    | but it is only indexed and listed in the sitemap once it has at least
    | min_listings real (non-demo) published listings, so empty or thin
    | pages never reach search engines.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Investment intelligence (Phase 14)
    |--------------------------------------------------------------------------
    |
    | Markets, data types, factors and the proposed initial scoring
    | methodology. The weights and parameters here only seed methodology
    | version 1; admins manage later versions in the Data Centre, and every
    | ranking stores the version it was calculated with. Nothing here is
    | market data: rankings use verified observations only.
    |
    */

    'investment' => [
        // Pakistan is the only active data market. Others are configured, not live.
        'markets' => [
            'Pakistan' => ['code' => 'PK', 'currency' => 'PKR', 'units' => ['marla', 'kanal', 'sqft', 'sqyd'], 'status' => 'active',
                'legal_checks' => 'Development authority approval / NOC (LDA, CDA, KDA, RDA, DHA and others); registry and mutation'],
            'United Arab Emirates' => ['code' => 'AE', 'currency' => 'AED', 'units' => ['sqft'], 'status' => 'planned', 'legal_checks' => 'RERA / DLD registration'],
            'Saudi Arabia' => ['code' => 'SA', 'currency' => 'SAR', 'units' => ['sqm'], 'status' => 'planned', 'legal_checks' => 'REGA registration'],
            'Qatar' => ['code' => 'QA', 'currency' => 'QAR', 'units' => ['sqm'], 'status' => 'planned', 'legal_checks' => 'Ministry of Justice registration'],
            'United Kingdom' => ['code' => 'GB', 'currency' => 'GBP', 'units' => ['sqft', 'sqm'], 'status' => 'planned', 'legal_checks' => 'HM Land Registry'],
        ],

        'source_types' => [
            'verified_transactions' => 'Verified transactions',
            'public_asking_prices' => 'Public asking prices',
            'authorized_report' => 'Authorized market report',
            'official_record' => 'Official development / approval record',
            'verified_rental' => 'Verified rental observations',
            'admin_research' => 'Meezan Property research',
            // Investment Index evidence sources.
            'site_inspection' => 'Independent site inspection',
            'audited_document' => 'Audited / certified document (rent roll, safety certificate, accounts)',
            'licensed_data' => 'Licensed data provider',
            'owner_document' => 'Owner or manager document',
            'developer_website' => 'Developer or project website (developer-reported)',
            'secondary_reference' => 'Secondary public reference (unverified)',
            'demo' => 'Illustrative data (not real)',
        ],

        /*
        | Demonstration data. Demo projects and market records (is_demo) are
        | ranked in their own calculation, which never replaces the real one.
        | Off by default: public rankings show real verified data only, and the
        | Meezan Investment Index never reads demo records at all. Admins can
        | switch it on for a private preview in Market Data Centre → Demo Data.
        */
        'demo_mode' => (bool) env('INVESTMENT_DEMO_MODE', false),
        'demo_notice' => 'DEMO DATA — Illustrative figures for platform preview only. Prices, rental yields, investment scores and forecasts are not verified market information.',
        'reliability' => ['high' => 1.0, 'medium' => 0.6, 'low' => 0.3],

        'price_bases' => ['fbr_tax' => 'FBR tax valuation (not a market price)', 'transaction' => 'Verified transaction', 'asking' => 'Asking (advertised)', 'valuation' => 'Professional valuation'],

        // Indicator levels and what each level scores (0-100, higher = better for the investor).
        'indicators' => [
            'demand' => ['label' => 'Demand', 'levels' => ['very_high' => 100, 'high' => 80, 'medium' => 55, 'low' => 25]],
            'supply' => ['label' => 'Supply', 'levels' => ['limited' => 90, 'balanced' => 65, 'high' => 30]],
            'liquidity' => ['label' => 'Liquidity', 'levels' => ['high' => 90, 'medium' => 60, 'low' => 25]],
            'infrastructure' => ['label' => 'Location & infrastructure', 'levels' => ['excellent' => 95, 'good' => 75, 'fair' => 50, 'poor' => 20]],
            'development_progress' => ['label' => 'Development progress (%)', 'numeric' => true],
            'legal_verification' => ['label' => 'Legal verification', 'levels' => ['verified' => 100, 'partially_verified' => 55, 'unverified' => 10, 'disputed' => 0]],
            'developer_status' => ['label' => 'Developer / project status', 'levels' => ['completed' => 95, 'on_track' => 75, 'delayed' => 35, 'stalled' => 5]],
            'growth_drivers' => ['label' => 'Future growth indicators', 'levels' => ['strong' => 90, 'moderate' => 60, 'weak' => 30]],
            // Shown on commercial dashboards only; not a scoring factor.
            'occupancy' => ['label' => 'Occupancy (%)', 'numeric' => true],
        ],

        'factors' => [
            'price_performance' => 'Historical price performance',
            'demand_supply' => 'Demand and supply',
            'rental_yield' => 'Rental yield',
            'location_infrastructure' => 'Location and infrastructure',
            'development_progress' => 'Development progress',
            'liquidity' => 'Market liquidity',
            'risk' => 'Investment risk (lower risk scores higher)',
            'growth' => 'Future growth indicators',
        ],

        'categories' => [
            'overall' => ['label' => 'Overall Investment', 'requires' => []],
            'appreciation' => ['label' => 'Best Capital Appreciation', 'requires' => ['price_performance']],
            'rental' => ['label' => 'Best Rental Investment', 'requires' => ['rental_yield']],
            'low_risk' => ['label' => 'Best Low-Risk Investment', 'requires' => ['risk']],
            'emerging' => ['label' => 'Best Emerging Investment', 'requires' => ['development_progress'], 'emerging_only' => true],
            'commercial' => ['label' => 'Best Commercial Investment', 'requires' => [], 'asset_class' => 'commercial'],
            'residential' => ['label' => 'Best Residential Investment', 'requires' => [], 'asset_class' => 'residential'],
        ],

        /*
        | Investment Rankings by property type. A project's category says what
        | it is; a segment is one ranking view (city → group → segment). Each
        | segment ranks only the entities and property types that belong to it,
        | with the weights of an existing methodology category ("weights"), so
        | there is one scoring system and admins change weights in one place.
        |   entities       which snapshot entities can appear (area, project)
        |   area_types     areas that count for the segment (null = any area)
        |   project_types  project categories that belong to the segment
        |   types          property types whose observations are used (null = all)
        |   group_by       size | bedrooms: one row per comparable size or bedroom count
        |   columns        ranking table columns, in order
        */
        'project_categories' => [
            'residential_society' => ['label' => 'Residential society / housing scheme', 'asset_class' => 'residential'],
            'apartment_complex' => ['label' => 'Apartment complex', 'asset_class' => 'residential'],
            'shopping_mall' => ['label' => 'Shopping mall', 'asset_class' => 'commercial'],
            'commercial_plaza' => ['label' => 'Commercial plaza', 'asset_class' => 'commercial'],
            'business_tower' => ['label' => 'Business tower', 'asset_class' => 'commercial'],
            'office_building' => ['label' => 'Office building / business centre', 'asset_class' => 'commercial'],
            'commercial_market' => ['label' => 'Market / commercial shops', 'asset_class' => 'commercial'],
            'commercial_land' => ['label' => 'Commercial land / business district plots', 'asset_class' => 'commercial'],
            'mixed_use' => ['label' => 'Mixed-use development', 'asset_class' => 'mixed'],
        ],

        'groups' => [
            'residential' => ['label' => 'Residential'],
            'commercial' => ['label' => 'Commercial'],
            'mixed-use' => ['label' => 'Mixed Use'],
        ],

        'segments' => [
            'societies' => ['group' => 'residential', 'label' => 'Residential Societies', 'singular' => 'Society', 'asset_class' => 'residential', 'entities' => ['area', 'project'],
                'area_types' => ['housing_scheme', 'cooperative_society', 'government_scheme', 'defence_scheme', 'cantonment', 'phase', 'sector', 'block', 'neighbourhood'],
                'project_types' => ['residential_society'], 'types' => null,
                'weights' => 'residential', 'columns' => ['price', 'change_1y', 'change_2y', 'yield', 'score', 'risk']],
            'plots' => ['group' => 'residential', 'label' => 'Residential Plots', 'singular' => 'Plot', 'asset_class' => 'residential', 'entities' => ['area', 'project'],
                'project_types' => ['residential_society'], 'types' => ['residential_plot', 'plot_file'], 'group_by' => 'size',
                'sizes' => ['3 Marla', '5 Marla', '7 Marla', '10 Marla', '1 Kanal', '2 Kanal'],
                'weights' => 'appreciation', 'columns' => ['size', 'price', 'change_1y', 'change_2y', 'score', 'development']],
            'houses' => ['group' => 'residential', 'label' => 'Houses', 'singular' => 'House', 'asset_class' => 'residential', 'entities' => ['area', 'project'],
                'project_types' => ['residential_society'], 'types' => ['house'], 'group_by' => 'size', 'sizes' => ['3 Marla', '5 Marla', '10 Marla', '1 Kanal'],
                'weights' => 'residential', 'columns' => ['size', 'price', 'change_1y', 'yield', 'score', 'risk']],
            'apartments' => ['group' => 'residential', 'label' => 'Apartments', 'singular' => 'Apartment', 'asset_class' => 'residential', 'entities' => ['area', 'project'],
                'project_types' => ['apartment_complex', 'mixed_use'], 'types' => ['apartment', 'penthouse'], 'group_by' => 'bedrooms',
                'bedrooms' => ['0' => 'Studio', '1' => '1 Bedroom', '2' => '2 Bedroom', '3' => '3 Bedroom', '4' => '4 Bedroom'],
                'weights' => 'rental', 'columns' => ['size', 'price', 'change_1y', 'yield', 'score', 'status']],
            'shopping-malls' => ['group' => 'commercial', 'label' => 'Shopping Malls', 'singular' => 'Mall', 'asset_class' => 'commercial', 'entities' => ['project'],
                'project_types' => ['shopping_mall'], 'types' => ['shop', 'office'],
                'weights' => 'rental', 'columns' => ['price_sqft', 'yield', 'score', 'occupancy', 'status']],
            'commercial-plazas' => ['group' => 'commercial', 'label' => 'Commercial Plazas', 'singular' => 'Plaza', 'asset_class' => 'commercial', 'entities' => ['project'],
                'project_types' => ['commercial_plaza'], 'types' => ['shop', 'office', 'building'],
                'weights' => 'commercial', 'columns' => ['price_sqft', 'change_1y', 'yield', 'score', 'occupancy', 'status']],
            'business-towers' => ['group' => 'commercial', 'label' => 'Business Towers', 'singular' => 'Tower', 'asset_class' => 'commercial', 'entities' => ['project'],
                'project_types' => ['business_tower', 'office_building'], 'types' => ['office', 'building'],
                'weights' => 'commercial', 'columns' => ['price_sqft', 'change_1y', 'yield', 'score', 'occupancy', 'status']],
            'office-spaces' => ['group' => 'commercial', 'label' => 'Office Spaces', 'singular' => 'Office', 'asset_class' => 'commercial', 'entities' => ['area', 'project'],
                'project_types' => ['business_tower', 'office_building', 'commercial_plaza', 'mixed_use'], 'types' => ['office'], 'group_by' => 'size',
                'weights' => 'rental', 'columns' => ['size', 'price', 'change_1y', 'yield', 'score', 'risk']],
            'commercial-shops' => ['group' => 'commercial', 'label' => 'Commercial Shops', 'singular' => 'Shop', 'asset_class' => 'commercial', 'entities' => ['area', 'project'],
                'project_types' => ['shopping_mall', 'commercial_plaza', 'commercial_market', 'mixed_use'], 'types' => ['shop'], 'group_by' => 'size',
                'weights' => 'rental', 'columns' => ['size', 'price', 'change_1y', 'yield', 'score', 'risk']],
            'commercial-plots' => ['group' => 'commercial', 'label' => 'Commercial Plots', 'singular' => 'Plot', 'asset_class' => 'commercial', 'entities' => ['area', 'project'],
                'project_types' => ['commercial_land'], 'types' => ['commercial_plot'], 'group_by' => 'size', 'sizes' => ['5 Marla', '8 Marla', '1 Kanal', '2 Kanal'],
                'weights' => 'appreciation', 'columns' => ['size', 'price', 'change_1y', 'change_2y', 'score', 'development']],
            'mixed-use' => ['group' => 'mixed-use', 'label' => 'Mixed-Use Developments', 'singular' => 'Development', 'asset_class' => 'mixed', 'entities' => ['project'],
                'project_types' => ['mixed_use'], 'types' => null,
                'weights' => 'overall', 'columns' => ['price_sqft', 'change_1y', 'yield', 'score', 'occupancy', 'status']],
        ],

        // Methodology version 1: the proposed initial weights (each category sums to 100).
        'default_weights' => [
            'overall' => ['price_performance' => 20, 'demand_supply' => 12, 'rental_yield' => 12, 'location_infrastructure' => 12, 'development_progress' => 10, 'liquidity' => 10, 'risk' => 14, 'growth' => 10],
            'appreciation' => ['price_performance' => 40, 'demand_supply' => 15, 'rental_yield' => 0, 'location_infrastructure' => 10, 'development_progress' => 5, 'liquidity' => 10, 'risk' => 5, 'growth' => 15],
            'rental' => ['price_performance' => 5, 'demand_supply' => 15, 'rental_yield' => 45, 'location_infrastructure' => 10, 'development_progress' => 5, 'liquidity' => 10, 'risk' => 10, 'growth' => 0],
            'low_risk' => ['price_performance' => 10, 'demand_supply' => 10, 'rental_yield' => 10, 'location_infrastructure' => 10, 'development_progress' => 5, 'liquidity' => 20, 'risk' => 35, 'growth' => 0],
            'emerging' => ['price_performance' => 5, 'demand_supply' => 15, 'rental_yield' => 0, 'location_infrastructure' => 20, 'development_progress' => 25, 'liquidity' => 5, 'risk' => 10, 'growth' => 20],
            'commercial' => ['price_performance' => 20, 'demand_supply' => 12, 'rental_yield' => 12, 'location_infrastructure' => 12, 'development_progress' => 10, 'liquidity' => 10, 'risk' => 14, 'growth' => 10],
            'residential' => ['price_performance' => 20, 'demand_supply' => 12, 'rental_yield' => 12, 'location_infrastructure' => 12, 'development_progress' => 10, 'liquidity' => 10, 'risk' => 14, 'growth' => 10],
        ],

        'default_parameters' => [
            // Annualised price change (%) mapped linearly onto 0-100 (clamped).
            'price_change_range' => [-10, 25],
            // Estimated gross rental yield (%) mapped onto 0-100 (clamped).
            'yield_range' => [2, 10],
            // A score needs data for at least this share of the category's weight...
            'min_coverage' => 0.7,
            // ...and loses up to this share of its value for the weight that is missing.
            'coverage_penalty' => 0.15,
            // Comparable windows for one- and two-year changes (months before the latest observation).
            'one_year_window' => [9, 15],
            'two_year_window' => [21, 27],
            'five_year_window' => [54, 66],
            // Rent and sale observations must be this close in time to compute a yield.
            'yield_max_gap_months' => 12,
            // Growth scenarios need this much history and this many observations.
            'scenario_min_months' => 24,
            'scenario_min_observations' => 3,
            'scenario_years' => 3,
            // Confidence: component weights (sum 100) and level thresholds.
            'confidence_weights' => ['observations' => 20, 'recency' => 20, 'source_quality' => 20, 'independence' => 15, 'consistency' => 10, 'coverage' => 15],
            'confidence_full_observations' => 10,
            'confidence_full_sources' => 3,
            'confidence_levels' => ['high' => 75, 'medium' => 50, 'limited' => 25],
            // Risk: minimum evidence and level thresholds (0-100, higher = riskier).
            'risk_min_components' => 3,
            'risk_levels' => ['low' => 35, 'medium' => 60],
        ],

        // "rules" (default) explains rankings from the snapshot data; "llm" rephrases that
        // explanation with the Gemini model (grounding-checked: no figure the data lacks).
        'ai_driver' => env('MEEZAN_INVESTMENT_AI', 'rules'),

        'disclaimer' => 'Meezan Property rankings and investment insights are informational tools and do not constitute financial, legal or investment advice. Property investments involve risk. Historical performance does not guarantee future returns.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Central AI Brain & agents (Phase 15)
    |--------------------------------------------------------------------------
    |
    | One provider layer for every agent: Google Gemini. Agents always work
    | from tools that read the real database; by default the model only
    | classifies requests and words the summary. An agent switched to
    | "model tool calls" may also call its own permitted tools through Gemini
    | function calling, within the limits below. Without an API key the agents
    | run in "rules" mode and say so. Each workload tier can use its own model.
    |
    */

    'agents' => [
        'provider' => env('MEEZAN_AI_PROVIDER', 'gemini'),
        // Registered providers (key => adapter class). Gemini only; another provider is added here.
        'adapters' => [
            'gemini' => GeminiAdapter::class,
        ],
        // A model per workload tier; empty means GEMINI_MODEL (or the model saved in Gemini Settings).
        'models' => [
            'routine' => env('GEMINI_MODEL_ROUTINE'),
            'extraction' => env('GEMINI_MODEL_EXTRACTION'),
            'support' => env('GEMINI_MODEL_SUPPORT'),
            'research' => env('GEMINI_MODEL_RESEARCH'),
            'reasoning' => env('GEMINI_MODEL_REASONING'),
        ],
        // Only meaningful with a second provider registered; it would send the same data to another company.
        'fallback_allowed' => (bool) env('MEEZAN_AI_FALLBACK', false),
        // Phone numbers, e-mail addresses, CNIC and card numbers are masked before text is sent to the provider.
        'redact_personal_data' => (bool) env('MEEZAN_AI_REDACT_PERSONAL_DATA', true),
        // Gemini function calling for agents switched to "model tool calls": model turns per task.
        'max_model_tool_rounds' => (int) env('MEEZAN_AGENT_MAX_MODEL_TOOL_ROUNDS', 4),
        // Use the model to classify requests and word summaries when a key is set.
        'use_llm' => (bool) env('MEEZAN_AGENTS_USE_LLM', true),
        'limits' => [
            'max_tool_calls' => (int) env('MEEZAN_AGENT_MAX_TOOL_CALLS', 25),
            'max_depth' => (int) env('MEEZAN_AGENT_MAX_DEPTH', 2),
            'max_subtasks' => (int) env('MEEZAN_AGENT_MAX_SUBTASKS', 8),
            'max_attempts' => (int) env('MEEZAN_AGENT_MAX_ATTEMPTS', 2),
            'max_seconds' => (int) env('MEEZAN_AGENT_MAX_SECONDS', 60),
            'max_tokens_per_task' => (int) env('MEEZAN_AGENT_MAX_TOKENS_PER_TASK', 8000),
            'daily_tokens_per_agent' => (int) env('MEEZAN_AGENT_DAILY_TOKENS', 200000),
            'daily_tokens_total' => (int) env('MEEZAN_AGENTS_DAILY_TOKENS', 1000000),
            // Cost budgets apply only when model pricing is configured in the Command Center.
            'daily_cost_total_usd' => env('MEEZAN_AGENTS_DAILY_COST_USD') !== null ? (float) env('MEEZAN_AGENTS_DAILY_COST_USD') : null,
            'approval_expiry_days' => 14,
        ],
        // Event-driven tasks (new listing, dealer, lead…), queued after commit.
        'events' => (bool) env('MEEZAN_AGENT_EVENTS', true),
        'queue' => env('MEEZAN_AGENT_QUEUE', 'agents'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Place photos (home and city page heroes)
    |--------------------------------------------------------------------------
    |
    | Landmark photos from Wikimedia Commons under Creative Commons licences,
    | resized to 1920 px (public/images/places). The licences require the
    | credit shown on each hero; the resized copies keep the same licence.
    | "position" is the CSS object-position that keeps the landmark in frame.
    |
    */

    'place_photos' => [
        'home' => ['file' => 'images/places/home.jpg', 'alt' => 'Minar-e-Pakistan in Iqbal Park, Lahore', 'position' => 'center 40%',
            'author' => 'Guilhem Vellut', 'license' => 'CC BY-SA 2.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/2.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Minar-e-Pakistan.jpg'],
        'Lahore' => ['file' => 'images/places/lahore.jpg', 'alt' => 'Badshahi Mosque, Lahore', 'position' => 'center 35%',
            'author' => 'Zapata1000', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Badshahi_mosque_of_Lahore,_Pakistan.jpg'],
        'Karachi' => ['file' => 'images/places/karachi.jpg', 'alt' => 'Mazar-e-Quaid, the mausoleum of Quaid-e-Azam Muhammad Ali Jinnah, Karachi', 'position' => 'center 45%',
            'author' => 'Jan Kranendonk', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Mazar-e-Quaid,_or_Jinnah_Mausoleum_in_Karachi,_Pakistan.jpg'],
        'Islamabad' => ['file' => 'images/places/islamabad.jpg', 'alt' => 'Faisal Mosque below the Margalla Hills, Islamabad', 'position' => 'center 75%',
            'author' => 'Ali Mujtaba', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:ALI_MUJTABA_WLM2015_FAISAL_MOSQUE_DSC_1542_m.jpg'],
        'Multan' => ['file' => 'images/places/multan.jpg', 'alt' => 'Tomb of Shah Rukn-e-Alam, Multan', 'position' => 'center 30%',
            'author' => 'Rana Abdul Qudoos', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Tomb_Shah_Rukn-e-Alam_-_remote_view.jpg'],
        'Faisalabad' => ['file' => 'images/places/faisalabad.jpg', 'alt' => 'Clock Tower (Ghanta Ghar) at night, Faisalabad', 'position' => 'center 40%',
            'author' => 'Usman Nadeem', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Clock_Tower_Faisalabad_by_Usman_Nadeem.jpg'],
        'Quetta' => ['file' => 'images/places/quetta.jpg', 'alt' => 'Hanna Lake and the hills near Quetta', 'position' => 'center 55%',
            'author' => 'Maham Saadat', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Hanna_Lake,_Quetta.jpg'],
        'Peshawar' => ['file' => 'images/places/peshawar.jpg', 'alt' => 'Islamia College, Peshawar', 'position' => 'center 50%',
            'author' => 'Zafarmaini', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Islamia_College_Peshawar_(Public_Sector_University),_Khyber_Pakhtunkhwa,_Pakistan_cropped.jpg'],
    ],

    'seo' => [
        'min_listings' => (int) env('MEEZAN_SEO_MIN_LISTINGS', 3),
        'categories' => [
            'property-for-sale' => ['label' => 'Property for Sale', 'purpose' => 'sale', 'group' => null, 'types' => null],
            'property-for-rent' => ['label' => 'Property for Rent', 'purpose' => 'rent', 'group' => null, 'types' => null],
            'houses-for-sale' => ['label' => 'Houses for Sale', 'purpose' => 'sale', 'group' => null, 'types' => ['house']],
            'houses-for-rent' => ['label' => 'Houses for Rent', 'purpose' => 'rent', 'group' => null, 'types' => ['house', 'upper_portion', 'lower_portion']],
            'flats-for-sale' => ['label' => 'Flats for Sale', 'purpose' => 'sale', 'group' => null, 'types' => ['apartment', 'penthouse']],
            'flats-for-rent' => ['label' => 'Flats for Rent', 'purpose' => 'rent', 'group' => null, 'types' => ['apartment', 'penthouse']],
            'plots-for-sale' => ['label' => 'Plots for Sale', 'purpose' => 'sale', 'group' => 'plots', 'types' => null],
            'commercial-for-sale' => ['label' => 'Commercial Property for Sale', 'purpose' => 'sale', 'group' => 'commercial', 'types' => null],
            'commercial-for-rent' => ['label' => 'Commercial Property for Rent', 'purpose' => 'rent', 'group' => 'commercial', 'types' => null],
        ],
        // Shown when a page has no image of its own (absolute URL built with asset()).
        'default_image' => 'images/ref/hero-home.jpg',
        // Staging/test copies of the site: send "X-Robots-Tag: noindex, nofollow" on every
        // response so search engines never index them. Never true on the live site.
        'noindex_site' => (bool) env('MEEZAN_SEO_NOINDEX_SITE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dealer CRM
    |--------------------------------------------------------------------------
    |
    | Reasons, reminder timing and the "needs attention" rule for the dealer
    | CRM (meezan:crm-reminders runs every five minutes). Leads are never
    | closed automatically.
    |
    */

    'crm' => [
        'lost_reasons' => [
            'price_mismatch' => 'Price mismatch',
            'location_mismatch' => 'Location mismatch',
            'changed_mind' => 'Buyer changed mind',
            'no_response' => 'No response',
            'bought_elsewhere' => 'Purchased or rented elsewhere',
            'invalid_requirement' => 'Invalid requirement',
            'other' => 'Other',
        ],
        'follow_up_types' => ['call' => 'Call', 'whatsapp' => 'WhatsApp', 'meeting' => 'Meeting', 'general' => 'Reminder'],
        'appointment_types' => ['viewing' => 'Property viewing', 'office' => 'Office meeting', 'phone' => 'Phone consultation', 'other' => 'Other'],
        'priorities' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'],
        // A pending follow-up is reported overdue this long after its due time.
        'overdue_after_minutes' => 60,
        // Dealers are reminded this long before an appointment.
        'appointment_reminder_minutes' => 60,
        // An open lead with no dealer activity for this many days is flagged for attention (never closed).
        'stale_after_days' => (int) env('MEEZAN_CRM_STALE_DAYS', 3),
        // Rates are shown only once a dealer has at least this many leads.
        'min_leads_for_rates' => 5,
        'kanban_per_column' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp channel
    |--------------------------------------------------------------------------
    |
    | Meta credentials live in config/services.php ("whatsapp") and .env only.
    | WHATSAPP_MOCK_MODE=true records messages without sending them, and false
    | uses Meta. When it is not set, MEEZAN_WHATSAPP_DRIVER decides.
    | driver: "meta" (WhatsApp Cloud API; sends only when WHATSAPP_ENABLED is
    | true and credentials are set) or "mock" (records messages as tests,
    | sends nothing; only works when APP_ENV is local or testing).
    | Automation, auto-reply and test mode are switched in Admin → WhatsApp.
    |
    */

    'whatsapp' => [
        'driver' => in_array(env('WHATSAPP_MOCK_MODE'), [null, ''], true) ? env('MEEZAN_WHATSAPP_DRIVER', 'meta') : (env('WHATSAPP_MOCK_MODE') ? 'mock' : 'meta'),
        // Queue for inbound processing and sending (run: php artisan queue:work --queue=whatsapp,default).
        'queue' => env('MEEZAN_WHATSAPP_QUEUE', 'whatsapp'),
        // Reply buttons (View/Change search/Talk to dealer). Off until tested with a live number.
        'interactive_buttons' => (bool) env('MEEZAN_WHATSAPP_INTERACTIVE', false),
        // Meta's customer-service window: free-form messages only within this many hours of the contact's last message.
        'service_window_hours' => 24,
        // Abuse protection. Messages above these limits are kept, not answered automatically.
        'max_inbound_per_minute' => (int) env('MEEZAN_WHATSAPP_MAX_INBOUND_PER_MINUTE', 10),
        'duplicate_window_seconds' => 60,
        'max_outbound_per_recipient_per_hour' => (int) env('MEEZAN_WHATSAPP_MAX_OUTBOUND_PER_HOUR', 15),
        // Sending attempts for temporary provider/network errors.
        'max_send_attempts' => (int) env('WHATSAPP_MAX_RETRIES', 3),
        // Larger webhook bodies are refused before any processing (Meta's own limit is 3 MB).
        'max_webhook_bytes' => 3 * 1024 * 1024,
        // Meta's sample template, used only by the owner's connection test.
        'test_template' => ['name' => 'hello_world', 'language' => 'en_US'],
        // Approved message templates, needed outside the service window (e.g. dealer lead alerts).
        // Create and get them approved in WhatsApp Manager first; see the Phase 10 report.
        'templates' => [
            'dealer_lead' => [
                'name' => env('MEEZAN_WHATSAPP_TEMPLATE_DEALER_LEAD'),
                'language' => env('MEEZAN_WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
            ],
        ],
        // Promotional (marketing) messages are not sent by this version.
        'promotional_enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing: packages, subscriptions and payments
    |--------------------------------------------------------------------------
    |
    | Packages, prices and limits live in the database (Admin → Packages).
    | This section holds only the rules around them. Payment credentials
    | come from the environment and are never sent to the browser.
    |
    */

    'billing' => [
        'currency' => 'PKR',
        'invoice_prefix' => env('MEEZAN_INVOICE_PREFIX', 'MP'),
        'invoice_due_days' => 7,
        // Unpaid checkouts are cancelled after this many days (the invoice is kept, marked cancelled).
        'checkout_expiry_days' => 14,
        // Renewal invoices are issued, and the dealer reminded, this many days before the period ends.
        'renewal_notice_days' => 7,
        // Days after the period ends that an unpaid renewal keeps the package (past due) before it expires.
        'grace_days' => 3,

        // Listing statuses that use up the listing allowance. Drafts and
        // listings that were rejected, need changes, expired, were archived
        // or suspended do not count.
        'counted_listing_statuses' => ['published', 'pending'],
        // Lead sources that use up the monthly lead allowance. Buyer
        // requirements are shared by Meezan Property; enquiries on a
        // dealer's own listing are always delivered and are not counted.
        'counted_lead_sources' => ['requirement'],

        // Limits for a dealer without a subscription when no package is set
        // as the default plan (null = no limit). Once an admin marks a
        // package as the default, its limits apply instead.
        'fallback_limits' => ['listings' => null, 'leads' => null, 'featured' => 0],

        // A dealer over a smaller package's limits (after a downgrade or
        // expiry): "keep_live" leaves existing listings live and blocks new
        // ones until back under the limit; "require_within_limit" refuses a
        // downgrade until the dealer has archived enough listings.
        'over_limit_policy' => env('MEEZAN_OVER_LIMIT_POLICY', 'keep_live'),

        // When every suitable dealer has used their lead allowance: "queue"
        // holds the lead for staff (never lost); "allow_over_limit" still
        // assigns it to the best-ranked dealers.
        'lead_overflow' => env('MEEZAN_LEAD_OVERFLOW', 'queue'),

        // Feature flags a package can switch on. Enforced: priority_leads
        // (ranking boost) and featured_listings (with the featured limit).
        // The others are recorded on packages for future use.
        'features' => [
            'priority_leads' => 'Priority in lead matching',
            'featured_listings' => 'Featured listings',
            'advanced_analytics' => 'Advanced analytics',
            'ai_assistant' => 'AI property assistant',
            'api_access' => 'API access',
            'priority_support' => 'Priority support',
        ],
        'priority_lead_boost' => 15,

        // Payment gateways. Selection is by configuration; only available
        // gateways are offered to dealers.
        'gateways' => [
            // Bank transfer reviewed by staff. Offered only when the account details are set.
            'manual' => [
                'enabled' => (bool) env('MEEZAN_MANUAL_PAYMENTS', false),
                'bank_name' => env('MEEZAN_BANK_NAME'),
                'account_title' => env('MEEZAN_BANK_ACCOUNT_TITLE'),
                'account_number' => env('MEEZAN_BANK_ACCOUNT_NUMBER'),
                'iban' => env('MEEZAN_BANK_IBAN'),
                'max_proof_kb' => 5120,
            ],
            // Test-only gateway: no money moves. Must be switched on explicitly,
            // and only ever works when APP_ENV is local or testing.
            'mock' => [
                'enabled' => (bool) env('MEEZAN_MOCK_PAYMENTS', false),
                'webhook_secret' => env('MEEZAN_MOCK_WEBHOOK_SECRET'),
            ],
        ],
    ],

];
