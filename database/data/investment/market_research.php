<?php

/*
| Project and market research read by hand on 5 November 2026 from pages Zameen's
| robots.txt allows (/new-projects/ and /index/; its /Lahore*-style paths are
| disallowed and were not read). Zameen's terms claim database rights, so this is a
| small set of attributed facts, not a copy of its data; the owner should consider a
| licence before relying on it at scale.
|
| These are marketing and asking-based figures. They establish that a project is
| advertised and what is being asked. They are not sales, and reach no score.
*/

$read = '2026-11-05';
$newProjects = 'https://www.zameen.com/new-projects/';

return [
    'sources' => [
        'zameen-new' => ['name' => 'Zameen — New Projects listings', 'publisher' => 'Zameen.com', 'type' => 'public_asking_prices', 'level' => 6, 'url' => $newProjects, 'method' => 'manual_read', 'city' => 'Pakistan', 'category' => 'existence',
            'access' => 'Public pages allowed by robots.txt, read by hand: the main list, Multan, Peshawar and the Offices list. Marketing listings, so they show a project is advertised, not that it is approved, built or sold.'],
        'zameen-index' => ['name' => 'Zameen Index — average house prices', 'publisher' => 'Zameen.com', 'type' => 'public_asking_prices', 'level' => 6, 'url' => 'https://www.zameen.com/index/', 'method' => 'manual_read', 'city' => 'Pakistan', 'category' => 'asking_prices',
            'access' => 'Public pages allowed by robots.txt, read by hand. The page does not state its method. Zameen is a listings portal, so the figures are treated as asking-based, never as sales.'],
        'wiki-dha' => ['name' => 'Wikipedia — Defence Housing Authority', 'publisher' => 'Wikipedia', 'type' => 'secondary_reference', 'level' => 7, 'url' => 'https://en.wikipedia.org/wiki/Defence_Housing_Authority', 'method' => 'manual_read', 'city' => 'Pakistan', 'category' => 'existence',
            'access' => 'A secondary reference. It shows a development is reported to exist and verifies nothing. The legal instruments it names were not read at source.'],
    ],

    /* name|city|category|location|type on the listing|advertised price */
    'portal_projects' => [
        // Multan
        'Al-Madev Complex - I|Multan|commercial_plaza|Old Shujabad Road, Multan|Shops|PKR 14.62 Lakh – 1.24 Crore',
        'Al-Madev Complex-II|Multan|commercial_plaza|Old Shujabad Road, Multan|Flats, Offices, Shops|PKR 16.12 Lakh – 3.94 Crore',
        'Prism Arcade DHA Multan|Multan|commercial_plaza|DHA Defence, Multan|Flats, Shops|PKR 96.72 Lakh – 22.86 Crore',
        'Vintage Commercials DHA Multan|Multan|commercial_plaza|DHA Defence, Multan|Flats, Shops, Other|PKR 22.5 Lakh – 2.56 Crore',
        'Mall Of Cantt|Multan|shopping_mall|Nusrat Road, Multan|Shops|Contact for price',
        'Boulevard Heights|Multan|apartment_complex|DHA Defence, Multan|Flats, Shops|PKR 42.42 Lakh – 2.31 Crore',
        'Downtown Rumanza|Multan|apartment_complex|DHA Defence, Multan|Flats, Shops|PKR 38.5 Lakh – 4.1 Crore',
        'Golf View Rumanza|Multan|apartment_complex|DHA Rumanza Golf Community, Multan|Flats, Commercial|PKR 1.12 Crore – 6.1 Crore',
        // Peshawar
        'Younas Plaza|Peshawar|commercial_plaza|Dilazak Road, Peshawar|Flats, Offices|Contact for price',
        'Burjuman Center|Peshawar|commercial_plaza|University Road, Peshawar|Flats, Offices, Shops|Contact for price',
        'Ismael Tower|Peshawar|commercial_plaza|GT Road, Peshawar|Flats, Offices, Shops|Contact for price',
        'Emirates Mall & Lodges|Peshawar|shopping_mall|Nasir Bagh Road, Peshawar|Flats, Shops|PKR 37.58 Lakh – 2.74 Crore',
        'Kings Mall & Hotel|Peshawar|shopping_mall|Hayatabad Phase 2, Peshawar|Shops|Contact for price',
        'Century Mall|Peshawar|shopping_mall|Ring Road, Peshawar|Flats, Offices, Shops|PKR 56 Lakh – 2.5 Crore',
        'Alina Mall|Peshawar|shopping_mall|Gulbahar Road, Peshawar|Offices, Shops|Contact for price',
        // Faisalabad
        'Sitara Laal Center|Faisalabad|commercial_plaza|Faisalabad|Offices, Shops, Warehouses|Contact for price',
        // Islamabad
        'Citadel 7|Islamabad|business_tower|Blue Area, Islamabad|Offices|PKR 2.73 Crore – 12.94 Crore',
        'Tycoon Tower|Islamabad|commercial_plaza|Faisal Hills - Block B, Islamabad|Flats, Offices, Shops|PKR 1.44 Crore – 2.95 Crore',
        'Zameen Ace Mall|Islamabad|shopping_mall|DHA Defence Phase 2, Islamabad|Shops, Offices|PKR 46.3 Lakh – 2.19 Arab',
        'City Mall & Residences|Islamabad|shopping_mall|Top City 1, Islamabad|Shops, Flats|From PKR 1.01 Crore',
        'Atrium Hills|Islamabad|apartment_complex|GT Road, Islamabad|Flats|From PKR 1.56 Crore',
        'Hyde Park Prestige|Islamabad|apartment_complex|Emaar Canyon Views, Islamabad|Flats, Commercial|From PKR 1.35 Crore',
        'Regent One|Islamabad|apartment_complex|Islamabad|Flats|From PKR 2 Crore',
        'Akbar Vista|Islamabad|apartment_complex|1st Avenue, Islamabad|Flats, Shops|From PKR 78.15 Lakh',
        'Ivory|Islamabad|apartment_complex|Bahria Town, Islamabad|Flats, Shops|PKR 68 Lakh – 5.78 Crore',
        // Lahore
        'Chohan Tower|Lahore|business_tower|Jail Road, Lahore|Offices, Other|Contact for price',
        'Studio Corporate Offices|Lahore|business_tower|Xinhua Mall, Lahore|Offices, Other|Contact for price',
        'Madison Square Central|Lahore|commercial_plaza|Garden Town, Lahore|Offices, Shops|PKR 1.21 Crore – 3.77 Crore',
        'The Walk By Icon|Lahore|commercial_plaza|Raiwind Road, Lahore|Commercial|From PKR 13.03 Crore',
        'Swiss Mall Gulberg|Lahore|shopping_mall|MM Alam Road, Lahore|Flats, Commercial|From PKR 1.21 Crore',
        'Madison Square Mall|Lahore|shopping_mall|Mian Mehmood Ali Kasoori Road, Lahore|Shops|From PKR 2.02 Crore',
        'ABS Mall & Residency|Lahore|shopping_mall|Bahria Town, Lahore|Flats, Offices, Shops, Penthouse|From PKR 60 Lakh',
        'Residence 41|Lahore|apartment_complex|Raiwind Road, Lahore|Flats|From PKR 1.04 Crore',
        'Clifton Square|Lahore|apartment_complex|Multan Road, Lahore|Flats|From PKR 56.4 Lakh',
        'Marbella Drive Residency|Lahore|apartment_complex|Raiwind Road, Lahore|Flats, Penthouse|From PKR 69.87 Lakh',
        'J Heights|Lahore|apartment_complex|Raiwind Road, Lahore|Flats|From PKR 1.65 Crore',
        'Zee Avenue|Lahore|apartment_complex|Zee Avenue, Lahore|Flats, Shops|From PKR 77.7 Lakh',
        'Pearl One Residencies|Lahore|apartment_complex|MM Alam Road, Lahore|Flats, Penthouse|From PKR 4.37 Crore',
        'Zameen Hive|Lahore|apartment_complex|Gulberg 3, Lahore|Flats|From PKR 2.23 Crore',
        'Zameen Arx|Lahore|apartment_complex|CBD Punjab (PCBDDA), Lahore|Flats, Penthouse, Offices, Commercial|PKR 3.08 Crore – 32.1 Crore',
        'Zameen Jade|Lahore|apartment_complex|Main Canal Bank Road, Lahore|Flats, Penthouse|PKR 1.84 Crore – 13 Crore',
        'Zameen EON|Lahore|apartment_complex|NSIT City, Lahore|Flats, Penthouse, Commercial|PKR 2.01 Crore – 15.98 Crore',
        'Zameen Quadrangle|Lahore|apartment_complex|Zafar Ali Road, Lahore|Flats, Shops|PKR 5.97 Crore – 6.65 Crore',
        'Zameen Phoenix|Lahore|apartment_complex|Main Canal Bank Road, Lahore|Flats, Shops|PKR 4.52 Crore – 6.25 Crore',
        'Zameen Aurum|Lahore|apartment_complex|Gulberg 3 - Block L, Lahore|Flats|PKR 3.03 Crore – 5.35 Crore',
        // Karachi
        'Aman Aura|Karachi|apartment_complex|Scheme 33, Karachi|Flats|From PKR 1.26 Crore',
        'Abraz Ul Minal Towers|Karachi|apartment_complex|Gulzar-e-Hijri, Karachi|Flats, Commercial|From PKR 90.32 Lakh',
        'Burj Al Jinnah|Karachi|apartment_complex|Jinnah Avenue, Karachi|Flats|From PKR 3.43 Crore',
        'Samrina Boulevard|Karachi|apartment_complex|Scheme 33, Karachi|Flats|From PKR 2.87 Crore',
        'Premier One|Karachi|apartment_complex|Karachi|Flats, Shops|From PKR 95.75 Lakh',
        'JBR Residency|Karachi|apartment_complex|Bahria Town - Precinct 8, Karachi|Shops, Flats|PKR 55.08 Lakh – 2.25 Crore',
        'Elegance 16|Karachi|apartment_complex|Bahria Town Karachi, Karachi|Flats, Shops|PKR 1.47 Crore – 5.22 Crore',
        'The Arkadians by AKD Group|Karachi|apartment_complex|DHA Phase 8 - Zone B, Karachi|Flats, Penthouse|PKR 11.78 Crore – 98.17 Crore',
        'Ahsan Town|Karachi|residential_society|Karachi Motorway, Karachi|Plots, Shops|PKR 36 Lakh – 1.5 Crore',
        'Qureshi Society|Karachi|residential_society|Qureshi Society, Karachi|Plots|PKR 60 Lakh – 1.6 Crore',
        'Ideas One|Islamabad|commercial_plaza|B-17, Islamabad|Shops|PKR 68.85 Lakh – 3.73 Crore',
        'Pavilion-17|Islamabad|apartment_complex|Eighteen, Islamabad|Flats|PKR 2.69 Crore – 7.3 Crore',
        'NORA Residences Islamabad|Islamabad|apartment_complex|GT Road, Islamabad|Flats|PKR 78.57 Lakh – 1.17 Crore',
        'Elanza Creeks|Islamabad|apartment_complex|Park View City, Islamabad|Flats, Shops|PKR 71.5 Lakh – 5.06 Crore',
        'Zameen NEO|Lahore|apartment_complex|Gulberg 3, Lahore|Flats|Contact for price',
        'Damaan City|Lahore|residential_society|Multan Road, Lahore|Houses, Plots|PKR 57.48 Lakh – 3.85 Crore',
        'Icon Valley Phase 2|Lahore|residential_society|Raiwind Road, Lahore|Plots, Buildings|PKR 1.19 Crore – 8.43 Crore',
    ],

    /* Large developments on no authority list read so far. Legal status stays unknown. */
    'societies' => [
        ['name' => 'DHA Lahore', 'city' => 'Lahore', 'source' => 'wiki-dha', 'developer' => 'Defence Housing Authority', 'instrument' => 'Formed under an Extraordinary Gazette of Pakistan Part-I dated 19 September 2002'],
        ['name' => 'DHA Karachi', 'city' => 'Karachi', 'source' => 'wiki-dha', 'developer' => 'Defence Housing Authority', 'instrument' => 'Formed under Presidential Order number 7 of 1980, later approved by the National Assembly'],
        ['name' => 'DHA Islamabad', 'city' => 'Islamabad', 'source' => 'wiki-dha', 'developer' => 'Defence Housing Authority', 'instrument' => 'Act no. XII of 2013, Gazette of Pakistan dated 19 March 2013'],
        ['name' => 'DHA Multan', 'city' => 'Multan', 'source' => 'wiki-dha', 'developer' => 'Defence Housing Authority'],
        ['name' => 'DHA Peshawar', 'city' => 'Peshawar', 'source' => 'wiki-dha', 'developer' => 'Defence Housing Authority'],
        ['name' => 'DHA Quetta', 'city' => 'Quetta', 'source' => 'wiki-dha', 'developer' => 'Defence Housing Authority'],
        ['name' => 'Bahria Town Lahore', 'city' => 'Lahore', 'source' => 'zameen-index'],
        ['name' => 'Bahria Town Karachi', 'city' => 'Karachi', 'source' => 'zameen-index'],
        // Not the Islamabad scheme of a similar name on CDA's list: Zameen files this one under Lahore.
        ['name' => 'Park View City Lahore', 'city' => 'Lahore', 'source' => 'zameen-index'],
    ],

    /*
    | Zameen Index, for houses, "Average Houses Price in Sep 2026". Ago figures are the page's
    | own relative columns, so they stay facts rather than dated prices. sizes: asking-based, per house.
    */
    'index' => [
        ['project' => 'DHA Lahore', 'city' => 'Lahore', 'url' => 'https://www.zameen.com/index/buy/houses/lahore/dha-defence-9/', 'avg' => 'PKR 8.92 Crore', 'six' => 'PKR 8.32 Crore', 'one' => 'PKR 8.08 Crore', 'two' => 'PKR 7.64 Crore',
            'sizes' => [[5, 'marla', 28700000], [1, 'kanal', 108700000]]],
        ['project' => 'DHA Karachi', 'city' => 'Karachi', 'url' => 'https://www.zameen.com/index/buy/houses/karachi/dha-defence-213/', 'avg' => 'PKR 22.35 Crore', 'six' => 'PKR 19.06 Crore', 'one' => 'PKR 17.49 Crore', 'two' => 'PKR 15.64 Crore',
            'sizes' => [[5, 'marla', 67200000], [10, 'marla', 95300000], [1, 'kanal', 190600000], [2, 'kanal', 332300000]]],
        ['project' => 'DHA Islamabad', 'city' => 'Islamabad', 'url' => 'https://www.zameen.com/index/buy/houses/islamabad/dha-defence-3188/', 'avg' => 'PKR 9.91 Crore', 'six' => 'PKR 8.62 Crore', 'one' => 'PKR 8.22 Crore', 'two' => 'PKR 8.61 Crore',
            'sizes' => [[5, 'marla', 27200000], [10, 'marla', 51100000], [1, 'kanal', 116300000], [2, 'kanal', 146100000]]],
        ['project' => 'Bahria Town Lahore', 'city' => 'Lahore', 'url' => 'https://www.zameen.com/index/buy/houses/lahore/bahria-town-509/', 'avg' => 'PKR 4.03 Crore', 'six' => 'PKR 3.81 Crore', 'one' => 'PKR 3.84 Crore', 'two' => 'PKR 3.94 Crore',
            'sizes' => [[5, 'marla', 22000000], [10, 'marla', 39300000], [1, 'kanal', 77000000], [2, 'kanal', 126700000]]],
        ['project' => 'Bahria Town Karachi', 'city' => 'Karachi', 'url' => 'https://www.zameen.com/index/buy/houses/karachi/bahria-town-karachi-8298/', 'avg' => 'PKR 2.72 Crore', 'six' => 'PKR 2.39 Crore', 'one' => 'PKR 2.34 Crore', 'two' => 'PKR 2.28 Crore',
            'sizes' => [[5, 'marla', 13400000], [10, 'marla', 21000000], [1, 'kanal', 53900000], [2, 'kanal', 115500000]]],
        ['project' => 'Park View City Lahore', 'city' => 'Lahore', 'url' => 'https://www.zameen.com/index/buy/houses/lahore/park-view-city-1466/', 'avg' => 'PKR 2.19 Crore', 'six' => 'PKR 2.16 Crore', 'one' => 'PKR 2.21 Crore', 'two' => 'PKR 2.12 Crore',
            'sizes' => [[5, 'marla', 20200000], [10, 'marla', 34600000], [1, 'kanal', 68800000]]],
    ],
];
