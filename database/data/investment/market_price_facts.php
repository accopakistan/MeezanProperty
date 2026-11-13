<?php

/**
 * Advertised (asking) market figures read from Zameen.com's public price-index pages, by hand, on the
 * date given. They are shown on ranking pages and project pages as advertised figures with their source.
 * They are NOT verified sales and do not feed the Investment Index score.
 *
 * One entry per project, area and plot size: the price for the latest month the source shows, and the
 * change against one and two years earlier, exactly as the source shows them. Leave a figure out when
 * the source does not give it; never estimate one.
 *
 * Loaded by: php artisan meezan:market-facts (replaces each project's earlier advertised figures).
 */
$zameen = fn (int $project, string $area, string $path, string $size, string $month, string $price, ?string $y1 = null, ?string $y2 = null) => [
    'project' => $project,
    'source' => 'Zameen.com price index — '.$area.' ('.$size.' residential plot)',
    // A path without a slash is a Lahore area; other cities give 'city/area'.
    'url' => 'https://www.zameen.com/index/buy/residential_plots/'.(str_contains($path, '/') ? $path : 'lahore/'.$path).'/'.str_replace(' ', '-', $size).'/',
    'read_on' => '2026-11-13',
    'facts' => array_filter([
        'Advertised price ('.$size.' plot)' => $price.' ('.$month.')',
        'Price change, 1 year ('.$size.' plot)' => $y1,
        'Price change, 2 years ('.$size.' plot)' => $y2,
    ]),
];

// Each society: [project id, the source's area name, index path], then 5 marla, 10 marla, 1 kanal.
$societies = [
    // Lahore
    [1404, 'Lake City', 'lake-city-1103', [
        ['5 marla', 'PKR 1.18 Crore', '+39%', '+51%'], ['10 marla', 'PKR 2.42 Crore', '+27%', '+46%'], ['1 kanal', 'PKR 4.45 Crore', '+19%', '+38%']]],
    [1547, 'Valencia Housing Society', 'valencia-housing-society-373', [
        ['5 marla', 'PKR 1.37 Crore', '+16%', '+14%'], ['10 marla', 'PKR 2.72 Crore', '+19%', '+23%'], ['1 kanal', 'PKR 4.09 Crore', '+18%', '+16%']]],
    [1555, 'Wapda Town', 'wapda-town-423', [
        ['5 marla', 'PKR 1.56 Crore', '+14%', '+14%'], ['10 marla', 'PKR 2.73 Crore', '+5%', '+17%'], ['1 kanal', 'PKR 4.44 Crore', '+15%', '+31%']]],
    [1241, 'Bahria Town', 'bahria-town-509', [
        // 5 marla fell slightly: PKR 82.28 Lakh a year earlier, 82.63 Lakh two years earlier.
        ['5 marla', 'PKR 80.95 Lakh', '-2%', '-2%'], ['10 marla', 'PKR 1.46 Crore', '+4%', '+3%'], ['1 kanal', 'PKR 2.81 Crore', '+5%', '+3%']]],
    [2025, 'Bahria Town', 'bahria-town-509', [
        ['5 marla', 'PKR 80.95 Lakh', '-2%', '-2%'], ['10 marla', 'PKR 1.46 Crore', '+4%', '+3%'], ['1 kanal', 'PKR 2.81 Crore', '+5%', '+3%']]],
    [2019, 'DHA Defence', 'dha-defence-9', [
        ['5 marla', 'PKR 1.12 Crore', '+18%', '+26%'], ['10 marla', 'PKR 2.17 Crore', '+15%', '+17%'], ['1 kanal', 'PKR 4.01 Crore', '+15%', '+24%']]],
    [1579, 'Jubilee Town', 'jubilee-town-766', [
        ['5 marla', 'PKR 1.03 Crore', '+11%', '+12%'], ['10 marla', 'PKR 1.57 Crore', '+17%', '+19%'], ['1 kanal', 'PKR 4.22 Crore', '+22%', '+2%']]],

    // Islamabad
    [1090, 'Bahria Town, Islamabad', 'islamabad/bahria-town-383', [
        ['5 marla', 'PKR 1.07 Crore', '+29%', '+29%'], ['10 marla', 'PKR 1.74 Crore', '+23%', '+26%'], ['1 kanal', 'PKR 3.49 Crore', '+12%', '+13%']]],
    [1121, 'MPCHS - Multi Gardens, B-17', 'islamabad/b-17/mpchs---multi-gardens-3115', [
        ['5 marla', 'PKR 85.95 Lakh', '+35%', '+65%'], ['10 marla', 'PKR 1.26 Crore', '+25%', '+54%'], ['1 kanal', 'PKR 2.74 Crore', '+26%', '+46%']]],

    // Multan
    [1146, 'Shah Rukn-e-Alam Colony, Multan', 'multan/shah-rukn-e-alam-colony-658', [
        ['5 marla', 'PKR 34.06 Lakh', '+22%', '+22%'], ['10 marla', 'PKR 1.38 Crore', '+22%', '+22%'], ['1 kanal', 'PKR 1.82 Crore', '+25%', '+25%']]],

    // No plot index was found (10 Nov 2026) for: Naya Nazimabad and Gul-e-Rehmat (Karachi),
    // Gulberg Greens (Islamabad, farmhouse plots), Sitara Sapna City (Faisalabad), Ali Town (Quetta),
    // Sheikh Yaseen Town (Peshawar). They stay without a price until a source exists.
];

$entries = [];
foreach ($societies as [$project, $area, $path, $sizes]) {
    foreach ($sizes as [$size, $price, $y1, $y2]) {
        $entries[] = $zameen($project, $area, $path, $size, 'Sep 2026', $price, $y1, $y2);
    }
}

return $entries;
