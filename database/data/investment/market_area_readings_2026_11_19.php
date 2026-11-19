<?php

/**
 * Area-level market figures for apartment projects, and secondary references for large malls, read by hand
 * on 19 Nov 2026 (notes: docs/research/zameen_index_readings_2026-11-19.md).
 *
 * Apartments: Zameen.com's public flat price and rent indexes for the project's area (Sep 2026). These are
 * AREA averages of advertised (asking) prices and rents, not the building's own figures, and every label
 * says so. Gross yield = rent per sq ft × 12 ÷ price per sq ft (same basis for both; Zameen rounds the
 * price per sq ft to the thousand, so the yield is approximate). An implausible yield is left out.
 *
 * Malls: encyclopedic facts (Wikipedia), labelled as an unverified secondary reference.
 *
 * Loaded by: php artisan meezan:market-facts
 */
$area = function (int $project, string $areaName, string $path, string $price, string $psf, ?string $rent = null, ?string $rentPsf = null, ?string $yield = null) {
    return [
        'project' => $project,
        'size' => 'area flats',
        'source' => 'Zameen.com price index — flats in '.$areaName,
        'url' => 'https://www.zameen.com/index/buy/flats/'.$path.'/',
        'read_on' => '2026-11-19',
        'facts' => array_filter([
            'Area flat price ('.$areaName.', advertised)' => $price.' average; '.$psf.' per sq ft (Sep 2026)',
            'Area flat rent ('.$areaName.', advertised)' => $rent ? $rent.' a month average; '.$rentPsf.' per sq ft (Sep 2026)' : null,
            'Rental yield, area flats (advertised)' => $yield,
        ]),
    ];
};
$reference = fn (int $project, string $url, array $facts) => [
    'project' => $project,
    'size' => 'reference',
    'source' => 'Wikipedia (secondary public reference, unverified)',
    'url' => $url,
    'read_on' => '2026-11-19',
    'basis' => 'Secondary public reference (unverified)',
    'facts' => $facts,
];

