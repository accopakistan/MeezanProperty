<?php

/**
 * Builds the Meezan Property Pakistan location master dataset (research date
 * 2026-10-30) into docs/research/*.csv. Run from the project root:
 *
 *   php docs/research/build_dataset.php
 *
 * Inputs: official lists transcribed in raw/ (CDA, QDA, MDA, LDA-as-reported)
 * and the curated records below. Coordinates are NOT set here: they come from
 * `php artisan meezan:geocode-areas` (OpenStreetMap) after import and are then
 * written back with `php artisan meezan:export-locations`.
 *
 * Rules applied: approval status is kept separate from searchability; nothing
 * is marked approved/illegal without a listed source; uncertain records carry
 * needs_review=1 and a note instead of being dropped.
 */
const RESEARCH_DATE = '2026-10-30';

$official = require __DIR__.'/raw/official_lists_transcribed.php';
$cda = json_decode(file_get_contents(__DIR__.'/raw/cda_private_housing_schemes_2026-10-30.json'), true);

$rows = [];

function slugify(string $s): string
{
    $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s));

    return trim($s, '-');
}

/**
 * Add a record. $o keys: type, parent (name in same city), aliases, authority,
 * status, ref, sources, url, confidence, notes, review, popular, official.
 */
function add(string $city, string $name, array $o = []): void
{
    global $rows;
    $key = slugify($city).'--'.slugify($name);
    if (isset($rows[$key])) {
        // Same location from two sources: merge evidence instead of duplicating.
        $r = &$rows[$key];
        $r['source_ids'] = implode('|', array_unique(array_filter(array_merge(explode('|', $r['source_ids']), (array) ($o['sources'] ?? [])))));
        $r['aliases'] = implode('|', array_unique(array_filter(array_merge(explode('|', $r['aliases']), (array) ($o['aliases'] ?? [])))));
        $definitive = fn ($st) => ! in_array($st, ['', 'unverified', 'not_applicable'], true);
        if (! empty($o['status']) && $definitive($o['status']) && $definitive($r['approval_status']) && $o['status'] !== $r['approval_status']) {
            // Two authorities' lists disagree: keep the first, surface the conflict for manual review.
            $r['needs_review'] = 1;
            $r['notes'] = trim($r['notes'].' CONFLICT: also listed as '.$o['status'].' ('.($o['ref'] ?? 'no reference').').');
        }
        if (! empty($o['parent']) && $r['parent_key'] === '') {
            $r['parent_key'] = slugify($city).'--'.slugify($o['parent']);
        }
        foreach (['status' => 'approval_status', 'ref' => 'approval_reference', 'authority' => 'authority', 'official' => 'official_name', 'url' => 'source_url'] as $in => $col) {
            if (! empty($o[$in]) && ($r[$col] === '' || $r[$col] === 'unverified' || $r[$col] === 'not_applicable')) {
                $r[$col] = $o[$in];
            }
        }
        if (! empty($o['notes'])) {
            $r['notes'] = trim($r['notes'].' '.$o['notes']);
        }

        return;
    }

    $rows[$key] = [
        'key' => $key,
        'city' => $city,
        'province' => PROVINCES[$city],
        'name' => $name,
        'official_name' => $o['official'] ?? '',
        'aliases' => implode('|', (array) ($o['aliases'] ?? [])),
        'type' => $o['type'] ?? 'neighbourhood',
        'parent_key' => isset($o['parent']) ? slugify($city).'--'.slugify($o['parent']) : '',
        'authority' => $o['authority'] ?? '',
        'approval_status' => $o['status'] ?? 'unverified',
        'approval_reference' => $o['ref'] ?? '',
        'source_ids' => implode('|', (array) ($o['sources'] ?? ['S00'])),
        'source_url' => $o['url'] ?? '',
        'research_date' => RESEARCH_DATE,
        'confidence' => $o['confidence'] ?? 'medium',
        'latitude' => '',
        'longitude' => '',
        'coordinate_precision' => '',
        'coordinate_source' => '',
        'needs_review' => ! empty($o['review']) ? 1 : 0,
        'popular' => ! empty($o['popular']) ? 1 : 0,
        'active' => 1,
        'notes' => $o['notes'] ?? '',
    ];
}

const PROVINCES = [
    'Lahore' => 'Punjab', 'Karachi' => 'Sindh', 'Islamabad' => 'Islamabad Capital Territory', 'Multan' => 'Punjab',
    'Faisalabad' => 'Punjab', 'Quetta' => 'Balochistan', 'Peshawar' => 'Khyber Pakhtunkhwa', 'Rawalpindi' => 'Punjab',
];

/** Neighbourhoods/commercial areas: established localities, not housing schemes. */
function areas(string $city, array $names, array $o): void
{
    foreach ($names as $n) {
        [$name, $aliases] = is_array($n) ? $n : [$n, []];
        add($city, $name, $o + ['aliases' => $aliases]);
    }
}

/** Title-case an all-caps official name and expand the authority's abbreviations. */
function normaliseOfficial(string $s): string
{
    $map = [
        '/\bE:C:H:S:?/i' => ' Employees Cooperative Housing Society ', '/\bE:H:S:?/i' => ' Employees Housing Society ', '/\bC:H:S:?/i' => ' Cooperative Housing Society ',
        '/\bH:S\b|\bH\s?\/\s?S\b|\bH\.S\b/i' => ' Housing Scheme ', '/\bH\/SCHEME\b|\bH\.\s?SCHEME\b|\bH\.SCH\./i' => ' Housing Scheme ',
        '/\bH\.\s?SOCIETY\b/i' => ' Housing Society ', '/\bCOOP\b\.?|\bCO-OP\b\.?|\bCO OPPERATIVE\b|\bCOOPPERATIVE\b|\bC OPPERATIVE\b|\bCO-OPRATIVE\b|\bCO-OPERATIVE\b/i' => ' Cooperative ',
        '/\bEMPL?\b:?/i' => ' Employees ', '/\bENGG\b\.?/i' => ' Engineering ', '/\bARCH\./i' => ' Architects ', '/\bCOL\./i' => ' College ', '/\bSCH\.?$/i' => ' Scheme',
        '/\bPH(?:ASE)?[-\s]?III\b|\bP-?3\b/i' => ' Phase 3 ', '/\bPH(?:ASE)?[-\s]?II\b|\bP-?2\b|\bP-II\b/i' => ' Phase 2 ', '/\bPH(?:ASE)?[-\s]?I\b|\bP-?1\b|\bP-I\b/i' => ' Phase 1 ',
        '/\bPH-V\b/i' => ' Phase 5 ', '/\bEXTN?\b\.?/i' => ' Extension ', '/\bSECT?\b-?/i' => ' Sector ', '/\bGOVT\b/i' => ' Government ', '/\bREV\b/i' => ' Revised',
    ];
    $s = preg_replace(array_keys($map), array_values($map), $s);
    $s = preg_replace(['/\s+/', '/\s+([)\-:,])/', '/([(\-])\s+/'], [' ', '$1', '$1'], trim($s, ' .:-'));
    $s = ucwords(strtolower($s), ' -(/.');
    // Urdu/Arabic connectors stay lower case; Roman numerals and known acronyms upper case.
    $s = preg_replace_callback('/(?<=[\s-])(E|I|Ul|Ud|Of|And)(?=[\s-])/', fn ($m) => strtolower($m[1]), $s);
    $s = preg_replace_callback('/\b(Ii|Iii|Iv|Vi|Vii|Pcsir|Nespak|Opf|Pia|Nfc|Iep|Pgehs|Kemc|P&d)\b/', fn ($m) => strtoupper($m[1]), $s);

    return preg_replace('/(?<=[\s-])I$/', 'I', $s);
}

