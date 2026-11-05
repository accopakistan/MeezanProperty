<?php

/*
| Real-world evidence read by hand from its original source on 5 November 2026.
| Nothing here is estimated: each value copies what the source says, and the
| source, its address and the read date travel with it into the database.
|
| Every evidence row is imported UNVERIFIED. It counts towards no score until a
| member of staff checks it against the source in the Verification Centre. A
| manager's or developer's statement is labelled as theirs, not as independent proof.
|
| Deliberately not recorded:
| - DCR's "dividend yield based on par value": it is not a property rental yield.
| - DCR's rental income as a yield for one building: the figure covers both
|   buildings together, and no per-building rent or value was available to read.
| - JS Rental REIT's ~43% occupancy as the occupancy of The Centre: it covers only
|   the seven floors the REIT holds, not the 22-floor building.
| - Sold or booked percentages as occupancy: a sold apartment is not an occupied one.
*/

$read = '2026-11-05';
$ahdrml = 'https://www.arifhabibdolmenreit.com/wp-content/uploads/2026/10/Quarterly%20Report%20%E2%80%93%20September%2030,%202026.pdf';

return [
    'sources' => [
        'dcr-site' => ['name' => 'Arif Habib Dolmen REIT Management — Dolmen City REIT page', 'publisher' => 'Arif Habib Dolmen REIT Management Limited', 'type' => 'owner_document', 'level' => 2,
            'url' => 'https://www.arifhabibdolmenreit.com/dolmen-city-reit-2-3/', 'method' => 'manual_read', 'city' => 'Karachi', 'category' => 'operations',
            'access' => 'Public page of the REIT\'s management company. The figures are the manager\'s own and undated on the page. Read once by hand.'],
        'ahdrml-q' => ['name' => 'Arif Habib Dolmen REIT Management — report for the quarter to 30 September 2026', 'publisher' => 'Arif Habib Dolmen REIT Management Limited', 'type' => 'owner_document', 'level' => 2,
            'url' => $ahdrml, 'method' => 'manual_read', 'city' => 'Karachi', 'category' => 'operations',
            'access' => 'Public PDF. The directors\' review is the manager\'s statement; condensed interim statements are not necessarily audited. Read once by hand.'],
        'srr-ar' => ['name' => 'Signature Residency REIT — Annual Report 2026', 'publisher' => 'Arif Habib Dolmen REIT Management Limited', 'type' => 'owner_document', 'level' => 2,
            'url' => 'https://www.arifhabibdolmenreit.com/wp-content/uploads/2026/10/SRR%20Annual%20Report%202026.pdf', 'method' => 'manual_read', 'city' => 'Karachi', 'category' => 'development',
            'access' => 'Public PDF. Construction wording is from the directors\' report, the manager\'s statement. Read once by hand.'],
        'nnar-ar' => ['name' => 'Naya Nazimabad Apartment REIT — Annual Report 2026', 'publisher' => 'Arif Habib Dolmen REIT Management Limited', 'type' => 'owner_document', 'level' => 2,
            'url' => 'https://www.arifhabibdolmenreit.com/wp-content/uploads/2026/10/NNAR%20Annual%20Report%202026.pdf', 'method' => 'manual_read', 'city' => 'Karachi', 'category' => 'development',
            'access' => 'Public PDF. Construction wording is from the directors\' report, the manager\'s statement. Read once by hand.'],
        'pacra-jsrr' => ['name' => 'PACRA — JS Rental REIT rating report, 2 January 2026', 'publisher' => 'The Pakistan Credit Rating Agency Limited', 'type' => 'authorized_report', 'level' => 4,
            'url' => 'https://pacra.com/view/storage/app/rating_reports/RR_2300_15417_02-Jan-26.pdf', 'method' => 'manual_read', 'city' => 'Karachi', 'category' => 'operations',
            'access' => 'Public rating report from an independent rating agency. Read once by hand.'],
        'askari-site' => ['name' => 'Askari Corporate Tower — project website', 'publisher' => 'Askari Corporate Tower', 'type' => 'developer_website', 'level' => 2,
            'url' => 'https://askaricorporatetower.com/?p=11', 'method' => 'manual_read', 'city' => 'Lahore', 'category' => 'existence',
            'access' => 'Public project website: the owner\'s own description. Read once by hand.'],
        'dailytimes-vertical' => ['name' => 'Daily Times — The Vertical breaks ground on V3 Business Centre, 25 February 2025', 'publisher' => 'Daily Times', 'type' => 'secondary_reference', 'level' => 7,
            'url' => 'https://dailytimes.com.pk/1269257/the-vertical-breaks-ground-on-v3-business-centre-a-new-hub-for-corporate-excellence-lahore-pakistan/', 'method' => 'manual_read', 'city' => 'Lahore', 'category' => 'existence',
            'access' => 'Public news item, written from the developer\'s announcement. It verifies nothing. Read once by hand.'],
        'propakistani-lifestyle' => ['name' => 'ProPakistani — Lifestyle Residency grey structure update, 31 October 2022', 'publisher' => 'ProPakistani', 'type' => 'secondary_reference', 'level' => 7,
            'url' => 'https://propakistani.pk/proproperty/?p=5112', 'method' => 'manual_read', 'city' => 'Islamabad', 'category' => 'existence',
            'access' => 'Public news item quoting an unnamed FGEHA official. Four years old, so it says nothing about today. Read once by hand.'],
        'zameen-opal' => ['name' => 'Zameen blog — Zameen Opal possession handover, 4 August 2023', 'publisher' => 'Zameen.com (sister company of the developer)', 'type' => 'developer_website', 'level' => 2,
            'url' => 'https://www.zameen.com/blog/zameen-opals-possession-handover-ceremony-1537.html', 'method' => 'manual_read', 'city' => 'Lahore', 'category' => 'existence',
            'access' => 'One public article, read once by hand. The developer\'s sister company wrote it. Zameen\'s listing pages are not collected: robots.txt disallows many of them.'],
    ],

    /* Developments the sources establish. Status is what the source reports. */
    'projects' => [
        ['name' => 'The Harbour Front', 'city' => 'Karachi', 'category' => 'business_tower', 'status' => 'completed', 'developer' => 'Dolmen City REIT (owner)', 'source' => 'dcr-site',
            'description' => 'The Harbour Front is an office building in Dolmen City, Sea View Road, Clifton, Karachi, held by Dolmen City REIT. Described by its manager, Arif Habib Dolmen REIT Management.'],
        ['name' => 'The Centre', 'city' => 'Karachi', 'category' => 'business_tower', 'status' => 'completed', 'source' => 'pacra-jsrr',
            'description' => 'The Centre is a 22-floor building on Abdullah Haroon Road, Saddar, Karachi. JS Rental REIT holds seven of its floors. Described in a PACRA rating report.'],
        ['name' => 'Askari Corporate Tower', 'city' => 'Lahore', 'category' => 'business_tower', 'status' => 'completed', 'source' => 'askari-site', 'website' => 'https://askaricorporatetower.com',
            'description' => 'Askari Corporate Tower is an office building at Liberty Roundabout, Main Boulevard, Gulberg III, Lahore. Described on its own website.'],
        ['name' => '94 Business Centre', 'city' => 'Lahore', 'category' => 'business_tower', 'source' => 'dailytimes-vertical', 'developer' => 'The Vertical',
            'description' => '94 Business Centre is named as an established corporate tower in Lahore in a news item based on its developer\'s announcement.'],
        ['name' => 'V2 Business Centre', 'city' => 'Lahore', 'category' => 'business_tower', 'source' => 'dailytimes-vertical', 'developer' => 'The Vertical',
            'description' => 'V2 is named as an established corporate tower in Lahore in a news item based on its developer\'s announcement.'],
        ['name' => 'V3 Business Centre', 'city' => 'Lahore', 'category' => 'business_tower', 'status' => 'under_construction', 'source' => 'dailytimes-vertical', 'developer' => 'The Vertical',
            'description' => 'V3 Business Centre is on Main Pine Avenue Road, Lahore. Piling had started in February 2025, according to a news item based on its developer\'s announcement.'],
        ['name' => 'Globe Residency', 'city' => 'Karachi', 'category' => 'apartment_complex', 'status' => 'under_construction', 'developer' => 'Globe Residency REIT', 'source' => 'ahdrml-q',
            'description' => 'Globe Residency is an apartment development on five FL sites in Naya Nazimabad, Karachi, held by Globe Residency REIT. Described by the REIT\'s manager.'],
        ['name' => 'Signature Tower', 'city' => 'Karachi', 'category' => 'apartment_complex', 'status' => 'under_construction', 'developer' => 'Signature Residency REIT', 'source' => 'srr-ar',
            'description' => 'Signature Tower is a mixed-use building on plot Com-102, Naya Nazimabad, Karachi, with 104 apartments. Described in the REIT\'s annual report.'],
        ['name' => 'Rahat Residency', 'city' => 'Karachi', 'category' => 'apartment_complex', 'status' => 'under_construction', 'developer' => 'Rahat Residency REIT', 'source' => 'ahdrml-q',
            'description' => 'Rahat Residency is a development of commercial and residential units on commercial sites in Naya Nazimabad, Karachi. Described by the REIT\'s manager.'],
        ['name' => 'Peace Apartments', 'city' => 'Karachi', 'category' => 'apartment_complex', 'status' => 'under_construction', 'developer' => 'Naya Nazimabad Apartment REIT', 'source' => 'nnar-ar',
            'description' => 'Peace Apartments is a mixed-use development in Naya Nazimabad, Karachi, launched in June 2024. Described in the REIT\'s annual report.'],
        ['name' => 'Silk Gardens', 'city' => 'Karachi', 'category' => 'apartment_complex', 'status' => 'planned', 'developer' => 'Silk Islamic Development REIT', 'source' => 'ahdrml-q',
            'description' => 'Silk Gardens is a gated development near Saima Arabian Villas, Karachi. Its manager reports that SBCA approvals were obtained; no SBCA record has been read.'],
        ['name' => 'Lifestyle Residency', 'city' => 'Islamabad', 'category' => 'apartment_complex', 'status' => 'under_construction', 'developer' => 'Federal Government Employees Housing Authority', 'source' => 'propakistani-lifestyle',
            'description' => 'Lifestyle Residency is an FGEHA apartment project in sectors G-13/1 and G-13/4, Islamabad. Its status comes from a 2022 news item and may be out of date.'],
        ['name' => 'Zameen Opal', 'city' => 'Lahore', 'category' => 'apartment_complex', 'status' => 'completed', 'developer' => 'Zameen Developments', 'source' => 'zameen-opal',
            'description' => 'Zameen Opal is an apartment project in Land Breeze Housing Society, Raiwind Road, Lahore. Its developer\'s sister company reported possession being handed over in phases in August 2023.'],
    ],

    /* Scored or context evidence. All unverified on import. */
    'evidence' => [
        ['project' => 'Dolmen Mall, Clifton', 'city' => 'Karachi', 'factor' => 'occupancy', 'percent' => 97.8, 'on' => $read, 'source' => 'dcr-site',
            'excerpt' => 'Occupancy: 97.80%. The page gives no date, so the read date is used. A September 2026 report gives over 97% for the two DCR buildings together.'],
        ['project' => 'Dolmen Mall, Clifton', 'city' => 'Karachi', 'factor' => 'operational_status', 'level' => 'operational', 'on' => '2026-09-30', 'source' => 'ahdrml-q',
            'excerpt' => 'Named with The Harbour Front as the rental REIT\'s assets, with rental income recorded in the quarter to 30 September 2026.'],
        ['project' => 'The Harbour Front', 'city' => 'Karachi', 'factor' => 'occupancy', 'percent' => 100, 'on' => $read, 'source' => 'dcr-site',
            'excerpt' => 'The page states the building is fully occupied. It gives no date, so the read date is used.'],
        ['project' => 'The Harbour Front', 'city' => 'Karachi', 'factor' => 'operational_status', 'level' => 'operational', 'on' => '2026-09-30', 'source' => 'ahdrml-q',
            'excerpt' => 'Named with Dolmen Mall Clifton as the rental REIT\'s assets, with rental income recorded in the quarter to 30 September 2026.'],
        ['project' => 'Globe Residency', 'city' => 'Karachi', 'factor' => 'operational_status', 'level' => 'partially_operational', 'on' => '2026-09-30', 'source' => 'ahdrml-q',
            'excerpt' => 'Construction continues; possession of FL-03 and FL-04 has been offered to buyers.'],
        ['project' => 'Globe Residency', 'city' => 'Karachi', 'factor' => 'delivery_status', 'level' => 'on_track', 'on' => '2026-09-30', 'source' => 'ahdrml-q',
            'excerpt' => 'The manager says construction "continues to progress satisfactorily" and possession was offered "in a timely manner". This is the manager\'s own view.'],
        ['project' => 'Signature Tower', 'city' => 'Karachi', 'factor' => 'operational_status', 'level' => 'under_construction', 'on' => '2026-06-30', 'source' => 'srr-ar',
            'excerpt' => 'Grey structure complete; now in the finishing phase.'],
        ['project' => 'Signature Tower', 'city' => 'Karachi', 'factor' => 'delivery_status', 'level' => 'on_track', 'on' => '2026-09-30', 'source' => 'ahdrml-q',
            'excerpt' => 'The manager says construction is "progressing in line with the approved development plan". This is the manager\'s own view.'],
        ['project' => 'Rahat Residency', 'city' => 'Karachi', 'factor' => 'operational_status', 'level' => 'partially_operational', 'on' => '2026-09-30', 'source' => 'ahdrml-q',
            'excerpt' => 'Construction is underway on two sites. Possession of 18 shops in Rahat I and 15 shops in Rahat II has been handed over.'],
        ['project' => 'Peace Apartments', 'city' => 'Karachi', 'factor' => 'operational_status', 'level' => 'under_construction', 'on' => '2026-06-30', 'source' => 'nnar-ar',
            'excerpt' => 'Construction is underway on three sites comprising five towers.'],
        ['project' => 'Silk Gardens', 'city' => 'Karachi', 'factor' => 'operational_status', 'level' => 'planned', 'on' => '2026-09-30', 'source' => 'ahdrml-q',
            'excerpt' => 'Sales centre and model apartments complete; the contractor appointment is underway for COM-8, The Palms.'],
        ['project' => 'Zameen Opal', 'city' => 'Lahore', 'factor' => 'operational_status', 'level' => 'operational', 'on' => '2023-08-04', 'source' => 'zameen-opal',
            'excerpt' => 'Possession granted in phases after the relevant departments completed their formalities.'],
    ],

    /* Facts shown on the profile with their source, never used in a score. */
    'facts' => [
        ['project' => 'Dolmen Mall, Clifton', 'city' => 'Karachi', 'source' => 'dcr-site', 'date' => $read, 'items' => [
            'Leasable area' => '542,847 sq ft', 'Built-up area' => 'About 1 million sq ft over three levels', 'Location' => 'Sea View Road, Clifton, Karachi']],
        ['project' => 'The Harbour Front', 'city' => 'Karachi', 'source' => 'dcr-site', 'date' => $read, 'items' => [
            'Leasable area' => '257,162 sq ft', 'Size' => 'About 270,000 sq ft over seventeen floors', 'Location' => 'Sea View Road, Clifton, Karachi']],
        ['project' => 'The Centre', 'city' => 'Karachi', 'source' => 'pacra-jsrr', 'date' => '2026-01-02', 'items' => [
            'Location' => 'Abdullah Haroon Road, Saddar, Karachi', 'Land area' => '3,988 sq yds', 'Floors' => '22, including six parking floors',
            'REIT holding' => 'JS Rental REIT holds seven floors. Its occupancy figure covers those floors only, so no building occupancy is recorded.']],
        ['project' => 'Askari Corporate Tower', 'city' => 'Lahore', 'source' => 'askari-site', 'date' => $read, 'items' => [
            'Location' => 'Liberty Roundabout, Main Boulevard, Gulberg III, Lahore', 'Stated certification' => 'LEED Gold, as stated by the building\'s website; not independently checked']],
        ['project' => 'Globe Residency', 'city' => 'Karachi', 'source' => 'ahdrml-q', 'date' => '2026-09-30', 'items' => [
            'Launched inventory' => '1,351 apartments', 'Sold' => '1,246 apartments (92.20%). Sold does not mean occupied.', 'Location' => 'Five FL sites, Naya Nazimabad, Karachi']],
        ['project' => 'Signature Tower', 'city' => 'Karachi', 'source' => 'srr-ar', 'date' => '2026-06-30', 'items' => [
            'Plot' => 'Com-102, 2,067 sq yds, Naya Nazimabad', 'Apartments' => '104', 'Stage' => 'Grey structure complete; finishing phase']],
        ['project' => 'Rahat Residency', 'city' => 'Karachi', 'source' => 'ahdrml-q', 'date' => '2026-09-30', 'items' => [
            'Rahat I' => 'About 98% of apartments booked; all 18 shops handed over', 'Rahat II' => '100% of apartments booked; over 88% of shops sold. Booked does not mean occupied.']],
        ['project' => 'Peace Apartments', 'city' => 'Karachi', 'source' => 'ahdrml-q', 'date' => '2026-09-30', 'items' => [
            'Apartments sold' => '750 of 1,294', 'Shops sold' => '230 of 383', 'Payment plan' => '70% during construction, 30% on possession']],
        ['project' => 'Silk Gardens', 'city' => 'Karachi', 'source' => 'ahdrml-q', 'date' => '2026-09-30', 'items' => [
            'Apartments booked' => '436 of 502 planned', 'Stated approval' => 'The manager says SBCA approvals were obtained; no SBCA record has been read, so the legal status stays unverified.']],
        ['project' => 'Lifestyle Residency', 'city' => 'Islamabad', 'source' => 'propakistani-lifestyle', 'date' => '2022-10-31', 'items' => [
            'Location' => 'Sectors G-13/1 and G-13/4, Islamabad', 'As reported in 2022' => 'About 680 of 1,148 flats at the structural stage. This is old and may have changed.']],
        ['project' => 'Zameen Opal', 'city' => 'Lahore', 'source' => 'zameen-opal', 'date' => '2023-08-04', 'items' => [
            'Location' => 'Land Breeze Housing Society, Raiwind Road, Lahore']],
    ],
];
