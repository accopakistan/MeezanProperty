<?php

/*
|--------------------------------------------------------------------------
| Meezan Investment Index
|--------------------------------------------------------------------------
| Ranks real estate DEVELOPMENTS only, in five categories, from verified,
| traceable evidence. Individual plots, houses, shops, apartments and
| listings stay in the marketplace and are never ranked here.
|
| Every score comes from verified records with a source URL and a
| collection time. Nothing is estimated, generated or filled in: a factor
| without verified evidence is "missing", and a project without enough of
| them shows "Insufficient Verified Data". Paid placement, featured flags
| and subscriptions are never read by the engine.
*/

return [

    /*
    | The five categories. project_categories: the Project::category values
    | that belong to it. factors: weight (sums to 100) and the label used
    | for that category. These are methodology version 1; admins change
    | weights in a new methodology version, never in place.
    */
    // Each public category leaderboard shows at most this many ranked projects (owner's rule, 6 Nov 2026).
    // Projects ranked below it stay ranked in admin and history, but no public page shows their position.
    'public_leaderboard_size' => 10,

    'categories' => [
        'housing-societies' => [
            'label' => 'Housing Societies', 'singular' => 'Housing society', 'leaderboard' => 'Top Housing Societies',
            'project_categories' => ['residential_society'],
            'factors' => [
                'legal_approval' => ['weight' => 25, 'label' => 'Legal approval'],
                'development_progress' => ['weight' => 20, 'label' => 'Development progress'],
                'occupancy' => ['weight' => 15, 'label' => 'Occupancy (residents)'],
                'market_performance' => ['weight' => 10, 'label' => 'Historical market performance'],
                'market_demand' => ['weight' => 10, 'label' => 'Market demand and liquidity'],
                'location' => ['weight' => 10, 'label' => 'Location'],
                'utilities' => ['weight' => 5, 'label' => 'Utilities'],
                'developer_track_record' => ['weight' => 5, 'label' => 'Developer track record'],
            ],
        ],
        'commercial-plazas' => [
            'label' => 'Commercial Plazas', 'singular' => 'Commercial plaza', 'leaderboard' => 'Top Commercial Plazas',
            'project_categories' => ['commercial_plaza'],
            'factors' => [
                'legal_approval' => ['weight' => 20, 'label' => 'Legal approval'],
                'rental_income' => ['weight' => 20, 'label' => 'Verified rental income'],
                'occupancy' => ['weight' => 15, 'label' => 'Shop occupancy'],
                'footfall' => ['weight' => 15, 'label' => 'Footfall and demand'],
                'location' => ['weight' => 10, 'label' => 'Location'],
                'tenant_quality' => ['weight' => 10, 'label' => 'Tenant quality'],
                'maintenance' => ['weight' => 5, 'label' => 'Building maintenance'],
                'market_performance' => ['weight' => 5, 'label' => 'Market performance'],
            ],
        ],
        'apartment-buildings' => [
            'label' => 'Apartment Buildings', 'singular' => 'Apartment building', 'leaderboard' => 'Top Apartment Buildings',
            'project_categories' => ['apartment_complex'],
            'factors' => [
                'legal_approval' => ['weight' => 20, 'label' => 'Legal approval'],
                'occupancy' => ['weight' => 15, 'label' => 'Occupancy'],
                'rental_income' => ['weight' => 15, 'label' => 'Rental income'],
                'structural_safety' => ['weight' => 15, 'label' => 'Construction and structural safety'],
                'developer_track_record' => ['weight' => 10, 'label' => 'Developer reputation'],
                'location' => ['weight' => 10, 'label' => 'Location'],
                'maintenance' => ['weight' => 5, 'label' => 'Maintenance'],
                'market_performance' => ['weight' => 5, 'label' => 'Historical price performance'],
                'market_demand' => ['weight' => 5, 'label' => 'Market demand'],
            ],
        ],
        'shopping-malls' => [
            'label' => 'Shopping Malls', 'singular' => 'Shopping mall', 'leaderboard' => 'Top Shopping Malls',
            'project_categories' => ['shopping_mall'],
            'factors' => [
                'legal_approval' => ['weight' => 20, 'label' => 'Legal approval'],
                'operational_performance' => ['weight' => 15, 'label' => 'Operational performance'],
                'occupancy' => ['weight' => 15, 'label' => 'Occupancy'],
                'rental_income' => ['weight' => 15, 'label' => 'Verified rental income'],
                'footfall' => ['weight' => 10, 'label' => 'Footfall'],
                'tenant_quality' => ['weight' => 10, 'label' => 'Tenant mix'],
                'location' => ['weight' => 5, 'label' => 'Location'],
                'structural_safety' => ['weight' => 5, 'label' => 'Building safety'],
                'financial_stability' => ['weight' => 5, 'label' => 'Financial stability'],
            ],
        ],
        'business-towers' => [
            'label' => 'Business Towers', 'singular' => 'Business tower', 'leaderboard' => 'Top Business Towers',
            'project_categories' => ['business_tower', 'office_building'],
            'factors' => [
                'legal_approval' => ['weight' => 20, 'label' => 'Legal approval'],
                'occupancy' => ['weight' => 15, 'label' => 'Office occupancy'],
                'rental_income' => ['weight' => 15, 'label' => 'Rental income'],
                'tenant_quality' => ['weight' => 15, 'label' => 'Corporate tenant quality'],
                'location' => ['weight' => 10, 'label' => 'Location'],
                'structural_safety' => ['weight' => 10, 'label' => 'Structural safety'],
                'developer_track_record' => ['weight' => 5, 'label' => 'Developer reputation'],
                'maintenance' => ['weight' => 5, 'label' => 'Building management'],
                'market_demand' => ['weight' => 5, 'label' => 'Market demand'],
            ],
        ],
    ],

    /*
    | How each factor is scored (0-100). type:
    |   legal      from verified legal records (see legal below)
    |   percent    a verified percentage, used as the score
    |   yield      a verified gross rental yield (%), mapped over yield_range
    |   level      a verified level, scored by its table
    |   history    annualised change in verified transaction/valuation prices
    |   developer  the developer's delivery record on its other projects
    | sources: market_sources.type values whose verified records count.
    */
    'factors' => [
        'legal_approval' => ['type' => 'legal', 'sources' => ['official_record']],
        'development_progress' => ['type' => 'percent', 'sources' => ['official_record', 'site_inspection', 'audited_document', 'owner_document']],
        'occupancy' => ['type' => 'percent', 'sources' => ['official_record', 'site_inspection', 'audited_document', 'licensed_data', 'owner_document']],
        'rental_income' => ['type' => 'yield', 'sources' => ['audited_document', 'verified_rental', 'licensed_data', 'official_record', 'owner_document']],
        'market_performance' => ['type' => 'history'],
        'market_demand' => ['type' => 'level', 'sources' => ['verified_transactions', 'licensed_data', 'authorized_report', 'official_record'],
            'levels' => ['very_high' => 100, 'high' => 80, 'medium' => 55, 'low' => 25]],
        'location' => ['type' => 'level', 'sources' => ['official_record', 'site_inspection', 'licensed_data', 'authorized_report'],
            'levels' => ['excellent' => 100, 'good' => 75, 'fair' => 50, 'poor' => 20]],
        // One record per utility (subject: electricity, gas, water, sewerage); the score is their average.
        'utilities' => ['type' => 'level', 'per_subject' => true, 'sources' => ['official_record', 'site_inspection', 'owner_document'],
            'levels' => ['connected' => 100, 'partial' => 50, 'not_available' => 0]],
        'developer_track_record' => ['type' => 'developer'],
        'footfall' => ['type' => 'level', 'sources' => ['audited_document', 'licensed_data', 'site_inspection', 'owner_document'],
            'levels' => ['very_high' => 100, 'high' => 80, 'medium' => 55, 'low' => 25]],
        'tenant_quality' => ['type' => 'level', 'sources' => ['audited_document', 'licensed_data', 'site_inspection', 'owner_document'],
            'levels' => ['excellent' => 100, 'good' => 75, 'fair' => 50, 'poor' => 20]],
        'maintenance' => ['type' => 'level', 'sources' => ['site_inspection', 'audited_document', 'official_record', 'owner_document'],
            'levels' => ['excellent' => 100, 'good' => 75, 'fair' => 50, 'poor' => 20]],
        'structural_safety' => ['type' => 'level', 'sources' => ['official_record', 'audited_document', 'site_inspection'],
            'levels' => ['certified' => 100, 'compliant' => 80, 'minor_deficiencies' => 45, 'major_deficiencies' => 10, 'unsafe' => 0]],
        'operational_performance' => ['type' => 'level', 'sources' => ['audited_document', 'licensed_data', 'site_inspection', 'owner_document'],
            'levels' => ['strong' => 100, 'stable' => 70, 'weak' => 35, 'distressed' => 5]],
        'financial_stability' => ['type' => 'level', 'sources' => ['audited_document', 'official_record', 'licensed_data', 'owner_document'],
            'levels' => ['strong' => 100, 'adequate' => 70, 'weak' => 30, 'distressed' => 0]],
    ],

    /*
    | Evidence that is recorded and shown but not weighted: it feeds the
    | risk classification, the developer track record and the profile.
    */
    'context_evidence' => [
        'delivery_status' => ['sources' => ['official_record', 'site_inspection', 'audited_document', 'owner_document'],
            'levels' => ['delivered_on_time' => 100, 'delivered_late' => 60, 'on_track' => 75, 'delayed' => 35, 'stalled' => 10, 'abandoned' => 0]],
        'operational_status' => ['sources' => ['official_record', 'site_inspection', 'audited_document', 'licensed_data', 'owner_document'],
            'levels' => ['operational' => 'Operational', 'partially_operational' => 'Partially operational', 'under_construction' => 'Under construction',
                'planned' => 'Planned', 'stalled' => 'Stalled', 'closed' => 'Closed']],
    ],

    /*
    | Legal status from verified legal records (official sources only).
    | Statuses in `exclude` (or any record of severity "serious") remove the
    | project from every positive recommendation until a later verified
    | record clears it. Only statuses in `eligible` can be ranked; any other
    | verified status is scored but not ranked, and no verified record
    | means "Legal status not verified" — never "approved".
    */
    'legal' => [
        'record_types' => [
            'approval' => 'Approval / NOC', 'cancellation' => 'Cancellation', 'illegal_listing' => 'Official illegal / unapproved list',
            'court_case' => 'Court case', 'regulatory_warning' => 'Regulatory warning', 'restriction' => 'Regulatory restriction', 'regularisation' => 'Regularisation / clearance',
        ],
        'statuses' => [
            'approved' => 'Verified Approved', 'partially_approved' => 'Partially Approved', 'pending' => 'Approval Pending',
            'cancelled' => 'Cancelled', 'illegal' => 'Verified Not Approved', 'restricted' => 'Restricted',
            'disputed' => 'Disputed', 'warning' => 'Under Legal Review', 'cleared' => 'Cleared',
        ],
        'severities' => ['none' => 'None', 'minor' => 'Minor', 'material' => 'Material', 'serious' => 'Serious'],
        'scores' => ['approved' => 100, 'partially_approved' => 65, 'pending' => 20],
        'eligible' => ['approved', 'partially_approved'],
        'exclude' => ['cancelled', 'illegal', 'restricted'],
        // Deducted from the legal score per open record of this severity.
        'deductions' => ['minor' => 10, 'material' => 30],
    ],

    /*
    | How much a verified record of each source type is trusted (0-1), for
    | the Data Confidence Score. Asking prices and demo records never count.
    */
    'source_authority' => [
        'official_record' => 1.0, 'audited_document' => 0.9, 'verified_transactions' => 0.9, 'licensed_data' => 0.8,
        'site_inspection' => 0.75, 'verified_rental' => 0.75, 'authorized_report' => 0.7, 'owner_document' => 0.6, 'admin_research' => 0.4,
    ],

    /*
    | Provisional Research Ranking: a separate list, not a lower bar for the real
    | one. It uses the same scoring, but also counts records staff have not yet
    | checked, so it is always labelled provisional. Visitors see it only after
    | the owner switches it on (Admin -> Investment Index -> Research queue).
    | It needs a legal status that is not adverse and on an authority's list,
    | at least two scored factors, and this share of the category's weight.
    */
    'provisional' => [
        'min_coverage' => 0.35,
        'min_factors' => 2,
    ],

    'parameters' => [
        // A project is ranked only with verified evidence for this share of its category's weight...
        'min_coverage' => 0.6,
        // The score is the weighted average of the verified factors, reduced by up to this share for the weight without evidence.
        'coverage_penalty' => 0.2,
        // ...and a Data Confidence Score of at least this.
        'min_confidence' => 35,
        // Evidence older than this (months) is not used; full recency credit up to fresh_months.
        'max_age_months' => 36,
        'fresh_months' => 12,
        // Distinct publishers for full independence credit.
        'full_sources' => 3,
        // Gross rental yield (%) and annualised price change (%) mapped onto 0-100.
        'yield_range' => [2, 10],
        'price_change_range' => [-10, 25],
        // Price history needs two verified transaction/valuation observations this far apart.
        'history_min_months' => 9,
        // Rent and price must be this close in time to compute a yield from market records.
        'yield_max_gap_months' => 12,
        // A category needs this many ranked projects before its projects enter the Overall Index.
        'overall_min_peers' => 3,
        'confidence_weights' => ['coverage' => 30, 'recency' => 25, 'authority' => 25, 'independence' => 10, 'corroboration' => 10],
        'confidence_levels' => ['high' => 75, 'medium' => 55, 'limited' => 35],
        // Risk points -> classification (lower bound of each class).
        'risk_classes' => ['low' => 0, 'moderate' => 2, 'elevated' => 4, 'high' => 6],
        // Recalculation runs this many seconds after the last verified change (changes in between are batched).
        'recalculate_delay' => 60,
    ],

    /*
    | Automatic collection from official sources. A source is collected only
    | when it is active AND an admin has recorded once that automated access
    | is permitted (access basis, terms). Requests identify the bot, obey
    | robots.txt, wait between pages and stay within size limits.
    */
    'collection' => [
        'user_agent' => env('INVESTMENT_COLLECTOR_USER_AGENT', 'MeezanPropertyBot/1.0 (+https://meezanproperty.com/contact)'),
        'timeout' => 30,
        'max_bytes' => 8 * 1024 * 1024,
        'delay_ms' => 1000,
        'max_pages' => 100,
        // A run that finds fewer than this share of the previous run's entries is treated
        // as a source or format problem: nothing is marked removed, staff are told.
        'min_entry_ratio' => 0.5,
        // Name similarity (0-1) to match an official entry to a project automatically, or to ask staff.
        'match_auto' => 0.92,
        'match_review' => 0.8,
        'access_bases' => [
            'public_record' => 'Public official record (published for the public; robots.txt and terms permit automated reading)',
            'licence' => 'Licensed data (written licence)',
            'agreement' => 'Data-sharing agreement with the authority',
            'owner_submission' => 'Owner-submitted documents (staff-verified)',
        ],
    ],

    /*
    | Collectors for official lists. driver "inertia" reads the page data an
    | Inertia (Laravel) site embeds in its HTML; "html_table" reads a table
    | configured per source. list_type: approved (status from the
    | authority's approval stage), illegal (an official illegal / unapproved
    | list), authority_scheme (developed by the authority itself). The LDA
    | presets match the LDA site as checked on 4 Nov 2026.
    */
    'collectors' => [
        'cda-housing' => ['label' => 'CDA — private housing schemes', 'driver' => 'cards', 'url' => 'https://www.cda.gov.pk/housing-schemes?letter=all', 'public_record' => true,
            'authority' => 'CDA', 'city' => 'Islamabad', 'list_type' => 'approved', 'stages' => 'cda', 'category' => 'residential_society',
            'fields' => ['key' => 'key', 'name' => 'name', 'location' => 'location', 'status' => 'status']],
        // Land-use schemes, not plazas: kept in the registry, no projects created from them.
        'cda-commercial' => ['label' => 'CDA — private commercial schemes', 'driver' => 'cards', 'url' => 'https://www.cda.gov.pk/commercial-schemes?letter=all', 'public_record' => true,
            'authority' => 'CDA', 'city' => 'Islamabad', 'list_type' => 'approved', 'stages' => 'cda', 'discover' => false,
            'fields' => ['key' => 'key', 'name' => 'name', 'location' => 'location', 'status' => 'status']],
        'cda-illegal' => ['label' => 'CDA — illegal housing schemes', 'driver' => 'list_items', 'url' => 'https://www.cda.gov.pk/illegal-housing-schemes', 'public_record' => true,
            'authority' => 'CDA', 'city' => 'Islamabad', 'list_type' => 'illegal',
            'fields' => ['key' => 'key', 'name' => 'name', 'location' => 'location']],
        'mda-schemes' => ['label' => 'MDA Multan — housing schemes developed by MDA', 'driver' => 'html_table', 'url' => 'https://mda.punjab.gov.pk/housing-schemes', 'public_record' => true, 'table' => 0,
            'authority' => 'MDA Multan', 'city' => 'Multan', 'list_type' => 'authority_scheme', 'category' => 'residential_society', 'columns' => ['name' => 'Name of Scheme']],
        'mda-mit-schemes' => ['label' => 'MDA Multan — MIT housing schemes', 'driver' => 'html_table', 'url' => 'https://mda.punjab.gov.pk/housing-schemes', 'public_record' => true, 'table' => 1,
            'authority' => 'MDA Multan', 'city' => 'Multan', 'list_type' => 'authority_scheme', 'category' => 'residential_society', 'columns' => ['name' => 'Name of Scheme']],
        'lda-approved' => ['label' => 'LDA — approved private housing schemes', 'public_record' => true, 'driver' => 'inertia', 'url' => 'https://lda.gop.pk/approved-schemes',
            'prop' => 'schemes', 'paginated' => false, 'authority' => 'LDA', 'city' => 'Lahore', 'list_type' => 'approved', 'stages' => 'lda',
            'fields' => ['key' => 'id', 'name' => 'scheme_name', 'location' => 'location', 'status' => 'approval_stage', 'date' => 'date_of_approval']],
        'lda-illegal' => ['label' => 'LDA — illegal private housing schemes', 'public_record' => true, 'driver' => 'inertia', 'url' => 'https://lda.gop.pk/illegal-schemes',
            'prop' => 'schemes', 'paginated' => true, 'authority' => 'LDA', 'city' => 'Lahore', 'list_type' => 'illegal',
            'fields' => ['key' => 'id', 'name' => 'scheme_name', 'location' => 'location', 'status' => 'illegal_status', 'date' => 'created_at', 'reference' => 'directorate'],
            // Only rows the authority marks active are current listings.
            'active' => ['illegal_status' => 'active']],
        'lda-regular' => ['label' => 'LDA — schemes developed by LDA', 'public_record' => true, 'driver' => 'inertia', 'url' => 'https://lda.gop.pk/regular-schemes',
            'prop' => 'schemes', 'paginated' => true, 'authority' => 'LDA', 'city' => 'Lahore', 'list_type' => 'authority_scheme',
            'fields' => ['key' => 'id', 'name' => 'scheme_name']],
        // Other authorities' lists published as HTML tables: url, table, columns and list type set per source.
        'html-table' => ['label' => 'Official list published as an HTML table (configured per source)', 'driver' => 'html_table', 'configurable' => true],
    ],

    'risk_classes' => ['low' => 'Low risk', 'moderate' => 'Moderate risk', 'elevated' => 'Elevated risk', 'high' => 'High risk', 'excluded' => 'Excluded — legal'],

    'statuses' => [
        'ranked' => 'Ranked',
        'not_eligible' => 'Not eligible for ranking',
        'insufficient' => 'Insufficient Verified Data',
        'excluded' => 'Excluded from recommendations',
    ],
];
