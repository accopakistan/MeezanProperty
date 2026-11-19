<?php

/**
 * Advertised (asking) plot prices read by hand on 19 Nov 2026 from Zameen.com's public price-index pages
 * (https://www.zameen.com/index/buy/residential_plots/…; /index/ is not disallowed by robots.txt).
 * Notes and the readings table: docs/research/zameen_index_readings_2026-11-19.md.
 *
 * Each reading holds what the page shows: the latest month's average and the averages 6 months, 1 year
 * and 2 years earlier, plus the 15-month table where the page has one. The 1- and 2-year changes are
 * worked out from those prices because the page shows its percentages without a sign. These are asking
 * prices, never sales, and never feed an Index score. A reading here replaces an older figure for the
 * same project and plot size in market_price_facts.php.
 *
 * Loaded by: php artisan meezan:market-facts
 */
$months = ['Jul 2025', 'Aug 2025', 'Sep 2025', 'Oct 2025', 'Nov 2025', 'Dec 2025', 'Jan 2026', 'Feb 2026', 'Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026'];

// Amounts in PKR Crore (L = Lakh, written as Crore / 100).
$reading = function (int $project, string $area, string $path, string $size, array $points, array $monthly = []) use ($months) {
    [$now, $m6, $y1, $y2] = $points;
    $pkr = fn (float $crore) => $crore >= 1 ? 'PKR '.rtrim(rtrim(number_format($crore, 2), '0'), '.').' Crore' : 'PKR '.rtrim(rtrim(number_format($crore * 100, 2), '0'), '.').' Lakh';
    $change = fn (float $from) => ($from > 0 ? (($d = round(($now / $from - 1) * 100)) >= 0 ? '+'.$d : (string) $d).'%' : null);
    $label = $size === 'all sizes' ? 'plot, all sizes' : $size.' plot';
    $series = [['Sep 2024', $pkr($y2)], ['Sep 2025', $pkr($y1)], ['Mar 2026', $pkr($m6)]];
    if ($monthly !== []) {
        // The 15-month table replaces the 1-year and 6-month points (it contains them); the 2-year point stays.
        $series = array_merge([['Sep 2024', $pkr($y2)]], array_map(fn ($m, $v) => [$m, $pkr($v)], array_slice($months, -count($monthly)), $monthly));
    } else {
        $series[] = ['Sep 2026', $pkr($now)];
    }

    return [
        'project' => $project,
        'size' => $size,
        'source' => 'Zameen.com price index — '.$area.' ('.($size === 'all sizes' ? 'all residential plot sizes' : $size.' residential plot').')',
        'url' => 'https://www.zameen.com/index/buy/residential_plots/'.$path.'/'.($size === 'all sizes' ? '' : str_replace(' ', '-', $size).'/'),
        'read_on' => '2026-11-19',
        'series' => $series,
        'facts' => array_filter([
            'Advertised price ('.$label.')' => $pkr($now).' (Sep 2026)',
            'Price change, 1 year ('.$label.')' => $change($y1),
            'Price change, 2 years ('.$label.')' => $change($y2),
        ]),
    ];
};
$L = fn (float $lakh) => $lakh / 100;