// ============================================================ CITIES
foreach (PROVINCES as $city => $_) {
    if ($city !== 'Rawalpindi') {
        add($city, $city, ['type' => 'city', 'authority' => '', 'status' => 'not_applicable', 'sources' => ['S18'], 'confidence' => 'high', 'popular' => 1]);
    }
}

// ============================================================ LAHORE
$L = 'Lahore';
$na = ['status' => 'not_applicable', 'authority' => 'Metropolitan Corporation Lahore / LDA', 'sources' => ['S00', 'S18']];
areas($L, [
    ['Gulberg', ['Gulberg Lahore']], 'Model Town', 'Garden Town', 'Faisal Town', 'Township', ['Johar Town', ['M.A. Johar Town', 'Muhammad Ali Johar Town']],
    ['Allama Iqbal Town', ['Iqbal Town']], 'Samanabad', 'Shadman', 'Cavalry Ground', 'Mozang', 'Ichhra', 'Muslim Town', 'Gulshan-e-Ravi', 'Sabzazar',
    'Mustafa Town', 'Shadbagh', 'Baghbanpura', 'Mughalpura', 'Harbanspura', 'Shalimar', ['Walled City of Lahore', ['Androon Shehr', 'Old City Lahore']],
    'Krishan Nagar', 'Garhi Shahu', 'Dharampura', 'Wahdat Colony', 'Gulshan-e-Lahore', 'Thokar Niaz Baig', 'Kot Lakhpat', 'Green Town', 'Township Sector A1',
    'Jubilee Town', 'Revenue Society', 'Canal View', 'Shah Jamal', 'Bhatti Gate', 'Raiwind',
], $na + ['type' => 'neighbourhood']);
add($L, 'Johar Town Phase 1', $na + ['type' => 'phase', 'parent' => 'Johar Town', 'aliases' => ['Johar Town Phase I']]);
add($L, 'Johar Town Phase 2', $na + ['type' => 'phase', 'parent' => 'Johar Town', 'aliases' => ['Johar Town Phase II']]);
foreach (['I', 'II', 'III', 'IV', 'V'] as $i => $roman) {
    add($L, 'Gulberg '.$roman, $na + ['type' => 'sub_area', 'parent' => 'Gulberg', 'aliases' => ['Gulberg '.($i + 1)]]);
}
add($L, 'Cantt', ['type' => 'cantonment', 'authority' => 'Lahore Cantonment Board', 'status' => 'not_applicable', 'aliases' => ['Lahore Cantt', 'Lahore Cantonment'], 'sources' => ['S00', 'S18']]);
add($L, 'Askari', ['type' => 'housing_scheme', 'authority' => 'Army Welfare Trust / Cantonment', 'status' => 'not_applicable', 'sources' => ['S00', 'S18'], 'notes' => 'Askari numbered schemes (e.g. Askari 10, 11) not split out; manual review.', 'review' => 1]);
areas($L, ['Raiwind Road', 'Bedian Road', 'Multan Road', ['Canal Road', ['Canal Bank Road']], 'Ferozepur Road', 'GT Road Lahore', 'Ring Road Lahore'], ['type' => 'corridor', 'status' => 'not_applicable', 'authority' => 'LDA', 'sources' => ['S00', 'S18'], 'notes' => 'Road corridor used by buyers to describe developments along it; not a single scheme.']);
areas($L, ['Liberty Market', ['MM Alam Road', ['M.M. Alam Road']], ['The Mall Lahore', ['Mall Road Lahore', 'Shahrah-e-Quaid-e-Azam']], 'Anarkali Bazaar', 'Hall Road', ['Main Boulevard Gulberg', ['Gulberg Main Boulevard']], 'Fortress Stadium'], ['type' => 'commercial_area', 'status' => 'not_applicable', 'authority' => 'LDA', 'sources' => ['S00', 'S18']]);