return [
    // Lahore — Gulberg (Pearl One on MM Alam Road; Zameen Aurum in Gulberg 3)
    $area(2006, 'Gulberg, Lahore', 'lahore/gulberg-7', 'PKR 10.61 Crore', 'PKR 36 Thousand', 'PKR 1.73 Lakh', 'PKR 177', '≈ 5.9% gross (area flats)'),
    $area(2013, 'Gulberg, Lahore', 'lahore/gulberg-7', 'PKR 10.61 Crore', 'PKR 36 Thousand', 'PKR 1.73 Lakh', 'PKR 177', '≈ 5.9% gross (area flats)'),

    // Islamabad — Bahria Town (Ivory). Its rent index implies ≈20%, which is implausible: price only.
    $area(1993, 'Bahria Town, Islamabad', 'islamabad/bahria-town-383', 'PKR 1.2 Crore', 'PKR 11 Thousand'),

    // Karachi
    $area(2015, 'Scheme 33, Karachi', 'karachi/scheme-33-495', 'PKR 1.37 Crore', 'PKR 11 Thousand', 'PKR 65 Thousand', 'PKR 47', '≈ 5.1% gross (area flats)'),
    $area(2057, 'Gadap Town, Karachi', 'karachi/gadap-town-570', 'PKR 76.99 Lakh', 'PKR 8 Thousand', 'PKR 42.15 Thousand', 'PKR 44', '≈ 6.6% gross (area flats)'),
    $area(2045, 'Gadap Town, Karachi', 'karachi/gadap-town-570', 'PKR 76.99 Lakh', 'PKR 8 Thousand', 'PKR 42.15 Thousand', 'PKR 44', '≈ 6.6% gross (area flats)'),
    $area(1963, 'Naya Nazimabad, Karachi', 'karachi/naya-nazimabad-10079', 'PKR 2.23 Crore', 'PKR 15 Thousand'),
    $area(1966, 'Naya Nazimabad, Karachi', 'karachi/naya-nazimabad-10079', 'PKR 2.23 Crore', 'PKR 15 Thousand'),

    // Multan — DHA Defence (Rumanza projects, Boulevard Heights). No rent index for the area.
    $area(1976, 'DHA Defence, Multan', 'multan/dha-defence-8581', 'PKR 2.57 Crore', 'PKR 20 Thousand'),
    $area(1977, 'DHA Defence, Multan', 'multan/dha-defence-8581', 'PKR 2.57 Crore', 'PKR 20 Thousand'),
    $area(1978, 'DHA Defence, Multan', 'multan/dha-defence-8581', 'PKR 2.57 Crore', 'PKR 20 Thousand'),

    // Malls — secondary references
    $reference(940, 'https://en.wikipedia.org/wiki/Emporium_Mall', [
        'Opened' => 'June 2016 (sources give 29 or 30 June)',
        'Floor area' => 'About 2.7 million sq ft',
        'Operator' => 'Nishat Hotels and Properties Ltd (Nishat Group)',
    ]),
    $reference(941, 'https://en.wikipedia.org/wiki/Packages_Mall', [
        'Opened' => '2017',
        'Owner' => 'Packages Limited',
        'Stores' => '200+ on three floors',
    ]),

    // Malls and towers, Wikipedia (read 19 Nov 2026). Figures the article contradicts itself on, or that look
    // implausible (Fortress Square's 60,942 sq ft for five levels), are left out; notes in the research file.
    $reference(953, 'https://en.wikipedia.org/wiki/LuckyOne_Mall', [
        'Location' => 'Rashid Minhas Road, Karachi',
        'Opened' => '6 May 2017',
        'Gross leasable area' => '3.4 million sq ft (as given)',
        'Stores' => '200+',
        'Owner' => 'Yunus Brothers Group (developer: Lucky Landmark)',
    ]),
    $reference(987, 'https://en.wikipedia.org/wiki/The_Centaurus', [
        'Location' => 'Jinnah Avenue, F-8, Islamabad',
        'Opened' => '17 February 2013 (mall, in phases)',
        'Stores' => '250+ outlets on four floors',
        'Developer' => 'Pak Gulf Construction, with Al Tamimi Group and Sardar Builders',
    ]),
    $reference(1069, 'https://en.wikipedia.org/wiki/The_Centaurus', [
        'Location' => 'Jinnah Avenue, F-8, Islamabad',
        'Height' => '116 m, 23 floors (completed 2013)',
        'Part of' => 'The Centaurus mixed-use complex (mall, three towers, hotel)',
    ]),
    $reference(988, 'https://en.wikipedia.org/wiki/World_Trade_Center_Islamabad', [
        'Location' => 'GT Road, DHA Phase 2, Islamabad',
        'Opened' => '17 August 2016',
        'Floors' => '14-storey complex: five-storey mall, six-storey office tower',
        'Owner' => 'Giga Group',
    ]),
    $reference(955, 'https://en.wikipedia.org/wiki/Ocean_Towers', [
        'Location' => 'Block 9, Clifton, Karachi',
        'Floor area' => 'About 850,000 sq ft',
        'Height' => '120 m, 28 floors above ground and 5 below',
        'Built' => '2009 – 2014',
    ]),
    $reference(942, 'https://en.wikipedia.org/wiki/Fortress_Square', [
        'Location' => 'Lahore Cantonment',
        'Opened' => '2014',
        'Stores' => '100+ on five levels',
        'Site' => 'Leased to DUPAK Pakistan Developers for 33 years',
    ]),
    $reference(1070, 'https://en.wikipedia.org/wiki/Arfa_Software_Technology_Park', [
        'Location' => '346-B Ferozepur Road, Lahore',
        'Height' => '106 m, 17 floors',
        'Owner' => 'Punjab Information Technology Board',
        'Inaugurated' => '9 February 2012',
    ]),
    $reference(1066, 'https://en.wikipedia.org/wiki/MCB_Tower', [
        'Height' => '116 m, 29 floors and 3 basements',
        'Built' => '2000 – 2005',
        'Architect' => 'Arshad Shah Abdullah',
        'Former use' => 'Headquarters of MCB Bank',
    ]),
    $reference(1067, 'https://en.wikipedia.org/wiki/Habib_Bank_Plaza', [
        'Location' => 'I. I. Chundrigar Road, Karachi',
        'Height' => 'About 101 m',
        'Architect' => 'Leo A. Daly',
    ]),
    $reference(1068, 'https://en.wikipedia.org/wiki/List_of_tallest_buildings_in_Pakistan', [
        'Height' => '101 m, 25 floors (listed 2017)',
    ]),
];
