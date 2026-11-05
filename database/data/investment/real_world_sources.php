<?php

/*
| Real-world records read by hand from their original sources, for sources the
| automated collector cannot read (PDF lists, a failing robots.txt, a
| certificate problem, a page rendered by script). Nothing here is estimated:
| each line copies what the source says, and the source's address and read date
| travel with it into the database. Personal data published on some lists
| (CNIC numbers, phone numbers, individual owners' names) is deliberately left out.
|
| Registry sources: name|location|date|developer|reference|raw status
|   Every list here has 'review' => true: copied by hand, so its legal records wait for a
|   person (owner's decision, 5 Nov 2026). Only data read directly from an authority verifies itself.
|   list_type approved / authority_scheme create projects; illegal lists stay in
|   the official registry and affect a project only if one matches.
| Reference sources: name|website|status|note
*/

$read = '2026-11-04';

return [
    [
        'key' => 'mda-private-approved', 'name' => 'MDA — sanctioned private housing schemes and land sub-divisions', 'publisher' => 'Multan Development Authority', 'authority' => 'MDA Multan', 'city' => 'Multan',
        'type' => 'official_record', 'level' => 1, 'url' => 'https://mda.punjab.gov.pk/system/files/approved.pdf', 'read_on' => '2026-11-05', 'method' => 'official_scan',
        'list_type' => 'approved', 'category' => 'residential_society', 'category_basis' => 'official', 'raw_status' => 'Listed as an approved private housing scheme',
        // Read by eye from a two-page scan, so each legal record waits for a person to check it against the scan.
        'review' => true,
        'access' => 'Public scanned PDF on the authority\'s site, listed there on 26 September 2025. Read by eye once, because it has no text layer. On the scan, dates with slashes were read as month/day/year and dates with dashes as day-month-year. Area is in kanal. MDA Officers Cooperative Housing Society also shows a second date, 04-01-2000. The first entry on the page is cut off at the margin.',
        'lines' => <<<'LINES'
Adams Housing|Mouza Jahangirabad, Matital Road|19-07-2021|||480
Al-Falah Deffence City|Mouza Durana Langana, Mattital Road|04-11-2011|||58.38
Alfalah Dubai LSD|Mouza Durana Langana, Multan Public School Road|01-10-2013|||72.45
Al-Falah Sharjah City LSD|Mouza Durana Langana, Multan Public School Road|17-07-2013|||55.39
Al-Jannat Homes LSD|Mouza Taraf Mubarak Doem, Off Nawab Pur Road|19-09-2000|||37.6
Buch Executive Villas (Extension)|Mouza Buch Khusro Abad, Bosan Road|11-12-2020|||2191
Buch Executive Villas Phase-II|Mouza Buch Khusro Abad, Bosan Road Multan|03-12-2022|||576.74
Buch LSD|Mouza Buch Khusro Abad, Bosan Road|11-08-2023|||39.65
Buch Villas Extension-I|Mouza Buch Khusro Abad, Bosan Road|19-01-2017|||1829
Buch Villas HS|Mouza Buch Khusro Abad, Bosan Road|24-06-2019|||1330
BZU Teachers Housing Project-I|Mouza Durrana Langana, Multan Public School Road|16-01-2004|||86.7
Canal Cantt View LSD|Mouza Langrial, Langrial Road|15-12-2015|||110
Cantt Canal View LSD|Mouza Langrial, Langrial Road|18-12-2015|||55.5
Citi Housing HS|Mouza Lutf Abad, Bosan Road Multan|01-09-2018|||997.15
City Housing Private Ltd|Mouza Lutf Abad, Bosan Road|24-01-2020|||1898.63
Classic Villas|Mouza Sajri, Shuja Abad Canal Road|12-01-2019|||306
Faiz-e-Aam Homes LSD|Mouza Basti Nau, Nawabpur Road|14-05-2018|||37.82
Falah-ud-Din City LSD|Mouza Durana Langana, Matital Road|22-05-2012|||53.42
Fatima LSD|Mouza Dura Langana, MA Jinnah Road|21-05-2012|||52.15
Grand Enclave LSD|Mouza Durana Langana, Northern Bypass Road|14-05-2022|||61
Green Avenue LSD|Mouza Kotla Matarban, Multan Public School Road|30-09-2005|||32
Hamza Villas LSD|Mouza Durana Langana, Multan Public School Road|20-02-2013|||39
Ibrahim LSD|Mouza Durana Langana, Northern Bypass Road|23-07-2007|||40.5
Liberty Homes / Royal Garden|Mouza Durana Langana, Northern Bypass Road|08-01-2021|||169
MDA Officers Cooperative Housing Society|Mouza Durana Langana, M. A. Jinnah Road|12-04-1997|||937.76
New Crystal City|Mouza Sujan Pur, LMQ Road|25-05-2018|||147.92
Nistarian's Cooperative Housing|Mouza Salah Mahy, Bosan Road|17-03-2010|||82.33
Paramount Villas LSD|Mouza Bua Pur, LMQ Road Multan|16-09-2020|||54.8
Posh Avenue LSD|Mouza Taraf Mubarak Doem, Ghaus ul Azam Road|29-09-2020|||27.2
Royal Orchard (Extension)|Mouza Durana Langana, Multan Public School Road|24-05-2018|||3190.25
Royal Orchard HS|Mouza Durana Langana, Public School Road|13-08-2020|||1200
Sahara LSD|Mouza Durana Langana, Multan Public School Road|19-05-2012|||158.96
Sharif City LSD|Mouza Jahangir Abad, Off LMQ Road|03-05-2011|||72.76
Smart Villas|Mouza Bua Pur, M. A. Jinnah Road|23-12-2021|||28.22
VIP LSD|Mouza Alamdi Surah, Suraj Miani Road|16-11-1998|||78
Adeel Bagh LSD|Mouza Samurana, Muzaffar Garh Road|29-01-2013|||20.4
Al Jawad Town LSD|Mouza Taraf Daira, Suraj Kund Road|15-12-2012|||10.95
Al-Hamd Canal View HS|Mouza Nai Wala, Budhla Road|25-05-2021|||155.94
Dream Garden Extension (Dream Cottages) HS|Mouza Kotla Abul Fatah, Askaria Bypass Road|11-09-2020|||200
Eden LSD|Mouza Kotla Muhammad Baqa, Off Bahawal Pur Road|07-08-2021|||40
Ehtesham City LSD|Mouza Kot Rab Nawaz Khan, Southern By Pass Road|10-03-2020|||99.75
Fahad Town HS|Mouza Hamid Pur Kanora, Multan Bypass Road|03-07-2019|||500.91
Faisal Cottage (Revised) Phase-II HS|Mouza Kotla Abul Fatah & Rangeel Pur, Old Shujabad Road|13-01-2021|||399.34
Fine City HS|Arazi Ghulam Mustafa, Southern Bypass Road|08-12-2020|||634.78
Gulastan e Ashar LSD|Mouza Kotla Muhammad Baqa, Off Bahawal Pur Road|10-08-2021|||99.37
Habib Town LSD|Mouza Dera Muhammadi, Off Bahawalpur Road|21-04-2012|||24
Inam LSD|Mouza Kotla Muhammad Baqa, Wali Muhammad Canal Road|28-10-2013|||86
Iram LSD|Mouza Kotla Muhammad Baqa, Off Bahawal Pur Road|07-08-2021|||36.8
Multan Canal View HS|Mouza Dera Muhammadi, Bahawalpur Road|08-12-2012|||247.7
Nayab City LSD|Mouza Bhaini, Stadium Road|29-11-2013|||95
Rasheed Avenue LSD|Mouza Ram Kali, Bahawalpur Road|20-12-2011|||31
Razia Saeed HS|Mouza Alamgir, Muzaffargarh Road|28-04-2021|||103.25
Revised Dream Garden HS|Mouza Kotla Abul Fatah, Askaria By Pass Road|21-10-2020|||780
Sabzwari Town HS|Mouza Kotla Muhammad Baqa, Near new Shah Shams Colony Vehari Road|15-09-2007|||394.75
Sayyam Village HS|Mouza Bhaini & Ram Kali, Southern Bypass Road|16-05-2019|||463.68
Shah Jahan Canal View HS|Mouza Ram Kali, Bahawalpur Road|06-07-2021|||304.5
Zain Masood LSD|Mouza Bhaini, Dunya Pur Road|01-10-2014|||58.85
Revised Green Lane LSD|Mouza Bakhar Arbi, Askaria Bypass Road|20-06-2022|||31.38
Omega City HS|Arazi Ghulam Mustafa Khan, Southern Bypass Road|16-08-2022|||642.88
Hateem City HS|Mouza Kotla Abul Fateh, Askaria Bypass Road|24-07-2023|||170.26
Khayebane Aziz LSD|Mouza Seer Kharak, Ghouse Azam Road|03-03-2023|||103.4
LINES,
    ],
    [
        'key' => 'qda-approved', 'review' => true, 'name' => 'QDA — approved private housing schemes', 'publisher' => 'Quetta Development Authority', 'authority' => 'QDA', 'city' => 'Quetta',
        'type' => 'official_record', 'level' => 1, 'url' => 'https://qda.gob.pk/img/scheme_lists/approved%20scheme-2024.pdf', 'read_on' => $read, 'method' => 'official_pdf',
        'list_type' => 'approved', 'category' => 'residential_society', 'category_basis' => 'official', 'raw_status' => 'Listed as an approved private housing scheme',
        'access' => 'Public PDF on the authority\'s site; no robots.txt (404). Read from the PDF\'s text once. The automated collector does not read PDFs. Owners\' personal details were not copied.',
        'lines' => <<<'LINES'
Ali Town|Western Bypass near Hazara Town|28-07-1990
Garden Town|Chashma Achozai, Quetta|15-08-2005
Quetta Avenue|Spinny Road, Quetta|18-10-2006
Eman City|Samungli Road near PAF base, Quetta|06-07-2007
Sangeen Housing Scheme|Killi Gull Muhammed, Airport Road, Quetta|10-11-2016
Subhan Housing Scheme|Main Samungli Road, Quetta
Gulshan-e-Aziz-I|Samungli Road near Khezai Chowk|30-12-2016
Chiltan Homes I & II|Airport Road, Quetta
Umar Homes|Nawa Killi, Zarghoon Abad, Quetta|03-02-2017
Blue Enclave|Nawa Killi, Saraghurgai, Quetta
Qasim Bagh Town|Baleli near FC Housing Scheme|26-04-2018
Adil H/S|Sara Ghurgai, Quetta
Jan Town-I|Saraghurgai, Quetta|18-10-2019
PHAF|Kuchlak Bypass|19-03-2018
Markhor Hills|Sabzal Road near Sabzal Spinny Road junction|29-04-2019
Quetta Enclave|Baleli Road, Quetta|24-04-2019
Shahzad H/S|Near PF base, Samungli Road, Quetta|21-07-2020
Residencia Avenue|Spinny Road, Quetta
Residencia Home|Spinny Road, Quetta|28-07-2020
Quetta Residencia ZKB|Bacha Agha Road, Baleli, Quetta|29-06-2020
Fazal Villas|Western Bypass near Airport Chowrangi, Quetta
Ashiyana City|Nawa Killi Bypass, Saraghurgai, Quetta|18-12-2020
Capital Enclave|Nawa Killi, Zarghoon Abad bypass road, Quetta|24-12-2020
Aqsa Homes|Main Chashma Baleli Road, Quetta
Kasi Behria Town Baleli|Baleli Road, Quetta|29-11-2019
Marina City|Nohsar Road near Aghbarg|03-03-2021
Takkatu Garden|Sara Ghurgai, Quetta|20-08-2021
Afraa City|Sara Ghurgai bypass road, Quetta
Zarghoon Garden|New bypass road, Saragurgai, Quetta|27-10-2021
Smart Way Valley|Chashma Achozai, Quetta|06-12-2021
Gulshan-e-Zaryab|Chashma Achozai, Quetta|04-12-2021
Subhan Avenue-II|Samungli Road near Zam Zama Hotel, Quetta|25-03-2022
Subhan City|Seryab Road, Quetta|25-03-2022
Khayaban-e-Quetta|Nawa Killi near Kashmir Colony|27-07-2022
Comfort Meadows|Killi Nohsar, Aghbargh, Quetta|07-11-2022
Baleli Homes|Bacha Agha Road, Quetta|28-09-2022
White Enclave|Hanna Urak Road, Quetta|31-01-2022
Jan Town-II|Saraghurgai, Quetta|01-02-2022
Al Murad Housing Scheme|Airport Road, Quetta|20-10-2022
Al Hudda Residencia|Nawa Killi near WAPDA Housing, Quetta
Green Hills|Pashtoon Bagh, Western Bypass, Quetta
Sharakat Villas|Baleli Road, Quetta
Al Rohan/Hassan Garden|Killi Nohsar, Quetta
Roshan Homes|Chashma Bypass Road, Quetta|09-05-2023
Royal City|Sara Ghurgai, Quetta
LINES,
    ],
    [
        'key' => 'fda-private-approved', 'review' => true, 'name' => 'FDA — approved private housing schemes', 'publisher' => 'Faisalabad Development Authority', 'authority' => 'FDA', 'city' => 'Faisalabad',
        'type' => 'official_record', 'level' => 1, 'url' => 'https://fda.gov.pk/schemes_private', 'read_on' => $read, 'method' => 'browser_read',
        'list_type' => 'approved', 'category' => 'residential_society', 'category_basis' => 'official', 'raw_status' => 'Approved', 'split_location' => true,
        'access' => 'Public page; no robots.txt (404). Read once in a browser. The automated collector cannot connect, because the site\'s security certificate chain is incomplete. The page shows 201 approved, 367 illegal and 226 under process; only the approved rows were copied.',
        'lines' => <<<'LINES'
Sitara Sapna City Chak No.123/JB
Al barkat Villas, Chak No. 226/RB, Faisalabad.
Cooperative Housing and Building Society Employees of University of Agriculture Faisalabad, Chak No. 196/RB
Vista Homes Chak No. 121/JB
Sitara Gold City Chak No. 215/RB
Govt: Employees Co- Operative Housing Society Scheme-I Chak No.213/RB
Rahim Town Chak No.220/RB
Icon Gold Valley Chak No. 192/RB Faislabad.
Nazimabad City Chak No. 220/RB
Model Town, Chak No. 263/RB, Samundari Road, dijkot, Faisalabad.
Lyallpur Garden Chak No. 212/RB
Liaqat Town Chak No.220/RB
New Farid Town Chak No.196/RB
Ibrahim Villas Chak No. 199/RB
Pine Park Chak No. 117/JB Faisalabad.
Raja Park Chak No.222/RB
Ghafoor Garden, Chak No. 204/RB
Sitara Park City Chak No. 215/RB
CITI Housing Scheme Chak No. 4&5/JB
Wadi-e-Sitara Chak No. 200/RB
Al Barkah Chak No. 226/RB Faisalabad.
Al-Hamra Town Chak No.199/RB
Usman Block Chak No.120/JB
Defence City, Chak No. 226/RB & 238/RB, Faisalabad.
Sitara Diamond City 226/RB, Satyana Road
Govt: Employees Co-Operative Housing Society-II Chak No.199/RB
Raheem Block Chak No. 204/RB Faisalabad.
Ideal Valley Chak No. 202/RB
Nawaz Town Chak No.120/JB
Lyallpur Villas Chak No. 204/RB
New Garden Block Phase II, Chak No.208/RB
Raza Garden Chak No.204/RB
Shahbaz Town Chak No.220/RB Faisalabad.
Sitara Park City Extension Chak No. 215/RB
Green Homes Chak No. 208/RB
Raza Town Chak No.204/RB
Madina Garden, Chak No. 239/GB, Faisalabad.
Sitara Qamar Villas Chak No. 198/RB Faisalabad
Iqbal Block, Chak No. 204/RB, Faisalabad
Al-Raheem Valley Extension Chak No. 215/RB, Faisalabad
Smart Block Sitara Diamond City 226/RB, Faisalabad
Green Orchard Chak No. 209 RB Faisalabad
Faisal Town Chak No.217/RB
Ibraheem Bajwa Town Chak No.217/RB
Zee Garden Chak No. 198/RB, Faisalabad.
Gulberg housing Chak No. 61/GB, 120/GB Faisalabad
Younis Town Chak No. 225/RB
Ali Town Sitara Gold city, Chak No. 215/RB, Satyana Road, Faisalabad.
Defence view Executive Block Chak No. 226/RB Satiana Road Faisalabad.
Madina Paradise Chak No. 235/RB Faisalabad
The Four Seasons Phase-I Chak No. 234/RB
Qamar Block Chak No. 208/RB Faisalabad
EMAAN CITY Chak No. 61/GB
New Muslim Town Chak No. 120/JB
Rehman Block Chak No. 204/RB
100 Homes Chak No. 204/RB
A.Zee Housing Chak No. 234/RB
Al-Jameel Farm Housing Scheme
Defense Fort Chak No. 223 RB
Green Town Chak No.122/JB
Iqbal Town Chak No.124/JB
Madina Green Valley, Chak No. 214/RB, Faisalabad
Niaz Garden Chak No.242/RB
Rehmat Town Chak No.124/JB
Sitara Supreme City Chak No.119/JB, Faisalabad.
Al-Mahboob Garden Chak No. 223/RB
Doctor City Chak No 238/RB Faisalabad
Canal Park Chak No.204/RB Faisalabad.
Green Valley Chak No. 234/RB
Ismael Green, Chak No. 196/RB, Millat Road, Faisalabad
Oasis Orchard Chak No.223/RB, Faisalabad
Riaz-ul-Jinnah Umar Block Chak No. 7/JB
Sitara Valley Chak No. 197/RB & 198/RB
Al-Qasim Blossom View Chak No.121/JB, Faisalabad.
Eden Garden Chak No. 208/RB
Green View Colony Chak No.123/RB
Jalal Town Chak No.196/RB
Executive Block Chak No. 204/RB Faisalabad.
Manan Town Chak No. 121/JB
Omega Residencia chak no.3/JB, FSD
Saeed Colony, 213/RB
Sitara Villas Chak No. 197/RB, 198/RB Faisalabad.
Al-Raheem Valley Chak No. 215/RB
Eden Garden Executive Block Chak No. 208/RB
Gulberg Valley Chak No. 215/RB
Jamil Town Chak No.124/JB
MID City Chak No.207/RB, Faisalabad.
Orchard Homes Chak No. 239/RB
Haider Valley Chak No. 225/RB Faisalabad.
Safeena Town Chak No.200/RB
SMD City Chak No. 196/RB, 202/RB Faisalabad.
Amin Town Chak No. 207/RB
Eden Garden Phase-II Chak No. 208/RB
GULBERG VILLAS Chak No. 219/RB Faisalabad.
Jawad Town, Chak No. 223/RB, Faisalabad.
Millat Colony Chak Chak No.223/RB
Palm Villas Chak No. 217/RB
Salma Town Chak No.224/RB
Sultan Town Chak No.217/RB
Khayaban e Manzoor Chak No. 209/RB Faisalabad.
Bilal City Chak No. 197/RB
Alpha Housing Chak No. 296/RB
Gulshan-e-Hayat Chak No.220/RB
Johar Town, Chak No. 243/RB, Faisalabad.
Millat Town-III Chak No.196/RB
Park View Colony Chak No.200/RB
Sareena Town Chak No.200/RB
Syed Block Chak No. 207/RB
Bilal Garden 196 RB, Faisalabad
Eden Orchard Chak No. 121/JB
Muhammad Ali Housing Scheme Chak No. 222/RB
Gulshan-e-Iqbal Chak No.222/RB
Model City Chak No. 465/GB
Park View Homes Chak No.5/JB, Sargodha Road Faisalabad.
Sehgal City Chak No. 234/RB (Approved by TMA)
Tech Town, Phase-V, Chak No. 225/RB, Malkhan wala, Faisalabad.
Bilal Orchard, Chak No.196/RB, Ghona Road, Faisalabad.
Eden Villas Chak No. 222/RB
Gulshan-e-Madina Chak No.120/JB
Karim Town Chak No.224/RB
Premium Block Chak No. 204/RB Faisalabad.
Model City-II Chak No. 238/RB
Pine Garden Chak No. 209 RB Faisalabad
Shadman Town Chak No.120/JB
Tech. Town Phase-II & III, Chak No. 226/RB
Abdullah Valley Chak No. 235/RB
Shalimar Homes 213/RB Faisalabad.
Ahmad Canal View Chak No247/RB
AJS Housing Chak No. 215/RB Faisalabad
The Four Seasons Phase-II Chak No. 234/RB Faisalabad.
Abdullah Defence Chak No. 229/RB Faisalabad.
Bismillah Orchard Chak No. 61/GB Faisalabad.
Canal Valley Chak No. 209 & 215/RB Faisalabad.
Azaan City Chak No. 239/GB Faisalabad.
Babar Sultan Valley Chak No. 258/RB, Faisalabad
Executive City Chak No. 215/RB Faisalabad
Deens Paradise VIP Block Chak No. 233/RB, Faisalabad.
Deens Valley Chak No. 233/RB Faisalabad.
Divine Enclave Chak No. 204/Rb Faislabad
Hameed Town Chak No.124/JB Faisalabad.
Dream Valley Chak No. 186/RB Faisalabad.
Faisalabad Lawyers Cooperative Housing Society Chak No. 198/RB
Khayaban Garden Hassan Block Chak No. 121/JB Faisalabad.
Grand Housing Arena Chak No. 235/RB Faisalabad.
GREEN BLOCK PHASE-I II, Chak No. 204/RB Faisalabad
GREEN BLOCK PHASE-IV Chak No. 204/RB Faisalabad
Muhammad Khan Town Chak No.207/RB Faisalabad.
Gulshane-e-Haram Villas Chak no.223/RB, Faisalabad
Prime City Chak NO. 239/RB, Staina Road, Faisalabad
Shalimar Park Chak No.224/RB Faisalabad.
The Meadows Phase-I, Chak No. 234/RB Faisalabad.
Lyallpur Avenue Chak No.227/RB Faisalabad.
Abdullah Farm Housing Scheme Chak No.204/RB
Lyallpur Villas Executive Block Chak No. 204/RB Faisalabad.
Madina Garden, Chak No. 387/GB, Samundari Road, Faisalabad.
Canal Villas Chak No. 204/RB Faisalabad.
Model City Royal Villas, Chak No. 214/RB Faisalabad.
Ejaz Town Chak No.124/JB Faisalabad.
Nazimabad Villas Chak No. 220/ RB, Faisalabad
Hamza Homes Chak No. 204/RB Faisalabad.
Oasis One Chak No. 209/RB Faisalabad.
Paradise Palm Chak No.234/RB, Faisalabad
Koh-I-Noor Town Chak No.213/RB Faisalabad.
Prime Valley Chak No. 225/RB Faisalabad
Nasir Town Chak No.224/RB Faisalabad.
Rehmat Garden Chak No. 222/RB, Faisalabad.
Rachna Town Chak No.214/RB Faisalabad.
Sitara Abban Valley Chak No. 197/RB & 198/RB Faisalabad.
Sitara Heights Chak No.120/JB Faisalabad.
SMD Homes, Chak No. 121/ JB, Sargodha Road, Near Same Nala, Faisalabad.
The Meadows Phase-II, Chak No. 234/RB Faisalabad.
The Fasialabad Lawyers Cooperative Housing Society, Chak No. 208/RB Faisalabad.
Top City Chak No. 236/RB Faisalabad.
Umer Exectutive Block Chak No. 07/JB Faisalabad.
Air Avinue City, Chak No. 230/RB Faisalabad.
Wadi-e-Aban Chak No.200/RB Faisalabad.
Chenab Garden Chak No. 223/RB Faisalabad.
Din Gardens Chak No. 144/RB Faisalabad
Fareed Town Chak No.214/RB Faisalabad.
Haider Garden Chak No. 119/GB Faisalabad.
Haven Habitat-I Chak No.204/RB Faisalabad.
LalazarColony Chak No.226/RB Faisalabad.
Nawab City Chak No. 5/JB Faisalabad.
Rafi Villas Chak No. 119/GB Faisalabad.
Sitara Colony Chak No.222/RB Faisalabad.
Umar Garden Chak No. 204/RB
Akbar Housing Scheme Chak No. 204/RB
CITI Housing Scheme Chak No. 234/RB
LINES,
    ],
    [
        'key' => 'fda-schemes', 'review' => true, 'name' => 'FDA — schemes developed by FDA', 'publisher' => 'Faisalabad Development Authority', 'authority' => 'FDA', 'city' => 'Faisalabad',
        'type' => 'official_record', 'level' => 1, 'url' => 'https://fda.gov.pk/schemes', 'read_on' => $read, 'method' => 'browser_read',
        'list_type' => 'authority_scheme', 'category' => 'residential_society', 'category_basis' => 'official', 'raw_status' => 'Scheme of the authority',
        'access' => 'Public page; no robots.txt (404). Read once in a browser; the collector cannot connect because the site\'s certificate chain is incomplete.',
        'lines' => <<<'LINES'
Ahmad Nagar|D-Type Colony Road, Faisalabad
Allama Iqbal Colony|Aziz Bhatti Shaheed Road, Faisalabad
Awami Colony|Tariq/Milad Road
FDA City|M4 Interchange, Sargodha Road
Gulberg Colony|Narwala Road, Faisalabad
Gulfishan Colony|Jhang Road, Faisalabad
Gulistan Colony-1|Raja Road, Shekhupura Road
Gulistan Colony-2|Millat Chowk, Shekhupura Road, Faisalabad
Gulshan Colony|Faisalabad
Madina Town (Extension)|Jaranwala/College Road, Faisalabad
Madina Town|Jaranwala/Susan Road, Faisalabad
Millat Town|Millat Road, Faisalabad
Nazim Abad|Dijkot Road, Faisalabad
Quaid-E-Azam Block|Faisalabad
Saeed Abad|GM Abad Road, Faisalabad
Sir Syed Town (Doso Barah)-I|Dijkot Road, Faisalabad
Sir Syed Town (Doso Barah)-II|Dijkot Road, Faisalabad
Sir Syed Town (Doso Barah)-III|Dijkot Road, Faisalabad
Weavar Colony|GM Abad Road, Faisalabad
LINES,
    ],
    [
        'key' => 'mda-commercial', 'review' => true, 'name' => 'MDA — commercial schemes developed by MDA', 'publisher' => 'Multan Development Authority', 'authority' => 'MDA Multan', 'city' => 'Multan',
        'type' => 'official_record', 'level' => 1, 'url' => 'https://mda.punjab.gov.pk/housing-schemes', 'read_on' => $read, 'method' => 'official_page',
        'list_type' => 'authority_scheme', 'category' => 'commercial_plaza', 'category_basis' => 'official', 'raw_status' => 'Scheme of the authority',
        'access' => 'Public page; robots.txt permits reading. Only the plaza, arcade and shopping centre rows were copied; the land markets on the same table are outside the five categories.',
        'lines' => <<<'LINES'
Plaza opposite Civil Hospital|Multan
Lohari Gate Shopping Centre|Multan
MDA Arcade (Block A and B)|Multan
LINES,
    ],
    [
        'key' => 'pda-approved', 'review' => true, 'name' => 'PDA — approved housing societies', 'publisher' => 'Peshawar Development Authority', 'authority' => 'PDA', 'city' => 'Peshawar',
        'type' => 'official_record', 'level' => 1, 'url' => 'https://www.pda.kp.gov.pk/illegalsocieties', 'read_on' => $read, 'method' => 'official_page',
        'list_type' => 'approved', 'category' => 'residential_society', 'category_basis' => 'official', 'raw_status' => 'APPROVED',
        'access' => 'Public page; robots.txt permits reading. Its cards have no stable format for the collector yet, so it was read once. The separate PDA downloads page and the KP LUBCA approved list disallow automated reading and were not read.',
        'lines' => <<<'LINES'
Sheikh Yaseen Town|Nasir Bagh Road, Peshawar
Shaheen Housing Scheme|Warsak Road, Peshawar
Zintara Residences|Ring Road, near Charsadda Road, Peshawar
New University Model Town|Nasir Bagh Road, Peshawar
Professors Model Town|Nasir Bagh Road, Peshawar
Safia Homes|Warsak Road, Peshawar
Garden Villas|Service Road M-1, near Toll Plaza, Peshawar
Hasham Babar Town|Mathra, Warsak Road, Peshawar
LINES,
    ],
    [
        'key' => 'pda-unapproved', 'review' => true, 'name' => 'PDA — un-approved housing societies', 'publisher' => 'Peshawar Development Authority', 'authority' => 'PDA', 'city' => 'Peshawar',
        'type' => 'official_record', 'level' => 1, 'url' => 'https://www.pda.kp.gov.pk/illegalsocieties', 'read_on' => $read, 'method' => 'official_page',
        'list_type' => 'illegal', 'category' => 'residential_society', 'category_basis' => 'official', 'raw_status' => 'UN-APPROVED',
        'access' => 'Public page; robots.txt permits reading. Read once. Held in the official registry only; these names are not published as projects.',
        'lines' => <<<'LINES'
Peshawar Garden|Muza Rasheeda, G.T. Road, Peshawar
Omal-Qura|Muza Rasheeda, G.T. Road, Peshawar
Madina Residencia|G.T. Road, Peshawar
LINES,
    ],
    [
        'key' => 'sbca-noc', 'review' => true, 'name' => 'SBCA — NOC for sale and advertisement (public sale projects)', 'publisher' => 'Sindh Building Control Authority', 'authority' => 'SBCA', 'city' => 'Karachi',
        'type' => 'official_record', 'level' => 1, 'url' => 'https://sbca.gos.pk/housing-Year-2025', 'read_on' => $read, 'method' => 'browser_read',
        'list_type' => 'approved', 'category' => 'residential_society', 'category_basis' => 'inferred', 'raw_status' => 'NOC for sale and advertisement issued',
        'access' => 'Public pages (2021 to 2025) read once in a browser, as a person would. SBCA\'s robots.txt returns a server error, so automated collection stays off. Six industrial or warehouse projects on these pages were left out. SBCA does not state a category: "housing" is inferred from the names and needs checking.',
        'lines' => <<<'LINES'
NAYA NAZIMABAD-I|Survey Nos. 121/1, 121/2 & 121/3, Deh Orangi, Manghopir Road, District West|20-03-2025|Javedan Corporation Limited|SBCA/DD(PSA&C)/392/Adv-02/2025
GUL-E-REHMAT|Survey No. 129, Deh Thano, Taluka Murad Memon, District Malir|12-12-2025|Kanjiani Builders & Developers|SBCA/DD(PSA&C)/411/Adv-44/2025
SAFA DREAM CITY|Sector-7-C, Deh Bhitti Amri, Scheme-33, Karachi East|04-06-2024|A.R Builders & Developers|SBCA/DD(PSA&C)/115/Adv-04/2024
GREEN VILLAS|Deh Joreji, Taluka Bin Qasim, District Malir|05-08-2024|HMS Builders & Developers|SBCA/DD(PSA&C)/299/Adv-07/2024
AL-SYED RESIDENCY||11-07-2023|Syed Associates|SBCA/DD(PSA&C)/220/Adv-279/2023
NAYA NAZIMABAD||16-01-2023|Javedan Corporation Limited|SBCA/DD(PSA&C)/155/Revised/Adv-236/2023
ARABIAN PARK VIEW||06-01-2023|MG Builders & Developers|SBCA/DD(PSA&C)/171/Adv-229/2023
LALAZAR||05-01-2022|I.T Associates|SBCA/DD(PSA&C)/142/Adv-31/2022
MALIR TOWN RESIDENCY PHASE-III||22-02-2022|Global Solution Builder & Developers|SBCA/DD(PSA&C)/41/Adv-36/2022
HAFSA GARDEN||23-02-2022|Hafsa Garden Enterprise|SBCA/DD(PSA&C)/154/Adv-38/2022
MALIR TOWN RESIDENCY PHASE-VI||08-03-2022|Global Solution Builder & Developers|SBCA/DD(PSA&C)/40/Adv-39/2022
MALIR TOWN RESIDENCY PHASE-IV||08-03-2022|Global Solution Builder & Developers|SBCA/DD(PSA&C)/126/Adv-40/2022
BAGH-E-MEHRAN||07-04-2022|Shahana Builder & Developers|SBCA/DD(PSA&C)/1503/Adv-56/2022
BIN RAHEEM GARDEN||27-06-2022|Bin Raheem & Co.|SBCA/DD(PSA&C)/61/Adv-94/2022
GREEN MALIR||19-08-2022|Dynacon Builder & Developers|SBCA/DD(PSA&C)/143/Adv-126/2022
DREAM PARADISE||19-08-2022|Peerless Builder & Developers|SBCA/DD(PSA&C)/16/Adv-125/2022
ORANGI TOWN RESIDENCY||30-06-2022|Global Solutions Builder & Developers|SBCA/DD(PSA&C)/76/Adv-103/2022
ORANGI TOWN RESIDENCY BLOCK-B||30-06-2022|Global Solutions Builder & Developers|SBCA/DD(PSA&C)/77/Adv-104/2022
ORANGI TOWN RESIDENCY BLOCK-C||30-06-2022|Global Solutions Builder & Developers|SBCA/DD(PSA&C)/78/Adv-105/2022
GULSHAN-E-AYSHA PHASE-II||31-08-2022|Alam Builder & Developers|SBCA/DD(PSA&C)/162/Adv-135/2022
GARDEN CITY EXECUTIVE BLOCK||16-09-2022|Atlas Builder & Developers|SBCA/DD(PSA&C)/102/Adv-142/2022
MEMON HEAVEN||16-09-2022|SBS Builder|SBCA/DD(PSA&C)/1353/Adv-144/2022
ANNAFEU RESIDENCY||07-10-2022|Annafeu Builders & Developers|SBCA/DD(PSA&C)/29/Adv-164/2022
ANNAFEU GARDEN VISTA||06-04-2022|Annafeu Builders & Developers|SBCA/DD(PSA&C)/164/Adv-55/2022
GOHAR VILLAS-II||15-06-2022|Gohar Villas|SBCA/DD(PSA&C)/22/Adv-92/2022
GOHAR VILLAS-I||15-06-2022|Gohar Villas|SBCA/DD(PSA&C)/22/Adv-93/2022
AL-JANNAT CITY||08-04-2022|M.A.A. Construction & Builders (Pvt.) Ltd|SBCA/DD(PSA&C)/22/Adv-58/2022
PS CITY PHASE-I||22-03-2021|PS Associates|SBCA/D.D(Design Section)/1558/ADV-01/2021
PS CITY PHASE-II||15-04-2021|PS Associate|SBCA/DD-II(Design Section)/1604/ADV-09/2021
PUNJABI SAUDAGRAN PHASE-II||17-05-2021|Saudagar Corporation|SBCA/DD(Design Section)/1559/ADV-29/2021
SAIMA ARABIAN RANCHES||09-02-2021|Saima Estate Builder & Developers|SBCA/DD-II(D-I)/1506/2021/ADV-71
PIR SARHINDI TOWN||22-04-2021|Khidmat Low Cost Housing Scheme Pvt. Ltd|SBCA/DD(D-II)/1562/2021/ADV-18
NORTH TOWN RESIDENCY OVERSEAS EXT||19-04-2021|Global Solution Builder & Developers|SBCA/DD(D-I)/132/2021/ADV-11
PS RESIDENCY-I||16-06-2021|Saudagar Corporation|SBCA/DD(Design Section)/29/2021/ADV-45
PS RESIDENCY-II||16-06-2021|Saudagar Corporation|SBCA/DD(Design Section)/27/2021/ADV-44
AL-JADEED GREENS||22-06-2021|Asif Zubair Builder & Developers|SBCA/DD(PSA&C)/32/2021/ADV-46
GULSHAN-E-BAHAR||02-03-2021|Home Enterprises|SBCA/DD-II(Design Section)/1608/2021/ADV-1060
AVIATION ENCLAVE||16-07-2021|Aviation Enclave (Pvt.) Ltd.|SBCA/DD (PSA&C)/983/Revised-Adv-53
PS CLASSIC-I||16-06-2021|Saudagar Corporation Pvt. Ltd|SBCA/DD(Design)/27/2021/ADV-42
PS CLASSIC-II||16-06-2021|Saudagar Corporation Pvt. Ltd|SBCA/DD(Design)/28/2021/ADV-43
LINES,
    ],

    /* Secondary references: the project exists as a named entry in a public reference. Nothing is verified by them. */
    [
        'key' => 'wiki-malls', 'name' => 'Wikipedia — List of shopping malls in Pakistan', 'publisher' => 'Wikipedia', 'type' => 'secondary_reference', 'level' => 7,
        'url' => 'https://en.wikipedia.org/wiki/List_of_shopping_malls_in_Pakistan', 'read_on' => $read, 'method' => 'public_reference', 'list_type' => 'reference',
        'category' => 'shopping_mall', 'category_basis' => 'source', 'access' => 'Public reference. Used only to learn that a named mall is reported to exist. It verifies nothing: legal status stays unknown. Listed websites were opened once, and only those that responded as the mall\'s own are kept.',
        'cities' => [
            'Lahore' => ['Emporium Mall|https://nishatemporium.com', 'Packages Mall|https://packagesmall.com', 'Fortress Square', 'Mall of Lahore', 'Amanah Mall|https://amanahmall.com.pk', 'Avenue Mall|https://avenuemall.pk', 'Gulberg Galleria', 'Siddiq Trade Center', 'Imperial Mall', 'Pace Shopping Mall|https://pacepakistan.com', 'Vogue Towers', 'Dolmen Mall Lahore'],
            'Karachi' => ['Dolmen Mall, Clifton', 'Lucky One Mall', 'Square One Mall|https://squareonemall.pk', 'Ocean Mall and Tower', 'Millennium Mall', 'Omega Mall', 'Atrium Mall', 'Com-3 Mall', 'Star City Mall', 'Glass Tower', 'Saima Paari Mall', 'The Place', 'Park Towers', 'Mall of Karachi', 'Northwalk|https://thenorthwalk.com', 'Emerald Tower', 'Deans Mall', 'The Forum Mall', 'Glamour One', 'Zamzama Mall', 'Mashriq Centre', 'The Central Shopping Mall', 'Metro Shopping Mall', 'Defence Mall', 'Cliff Shopping Mall', 'The Center', 'Al-Madni Mall', 'Royal Defence Mall', 'Vincy Mall', 'Jumerah Mall', 'Al Najibi Mall', 'Saima Mall', 'RJ Mall Karachi', 'Samama Shopping Mall', 'Dynasty Tower & Shopping Mall'],
            'Islamabad' => ['The Centaurus', 'Giga Mall (World Trade Center)', 'Safa Gold Mall', 'Amazon Mall', 'Imarat Builders Mall', 'The Olympus', 'The Magnus Mall', 'The Sixth Boulevard, Mall & Apartment', 'The Nexus Mall', 'Gulberg Mall & Signature Living', 'Skypark One', 'Opal Mall', 'Gulberg Arena', 'Gulberg Heights', 'Prism Heights', 'Winston Mall', 'The Gate', 'J7 Icon', 'J7 Emporium', 'Mall of Islamabad', 'The Shopping Mall (TSM)', 'The Aquatic Mall', 'Z1 (Zeta 1) Mall'],
            'Multan' => ['Mall of Multan', 'United Mall Gulgasht', 'Centro Plaza Shopping Mall', 'Crystal Mall', 'Panorama Mall Multan', 'Citi Mall Multan', 'Orient Mall', 'SS Mall Multan Cantt'],
            'Faisalabad' => ['Lyallpur Galleria|https://lyallpurgalleria.pk', 'Lyallpur Galleria II', 'The Grand Central Mall', 'The Boulevard Mall', 'Soha Mall', 'The Grand Atrium Shopping Mall', 'Sitara Mall', 'Kohinoor Shopping Mall', 'Do Burj Shopping Mall', 'Paradise Atrium', 'Pearl City Shopping Mall', 'The Edge Mall', 'Value Mall', 'Chase Up Mall', 'Misaq Ul Mall', 'Gutwala Commercial Hub'],
            'Quetta' => ['Millennium Mall', 'Gold City Mall', 'Balochistan Askari Mall'],
            'Peshawar' => ['Dean\'s Shopping Mall', 'AFI Tower I', 'AFI Tower II', 'Glorious Mall', 'Diamond Mall', '091 Mall', 'Century Mall', 'Central Mall', 'Mall of Peshawar', 'Mall of KPK', 'Kings Mall and Hotel', 'Great Wall Shopping Mall', 'Marina Mall', 'Sheikh Yaseen Mall and Trade Center', 'AH Tower', 'Syed\'s Towers', 'Al Haj Mall and Trade Center', 'Millennium Mall', 'Metropolis Mall', 'Rabbani Mall', 'City Towers', 'Rahman Twin Towers', 'Himalaya Complex', 'Deans City Complex', 'Kaur Complex', 'Florenza Mall', 'Saif Mall and Residency'],
        ],
    ],
    [
        'key' => 'wiki-plazas', 'name' => 'Wikipedia — List of shopping malls in Pakistan (entries named as plazas)', 'publisher' => 'Wikipedia', 'type' => 'secondary_reference', 'level' => 7,
        'url' => 'https://en.wikipedia.org/wiki/List_of_shopping_malls_in_Pakistan', 'read_on' => $read, 'method' => 'public_reference', 'list_type' => 'reference',
        'category' => 'commercial_plaza', 'category_basis' => 'inferred', 'access' => 'Public reference. It verifies nothing. The plaza category comes from the entry\'s own name.',
        'cities' => ['Peshawar' => ['Arbab Plaza', 'Naiz Plaza']],
    ],
    [
        'key' => 'wiki-towers-office', 'name' => 'Wikipedia — tallest buildings in Pakistan, Karachi and Lahore (office use stated)', 'publisher' => 'Wikipedia', 'type' => 'secondary_reference', 'level' => 7,
        'url' => 'https://en.wikipedia.org/wiki/List_of_tallest_buildings_in_Pakistan', 'read_on' => $read, 'method' => 'public_reference', 'list_type' => 'reference',
        'category' => 'business_tower', 'category_basis' => 'source', 'access' => 'Public reference. It verifies nothing. Only buildings whose office use the page states, in its text or in the building\'s own name, are listed.',
        'cities' => [
            'Karachi' => ['MCB Tower||completed|Listed as an office building, completed 2005.', 'Habib Bank Plaza||completed|Listed as an office building, completed 1963.', 'UBL Tower||completed|Listed as the UBL head office.'],
            'Islamabad' => ['The Centaurus Corporate||completed|Listed as completed in 2013; the office use comes from its name.'],
            'Lahore' => ['Arfa Software Technology Park||completed|Listed as completed in 2012; an IT park, as its name states.', 'Celestia IT & Office Tower||under_construction|Listed as under construction.'],
        ],
    ],
    [
        'key' => 'wiki-towers-residential', 'name' => 'Wikipedia — tallest buildings in Pakistan, Karachi and Lahore (residential use stated)', 'publisher' => 'Wikipedia', 'type' => 'secondary_reference', 'level' => 7,
        'url' => 'https://en.wikipedia.org/wiki/List_of_tallest_buildings_in_Pakistan', 'read_on' => $read, 'method' => 'public_reference', 'list_type' => 'reference',
        'category' => 'apartment_complex', 'category_basis' => 'source', 'access' => 'Public reference. It verifies nothing. Only buildings whose residential use the page states, in its text or in the building\'s own name, are listed.',
        'cities' => [
            'Karachi' => ['Bahria Apartment Tower||completed|Listed as completed in 2021; the use comes from its name.'],
            'Islamabad' => ['The Centaurus Residencia||completed|Listed as completed in 2013; the use comes from its name.'],
            'Lahore' => ['Galleria Residences||under_construction|Listed as under construction.', 'The Skyscraper|||Listed with apartments in a cited source.'],
        ],
    ],
    ...require __DIR__.'/sbca_building_nocs.php',
];