// DHA Lahore: phases (existing records keep their names), Rahbar and sectors (Graana guide, secondary).
add($L, 'DHA Lahore', ['type' => 'defence_scheme', 'authority' => 'DHA Lahore', 'status' => 'not_applicable', 'aliases' => ['DHA Defence Lahore', 'Defence Housing Authority Lahore', 'DHA'], 'sources' => ['S16', 'S18'], 'popular' => 1]);
foreach ([1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII'] as $n => $roman) {
    add($L, 'DHA Phase '.$n, ['type' => 'phase', 'parent' => 'DHA Lahore', 'authority' => 'DHA Lahore', 'status' => 'not_applicable', 'aliases' => ['DHA Phase '.$roman, 'Defence Phase '.$n], 'sources' => ['S16', 'S18'], 'popular' => in_array($n, [5, 6], true)]);
}
add($L, 'DHA Phase 9 Prism', ['type' => 'phase', 'parent' => 'DHA Lahore', 'authority' => 'DHA Lahore', 'status' => 'not_applicable', 'aliases' => ['DHA Phase IX Prism', 'DHA Prism'], 'sources' => ['S16']]);
add($L, 'DHA Phase 9 Town', ['type' => 'phase', 'parent' => 'DHA Lahore', 'authority' => 'DHA Lahore', 'status' => 'not_applicable', 'aliases' => ['DHA 9 Town', 'DHA Phase IX Town'], 'sources' => ['S16']]);
add($L, 'DHA Phase 10', ['type' => 'phase', 'parent' => 'DHA Lahore', 'authority' => 'DHA Lahore', 'status' => 'not_applicable', 'aliases' => ['DHA Phase X'], 'sources' => ['S16'], 'notes' => 'Under development per secondary sources.']);
add($L, 'DHA Phase 13', ['type' => 'phase', 'parent' => 'DHA Lahore', 'authority' => 'DHA Lahore', 'status' => 'not_applicable', 'aliases' => ['DHA Phase XIII'], 'sources' => ['S16'], 'confidence' => 'low', 'review' => 1, 'notes' => 'Secondary sources disagree on whether DHA Lahore has 12 or 13 phases; confirm with DHA Lahore.']);
add($L, 'DHA Rahbar', ['type' => 'phase', 'parent' => 'DHA Lahore', 'authority' => 'DHA Lahore', 'status' => 'not_applicable', 'aliases' => ['DHA Phase 11', 'DHA Phase XI', 'Rahbar'], 'sources' => ['S16', 'S17'], 'notes' => 'Treated as DHA Lahore Phase XI by secondary sources (Graana, Zameen).']);
foreach (['Rahbar Phase 1', 'Rahbar Phase 2', 'Rahbar Phase 2 Extension', 'Rahbar Phase 3 Halloki Gardens', 'Rahbar Phase 4'] as $rp) {
    add($L, 'DHA '.$rp, ['type' => 'sector', 'parent' => 'DHA Rahbar', 'authority' => 'DHA Lahore', 'status' => 'not_applicable', 'sources' => ['S17']]);
}
$dhaSectors = [1 => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P'], 2 => ['Q', 'R', 'S', 'T', 'U', 'V'], 3 => ['W', 'X', 'Y', 'Z', 'XX'], 4 => ['AA', 'BB', 'CC', 'DD', 'EE', 'FF', 'GG', 'HH', 'JJ']];
foreach ($dhaSectors as $phase => $letters) {
    foreach ($letters as $letter) {
        add($L, "DHA Phase {$phase} Sector {$letter}", ['type' => 'sector', 'parent' => 'DHA Phase '.$phase, 'authority' => 'DHA Lahore', 'status' => 'not_applicable', 'aliases' => ["DHA Phase {$phase} Block {$letter}", "{$letter} Block DHA Phase {$phase}"], 'sources' => ['S16'], 'confidence' => 'medium']);
    }
}
add($L, 'EME Society', ['type' => 'cooperative_society', 'authority' => 'LDA', 'aliases' => ['EME Cooperative Housing Society', 'DHA EME', 'DHA Phase 12', 'DHA Phase XII'], 'sources' => ['S04', 'S16'], 'review' => 1, 'notes' => 'Listed by LDA (2021, as reported) as "EME COOPPERATIVE HOUSING". Secondary sources describe it as DHA Lahore Phase XII (EME sector); the relationship to DHA is unconfirmed.']);

// Bahria Town Lahore: sectors and well-known blocks (market names; verify per block).
add($L, 'Bahria Town', ['type' => 'housing_scheme', 'authority' => 'LDA', 'aliases' => ['Bahria Town Lahore'], 'sources' => ['S04', 'S18'], 'popular' => 1, 'notes' => 'Only Sector A and Safari Town appear in the LDA 2021 list as reported; other sectors unverified.']);
foreach (['B', 'C', 'D', 'E', 'F'] as $s) {
    add($L, 'Bahria Town Sector '.$s, ['type' => 'sector', 'parent' => 'Bahria Town', 'authority' => 'LDA', 'sources' => ['S00', 'S18']]);
}
foreach (['Jasmine', 'Iris', 'Tulip', 'Gulbahar', 'Talha', 'Usman', 'Umar', 'Ali', 'Rafi', 'Nargis', 'Shaheen', 'Takbeer', 'Tauheed', 'Janiper', 'Overseas A', 'Overseas B'] as $b) {
    add($L, "Bahria Town {$b} Block", ['type' => 'block', 'parent' => 'Bahria Town', 'authority' => 'LDA', 'aliases' => ["{$b} Block Bahria Town"], 'sources' => ['S00', 'S18'], 'confidence' => 'low', 'review' => 1, 'notes' => 'Block name from common market usage; parent sector not recorded.']);
}
add($L, 'Bahria Orchard', ['type' => 'housing_scheme', 'authority' => 'LDA', 'sources' => ['S00', 'S18']]);
foreach ([1, 2, 3, 4] as $p) {
    add($L, 'Bahria Orchard Phase '.$p, ['type' => 'phase', 'parent' => 'Bahria Orchard', 'authority' => 'LDA', 'sources' => ['S00', 'S18'], 'confidence' => 'medium']);
}

// Existing app areas and other well-known schemes (status only where a source exists).
foreach ([
    'Lake City' => ['aliases' => ['Lake City Lahore'], 'popular' => 1], 'Valencia Town' => ['aliases' => ['Valencia Housing Society']], 'Wapda Town' => ['aliases' => ['WAPDA Town', 'Wapda Town Lahore'], 'popular' => 1],
    'Paragon City' => [], 'Park View City' => ['aliases' => ['Park View Villas Lahore']], 'Al Rehman Garden' => ['aliases' => ['Al-Rehman Garden']], 'Central Park' => ['aliases' => ['Central Park Housing Scheme']],
    'Eden City' => [], 'Etihad Town' => [], 'Sukh Chayn Gardens' => ['aliases' => ['Sukh Chain Gardens', 'Sukh Chayn']], 'State Life Housing Society' => ['aliases' => ['State Life Society']],
    'Punjab Coop Housing Society' => ['aliases' => ['Punjab Cooperative Housing Society', 'Punjab Society']], 'Fazaia Housing Scheme' => ['aliases' => ['Fazaia Housing Scheme Lahore']],
    'New Lahore City' => [], 'Lahore Motorway City' => [], 'LDA Avenue' => ['authority' => 'LDA', 'status' => 'not_applicable', 'notes' => 'LDA\'s own scheme.'], 'LDA City' => ['authority' => 'LDA', 'status' => 'not_applicable', 'notes' => 'LDA\'s own scheme.'],
    'Pak Arab Housing Society' => [], 'Izmir Town' => [], 'Bankers Avenue' => [], 'Khayaban-e-Amin' => [], 'IEP Town' => ['aliases' => ['Engineers Town']], 'PCSIR Housing Scheme' => [], 'Al-Kabir Town' => ['aliases' => ['Al Kabir Town']],
    'Eden Gardens' => [], 'Citi Housing Lahore' => ['review' => 1, 'confidence' => 'low'], 'DHA Rahbar' => [],
] as $name => $o) {
    add($L, $name, $o + ['type' => 'housing_scheme', 'authority' => 'LDA', 'sources' => ['S00', 'S18']]);
}
add($L, 'Wapda Town Phase 1', ['type' => 'phase', 'parent' => 'Wapda Town', 'authority' => 'LDA', 'sources' => ['S00']]);
add($L, 'Wapda Town Phase 2', ['type' => 'phase', 'parent' => 'Wapda Town', 'authority' => 'LDA', 'sources' => ['S00']]);
add($L, 'Fazaia Housing Scheme Phase 2', ['type' => 'phase', 'parent' => 'Fazaia Housing Scheme', 'authority' => 'LDA', 'sources' => ['S04']]);

// LDA approved list (2021, as reported). Names that match a record above update its status.
$ldaMatches = [
    'BAHRIA TOWN SECTOR A' => ['Bahria Town Sector A', 'Bahria Town'], 'BAHRIA TOWN(SAFARI TOWN)' => ['Bahria Town Safari Town', 'Bahria Town'],
    'CENTRAL PARK' => ['Central Park'], 'ETIHAD TOWN' => ['Etihad Town'], 'SUKH CHAIN GARDEN' => ['Sukh Chayn Gardens'], 'EME COOPPERATIVE HOUSING' => ['EME Society'],
    'PUNJAB CO OPPERATIVE' => ['Punjab Coop Housing Society'], 'LAKE CITY BELLA VISTA' => ['Lake City Bella Vista', 'Lake City'],
    'VALENCIA H/SCHEME PH I-IV' => ['Valencia Town Phase 1-4', 'Valencia Town'], 'VALENCIA H/SCHEME PH-V' => ['Valencia Town Phase 5', 'Valencia Town'],
    'WAPDA EMPL:C:H:S: PHASE-I' => ['Wapda Town Phase 1'], 'WAPDA EMPL:C:H:S: PHASE-I EXT' => ['Wapda Town Phase 1 Extension', 'Wapda Town'], 'WAPDA TOWN P2' => ['Wapda Town Phase 2'],
    'FAZAIA HOUSING SCHEME' => ['Fazaia Housing Scheme'], 'FAZAIA HOUSING SCHEME PHASE-II' => ['Fazaia Housing Scheme Phase 2'], 'NEW LAHORE CITY' => ['New Lahore City'],
    'PAK ARAB HOUSING SCHEME' => ['Pak Arab Housing Society'], 'IZMIR TOWN' => ['Izmir Town'], 'BANKERS AVENUE CO-OP H/S' => ['Bankers Avenue'], 'KHYABAN-E-AMIN' => ['Khayaban-e-Amin'],
    'IEP TOWN SEC A' => ['IEP Town Sector A', 'IEP Town'], 'IEP M TOWN SEC B' => ['IEP Town Sector B', 'IEP Town'], 'ALKABIR TOWN' => ['Al-Kabir Town'], 'EDEN GARDENS' => ['Eden Gardens'],
    'EDEN GARDENS EXT' => ['Eden Gardens Extension', 'Eden Gardens'], 'SATELLITE TOWN' => ['Satellite Town Lahore'],
];
foreach ($official['lda_approved_2021'] as $raw) {
    $match = $ldaMatches[$raw] ?? [normaliseOfficial($raw)];
    [$name, $parent] = [$match[0], $match[1] ?? null];
    $known = isset($ldaMatches[$raw]);
    add($L, $name, array_filter([
        'type' => $parent ? 'phase' : 'housing_scheme', 'parent' => $parent, 'official' => $raw, 'authority' => 'LDA',
        'status' => 'approved', 'ref' => 'LDA list of 256 approved housing schemes (as reported by Zameen News, 1 Mar 2021)',
        'sources' => ['S04'], 'url' => 'https://www.zameen.com/news/list-of-lda-approved-housing-schemes-in-lahore-district.html',
        'confidence' => 'medium', 'review' => ! $known && preg_match('/[?:()]|[A-Z][0-9]|IRRIGATIONSOCIETY|EMP\b|SCH\.|REVNUE|TAXSATION|SUPIRIOR|SERVENT|JODICIAL|OPPERATIVE|NORTHEN|ENTER PRISERS/', $raw) ? 1 : 0,
        'notes' => $known ? '' : 'Display name normalised from the official all-caps listing; check spelling against LDA records.',
    ], fn ($v) => $v !== null && $v !== ''));
}

// ============================================================ KARACHI
$K = 'Karachi';
$kna = ['type' => 'neighbourhood', 'status' => 'not_applicable', 'authority' => 'Karachi Metropolitan Corporation / KDA', 'sources' => ['S00', 'S18']];
areas($K, [
    ['Gulshan-e-Iqbal', ['Gulshan e Iqbal', 'Gulshan']], ['Gulistan-e-Jauhar', ['Gulistan e Johar', 'Jauhar']], ['North Nazimabad', ['N. Nazimabad']], 'Nazimabad',
    'North Karachi', ['Federal B Area', ['F.B. Area', 'FB Area', 'Federal B. Area']], ['Malir', []], ['Shah Faisal Town', ['Shah Faisal Colony']], 'Korangi', 'Landhi',
    ['PECHS', ['Pakistan Employees Cooperative Housing Society']], 'Bahadurabad', 'Saddar', 'Garden East', 'Garden West', 'Lyari', 'Kharadar', 'Keamari', 'Surjani Town',
    'Naya Nazimabad', 'Gulshan-e-Maymar', 'Bath Island', 'Frere Town', 'Civil Lines', 'Gizri', 'Mehmoodabad', 'Model Colony', 'Shah Latif Town', 'Metroville Colony',
    'New Karachi', 'Orangi Town', 'Baldia Town', 'Liaquatabad', 'Gulshan-e-Hadeed', 'Gulberg Town Karachi', 'Defence View', 'Jamshed Town', 'Soldier Bazaar',
    'Pir Ahmed Zaman Town', 'Safoora Goth', 'Gadap Town', 'Kemari Town',
], $kna);
areas($K, [['Scheme 33', ['KDA Scheme 33', 'Gulzar-e-Hijri']], ['Scheme 45', ['KDA Scheme 45', 'Taiser Town']], ['KDA Scheme 1', ['Karsaz Scheme 1']], ['KAECHS', ['Karachi Administration Employees Cooperative Housing Society']],
    'Capital Cooperative Housing Society', 'Saima Arabian Villas'], ['type' => 'housing_scheme', 'status' => 'unverified', 'authority' => 'KDA / SBCA', 'sources' => ['S00', 'S19', 'S18'], 'notes' => 'Approval status not verified: SBCA/KDA scheme lists were not accessed in this research.']);
add($K, 'Malir Cantonment', ['type' => 'cantonment', 'authority' => 'Malir Cantonment Board', 'status' => 'not_applicable', 'aliases' => ['Malir Cantt'], 'sources' => ['S00', 'S18']]);
add($K, 'Faisal Cantonment', ['type' => 'cantonment', 'authority' => 'Faisal Cantonment Board', 'status' => 'not_applicable', 'sources' => ['S19', 'S18']]);
add($K, 'Navy Housing Scheme Karsaz', ['type' => 'housing_scheme', 'authority' => 'Pakistan Navy / Faisal Cantonment', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
foreach (['Askari 4', 'Askari 5'] as $a) {
    add($K, $a.' Karachi', ['type' => 'housing_scheme', 'authority' => 'Army Welfare Trust / Malir Cantonment', 'status' => 'not_applicable', 'sources' => ['S00', 'S18'], 'review' => 1]);
}
areas($K, ['Tariq Road', ['Shahrah-e-Faisal', ['Sharae Faisal']], 'Zamzama', ['Korangi Industrial Area', ['KIA']], ['SITE Karachi', ['Sindh Industrial Trading Estate', 'SITE']], 'II Chundrigar Road'], ['type' => 'commercial_area', 'status' => 'not_applicable', 'authority' => 'KMC', 'sources' => ['S00', 'S19', 'S18']]);
add($K, 'Clifton', ['type' => 'neighbourhood', 'authority' => 'Cantonment Board Clifton', 'status' => 'not_applicable', 'sources' => ['S00', 'S19', 'S18'], 'popular' => 1]);
foreach (range(1, 9) as $b) {
    add($K, 'Clifton Block '.$b, ['type' => 'block', 'parent' => 'Clifton', 'authority' => 'Cantonment Board Clifton', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
}
add($K, 'DHA Karachi', ['type' => 'defence_scheme', 'authority' => 'DHA Karachi', 'status' => 'not_applicable', 'aliases' => ['DHA Defence Karachi', 'Defence Housing Authority Karachi', 'DHA Defence'], 'sources' => ['S13', 'S19'], 'url' => 'https://en.wikipedia.org/wiki/Defence_Housing_Authority,_Karachi', 'popular' => 1]);
foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $p) {
    add($K, 'DHA Phase '.$p.' Karachi', ['type' => 'phase', 'parent' => 'DHA Karachi', 'authority' => 'DHA Karachi', 'status' => 'not_applicable', 'aliases' => ['DHA Karachi Phase '.$p, 'Defence Phase '.$p.' Karachi'], 'sources' => ['S13'],
        'review' => $p === 3 ? 1 : 0, 'notes' => $p === 3 ? 'Wikipedia lists Phase 3; portal guides omit it. Confirm with DHA Karachi.' : '']);
}
foreach (['DHA Phase 2 Extension Karachi', 'DHA Phase 7 Extension Karachi', 'DHA Phase 8 Extension Karachi'] as $e) {
    add($K, $e, ['type' => 'phase', 'parent' => 'DHA Karachi', 'authority' => 'DHA Karachi', 'status' => 'not_applicable', 'sources' => ['S13', 'S00'], 'confidence' => 'medium']);
}
add($K, 'DHA City Karachi', ['type' => 'defence_scheme', 'authority' => 'DHA Karachi', 'status' => 'not_applicable', 'aliases' => ['DCK', 'DHA City'], 'sources' => ['S13', 'S19'], 'notes' => 'On the M-9 motorway (Super Highway); sometimes called the 9th phase.']);
add($K, 'Bahria Town Karachi', ['type' => 'housing_scheme', 'authority' => 'MDA (Malir Development Authority)', 'status' => 'unverified', 'sources' => ['S19', 'S18'], 'popular' => 1, 'review' => 1, 'notes' => 'Subject to Supreme Court proceedings over land; approval status not verified here. Precincts beyond Precinct 15 need manual research.']);
add($K, 'Bahria Town Karachi Precinct 15', ['type' => 'sector', 'parent' => 'Bahria Town Karachi', 'authority' => 'MDA (Malir Development Authority)', 'status' => 'unverified', 'aliases' => ['Bahria Town Precinct 15'], 'sources' => ['S19']]);

// ============================================================ ISLAMABAD
$I = 'Islamabad';
$sectors = ['B-17', 'C-14', 'C-15', 'D-12', 'D-17', 'E-7', 'E-8', 'E-9', 'E-10', 'E-11', 'E-12', 'E-16', 'E-17', 'F-5', 'F-6', 'F-7', 'F-8', 'F-9', 'F-10', 'F-11',
    'G-5', 'G-6', 'G-7', 'G-8', 'G-9', 'G-10', 'G-11', 'G-13', 'G-14', 'H-8', 'H-9', 'H-10', 'H-11', 'H-12', 'I-8', 'I-9', 'I-10', 'I-11', 'I-12', 'I-14', 'I-15', 'I-16'];
foreach ($sectors as $s) {
    add($I, $s, ['type' => 'sector', 'authority' => 'CDA', 'status' => 'not_applicable', 'aliases' => [str_replace('-', '', $s), str_replace('-', ' ', $s), 'Sector '.$s], 'sources' => ['S03'], 'url' => 'https://en.wikipedia.org/wiki/Sectors_of_Islamabad', 'confidence' => 'high',
        'popular' => in_array($s, ['F-7', 'F-6', 'E-11', 'G-11', 'F-10', 'I-8'], true), 'notes' => $s === 'E-11' ? 'Private schemes are also permitted in E-11 (CDA).' : '']);
}
areas($I, [['Bani Gala', ['Bani Galla']], 'Chak Shahzad', 'Shahzad Town', 'Tarlai', ['Bhara Kahu', ['Bharakahu']], ['Golra Sharif', ['Golra']], 'Saidpur', 'Diplomatic Enclave', 'Nilore', 'Rawat'], ['type' => 'neighbourhood', 'authority' => 'CDA / ICT Administration', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
areas($I, [['Blue Area', ['Jinnah Avenue']], ['Jinnah Super Market', ['F-7 Markaz', 'Jinnah Super']], 'Kohsar Market', 'Aabpara Market', 'Centaurus Mall'], ['type' => 'commercial_area', 'authority' => 'CDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
areas($I, ['Islamabad Expressway', 'Kashmir Highway', 'Srinagar Highway', 'Park Road', 'Lehtrar Road', 'Simly Dam Road', 'Kuri Road'], ['type' => 'corridor', 'authority' => 'CDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
add($I, 'Park Enclave', ['type' => 'government_scheme', 'authority' => 'CDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S18'], 'notes' => 'CDA\'s own residential scheme.']);
add($I, 'DHA Islamabad Phase 2', ['type' => 'defence_scheme', 'authority' => 'DHA Islamabad-Rawalpindi', 'status' => 'not_applicable', 'aliases' => ['DHA Phase 2 Islamabad', 'DHA Phase II Islamabad', 'DHA Islamabad'], 'sources' => ['S15', 'S00'], 'url' => 'https://v2.dhai-r.com.pk/phases/phase-ii-ext/', 'popular' => 1,
    'notes' => 'Phase 2 lies in Islamabad (Zone V, Islamabad Highway). DHA Phases 1 and 4 are in Rawalpindi District and are excluded (Rawalpindi is not a launch city).']);
add($I, 'Top City-1', ['type' => 'housing_scheme', 'authority' => 'CDA (to confirm)', 'aliases' => ['Top City 1', 'Top City Islamabad'], 'sources' => ['S00', 'S18'], 'review' => 1, 'confidence' => 'low', 'notes' => 'Not on the CDA private-scheme or illegal lists accessed; jurisdiction and approval unconfirmed.']);
add($I, 'Faisal Town Phase 1 (F-18)', ['type' => 'housing_scheme', 'authority' => 'CDA (to confirm)', 'aliases' => ['Faisal Town F-18', 'Faisal Town Islamabad'], 'sources' => ['S00'], 'review' => 1, 'confidence' => 'low', 'notes' => 'Distinct from "Faisal Town, Islamabad Highway" on the CDA illegal list; status unverified.']);
foreach (['PWD Housing Scheme' => ['PWD Society', 'PWD'], 'Pakistan Town' => ['Pakistan Town Islamabad'], 'Korang Town' => ['Korang Town Islamabad'], 'Gulberg Islamabad' => ['Gulberg Greens and Residencia']] as $n => $al) {
    add($I, $n, ['type' => 'housing_scheme', 'authority' => 'CDA (to confirm)', 'aliases' => $al, 'sources' => ['S00', 'S18'], 'review' => 1, 'confidence' => 'low',
        'notes' => $n === 'Gulberg Islamabad' ? 'Umbrella market name; CDA lists Gulberg Greens and Gulberg Residencia separately.' : 'On Islamabad Expressway near the Islamabad/Rawalpindi boundary; confirm jurisdiction before treating as Islamabad.']);
}

$cdaStatus = function (array $s): array {
    $lop = strtolower($s['lop'] ?? '');
    $noc = strtolower($s['noc'] ?? '');

    return match (true) {
        $lop === 'cancelled' => ['cancelled', 'CDA: layout plan cancelled'.($noc ? ", NOC {$noc}" : '')],
        $noc === 'cancelled' => ['cancelled', 'CDA: layout plan approved, NOC cancelled'],
        $noc === 'issued' || $noc === 'approved' => ['noc_issued', 'CDA: layout plan approved, NOC issued'],
        $lop === 'provisional approval' => ['under_process', 'CDA: layout plan provisionally approved, NOC not issued'],
        $lop === 'approved' => ['layout_plan_approved', 'CDA: layout plan approved, NOC '.($noc ?: 'not stated')],
        default => ['unverified', 'CDA listing without status'],
    };
};
$cdaNames = [
    'Gulberg Greens Farm Housing Scheme' => ['Gulberg Greens', ['Gulberg Greens Islamabad']], 'Gulberg Residencia (IBECHS Ph-III) Housing Scheme' => ['Gulberg Residencia', ['IBECHS Phase III']],
    'Multi Gardens, Phase-I' => ['Multi Gardens B-17 Phase 1', ['B-17 Multi Gardens', 'MPCHS B-17']], 'Multi Gardens, Phase-II' => ['Multi Gardens B-17 Phase 2', ['B-17 Multi Gardens Phase 2']],
    'Naval Anchorage' => ['Naval Anchorage', ['Navy Anchorage']], 'Soan Garden Housing Scheme' => ['Soan Garden', ['Soan Gardens']], 'Park View City Housing Scheme' => ['Park View City Islamabad', ['Park View City']],
    'Federal Government Employees Housing Foundation (FGEHF)' => ['FGEHF Housing Scheme', ['FGEHA', 'Federal Government Employees Housing Foundation']],
    'Bahria Town, Phase-II,III,V & VI' => ['Bahria Town Islamabad Phases 2, 3, 5 & 6', ['Bahria Town Islamabad']], 'Bahria Town, Phase VII' => ['Bahria Town Islamabad Phase 7', ['Bahria Town Phase 7']],
    'Bahria Town (Phase-VII-E)' => ['Bahria Town Islamabad Phase 7-E', []], 'Bahria Town (Phase-III-E- IV)' => ['Bahria Town Islamabad Phase 3-E & 4', []],
];
foreach ($cda['schemes'] as $s) {
    [$status, $ref] = $cdaStatus($s);
    [$name, $al] = $cdaNames[$s['name']] ?? [$s['name'], []];
    add($I, $name, ['type' => 'housing_scheme', 'official' => $s['name'], 'aliases' => $al, 'authority' => 'CDA', 'status' => $status,
        'ref' => trim($ref.'. '.($s['zone'] ?? '').(! empty($s['road']) ? ', '.$s['road'] : '').(! empty($s['area']) ? ', '.$s['area'] : '')),
        'sources' => ['S01'], 'url' => 'https://www.cda.gov.pk/public/housing-schemes', 'confidence' => 'high', 'popular' => in_array($name, ['Gulberg Greens', 'Gulberg Residencia', 'Bahria Town Islamabad Phases 2, 3, 5 & 6'], true)]);
}
foreach ($cda['illegal'] as $raw) {
    $raw = trim($raw);
    if ($raw === '') {
        continue;
    }
    add($I, $raw, ['type' => 'housing_scheme', 'official' => $raw, 'authority' => 'CDA', 'status' => 'declared_illegal', 'ref' => 'Listed by CDA as an illegal private housing / agro-farming scheme (no CDA approval or NOC)',
        'sources' => ['S02'], 'url' => 'https://www.cda.gov.pk/public/illegal-housing-schemes', 'confidence' => 'high']);
}

// ============================================================ MULTAN
$M = 'Multan';
areas($M, ['Gulgasht Colony', 'Mumtazabad', 'New Multan', 'Qasim Bela', 'Chowk Kumharan', 'Shah Shams Colony', 'Officers Colony Multan', 'Garden Town Multan', 'Shalimar Colony', 'Nishtar Colony',
    'Old Shujabad Road Area', 'Suraj Miani', 'Nawabpur', 'Mattital'], ['type' => 'neighbourhood', 'authority' => 'MDA / Metropolitan Corporation Multan', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
areas($M, ['Bosan Road', 'Northern Bypass', 'Southern Bypass', 'Vehari Road', 'Khanewal Road', 'Shujabad Road', 'Bahawalpur Road', 'LMQ Road', 'Multan Public School Road', 'Askaria Bypass Road'], ['type' => 'corridor', 'authority' => 'MDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S10', 'S18']]);
areas($M, [['Hussain Agahi', ['Hussain Agahi Bazaar']], 'Abdali Road', 'Nishtar Road', 'Cantt Market Multan'], ['type' => 'commercial_area', 'authority' => 'MDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
add($M, 'Multan Cantt', ['type' => 'cantonment', 'authority' => 'Multan Cantonment Board', 'status' => 'not_applicable', 'aliases' => ['Multan Cantonment'], 'sources' => ['S00', 'S18']]);
add($M, 'DHA Multan', ['type' => 'defence_scheme', 'authority' => 'DHA Multan', 'status' => 'not_applicable', 'aliases' => ['DHA Defence Multan', 'Defence Housing Authority Multan'], 'sources' => ['S00', 'S18'], 'popular' => 1, 'notes' => 'Sectors not researched; manual review.']);
foreach (['Multan Model Town' => ['Model Town Multan'], 'Shah Rukn-e-Alam Colony' => ['Shah Rukn e Alam', 'SRA Colony'], 'FJ Town Phase 1' => ['Fatima Jinnah Town Phase 1'], 'FJ Town Phase 2' => ['Fatima Jinnah Town Phase 2']] as $n => $al) {
    add($M, $n, ['type' => 'government_scheme', 'authority' => 'MDA', 'status' => 'not_applicable', 'aliases' => $al, 'sources' => ['S10'], 'url' => 'https://mda.punjab.gov.pk', 'confidence' => 'high', 'notes' => 'Listed among MDA\'s own projects on the MDA website.']);
}
add($M, 'Wapda Town Multan', ['type' => 'housing_scheme', 'authority' => 'MDA', 'aliases' => ['WAPDA Town Multan'], 'sources' => ['S00', 'S18']]);
$mdaNames = ['Citi Housing HS' => 'Citi Housing Multan', 'Citi Housing Private Ltd' => 'Citi Housing Multan Extension', 'Royal Orchard HS' => 'Royal Orchard Multan', 'Royal Orchard (Extension)' => 'Royal Orchard Multan Extension', 'Buch Villas HS' => 'Buch Villas'];
foreach ($official['mda_approved'] as [$n, $mouza, $road, $area, $date, $zone]) {
    $name = $mdaNames[$n] ?? $n;
    add($M, $name, ['type' => 'housing_scheme', 'official' => $n, 'authority' => 'MDA', 'status' => 'approved',
        'ref' => "MDA sanctioned {$date} ({$zone} Zone; mouza {$mouza}; {$road}; {$area} kanal)",
        'aliases' => $name !== $n ? [$n] : [], 'sources' => ['S10'], 'url' => 'https://mda.punjab.gov.pk/system/files/approved.pdf', 'confidence' => 'high',
        'popular' => in_array($name, ['Citi Housing Multan', 'Royal Orchard Multan', 'Buch Villas'], true)]);
}

// ============================================================ FAISALABAD
$F = 'Faisalabad';
areas($F, ['Madina Town', ['Peoples Colony', ['People\'s Colony']], 'Peoples Colony 1', 'Peoples Colony 2', ['Gulberg Faisalabad', ['Gulberg']], 'Millat Town', ['Samanabad Faisalabad', ['Samanabad']], 'Jinnah Colony',
    'Gulistan Colony', 'Civil Lines Faisalabad', 'Raza Abad', 'Ghulam Muhammad Abad', 'Allama Iqbal Colony', 'Saeed Colony', 'Officers Colony Faisalabad', 'Nisar Colony', 'Mansoorabad',
    'Batala Colony', 'Al-Najaf Colony', 'Kohinoor City', 'Abdullah Garden'], ['type' => 'neighbourhood', 'authority' => 'FDA / Metropolitan Corporation Faisalabad', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
foreach (['Peoples Colony 1', 'Peoples Colony 2'] as $pc) {
    add($F, $pc, ['parent' => 'Peoples Colony']);
}
areas($F, ['Canal Road Faisalabad', 'Satiana Road', 'Jaranwala Road', 'Sargodha Road', 'Susan Road', 'Jhang Road', 'Sheikhupura Road'], ['type' => 'corridor', 'authority' => 'FDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
areas($F, [['Clock Tower Faisalabad', ['Ghanta Ghar']], ['D-Ground', ['D Ground']], 'Kohinoor Plaza Area'], ['type' => 'commercial_area', 'authority' => 'FDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
foreach (['WAPDA City' => ['Wapda City Faisalabad'], 'Eden Gardens Faisalabad' => ['Eden Garden Faisalabad'], 'Citi Housing Faisalabad' => ['Citi Housing Society Faisalabad'], 'FDA City' => ['FDA City Faisalabad'],
    'Eden Valley Faisalabad' => [], 'Canal Garden Faisalabad' => [], 'Sitara Valley' => []] as $n => $al) {
    add($F, $n, ['type' => $n === 'FDA City' ? 'government_scheme' : 'housing_scheme', 'authority' => 'FDA', 'status' => $n === 'FDA City' ? 'not_applicable' : 'unverified', 'aliases' => $al,
        'sources' => ['S00', 'S18'], 'popular' => in_array($n, ['WAPDA City', 'Citi Housing Faisalabad', 'FDA City'], true),
        'notes' => $n === 'FDA City' ? 'FDA\'s own scheme.' : 'FDA approval lists could not be accessed (fda.gop.pk unreachable); status unverified.', 'review' => in_array($n, ['Eden Valley Faisalabad', 'Canal Garden Faisalabad', 'Sitara Valley'], true) ? 1 : 0]);
}

// ============================================================ QUETTA
$Q = 'Quetta';
areas($Q, ['Satellite Town Quetta', 'Jinnah Town', 'Shahbaz Town', 'Model Town Quetta', 'Hazara Town', 'Marriabad', 'Nawa Killi', 'Chashma Achozai', 'Kuchlak', 'Sariab', 'Killi Shabo', 'Faizabad Quetta', 'Pashtoonabad'],
    ['type' => 'neighbourhood', 'authority' => 'QDA / Metropolitan Corporation Quetta', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
areas($Q, ['Samungli Road', 'Airport Road Quetta', 'Sariab Road', 'Brewery Road', 'Spinny Road', 'Nawa Killi Road', 'Qambrani Road', 'Western Bypass Quetta', 'Eastern Bypass Quetta', 'Arbab Karam Khan Road', 'Kasi Road'],
    ['type' => 'corridor', 'authority' => 'QDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S07', 'S18']]);
areas($Q, ['Jinnah Road Quetta', 'Prince Road', 'Liaquat Bazaar'], ['type' => 'commercial_area', 'authority' => 'QDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
add($Q, 'Quetta Cantonment', ['type' => 'cantonment', 'authority' => 'Quetta Cantonment Board', 'status' => 'not_applicable', 'aliases' => ['Quetta Cantt'], 'sources' => ['S00', 'S18']]);
add($Q, 'DHA Quetta', ['type' => 'defence_scheme', 'authority' => 'DHA Quetta', 'status' => 'not_applicable', 'aliases' => ['Defence Housing Authority Quetta'], 'sources' => ['S00', 'S18'], 'popular' => 1, 'notes' => 'DHA Quetta is governed by its own act, not QDA. Phases/sectors not researched.']);
foreach (['Zarghoon Housing Scheme' => ['QDA Zarghoon Housing Scheme'], 'Chaman Housing Scheme' => []] as $n => $al) {
    add($Q, $n, ['type' => 'government_scheme', 'authority' => 'QDA', 'status' => 'not_applicable', 'aliases' => $al, 'sources' => ['S07', 'S00'], 'notes' => 'QDA scheme (referenced as "QDA Zarghoon Housing Scheme Road" in the QDA illegal list).']);
}
foreach ($official['qda_approved'] as $no => [$n, $file]) {
    add($Q, $n, ['type' => 'housing_scheme', 'official' => $n, 'authority' => 'QDA', 'status' => 'approved', 'ref' => "QDA: NOC approved, file {$file} (approved list S.No {$no})",
        'sources' => ['S06'], 'url' => 'https://qda.gob.pk/img/scheme_lists/approved_scheme.pdf', 'confidence' => 'high']);
}
foreach ($official['qda_illegal'] as [$n, $loc]) {
    add($Q, $n, ['type' => 'housing_scheme', 'official' => $n, 'authority' => 'QDA', 'status' => 'declared_illegal', 'ref' => 'QDA "Illegal Private Housing Scheme" list, No. 745 dated 09-12-2024'.($loc ? "; location: {$loc}" : ''),
        'sources' => ['S07'], 'url' => 'https://qda.gob.pk/img/scheme_lists/illegal_scheme_list.pdf', 'confidence' => 'high']);
}
foreach ($official['qda_under_process'] as $n) {
    add($Q, $n, ['type' => 'housing_scheme', 'official' => $n, 'authority' => 'QDA', 'status' => 'under_process', 'ref' => 'QDA "Under Process Private Housing Scheme" list (NOC not issued unless stated)',
        'sources' => ['S08'], 'url' => 'https://qda.gob.pk/img/scheme_lists/under%20process.pdf', 'confidence' => 'medium', 'review' => 1,
        'notes' => 'Name read from a misaligned PDF table; location column not used.']);
}

// ============================================================ PESHAWAR
$P = 'Peshawar';
add($P, 'Hayatabad', ['type' => 'government_scheme', 'authority' => 'PDA', 'status' => 'not_applicable', 'aliases' => ['Hayatabad Peshawar'], 'sources' => ['S14', 'S18'], 'url' => 'https://en.wikipedia.org/wiki/Hayatabad', 'popular' => 1,
    'notes' => 'Seven numbered phases, each with lettered sectors (A–H) per Wikipedia; sector-level records not created pending verification.']);
foreach (range(1, 7) as $p) {
    add($P, 'Hayatabad Phase '.$p, ['type' => 'phase', 'parent' => 'Hayatabad', 'authority' => 'PDA', 'status' => 'not_applicable', 'aliases' => ['Phase '.$p.' Hayatabad'], 'sources' => ['S14'], 'url' => 'https://en.wikipedia.org/wiki/Hayatabad',
        'notes' => $p === 5 ? 'Described as mainly institutional (PDA) with little residential property.' : ($p === 7 ? 'Older sources say reserved for future use; current listings exist.' : '')]);
}
areas($P, ['University Town', 'Gulbahar', 'Doranpur', 'Shahi Bagh', 'Tehkal', 'Faqirabad', 'Defence Officers Colony', 'Gulshan Rehman Colony', 'Sheikh Yasin Town', 'Professors Colony'],
    ['type' => 'neighbourhood', 'authority' => 'PDA / TMA', 'status' => 'not_applicable', 'sources' => ['S00', 'S21', 'S18']]);
areas($P, ['University Road', 'Warsak Road', 'Ring Road Peshawar', 'Nasir Bagh Road', 'Dalazak Road', 'Charsadda Road', 'Kohat Road', 'Jamrud Road', 'Shami Road', 'Arbab Road', 'Old Bara Road'],
    ['type' => 'corridor', 'authority' => 'PDA', 'status' => 'not_applicable', 'sources' => ['S00', 'S21', 'S18']]);
areas($P, ['Board Bazaar', 'Saddar Peshawar', 'Khyber Bazaar', 'Qissa Khwani Bazaar'], ['type' => 'commercial_area', 'authority' => 'PDA / Cantonment', 'status' => 'not_applicable', 'sources' => ['S00', 'S18']]);
add($P, 'Peshawar Cantt', ['type' => 'cantonment', 'authority' => 'Peshawar Cantonment Board', 'status' => 'not_applicable', 'aliases' => ['Peshawar Cantonment'], 'sources' => ['S00', 'S18']]);
add($P, 'DHA Peshawar', ['type' => 'defence_scheme', 'authority' => 'DHA Peshawar', 'status' => 'not_applicable', 'aliases' => ['Defence Housing Authority Peshawar'], 'sources' => ['S00', 'S18'], 'popular' => 1, 'notes' => 'Sectors not researched.']);
add($P, 'Regi Model Town', ['type' => 'government_scheme', 'authority' => 'PDA', 'status' => 'not_applicable', 'aliases' => ['Regi Lalma', 'RMT'], 'sources' => ['S21', 'S18'], 'popular' => 1, 'notes' => 'PDA scheme; zones not researched.']);
foreach (['Nova City Peshawar' => ['Nova City'], 'Shaheen Muslim Town' => [], 'New University Town' => [], 'Safia Homes Peshawar' => ['Safia Homes'], 'Shaheen Housing Scheme Peshawar' => ['Shaheen Housing Scheme']] as $n => $al) {
    add($P, $n, ['type' => 'housing_scheme', 'authority' => 'PDA', 'status' => 'unverified', 'aliases' => $al, 'sources' => $n === 'Nova City Peshawar' ? ['S21', 'S18'] : ['S00', 'S18'], 'review' => $n === 'Nova City Peshawar' ? 0 : 1,
        'notes' => 'PDA approval lists could not be accessed (pda.gkp.pk unreachable). In Jan 2021, 181 schemes were banned on PDA\'s recommendation (names not obtained).']);
}

// ============================================================ RAWALPINDI (research only, not imported)
foreach (['DHA Islamabad Phase 1' => 'Graana lists "DHA Phase 1, Sector F Rawalpindi".', 'DHA Islamabad Phase 4' => 'Within Rawalpindi District since 2014 per secondary sources.', 'Bahria Town Phase 8' => 'Bahria Town Phase 8 is in Rawalpindi District.'] as $n => $why) {
    add('Rawalpindi', $n, ['type' => 'phase', 'authority' => 'DHA Islamabad-Rawalpindi / RDA', 'status' => 'not_applicable', 'sources' => ['S15', 'S00'], 'notes' => 'Marketed with Islamabad but located in Rawalpindi. '.$why.' Not imported: Rawalpindi is not a launch city.']);
}
foreach ($rows as &$r) {
    if ($r['city'] === 'Rawalpindi') {
        $r['active'] = 0;
    }
}
unset($r);

// ============================================================ VALIDATE + WRITE
$keys = array_flip(array_keys($rows));
$problems = [];
foreach ($rows as $r) {
    if ($r['parent_key'] && ! isset($keys[$r['parent_key']])) {
        $problems[] = "Missing parent {$r['parent_key']} for {$r['key']}";
    }
}
if ($problems) {
    fwrite(STDERR, implode("\n", $problems)."\n");
    exit(1);
}

$out = fopen(__DIR__.'/Meezan_Pakistan_Locations.csv', 'w');
fputcsv($out, array_keys(reset($rows)));
foreach ($rows as $r) {
    fputcsv($out, $r);
}
fclose($out);

$schemeTypes = ['housing_scheme', 'cooperative_society', 'government_scheme', 'defence_scheme'];
$out = fopen(__DIR__.'/Meezan_Pakistan_Housing_Societies.csv', 'w');
$cols = ['key', 'city', 'name', 'official_name', 'type', 'parent_key', 'authority', 'approval_status', 'approval_reference', 'source_ids', 'source_url', 'research_date', 'confidence', 'needs_review', 'notes'];
fputcsv($out, $cols);
foreach ($rows as $r) {
    if (in_array($r['type'], $schemeTypes, true)) {
        fputcsv($out, array_map(fn ($c) => $r[$c], $cols));
    }
}
fclose($out);

$sources = [
    ['S00', 'Established local usage / researcher knowledge (requires confirmation)', 'Meezan Property research', '', 'secondary', 'n/a', 'Used only to propose candidate names; each is confirmed by another source or OpenStreetMap match, or flagged needs_review.'],
    ['S01', 'CDA — Private Housing Schemes (registered, with LOP/NOC status)', 'Capital Development Authority', 'https://www.cda.gov.pk/public/housing-schemes', 'official', 'accessed', '70 schemes parsed with zone, road, area, LOP and NOC status.'],
    ['S02', 'CDA — Illegal Private Housing Schemes', 'Capital Development Authority', 'https://www.cda.gov.pk/public/illegal-housing-schemes', 'official', 'accessed', '98 entries.'],
    ['S03', 'Sectors of Islamabad', 'Wikipedia', 'https://en.wikipedia.org/wiki/Sectors_of_Islamabad', 'secondary', 'accessed', 'Sector series and Zone I/II sectors.'],
    ['S04', 'List of LDA-approved housing schemes in Lahore district (256)', 'Zameen News, citing the LDA website', 'https://www.zameen.com/news/list-of-lda-approved-housing-schemes-in-lahore-district.html', 'secondary (reports official list)', 'accessed', 'Dated 1 Mar 2021; may be outdated.'],
    ['S05', 'Lahore Development Authority website', 'LDA', 'https://www.lda.gop.pk', 'official', 'inaccessible', 'Connection failed from the research machine; current approved/illegal lists not verified.'],
    ['S06', 'QDA Approved Private Housing Scheme (scanned)', 'Quetta Development Authority', 'https://qda.gob.pk/img/scheme_lists/approved_scheme.pdf', 'official', 'accessed', '51 rows; row 30 unreadable (page break).'],
    ['S07', 'QDA Illegal Private Housing Scheme, No. 745 dated 09-12-2024 (scanned)', 'Quetta Development Authority', 'https://qda.gob.pk/img/scheme_lists/illegal_scheme_list.pdf', 'official', 'accessed', '139 rows transcribed; personal contact details omitted.'],
    ['S08', 'QDA Under Process Private Housing Scheme', 'Quetta Development Authority', 'https://qda.gob.pk/img/scheme_lists/under%20process.pdf', 'official', 'accessed', '110 rows; names only (table misaligned); CNIC/phone data in source deliberately not copied.'],
    ['S09', 'MDA List of ILLEGAL Private Housing Schemes / Land Sub Divisions (Urdu, scanned)', 'Multan Development Authority', 'https://mda.punjab.gov.pk/system/files/illegal.pdf', 'official', 'accessed, not transcribed', 'Urdu scanned notice (~4 pages); not transcribed — manual review needed before marking Multan schemes illegal.'],
    ['S10', 'MDA Updated List of Sanctioned/Approved Private Housing Schemes / LSDs (Sep 26, 2025)', 'Multan Development Authority', 'https://mda.punjab.gov.pk/system/files/approved.pdf', 'official', 'accessed', '61 schemes with mouza, road, area and sanction date. Under-process list (HS1_HS2_HS3_merged.pdf) downloaded but not transcribed.'],
    ['S11', 'Faisalabad Development Authority website', 'FDA', 'https://www.fda.gop.pk', 'official', 'inaccessible', 'Connection failed; approved/illegal lists not obtained.'],
    ['S12', 'Peshawar Development Authority website', 'PDA', 'https://www.pda.gkp.pk', 'official', 'inaccessible', 'Connection failed; approved/illegal lists not obtained.'],
    ['S13', 'Defence Housing Authority, Karachi', 'Wikipedia', 'https://en.wikipedia.org/wiki/Defence_Housing_Authority,_Karachi', 'secondary', 'via search', 'Phases 1–8 and DHA City.'],
    ['S14', 'Hayatabad', 'Wikipedia', 'https://en.wikipedia.org/wiki/Hayatabad', 'secondary', 'via search', 'Seven phases with lettered sectors; PDA responsible.'],
    ['S15', 'DHA Islamabad-Rawalpindi — Phase II Ext', 'DHA Islamabad-Rawalpindi', 'https://v2.dhai-r.com.pk/phases/phase-ii-ext/', 'official', 'via search', 'Phase numbering; jurisdiction notes from secondary sources.'],
    ['S16', 'A complete guide to DHA Lahore', 'Graana', 'https://www.graana.com/blog/a-complete-guide-to-dha-lahore/amp/', 'secondary', 'via search', 'Phase and sector letters for Phases 1–4; sources disagree on phase count.'],
    ['S17', 'Popular areas for buying property in DHA Rahbar', 'Zameen Blog', 'https://www.zameen.com/blog/popular-areas-buying-property-dha-rahbar-lahore.html', 'secondary', 'via search', 'Rahbar sub-phases.'],
    ['S18', 'OpenStreetMap Nominatim geocoder', 'OpenStreetMap contributors', 'https://nominatim.openstreetmap.org', 'secondary (map data)', 'accessed', 'Existence check and approximate centre per record; the matched OSM object URL is stored per record.'],
    ['S19', 'Zameen area guides — Karachi', 'Zameen.com', 'https://www.zameen.com/area-guides/karachi-2/', 'secondary', 'accessed', 'Common market names.'],
    ['S20', 'List of Union Councils of Karachi', 'Wikipedia', 'https://en.wikipedia.org/wiki/List_of_Union_Councils_of_Karachi', 'secondary', 'via search', 'Town/UC structure (not imported as locations).'],
    ['S21', 'Zameen area guides — Peshawar', 'Zameen.com', 'https://www.zameen.com/area-guides/peshawar-17/', 'secondary', 'accessed', 'Common market names.'],
];
$out = fopen(__DIR__.'/Meezan_Pakistan_Location_Sources.csv', 'w');
fputcsv($out, ['source_id', 'title', 'publisher', 'url', 'source_type', 'access_status', 'notes']);
foreach ($sources as $s) {
    fputcsv($out, array_merge($s, []));
}
fclose($out);

$byCity = [];
foreach ($rows as $r) {
    $byCity[$r['city']][$r['type']] = ($byCity[$r['city']][$r['type']] ?? 0) + 1;
}
echo count($rows)." records\n";
foreach ($byCity as $c => $t) {
    echo str_pad($c, 12).array_sum($t).'  '.json_encode($t)."\n";
}
