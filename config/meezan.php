<?php

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
    | Only verified, company-owned contact details belong here. When the
    | WhatsApp number is empty the site hides WhatsApp call-to-actions.
    |
    */

    'contact' => [
        'email' => env('MEEZAN_CONTACT_EMAIL'),
        'phone' => env('MEEZAN_CONTACT_PHONE'),
        'whatsapp' => env('MEEZAN_WHATSAPP_NUMBER'),
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
        // Behind a proxy or CDN (e.g. Cloudflare): "*" or a comma-separated
        // list, so HTTPS and client IPs are detected correctly. Empty: none.
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
    | The map uses Leaflet with a configurable tile server. The default is
    | OpenStreetMap, whose usage policy suits development and light traffic;
    | configure a commercial tile provider before high-traffic launch.
    |
    */

    'map' => [
        'enabled' => (bool) env('MEEZAN_MAP_ENABLED', true),
        'tile_url' => env('MEEZAN_MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MEEZAN_MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
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
    | "rules" is the built-in deterministic parser. "llm" adds an
    | OpenAI-compatible model (config/services.php "openai") for parsing only;
    | matching always runs against the database.
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
    | driver: "meta" (WhatsApp Cloud API; sends only when WHATSAPP_SEND_ENABLED
    | is true and credentials are set) or "mock" (records messages as tests,
    | sends nothing; only works when APP_ENV is local or testing).
    | Automation, auto-reply and test mode are switched in Admin → WhatsApp.
    |
    */

    'whatsapp' => [
        'driver' => env('MEEZAN_WHATSAPP_DRIVER', 'meta'),
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
        'max_send_attempts' => 3,
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