return [
    // Islamabad — Gulberg Residencia (the Gulberg area index; Gulberg Greens is farmhouse plots and is not given this index).
    $reading(1102, 'Gulberg, Islamabad', 'islamabad/gulberg-1682', '5 marla', [$L(88.49), $L(82.24), $L(67.98), $L(55.62)],
        array_map($L, [66.77, 69.85, 67.98, 73.14, 76.23, 76.36, 79.42, 80.17, 82.24, 86.63, 85.87, 86.22, 88.5, 87.17, 88.49])),
    $reading(1102, 'Gulberg, Islamabad', 'islamabad/gulberg-1682', '10 marla', [1.63, 1.49, 1.44, 1.36],
        [1.43, 1.42, 1.44, 1.44, 1.45, 1.44, 1.47, 1.46, 1.49, 1.49, 1.52, 1.5, 1.54, 1.58, 1.63]),
    $reading(1102, 'Gulberg, Islamabad', 'islamabad/gulberg-1682', '1 kanal', [2.82, 2.67, 2.52, 2.35],
        [2.51, 2.5, 2.52, 2.51, 2.6, 2.58, 2.63, 2.63, 2.67, 2.72, 2.75, 2.79, 2.84, 2.81, 2.82]),

    // Lahore — LDA Avenue
    $reading(1581, 'LDA Avenue', 'lahore/lda-avenue-547', '5 marla', [$L(95.58), $L(85.2), $L(84.66), $L(71.54)],
        array_map($L, [80.76, 81.15, 84.66, 86.2, 85.32, 84.07, 84.96, 84.28, 85.2, 86.89, 85.74, 91.84, 92.86, 97.26, 95.58])),
    $reading(1581, 'LDA Avenue', 'lahore/lda-avenue-547', '10 marla', [1.36, 1.17, 1.15, 1.08],
        [1.12, 1.13, 1.15, 1.16, 1.16, 1.17, 1.17, 1.18, 1.17, 1.2, 1.21, 1.25, 1.29, 1.35, 1.36]),
    $reading(1581, 'LDA Avenue', 'lahore/lda-avenue-547', '1 kanal', [2.28, 2.06, 2.05, 1.83],
        [2.02, 2.01, 2.05, 2.04, 2.05, 2.04, 2.02, 2.04, 2.06, 2.05, 2.07, 2.14, 2.13, 2.2, 2.28]),

    // Karachi — Naya Nazimabad
    $reading(1848, 'Naya Nazimabad, Karachi', 'karachi/naya-nazimabad-10079', '5 marla', [2.09, 2.03, 1.82, 1.4],
        [1.68, 1.78, 1.82, 1.89, 1.93, 1.95, 1.97, 1.99, 2.03, 2.06, 2.04, 2.06, 2.06, 2.09, 2.09]),
    $reading(1848, 'Naya Nazimabad, Karachi', 'karachi/naya-nazimabad-10079', '10 marla', [4.7, 4.37, 4.17, 2.85],
        [3.82, 3.98, 4.17, 4.24, 4.3, 4.33, 4.33, 4.4, 4.37, 4.5, 4.52, 4.56, 4.58, 4.67, 4.7]),
    $reading(1848, 'Naya Nazimabad, Karachi', 'karachi/naya-nazimabad-10079', '1 kanal', [5.58, 4.45, 4.45, 4.24],
        [4.45, 4.45, 4.45, 4.45, 4.45, 4.45, 4.45, 4.45, 4.45, 4.45, 4.68, 4.86, 5.06, 5.31, 5.58]),

    // Faisalabad and Peshawar — small areas: the page gives one average for all plot sizes, no monthly table.
    $reading(1636, 'Sitara Sapna City, Faisalabad', 'faisalabad/sitara-sapna-city-1211', 'all sizes', [1.3, 1.06, 1.06, 1.06]),
    $reading(1640, 'Sitara Gold City, Faisalabad', 'faisalabad/sitara-gold-city-10822', 'all sizes', [$L(83.81), $L(69.44), $L(57.71), $L(57.71)]),
    $reading(1840, 'Sheikh Yaseen Town, Peshawar', 'peshawar/sheikh-yaseen-town-13024', 'all sizes', [$L(65.18), $L(55.7), $L(55.7), $L(56.6)]),

    // Monthly history for 10 marla plots already on file (replaces the 13 Nov 10 marla figure for these projects).
    $reading(1404, 'Lake City', 'lahore/lake-city-1103', '10 marla', [2.42, 2.04, 1.9, 1.65],
        [1.84, 1.9, 1.9, 1.9, 1.88, 2.01, 1.99, 2.01, 2.04, 2.11, 2.16, 2.22, 2.31, 2.37, 2.42]),
    $reading(1547, 'Valencia Housing Society', 'lahore/valencia-housing-society-373', '10 marla', [2.72, 2.28, 2.28, 2.22],
        [2.28, 2.28, 2.28, 2.28, 2.28, 2.28, 2.28, 2.28, 2.28, 2.28, 2.37, 2.49, 2.54, 2.64, 2.72]),
    $reading(1555, 'Wapda Town', 'lahore/wapda-town-423', '10 marla', [2.73, 2.48, 2.59, 2.32],
        [2.47, 2.42, 2.59, 2.44, 2.41, 2.45, 2.45, 2.48, 2.48, 2.48, 2.6, 2.68, 2.64, 2.67, 2.73]),
    $reading(1241, 'Bahria Town', 'lahore/bahria-town-509', '10 marla', [1.46, 1.39, 1.4, 1.42],
        [1.45, 1.42, 1.4, 1.42, 1.41, 1.42, 1.43, 1.41, 1.39, 1.38, 1.38, 1.41, 1.44, 1.46, 1.46]),
    $reading(2025, 'Bahria Town', 'lahore/bahria-town-509', '10 marla', [1.46, 1.39, 1.4, 1.42],
        [1.45, 1.42, 1.4, 1.42, 1.41, 1.42, 1.43, 1.41, 1.39, 1.38, 1.38, 1.41, 1.44, 1.46, 1.46]),
    $reading(1579, 'Jubilee Town', 'lahore/jubilee-town-766', '10 marla', [1.57, 1.41, 1.35, 1.32],
        [1.34, 1.33, 1.35, 1.37, 1.36, 1.38, 1.39, 1.4, 1.41, 1.41, 1.47, 1.48, 1.52, 1.55, 1.57]),
    $reading(1090, 'Bahria Town, Islamabad', 'islamabad/bahria-town-383', '10 marla', [1.74, 1.47, 1.41, 1.38],
        [1.39, 1.4, 1.41, 1.42, 1.42, 1.42, 1.43, 1.45, 1.47, 1.52, 1.54, 1.58, 1.63, 1.7, 1.74]),
    $reading(1121, 'MPCHS - Multi Gardens, B-17', 'islamabad/b-17/mpchs---multi-gardens-3115', '10 marla', [1.26, 1.11, 1.01, $L(81.41)],
        [$L(98.61), 1.0, 1.01, 1.03, 1.05, 1.05, 1.08, 1.11, 1.11, 1.15, 1.14, 1.17, 1.18, 1.22, 1.26]),
    // Multi Gardens Phase II lies in the same MPCHS B-17 area as Phase I.
    $reading(1122, 'MPCHS - Multi Gardens, B-17', 'islamabad/b-17/mpchs---multi-gardens-3115', '10 marla', [1.26, 1.11, 1.01, $L(81.41)],
        [$L(98.61), 1.0, 1.01, 1.03, 1.05, 1.05, 1.08, 1.11, 1.11, 1.15, 1.14, 1.17, 1.18, 1.22, 1.26]),
    $reading(1146, 'Shah Rukn-e-Alam Colony, Multan', 'multan/shah-rukn-e-alam-colony-658', '10 marla', [1.38, 1.14, 1.14, 1.14],
        [1.14, 1.14, 1.14, 1.14, 1.14, 1.14, 1.14, 1.14, 1.14, 1.14, 1.17, 1.23, 1.3, 1.33, 1.38]),
];
