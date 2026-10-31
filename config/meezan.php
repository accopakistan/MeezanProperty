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

];
