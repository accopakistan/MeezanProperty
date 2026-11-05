<?php

/*
| SBCA's "Public Sale Projects (Buildings)": NOCs for sale and advertisement.
| Read on 5 November 2026 from sbca.gos.pk/home/public_sale_projects and the Year-2023 to
| Year-2026 pages, as a person would (SBCA's robots.txt returns a server error, so automated
| collection stays off). Earlier year pages (2010 to 2022) are not read yet.
|
| Line: name|location|date|builder|NOC reference
|
| SBCA does not state each building's use. The grouping below is inferred from the name or
| plot code and marked as inferred. These rows came through a text conversion of the page,
| so every legal record waits for a person to check it against SBCA's page.
|
| Left out on purpose, so one building is not counted twice:
| - Naya Nazimabad Apartment-3, 4, 5, 7, 8 and Com-109: the same plots appear later under new
|   names with revised NOCs (Globe Residency-I to V, Rahat Residency-I).
| - The earlier Sam Imperial NOC (2022): the 2024 NOC for the same plot is kept.
| KN Gohar Green City: 33 NOCs of 24 November 2020 for blocks of one project are one entry.
*/

$list = fn (string $category, string $lines) => [
    'key' => 'sbca-buildings-'.$category, 'review' => true, 'name' => 'SBCA — NOC for sale, public sale buildings', 'publisher' => 'Sindh Building Control Authority', 'authority' => 'SBCA', 'city' => 'Karachi',
    'type' => 'official_record', 'level' => 1, 'url' => 'https://sbca.gos.pk/home/public_sale_projects', 'read_on' => '2026-11-05', 'method' => 'browser_read',
    'list_type' => 'approved', 'category' => $category, 'category_basis' => 'inferred', 'raw_status' => 'NOC for sale and advertisement issued',
    'access' => 'Public pages read once, as a person would, for buildings with an NOC dated 2019 to 2026. SBCA\'s robots.txt returns a server error, so automated collection stays off. An NOC for sale allows a builder to sell and advertise; it does not show that the building is finished. SBCA does not state the use of each building, so the category is inferred and needs checking.',
    'lines' => $lines,
];

return [
    $list('apartment_complex', <<<'LINES'
SILVER TOWER|Plot No. 09, Block-II, Sub-Block-B, Old Nazimabad, District Central, Karachi|16-04-2026|Silver Builders|SBCA/DD/(PSA&C)/480/Adv-67/2026
AROMA HEIGHTS|SC-18, Block-H, Scheme-2, District Central, Karachi|31-07-2026|Boga's Builders|SBCA/DD/(PSA&C)/475/Adv-94/2026
GLOBE RESIDENCY-I|FL-03, Survey Nos. 38 & 40, Naya Nazimabad, Scheme-43, Deh Manghopir, Gadap Town, Karachi|11-10-2024|Arif Habib Dolmen Reit Management Ltd.|SBCA/DD(PSA&C)/95/Revised/Adv-16/2024
GLOBE RESIDENCY-II|FL-04, Survey Nos. 38 & 40, Naya Nazimabad, Scheme-43, Deh Manghopir, Gadap Town, Karachi|11-10-2024|Arif Habib Dolmen Reit Management Ltd.|SBCA/DD(PSA&C)/98/Revised/Adv-18/2024
GLOBE RESIDENCY-III|FL-05, Naya Nazimabad, Survey Nos. 38 & 40, Deh Manghopir, Scheme-43, Gadap Town, Karachi|02-06-2025|Arif Habib Dolmen Reit Management Ltd.|SBCA/DD(PSA&C)/407/99/Revised/Adv-16/2025
GLOBE RESIDENCY-IV|FL-07, Naya Nazimabad, Survey No. 256, Deh Manghopir, Scheme-43, Gadap Town, Karachi|02-06-2025|Arif Habib Dolmen Reit Management Ltd.|SBCA/DD(PSA&C)/409/97/Revised/Adv-17/2025
GLOBE RESIDENCY-V|FL-08, Naya Nazimabad, Survey No. 256, Deh Manghopir, Scheme-43, Gadap Town, Karachi|02-06-2025|Arif Habib Dolmen Reit Management Ltd.|SBCA/DD(PSA&C)/408/96/Revised/Adv-18/2025
SIGNATURE RESIDENCY-I|Comm-102, Block-A, Naya Nazimabad, Manghopir, Gadap Town, Karachi|02-06-2025|Arif Habib Dolmen Reit Management Ltd.|SBCA/DD(PSA&C)/410/190/Revised/Adv-19/2025
THE COURT GARDEN|Plot No. 06, Survey No 205, Deh Gujro, Tappo Songal, Taluka Gulshan-e-Iqbal, District East, Karachi|27-03-2025|Sky Lane Devisor (Pvt.) Ltd.|SBCA/DD/(PSA&C)/383/Adv-05/2025
RAHAT RESIDENCY-I|COM-109, Block-B, Survey No. 99, Naya Nazimabad, Scheme-43, Deh Manghopir, Gadap Town, Karachi|11-10-2024|Arif Habib Dolmen Reit Management Ltd.|SBCA/DD(PSA&C)/71/Revised/Adv-17/2024
ARMANI SKY LINE|Plot No. 29-A, Sector-29, Survey No. 242, Deh Dozan, Taluka Gulzar-e-Hijri, Scheme-33, District East, Karachi|09-08-2024|Armani Builders & Developers|SBCA/DD(PSA&C)/301/Adv-08/2024
THE 7 (SEVEN) TOWERS|LA-4/1, Block-21, F.B Area, Scheme-16, District Central, Karachi|23-05-2024|N.S.A Builders & Developers|SBCA/DD(PSA&C)/361/Adv-02/2024
SHAHMIR HEIGHTS|Flat site in Shahmir Residency, Survey Nos. 02, 03, 04 & 05, Deh Songal, Tappo Gujro, Scheme-33, Karachi|17-05-2024|Center Stone (Pvt). Ltd|SBCA/DD(PSA&C)/347/Adv-01/2024
SAM IMPERIAL|Com 8/4, Sector Y-IV, Scheme 45, Gulshan-e-Maymar, Karachi|25-01-2024|Sam Builders & Developers|SBCA/DD(PSA&C)/2024/46
BURJ AL STAR|Plot # 84, Garden West Quarters, Karachi|24-08-2023|Hunani Builders & Developers|SBCA/DD(PSA&C Section)/302/Adv-295/2023
EASTERN ROYAL RESIDENCY|Plot No B-79, Block-01, Gulistan-e-Jauhar, Karachi|31-08-2023|Eastern Construction Company|SBCA/DD(PSA&C)/300/Adv-297/2023
SOHNI SAIBAN|3-B/1, Sector-3-B, Deh Songal Tapo Gujro, Scheme-33, Karachi|31-01-2022|Total Project Solution|SBCA/DD(PSA&C)/137/Revised Adv-32/2022
7TH AVENUE PHASE-I|SB-94, Sub Sector-A, Sector-7, Scheme-41, Surjani Town, Gadap Town, Karachi|19-04-2021|Chayell Enterprises|SBCA/DD(D-II)/Prop/20200515-1/Adv-10/2021
PS NORTHERN VIEW|Survey No.305, Deh Thoming Tappo Gujro, Scheme-33, Karachi|09-03-2022|Saudagar Corporation|SBCA/DD(PS&C)/45/Adv-41/2022
PS DYNASTY|Survey No.305/1, Deh Thoming Tappo Gujro, Scheme-33, Karachi|09-03-2022|Saudagar Corporation|SBCA/DD(PS&C)/38/Adv-42/2022
PREMIER ONE|1, Sector-4-C, Deh Songal, Scheme-33, Karachi|09-03-2022|Premier Property|SBCA/DD(PS&C)/40/Adv-43/2022
PREMIER BRIDGE VIEW|2, Sector-4-C, Deh Songal, Scheme-33, Karachi|09-03-2022|Premier Property|SBCA/DD(PS&C)/41/Adv-44/2022
CITY COMFORTS|COM-14, Sector-31, Deh Songal, Scheme-33, Gulshan Town, Karachi|14-03-2022|Estate 1. Com Builders & Developers|SBCA/DD(PS&C)/161/Adv-48/2022
SUMAIRA I-CON|24-B/1, Sector-24-B, Corridor, Scheme-33, Gulshan Town, Karachi|14-03-2021|Al-Shafquat Associates|SBCA/DD(D-II)/46/ADV-49/2022
SAEEDA RESIDENCY|A-2, Piece-18, NA-Class-05, Sector-51-A, Deh Bhitti Amri, Scheme-33, Gulzar-e-Hijri, Karachi|15-03-2022|Saeeda Builders & Developers|SBCA/DD(PS&C)/43/Adv-51/2022
KING'S REGENCY TOWER|Survey No.652, Deh Gujro, Tapo Songal, Block-9, F.B. Area, Gulberg Town, Karachi|16-08-2021|King's Enterprises|SBCA/DD(PS&C)/2512/Adv-05/2021
QUANITA RESIDENCIA-I|FL-03, Sector-1 & 3, Deh Taiser, Tappo Songal, Scheme-45, Gadap Town, Karachi|25-11-2021|Quanita builders Pvt. Ltd|SBCA/DD(PS&C)/04-Prop-20210409-1/Adv-25/2021
QUANITA RESIDENCIA|Comm-05, Sector-1 & 3, Deh Taiser, Tappo Songal, Scheme-45, Gadap Town, Karachi|25-11-2021|Quanita builders Pvt. Ltd|SBCA/DD(PS&C)/03-Prop-20200612-2/Adv-26/2021
UD COURT YARD|Comm-09, Sector-Y-IV, Scheme-45, Gulshan-e-Maymar, Karachi|22-02-2022|UD Constructions Solution (Pvt) Ltd|SBCA/DD(PS&C)/05-Prop-20201116-21/Adv-37/2022
SAYA RESIDENCY|Comm-5/1, Sector-Y-IV, Scheme-45, Gulshan-e-Maymar, Karachi|08-09-2021|Amraj Builders & Developers|SBCA/DD(PS&C)/Proop-20200131/1/Adv-12/2021
360 ICON|SB-49, Sub Sector-B, Sector-4, Scheme-41, Surjani Town, Gadap Town, Karachi|22-04-2022|Concrete Builders & Developers|SBCA/DD(PS&C)/56/Adv-60/2022
TRIBECA SQUARE|Survey No.542, Deh Dih Tappo Korangi, District Korangi, Karachi|16-05-2022|Core Construction|SBCA/DD(PS&C)/25/Adv-67/2021
THE COURT HEIGHTS-I|Com-High-Rise-14, NC-193, Callachi CHS, Deh Okhewari, Block-10-A, Gulshan-e-Iqbal, Scheme-24, Karachi|13-06-2022|Cascade Builder & Developers|SBCA/DD(PS&C)/90/Adv-87/2022
THE COURT HEIGHTS-II|Com-High-Rise-15, NC-193, Callachi CHS, Deh Okhewari, Block-10-A, Gulshan-e-Iqbal, Scheme-24, Karachi|13-06-2022|Cascade Builder & Developers|SBCA/DD(PS&C)/91/Adv-88/2022
BURJ SULTAN|H-3, Survey No.321, NC-24, Sector-5, Deh Taluka Korangi, Karachi|19-05-2022|Lilly Builders|SBCA/DD(PS&C)/73/Adv-76/2022
SUPERIOR VIEW|Com-01, (Com-02, Block-2), Survey No.137, Deh Jam Chakro, Manghopir Road, Karachi|06-06-2022|Global Solution Builder & Developers|SBCA/DD(PS&C)/73/Adv-76/2022
KARIMI GLASS TOWER|SB-46, Sector-5-D, Scheme-41, Surjani Town, Karachi|13-06-2022|Karimi Solution Builder & Developers|SBCA/DD(PS&C)/60/Adv-89/2022
MEHRAN ICON|Comm-20, Sector-Y-IV, Gulshan-e-Maymar, Scheme-45, Gadap Town, Karachi|04-10-2021|Mehran Enterprises|SBCA/DD(PS&C)/37/Adv-15/2021
PS VIEW|Com-18, Sector-31, Deh Songal, Scheme-33, Karachi|21-02-2022|Faisal Premier Associates|SBCA/DD(PS&C)/37/Adv-34/2022
PS HEAVEN|Com-19, Sector-31, Deh Songal, Scheme-33, Karachi|21-02-2022|Faisal Premier Associates|SBCA/DD(PS&C)/39/Adv-35/2022
BLOOM GARDENS|A-1, Sector-8-B, Scheme-33, Gadap Town, Karachi|13-06-2022|Sherwani Builder & Developers|SBCA/DD(PS&C)/88/Adv-91/2022
A & Z TOWER|COM-2.2-A, Block-1, KDA Scheme-5, Clifton, District South, Karachi|27-06-2022|Riffat Builder & Developers|SBCA/DD(PS&C)/80/Adv-95/2022
KUBRA RESIDENCY|MC-8/1, Sector-1, Lines Area, Scheme-35, Jamshed Town, Karachi|30-06-2022|City Scape Builder & Developers|SBCA/DD(PS&C)/81/Adv-97/2022
INSHAL HEIGHTS|SB-48, Sector-7-A, Scheme-41, Surjani, Gadap Town, Karachi|05-07-2022|Talia Builder & Developers|SBCA/DD(PS&C)/100/Adv-107/2022
IMPERIAL RESIDENCY HYDERI|SC-03, Block-C, Scheme-2, North Nazimabad, District Central, Karachi|29-07-2022|Muhibullah Builder & Developers|SBCA/DD(PS&C)/95/Adv-114/2022
DANY CRAFT TOWER|16, PR-2, Preedy Quarters, Karachi|01-08-2022|Victoria Properties|SBCA/DD(PS&C)/96/Adv-115/2022
AA ENCLAVE|Com-High-Rise-05, NC-193, Callachi CHS, Deh Okewari, Block-10-A, Scheme-24, Gulshan Town, Karachi|01-08-2022|AAR Builder & Developers|SBCA/DD(PS&C)/79/Adv-116/2022
ZULEKHA RESIDENCY|Com-12/1, Sector-Y-IV, Scheme-45, Gulshan-e-Maymar, Karachi|02-08-2022|H.J. Builder & Developers|SBCA/DD(PS&C)/94/Adv-117/2022
SUMAYA SHAFI HEIGHTS|F-2, KDA Scheme-1, Karachi|03-08-2022|MMM Associates|SBCA/DD(PS&C)/92/Adv-119/2022
STAR MANSION|46, GRW Garden West Quarters, District South, Karachi|04-08-2022|Hunani Builder & Developers|SBCA/DD(PS&C)/111/Adv-120/2022
PEARL SKY LINE|SB-89, Sector-5-D, Scheme-41, Surjani Town, Karachi|05-08-2022|Nashar Associates Builder & Developers|SBCA/DD(PS&C)/105/Adv-122/2022
AZIZ BROADWAY RESIDENCY|SA-15, Sector-5-H, North Karachi Township, Karachi|17-08-2022|Fariha Builder & Developers|SBCA/DD(PS&C)/112/Adv-123/2022
ZULEKHA VIEW|17, Block-7 & 8, Bihar Muslim CHS, KCHS, Karachi|22-08-2022|H.J. Builder & Developers|SBCA/DD(PS&C)/118/Adv-127/2022
ZULEKHA PRIDE|15/177, Block-3 & 4, Bihar Muslim CHS, KCHS, District East, Karachi|22-08-2022|H.J. Builder & Developers|SBCA/DD(PS&C)/119/Adv-128/2022
GREY HORIZON|29-B, Sector-29, Deh Dozan, Scheme-33, Gulzar-e-Hijri, Karachi|24-08-2022|Horizon Builders|SBCA/DD(PSA&C)/99/Adv-130/2022
SEA FRONT|FL-11, Block-1, Scheme-05, Clifton, Karachi|29-08-2022|Jubilee Builder & Developers|SBCA/DD(PSA&C)/125/Adv-132/2022
BLUE SKY RESIDENCY|NC-21, Sector-34-A, Deh Dozan Tappo Gujro, Scheme-33, Karachi|02-09-2022|Almas Builder & Developers|SBCA/DD(PSA&C)/87/Adv-136/2022
LE-GRAND|SB-16, Sector-5-D, Scheme-41, Surjani, Gadap Town, Karachi|05-09-2022|Le-Super Builder & Developers|SBCA/DD(PSA&C)/120/Adv-137/2022
M & H ONE|SB-01, Block-3-A, Scheme-36, Gulistan-e-Jouhar, Karachi|14-09-2022|Maheen & Hassan Builder & Developers|SBCA/DD(PSA&C)/113/Adv-140/2022
NADEEM ELITE TOWER|C-10, Block-10, F.B. Area, Gulberg Town, Scheme-16, District Central, Karachi|16-09-2022|N.B. Properties|SBCA/DD(PSA&C)/149/Adv-141/2022
MODERN JINNAH TOWER|SB-01, Block-C, Millat Garden, Malir Town, Karachi|19-09-2022|Ais International|SBCA/DD(PSA&C)/126/Adv-145/2022
AL-REEF TWIN TOWER|03, Sub Block-E, Block-III, Nazimabad, Liaquatabad Town, District Central, Karachi|16-09-2022|F.N.F. Builders|SBCA/DD(PSA&C)/19/Adv-143/2022
HABIB FALAK ICON|14/1, CL-06, Civil Lines Quarters, District South, Karachi|21-09-2022|IFC Associates|SBCA/DD(PSA&C)/143/Adv-148/2022
ISRA PEARL|SB-20, Block-3-A, Scheme-36, Gulistan-e-Jouhar, District East, Karachi|23-09-2022|Isra Builder & Developers|SBCA/DD(PSA&C)/146/Adv-150/2022
ISRA PALM|SB-26, Block-11, Scheme-36, Gulistan-e-Jouhar, Karachi|23-09-2022|Isra Builder & Developers|SBCA/DD(PSA&C)/151/Adv-151/2022
ROSHAN RESIDENCIA|241-5-A, Block-2, PECHS, District East, Karachi|26-09-2022|Stone Bridge Builder & Developers|SBCA/DD(PSA&C)/142/Adv-152/2022
OTEL RESIDENCY|Survey No.115, Deh Drigh Road, Taluka Shah Faisal, District Korangi, Karachi|21-09-2022|Ifisaw Conception|SBCA/DD(PSA&C)/137/Adv-147/2022
RANA TWIN TOWER|Deh Songal, Tappo Gujro, Sector-8-B, Scheme-33, Karachi|28-09-2022|Rana Builder & Developers|SBCA/DD(PSA&C)/140/Adv-156/2022
IBRAHIM GOLD MARK|SB-57, Sector-5-D, Scheme-41, Surjani, Gadap Town, Karachi|27-09-2022|SD Builder & Developers|SBCA/DD(PSA&C)/157/Adv-153/2022
RANA RESIDENCY|ST-01, Block-01, Scheme-36, Gulistan-e-Jouhar, Karachi|28-09-2022|Rana Builder & Developers|SBCA/DD(PSA&C)/139/Adv-157/2022
OCEAN-99|Deh Songal Tappo Gujro, Sector-19-C, Scheme-33, District East, Karachi|30-09-2022|T.M Associates|SBCA/DD(PSA&C)/144/Adv-159/2022
KHURASAN HEIGHTS|CS-10, Sector-19-C, Gulistan CHS, Scheme-33, Gulshan Town, Karachi|03-10-2022|Maskan Associates|SBCA/DD(PSA&C)/153/Adv-160/2022
IQRA SKY LINE|181, GRW, Garden West Quarters, District South, Karachi|03-10-2022|Iqra Builders|SBCA/DD(PSA&C)/141/Adv-161/2022
STAR SKY SCRAPER|90-3, GRW, Garden West Quarters, District South, Karachi|03-10-2022|Hunani Builder & Developers|SBCA/DD(PSA&C)/150/Adv-162/2022
SHADAB HEAVEN|551-C, Block-2, (C-Market), PECHS, Karachi|06-10-2022|Hunani Builder & Developers|SBCA/DD(PSA&C)/159/Adv-163/2022
ZAM ZAM HOMES|C-3, (59), Sheet No.B, Bath Island Quarters, Karachi|07-10-2022|Z.Z.H Builder|SBCA/DD(PSA&C)/156/Adv-165/2022
BRB HEXAGON TOWER|FL-01, Survey # 70 & 96/1, Deh Tore, Tappo Konkar, Murad Memon, District Malir, Karachi|03-01-2022|BRB Properties|SBCA/DD(PSA&C)/Prop-20210825-25(24)/Adv-30/2022
IMPERIAL ICON|172-Z, Block-2, PECHS, District East, Karachi|28-10-2022|Muhibullah Builder & Developers|SBCA/DD(PSA&C)/130/Adv-172/2022
SAWERA GALAXY HEIGHTS|13, Block-3, Overseas CHS, KCHS, Karachi|27-10-2022|Doni Builder & Developers|SBCA/DD(PSA&C)/165/Adv-17/2022
CENTRAL ICON|1/2, Firdous C.H.S, Liaquatabad Town, District Central, Karachi|17-10-2022|Rawat Builders & Developers|SBCA/DD(PSA&C)/160/Adv-168/2022
FM TOWERS|Com-1, Sector-15-B, Deh Gujro, Gulzar-e-Hijri, Scheme-33, Gulshan Town, Karachi|29-03-2021|FM Constructor|SBCA/DD-II(D-I)/1615/2021/ADV-03
FOUR STAR RESIDENCY|SB-72, Sector-1, Scheme-41, Surjani Town, Karachi|31-03-2021|Etmad Builder & Developers|SBCA/DD-II(D-I)/117/2021/ADV-04
THE COURT SKY TOWER|66/3/1, Deh Drigh Tappo Ibrahim Hyderi, District Korangi Town, Karachi|26-02-2021|Rasheeda Enterprises|SBCA/DD-II(D-I)/160/2021/ADV-73
ABDULLAH TOWERS|ST-02, Sub Sector-2, Sector-6, Abdullahabad, Scheme-41, Surjani Town, Karachi|04-03-2021|Abid & Company|SBCA/DD-II(D-I)/140/2021/ADV-75
CHINA HILTON RESIDENCY|10, Sub Block-E, Block-III, Old Nazimabad, Liaquatabad Town, Karachi|11-03-2021|China Builder & Developers|SBCA/DD-II(D-I)/152/2021/ADV-77
LAKHANI PARK VISTA|Comm-03, Sector-Y, Sub Sector-IV, Gulshan-e-Maymar, Scheme-45, Karachi|05-04-2021|Lakhani Group Builder & Developers|SBCA/DD-II(D-I)/84/2021/ADV-05
SIGNATURE-33|Comm-04, Sector-17-A, Pakistan Scientist CHS, Scheme-33, Gulshan Town, Karachi|22-04-2021|Discovery Estate & Construction|SBCA/DD-II(D-II)/1606/2021/ADV-19
FAIZ RESIDENCY|FL-4-A, Sub Sector-A, Sector-4, Scheme-41, Surjani Town, Gadap Town, Karachi|28-04-2021|TAP Builder|SBCA/DD(D-I)/17/2021/ADV-20
ZULEKHA PARK VIEW|SC-19, Scheme-07, Chandni Chowk, Gulshan Town, Karachi|03-06-2021|H.J Solution Builder & Developers|SBCA/DD(Design)/909-3/2021/ADV-36
SAIMA PS RESIDENCY|COM-1, Deh Songal, Sector-31, Scheme-33, Karachi|07-06-2021|Saima Estate Builder & Developers|SBCA/DD(Design)/08/2021/ADV-37
SAIMA PS RESIDENCY-II|COM-2, Deh Songal, Sector-31, Scheme-33, Karachi|07-06-2021|Saima Estate Builder & Developers|SBCA/DD(Design)/09/2021/ADV-38
RUBI TOWER PHASE-II|SB-12, Block-11, Scheme-36, Gulistan-e-Jouhar, Karachi|08-06-2021|Imran Construction Company|SBCA/AD-South(Design)/1207-7/2021/ADV-40
YOUNUS PRESIDENCY|COM-II, Survey Nos.218 & 506, Deh Kharkharo Tappo Konkar, Gadap Town, District Malir, Karachi|14-06-2021|Global Solution Builder & Developers|SBCA/AD-South(Design)/03/2021/ADV-41
PS CLASSIC-I|Survey No.304, Deh Thoming, Scheme-33, Karachi|16-06-2021|Saudagar Corporation Pvt. Ltd|SBCA/DD(Design)/27/2021/ADV-42
PS CLASSIC-II|Survey No.304/1, Deh Thoming, Scheme-33, Karachi|16-06-2021|Saudagar Corporation Pvt. Ltd|SBCA/DD(Design)/28/2021/ADV-43
HASHMANI GOLD VIEW|57/1, Sheet No.GRW-01, Garden West Quarters, Karachi|28-06-2021|Hashmani Builders|SBCA/AD(PSA&C)/Prop-20191208-01/10/ADV-47
STAR RESIDENCY|90/2/2, Sheet No.GRW-02, Garden West Quarters, Karachi|28-06-2021|Hunani Builders & Developers|SBCA/AD(South)/PSA&C)/20200909-18/44/ADV-48
BARJ-AL-JINNAH|ST-1, Sector-45-A/1, Scheme-33, NIPA Employees CHS, Gulshan Town-I, Karachi|30-06-2021|S.M. Builders & Developers|SBCA/DD(PSA&C)/34/ADV-49
AL-KAREEM RESIDENCY|40 & 41, Survey No.108, Saleem Housing Project, Shah Faisal Colony No.3, District Korangi, Karachi|06-10-2021|Khursheed Enterprises|SBCA/DD(PSA&C)/Prop-20210101/42/ADV-17
ABRAZ-UL-MINAL TOWERS|4C-1, Sector-4C, Deh Songal Tappo Gujro, Scheme-33, Gulzar-e-Hijri, Karachi|15-10-2021|Grand Mark Builder & Developers|SBCA/DD(PSA&C)/23/Adv-19/2021/
AZIZ EXCELLENCY|SB-55, Sub Sector-A, Sector-11, Scheme-41, Surjani Town, Gadap Town, Karachi|06-05-2021|Fariha Builder & Developers|SBCA/DD(Design)/Prop-20200211-20/Adv-25/2021
THE GARDEN BLOCK-A|01, Naclass-21, Sector-36-B, Scheme-33, Deh Dozan, Karachi|06-11-2020|S.A. Enterprises|SBCA/DD(D-II)/1580/ADV-1020/2020
THE GARDEN BLOCK-B|01/A, Naclass-21, Sector-36-B, Scheme-33, Deh Dozan, Karachi|06-11-2020|S.A. Enterprises|SBCA/DD(D-II)/1581/ADV-1021/2020
H.S.J. ICON|17, Sheet CL-09, Civil Lines Quarters, Karachi|09-11-2020|H.S.J. Construction|SBCA/DD(D-III)/2415/ADV-76/2020
KN GOHAR GREEN CITY|Survey No.01, 02 & 245/3, Karachi (33 NOCs for blocks: Red, Green, Blue and Yellow Line, Green-I to XII)|24-11-2020|Nabeel Corporation Builder & Developers|SBCA/DD(D-II)/1567 to 1602, ADV-1022 to ADV-1057/2020
SAMRINA GRAND|Com-06, Kathiawar CHS, Sector-19-A/1, Scheme-33, Gulshan Town, Karachi|01-11-2020|Dream Homes Associates|SBCA/DD(D-II)/1526/ADV-981/2020
THE ADDRESS|25/13, Block-7 & 8, Maqbolabad CHS, Jamshed Town, Karachi|28-11-2020|Danish Builder & Developers|SBCA/DD(D-II)/1533/ADV-989/2020
AYESHA GARDEN|327/5/4, Garden East, Jamshed Town, Karachi|10-01-2020|Ayesha Associates|SBCA/DD(D-II)/1474/ADV-984/2020
ASKAN HILL VIEW|SA-03, Block-02, Gulistan-e-Jouhar, Gulshan Town, Karachi|20-01-2020|A.A.K. Builder & Developers|SBCA/DD(D-II)/1522/ADV-986/2020
KULSOOM SKY VIEW|111, Block-A, Sindhi Muslim CHS, Jamshed Town, Karachi|31-12-2019|R.R. Builder & Developers|SBCA/DD(D-II)/1517/ADV-980/2020
CITY VIEW APARTMENT|Com-17, Sector-31, Scheme-33, Gulshan Town, Karachi|17-09-2020|Estate I. Con|SBCA/DD(D-II)/1563/ADV-1015/2020
ISRA TOWER|SB-08, Block-7, Scheme-36, Gulistan-e-Jouhar, Karachi|13-08-2020|Isra Builder & Developers|SBCA/DD(D-II)/1538/ADV-1013/2020
SUMAIRA COMFORTS|25-B/2, Sector-25-B, Corridor, Scheme-33, Gulshan Town, Karachi|16-07-2020|Sumair Builder|SBCA/DD(D-II)/1540/ADV-1010/2020
DEFENCE BRIDGE VIEW APARTMENTS|126, NC-43, Deh Drigh, Sector-6-J/1, Shaheed-e-Millat Expressway, Jamshed Town, Karachi|04-08-2020|Defence Bridge View Apartment|SBCA/DD(D-II)/1542/ADV-1012/2020
SALMAN ROYAL TOWER|401, Survey Nos. 276 & 402, Deh Drigh, Shah Faisal Town, Karachi|23-09-2020|Salman Enterprises|SBCA/DD(D-II)/1521/ADV-1016/2020
ROYAL GULF RESIDENCY|Survey No.96, NC-29, Deh Surjani, Tapo Manghopir, Gadap Town, Karachi|08-01-2020|Royal Builders & Developers|SBCA/DD(D-II)/93/ADV-26/2020
COMMANDER ENCLAVE-II|FL-2, Survey No.103, Deh Tore, Tappo Konkar, Gadap Town, Karachi|13-01-2020|Commander Builder|SBCA/DD(D-I)/1515/ADV-27/2020
COMMANDER ENCLAVE-III|Com. Survey No.103, Deh Tore, Tappo Konkar, Gadap Town, Karachi|13-01-2020|Commander Builder|SBCA/DD(D-I)/1516/ADV-28/2020
GREEN LINE RESIDENCIA|Survey No. 105, Deh Surjani, Tapo Manghopir, Gadap Town, Karachi|04-02-2020|Olympic Properties|SBCA/DD(D-I)/104/ADV-35/2020
AREEBA RESIDENCY|SR-56, Sector-5-D, Scheme-41, Surjani Town, Gadap Town, Karachi|04-02-2020|R & R Builder & Developers|SBCA/DD(D-I)/69/ADV-36/2020
SARTAJ TOWER|Survey No.104, Deh Surjani, Tapo Manghopir, Gadap Town, Karachi|10-02-2020|Sartaj Properties|SBCA/DD(D-I)/103/ADV-40/2020
GREEN-I|SB-48, Sub Sector-B, Sector-4, Scheme-41, Surjani Town, Gadap Town, Karachi|07-02-2020|Green Builder & Developers|SBCA/DD(D-I)/78/ADV-37/2020
GREEN-II|SB-41, Sub Sector-B, Sector-4, Scheme-41, Surjani Town, Gadap Town, Karachi|07-02-2020|Green Builder & Developers|SBCA/DD(D-I)/79/ADV-38/2020
GREEN-III|SB-42, Sub Sector-B, Sector-4, Scheme-41, Surjani Town, Gadap Town, Karachi|07-02-2020|Green Builder & Developers|SBCA/DD(D-I)/80/ADV-39/2020
ALIZE HEIGHTS-I|SB-04, Mehran Garden, Block-1, Deh Malh, Tapo Darsano Channo, District Malir, Gadap Town, Karachi|14-02-2020|Ali-ze Builder & Developers|SBCA/DD(D-I)/90/ADV-41/2020
ALIZE HEIGHTS-II|SB-07, Mehran Garden, Block-1, Deh Malh, Tapo Darsano Channo, District Malir, Gadap Town, Karachi|14-02-2020|Ali-ze Builder & Developers|SBCA/DD(D-I)/91/ADV-42/2020
ALIZE HEIGHTS-III|SB-10, Mehran Garden, Block-1, Deh Malh, Tapo Darsano Channo, District Malir, Gadap Town, Karachi|14-02-2020|Ali-ze Builder & Developers|SBCA/DD(D-I)/92/ADV-43/2020
NORTH CORNER EXTENSION|A-2, Block-A, NC-29, Deh Surjani, Sector-5-D, Scheme-41, Karachi|19-02-2020|Zain Builder & Developers|SBCA/DD(D-I)/1483/ADV-44/2020
BARKAAT PRESIDENCY|Survey No.176, Deh Tore, Tapo Konkar, Gadap Town, District Malir, Karachi|21-02-2020|Barkaat Builder & Developers|SBCA/DD(D-I)/76/ADV-45/2020
LASANI SQUARE|SA-20, Sector-5-H, North Karachi, Karachi|18-03-2020|Ellahi Builder|SBCA/DD(D-I)/63/ADV-46/2020
AMMAR ICON TOWERS|03, Row-A, Block-I, Gulshan-e-Kaneez Fatima, Sector-3, Scheme-41, Surjani Town, Gadap Town, Karachi|10-07-2020|Ammar Builders & Developers|SBCA/DD(D-I)/119/ADV-48/2020
SURJANI APARTMENTS|FL-02, Block-B-6, Sector-12, Scheme-41, Surjani Town, Gadap Town, Karachi|26-08-2020|Suburb Properties|SBCA/DD(D-I)/106/ADV-49/2020
ADEENA CORNER|SB-159, Sector-4-B, Scheme-41, Surjani Town, Gadap Town, Karachi|26-08-2020|Crescent Builder & Developers|SBCA/DD(D-I)/1400/ADV-50/2020
ELIYANA RISE|SB-55, Sector-4-B, Scheme-41, Surjani Town, Gadap Town, Karachi|27-08-2020|EK International Builder & Developers|SBCA/DD(D-I)/120/ADV-51/2020
MALIHA GARDEN|Survey No. 444, Deh Thano, Tapo Malir, Taluka Murad Memon, District Malir, Karachi|15-09-2020|Samma Builder & Developers|SBCA/DD(D-I)/83/ADV-52/2020
SHAHZAIN RESIDENCY|SB-07, Sector-6, Sub Sector-B-1, Scheme-41, Gulshan-e-Bilal, Surjani Town, Gadap Town, Karachi|16-09-2020|Shahzain Enterprises|SBCA/DD(D-I)/85/ADV-53/2020
LAKHANI BLESSING|Sector-78, Taiser Town, Scheme-45, Gadap Town, Karachi|17-09-2020|Lakhani Properties|SBCA/DD(D-I)/94/ADV-54/2020
ARY PEARL RESIDENCY|FL-17, Sector-1 & 3, Taiser Town, Scheme-45, Karachi|21-09-2020|Ali Lakhani Builder|SBCA/DD(D-I)/125/ADV-56/2020
KING'S PARADIES-5|CS-5, Al-Ahmed Town, Survey No.139 to 142, Deh Manghopir, Gadap Town, Karachi|01-10-2020|King's Enterprises|SBCA/DD(D-I)/113/ADV-58/2020
KING'S PARADIES-6|CS-06, Al-Ahmed Town, Survey No.139 to 142, Deh Manghopir, Gadap Town, Karachi|01-10-2020|King's Enterprises|SBCA/DD(D-I)/110/ADV-59/2020
KING'S PARADIES-13|CS-13, Al-Ahmed Town, Survey No.139 to 142, Deh Manghopir, Gadap Town, Karachi|01-10-2020|King's Enterprises|SBCA/DD(D-I)/114/ADV-60/2020
KING'S PARADIES-14|CS-14, Al-Ahmed Town, Survey No.139 to 142, Deh Manghopir, Gadap Town, Karachi|01-10-2020|King's Enterprises|SBCA/DD(D-I)/111/ADV-61/2020
KING'S PARADIES-15|CS-15, Al-Ahmed Town, Survey No.139 to 142, Deh Manghopir, Gadap Town, Karachi|01-10-2020|King's Enterprises|SBCA/DD(D-I)/112/ADV-62/2020
BASIT CORNER PHASE-III|SB-15, Sub Sector-A, Sector-11, Scheme-41, Surjani Town, Gadap Town, Karachi|26-10-2020|Hut Provider Builder & Developers|SBCA/DD(D-I)/129/Revised ADV-63/2020
OPAL EXCELLENCY|02, Sub Block-K, Block-I, (I-K/2), Nazimabad, Karachi|29-10-2020|A.F.R Enterprises|SBCA/DD(D-I)/42/ADV-64/2020
HUSNAIN RESIDENCY|SB-08, Sector-6, Sub Sector-B-1, Scheme-41, Gulshan-e-Bilal, Surjani Town, Gadap Town, Karachi|02-11-2020|Hamdan Associates|SBCA/DD(D-I)/85/ADV-67/2020
OASIS PARK RESIDENCIA|Survey No.70 & 96/1, Deh Tore, Taluka Murad Memon, Gadap Town, Karachi|02-11-2020|BRB Properties|SBCA/DD(D-I)/1482/ADV-68/2020
LINES),
    $list('shopping_mall', <<<'LINES'
RUBY CINEMA & SHOPPING MALL|ST-02, Sector-3/B, Baldia Town, District Keamari, Karachi|24-05-2024|Alif Laila Properties|SBCA/DD(PSA&C)/355/Adv-03/2024
ICON MALL & RESIDENCY|SB-06, (ST-22/E), Sector-35-F, Korangi Township, District Korangi, Karachi|14-03-2022|Town City Builders & Developers|SBCA/DD(PS&C)/34/Adv-47/2022
JILANI SHOPPING CENTRE & RESIDENCE|829-1, Deh Thano, Tappo Malir, Taluka Murad Memon, District Malir, Karachi|28-03-2022|Jilani Properties|SBCA/DD(PS&C)/53/Adv-52/2022
DOHA MALL & RESIDENCY|47-B, Survey No.47, Sector-19-A, Scheme-25-A, Shah Latif Town, District Malir, Karachi|17-08-2022|Rupali Builders|SBCA/DD(PS&C)/877/Adv-124/2022
KALYANA MALL & RESIDENCY NEW HYDERI MARKET|SB-50, Sector-7-A, Scheme-41, Surjani Town, Gadap Town, Karachi|29-06-2022|Kalyanan Builder & Developers|SBCA/DD(PSA&C)/31/Adv-96/2022
GOHAR RESIDENCIA & SHOPPING MALL|1-A, Survey No.118, Deh Bhitti Amri, Scheme-33, Karachi|09-03-2021|Laiba Enterprises Builder & Developers|SBCA/DD-II(D-I)/159/2021/ADV-76
THE CHAYELLZ MOBILE MALL & RESIDENCY PHASE-I|SB-166, Sector-7-A, Scheme-41, Surjani Town, Karachi|16-03-2021|Chayell Properties|SBCA/DD-II(D-I)/138/2021/ADV-79
MALL OF MALIR & RESIDENCY|Survey No.278/1, Deh Thanno, Tappo Malir, Taluka Murad Memon, District Malir, Malir Town, Karachi|28-10-2021|Bin Sadiq Builders (Pvt.) Ltd.|SBCA/DD(PSA&C)/Prop-20201029-43(08)/Adv-22/2021
ABM IQBAL SHOPPING CENTRE|283, Sheet-01, Quaidabad, District Malir, Karachi|20-12-2021|Concrete Builder & Developers|SBCA/DD(PSA&C)/44/Adv-28/2021
JAFCO MALL & APARTMENT|2, Survey No.443, Deh Landhi, Shah Latif Town, Scheme-25-A, Bin Qasim Town, Karachi|22-01-2020|Jasco Builder & Developers|SBCA/DD(D-II)/1534/ADV-987/2020
LAKHANI TOWER & SHOPPING MALL|ST-1/2/26, Sector-1, Ahsanabad CHS, Scheme-33, Karachi|01-01-2020|Lakhani Apex Builders & Developers|SBCA/DD(D-I)/670/Revised ADV-25/2020
LINES),
    $list('commercial_plaza', <<<'LINES'
TAYYAB CITY PLAZA|213, Survey No.17/18, Deh Drigh Road, Shah Faisal, District Korangi, Karachi|13-08-2022|Taiba Builder & Developers|SBCA/DD(PS&C)/106/Adv-139/2022
SADIQ TRADE CENTRE|101, Block-A, Sindhi Muslim CHS, Karachi|17-10-2022|Al-Sadiq Associates|SBCA/DD(PSA&C)/163/Adv-169/2022
BALAD TRADE CENTRE-II|114, Bahadur Yar Jung CHS, Gulshan Town-II, Karachi|06-11-2020|Glorious Associates|SBCA/DD(D-II)/1529/ADV-982/2020
NAYA NAZIMABAD COM-33|Com-33, Survey No.260, Naya Nazimabad, Scheme-43, Deh Manghopir, Gadap Town, Karachi|30-06-2022|Javedan Corporation Pvt. Ltd|SBCA/DD(PS&C)/58/Adv-98/2022
NAYA NAZIMABAD COM-35|Com-35, Survey No.34, Naya Nazimabad, Scheme-43, Deh Manghopir, Gadap Town, Karachi|30-06-2022|Javedan Corporation Pvt. Ltd|SBCA/DD(PS&C)/59/Adv-99/2022
NAYA NAZIMABAD COM-37|Com-37, Survey No.34, Naya Nazimabad, Scheme-43, Deh Manghopir, Gadap Town, Karachi|30-06-2022|Javedan Corporation Pvt. Ltd|SBCA/DD(PS&C)/60/Adv-101/2022
NAYA NAZIMABAD COM-39|COM-39, Survey No.34 & 38, Naya Nazimabad, Scheme-43, Deh Manghopir, Gadap Town, Karachi|30-06-2022|Javedan Corporation Pvt. Ltd|SBCA/DD(PSA&C)/61/Adv-100/2022
NAYA NAZIMABAD COM-41|COM-41, Survey No.38, Naya Nazimabad, Scheme-43, Deh Manghopir, Gadap Town, Karachi|30-06-2022|Javedan Corporation Pvt. Ltd|SBCA/DD(PSA&C)/62/Adv-102/2022
LINES),
    $list('business_tower', <<<'LINES'
COUNTRY FINANCE TOWER|CA-01, Block-04, Clifton, Karachi|07-06-2021|Seventh Sky Builder|SBCA/AD-South(Design)/26/2021/ADV-39
INDIGO BUSINESS CENTRE|16/104, Block-03, Bahadur Yar Jung CHS, Gulshan Town, Karachi|31-12-2019|Apex Land Solution|SBCA/DD(D-II)/1530/ADV-979/2020
LINES),
    $list('residential_society', <<<'LINES'
NORTH TOWN VILLAS|NC-90, Deh Surjani, Gadap Town, Karachi|16-03-2021|Global Solution Builder & Developers|SBCA/DD-II(D-I)/153/2021/ADV-78
NORTH TOWN RESIDENCY PHASE-II|Naclass No.294, Deh Halkani, Tapo Manghopir, Gadap Town, Karachi|04-06-2020|Global Solution Builders & Developers|SBCA/DD(D-I)/100/ADV-47/2020
NORTH TOWN RESIDENCY GOLD EXTENSION-I|NC-54, Deh Jam Chakro, Tappo Manghopir, District West, Karachi|19-04-2021|Global Solution Builder & Developers|SBCA/DD(D-I)/133/2021/ADV-15
NORTH TOWN RESIDENCY SUPERIOR-II|NC-54, Deh Jam Chakro, Tappo Manghopir, District West, Karachi|19-04-2021|Global Solution Builder & Developers|SBCA/DD(D-I)/130/2021/ADV-16
MALIR TOWN RESIDENCY PHASE-II|Survey No. 218, Deh Kharkharo, Tapo Konkar, Gadap Town, District Malir, Karachi|15-01-2020|Global Solution Builders & Developers|SBCA/DD(D-I)/75/ADV-29/2020
MALIR TOWN RESIDENCY EXTENSION|Survey No.263, Deh Malh, Tapo Darsano Chano, Taluka Murad Memon, District Malir, Karachi|03-06-2021|Global Solution Builder & Developers|SBCA/DD(Design)/73/2021/ADV-34
MALIR TOWN RESIDENCY EXTENSION-I|Survey No.660, Deh Malh, Tapo Darsano Chano, Taluka Murad Memon, District Malir, Karachi|03-06-2021|Global Solution Builder & Developers|SBCA/DD(Design)/74/2021/ADV-35
AL-ZOHRA VILLAS & COTTAGES|Survey Nos.139, 142, 143 & 289, Deh Allah Phiahi, Gadap Town, District Malir, Karachi|24-08-2022|Associates|SBCA/DD(PS&C)/116/Adv-129/2022
GOHAR VILLAS|Survey No.57, Deh Allah Phiahi, Tappo Songal, Gadap Town, Karachi|17-09-2020|Nabeel Corporation Builder & Developers|SBCA/DD(D-I)/1451/ADV-55/2020
SUMSUM HOUSING SCHEME|Sector-59, Taiser Town, Scheme-45, Gadap Town, Karachi|30-09-2020|Sumsum Group Builder & Developers|SBCA/DD(D-I)/81/ADV-57/2020
LINES),
];
