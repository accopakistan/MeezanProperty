# Project indexing review — the 153 noindex profiles

Data read on 28 November 2026 from the live database (read-only query). Rule applied by the new code (`Project::indexable()`): a real, published profile is indexable when it has at least one researched fact, an approval status or a developer. These 153 profiles have **none of the three**. They stay public at their existing URLs, set to **noindex, follow** and kept out of the sitemap. Nothing was changed in the data.

The full list is also in `noindex-project-profiles.csv` (same folder), with the reason and recommended improvement for each row.

## Summary

| Source the profile came from | Profiles | Potential after genuine research | Why noindex today |
|---|---|---|---|
| MDA Multan — MIT housing schemes | 21 | High | Official list entry cited, but its details were not captured |
| MDA Multan — housing schemes developed by MDA | 10 | High | same |
| FDA — approved private housing schemes | 1 | High | same |
| Wikipedia — tallest buildings (residential or office use stated) | 5 | Medium | Secondary reference only, no facts recorded |
| Wikipedia — list of shopping malls (incl. 2 named as plazas) | 116 | Low | Secondary reference only, no facts recorded |
| **Total** | **153** | 32 high · 5 medium · 116 low | |

By city: Multan 39, Karachi 33, Peshawar 27, Islamabad 22, Faisalabad 17, Lahore 12, Quetta 3. None has coordinates, and only 2 are linked to an area.

For every row, developer, approval status and research facts are all **not on file**. That is what the rule checks.

## Which could become valuable indexable pages

1. **The 32 authority-listed housing schemes (High).** The authority page is already cited on each profile. Recording what that entry actually states (location, scheme type, approved area or plots, approval stage) as dated, sourced facts would qualify them, the same way the 1,078 Lahore, Islamabad and Quetta profiles already qualify. Approval status should be recorded only if the list itself states it; inclusion in a list is not to be read as approval.
2. **The 5 tall buildings (Medium).** The cited list usually states height, floors and use. These can be recorded as "Secondary public reference (unverified)", as was done for 10 similar towers on 19 November. Confirm later from the developer or building authority.
3. **The 116 malls (Low).** The only source is a list of names. Each needs an independent, verifiable source (the mall's own site, its developer, or an authority record) before anything is added. Low value until then.

No profile should be switched to indexable by hand, and no facts should be inferred or invented. Once real facts are added, a profile qualifies automatically and joins the sitemap within an hour.

## Full list

| Project | URL | City | Category | Source cited | Potential |
|---|---|---|---|---|---|
| Al-Jameel Farm Housing Scheme | [al-jameel-farm-housing-scheme](https://meezanproperty.com/projects/al-jameel-farm-housing-scheme) | Faisalabad | Housing society | FDA — approved private housing schemes | High |
| Chase Up Mall | [chase-up-mall](https://meezanproperty.com/projects/chase-up-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Do Burj Shopping Mall | [do-burj-shopping-mall](https://meezanproperty.com/projects/do-burj-shopping-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Gutwala Commercial Hub | [gutwala-commercial-hub](https://meezanproperty.com/projects/gutwala-commercial-hub) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Kohinoor Shopping Mall | [kohinoor-shopping-mall](https://meezanproperty.com/projects/kohinoor-shopping-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Lyallpur Galleria | [lyallpur-galleria](https://meezanproperty.com/projects/lyallpur-galleria) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Lyallpur Galleria II | [lyallpur-galleria-ii](https://meezanproperty.com/projects/lyallpur-galleria-ii) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Misaq Ul Mall | [misaq-ul-mall](https://meezanproperty.com/projects/misaq-ul-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Paradise Atrium | [paradise-atrium](https://meezanproperty.com/projects/paradise-atrium) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Pearl City Shopping Mall | [pearl-city-shopping-mall](https://meezanproperty.com/projects/pearl-city-shopping-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Sitara Mall | [sitara-mall](https://meezanproperty.com/projects/sitara-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Soha Mall | [soha-mall](https://meezanproperty.com/projects/soha-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Boulevard Mall | [the-boulevard-mall](https://meezanproperty.com/projects/the-boulevard-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Edge Mall | [the-edge-mall](https://meezanproperty.com/projects/the-edge-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Grand Atrium Shopping Mall | [the-grand-atrium-shopping-mall](https://meezanproperty.com/projects/the-grand-atrium-shopping-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Grand Central Mall | [the-grand-central-mall](https://meezanproperty.com/projects/the-grand-central-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Value Mall | [value-mall](https://meezanproperty.com/projects/value-mall) | Faisalabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Centaurus Residencia | [the-centaurus-residencia](https://meezanproperty.com/projects/the-centaurus-residencia) | Islamabad | Apartment building | Wikipedia — tallest buildings in Pakistan, Karachi and Lahore (residential use stated) | Medium |
| Amazon Mall | [amazon-mall](https://meezanproperty.com/projects/amazon-mall) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Gulberg Arena | [gulberg-arena](https://meezanproperty.com/projects/gulberg-arena) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Gulberg Heights | [gulberg-heights](https://meezanproperty.com/projects/gulberg-heights) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Gulberg Mall & Signature Living | [gulberg-mall-signature-living](https://meezanproperty.com/projects/gulberg-mall-signature-living) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Imarat Builders Mall | [imarat-builders-mall](https://meezanproperty.com/projects/imarat-builders-mall) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| J7 Emporium | [j7-emporium](https://meezanproperty.com/projects/j7-emporium) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| J7 Icon | [j7-icon](https://meezanproperty.com/projects/j7-icon) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Mall of Islamabad | [mall-of-islamabad](https://meezanproperty.com/projects/mall-of-islamabad) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Opal Mall | [opal-mall](https://meezanproperty.com/projects/opal-mall) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Prism Heights | [prism-heights](https://meezanproperty.com/projects/prism-heights) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Safa Gold Mall | [safa-gold-mall](https://meezanproperty.com/projects/safa-gold-mall) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Skypark One | [skypark-one](https://meezanproperty.com/projects/skypark-one) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Aquatic Mall | [the-aquatic-mall](https://meezanproperty.com/projects/the-aquatic-mall) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Gate | [the-gate](https://meezanproperty.com/projects/the-gate) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Magnus Mall | [the-magnus-mall](https://meezanproperty.com/projects/the-magnus-mall) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Nexus Mall | [the-nexus-mall](https://meezanproperty.com/projects/the-nexus-mall) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Olympus | [the-olympus](https://meezanproperty.com/projects/the-olympus) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Shopping Mall (TSM) | [the-shopping-mall-tsm](https://meezanproperty.com/projects/the-shopping-mall-tsm) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Sixth Boulevard, Mall & Apartment | [the-sixth-boulevard-mall-apartment](https://meezanproperty.com/projects/the-sixth-boulevard-mall-apartment) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Winston Mall | [winston-mall](https://meezanproperty.com/projects/winston-mall) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Z1 (Zeta 1) Mall | [z1-zeta-1-mall](https://meezanproperty.com/projects/z1-zeta-1-mall) | Islamabad | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Bahria Apartment Tower | [bahria-apartment-tower](https://meezanproperty.com/projects/bahria-apartment-tower) | Karachi | Apartment building | Wikipedia — tallest buildings in Pakistan, Karachi and Lahore (residential use stated) | Medium |
| Al Najibi Mall | [al-najibi-mall](https://meezanproperty.com/projects/al-najibi-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Al-Madni Mall | [al-madni-mall](https://meezanproperty.com/projects/al-madni-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Atrium Mall | [atrium-mall](https://meezanproperty.com/projects/atrium-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Cliff Shopping Mall | [cliff-shopping-mall](https://meezanproperty.com/projects/cliff-shopping-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Com-3 Mall | [com-3-mall](https://meezanproperty.com/projects/com-3-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Deans Mall | [deans-mall](https://meezanproperty.com/projects/deans-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Defence Mall | [defence-mall](https://meezanproperty.com/projects/defence-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Dynasty Tower & Shopping Mall | [dynasty-tower-shopping-mall](https://meezanproperty.com/projects/dynasty-tower-shopping-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Emerald Tower | [emerald-tower](https://meezanproperty.com/projects/emerald-tower) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Glamour One | [glamour-one](https://meezanproperty.com/projects/glamour-one) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Glass Tower | [glass-tower](https://meezanproperty.com/projects/glass-tower) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Jumerah Mall | [jumerah-mall](https://meezanproperty.com/projects/jumerah-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Mall of Karachi | [mall-of-karachi](https://meezanproperty.com/projects/mall-of-karachi) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Mashriq Centre | [mashriq-centre](https://meezanproperty.com/projects/mashriq-centre) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Metro Shopping Mall | [metro-shopping-mall](https://meezanproperty.com/projects/metro-shopping-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Millennium Mall | [millennium-mall](https://meezanproperty.com/projects/millennium-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Northwalk | [northwalk](https://meezanproperty.com/projects/northwalk) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Omega Mall | [omega-mall](https://meezanproperty.com/projects/omega-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Park Towers | [park-towers](https://meezanproperty.com/projects/park-towers) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| RJ Mall Karachi | [rj-mall-karachi](https://meezanproperty.com/projects/rj-mall-karachi) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Royal Defence Mall | [royal-defence-mall](https://meezanproperty.com/projects/royal-defence-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Saima Mall | [saima-mall](https://meezanproperty.com/projects/saima-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Saima Paari Mall | [saima-paari-mall](https://meezanproperty.com/projects/saima-paari-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Samama Shopping Mall | [samama-shopping-mall](https://meezanproperty.com/projects/samama-shopping-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Square One Mall | [square-one-mall](https://meezanproperty.com/projects/square-one-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Star City Mall | [star-city-mall](https://meezanproperty.com/projects/star-city-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Center | [the-center](https://meezanproperty.com/projects/the-center) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Central Shopping Mall | [the-central-shopping-mall](https://meezanproperty.com/projects/the-central-shopping-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Forum Mall | [the-forum-mall](https://meezanproperty.com/projects/the-forum-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| The Place | [the-place](https://meezanproperty.com/projects/the-place) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Vincy Mall | [vincy-mall](https://meezanproperty.com/projects/vincy-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Zamzama Mall | [zamzama-mall](https://meezanproperty.com/projects/zamzama-mall) | Karachi | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Galleria Residences | [galleria-residences](https://meezanproperty.com/projects/galleria-residences) | Lahore | Apartment building | Wikipedia — tallest buildings in Pakistan, Karachi and Lahore (residential use stated) | Medium |
| The Skyscraper | [the-skyscraper](https://meezanproperty.com/projects/the-skyscraper) | Lahore | Apartment building | Wikipedia — tallest buildings in Pakistan, Karachi and Lahore (residential use stated) | Medium |
| Celestia IT & Office Tower | [celestia-it-office-tower](https://meezanproperty.com/projects/celestia-it-office-tower) | Lahore | Business tower | Wikipedia — tallest buildings in Pakistan, Karachi and Lahore (office use stated) | Medium |
| Amanah Mall | [amanah-mall](https://meezanproperty.com/projects/amanah-mall) | Lahore | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Avenue Mall | [avenue-mall](https://meezanproperty.com/projects/avenue-mall) | Lahore | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Dolmen Mall Lahore | [dolmen-mall-lahore](https://meezanproperty.com/projects/dolmen-mall-lahore) | Lahore | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Gulberg Galleria | [gulberg-galleria](https://meezanproperty.com/projects/gulberg-galleria) | Lahore | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Imperial Mall | [imperial-mall](https://meezanproperty.com/projects/imperial-mall) | Lahore | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Mall of Lahore | [mall-of-lahore](https://meezanproperty.com/projects/mall-of-lahore) | Lahore | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Pace Shopping Mall | [pace-shopping-mall](https://meezanproperty.com/projects/pace-shopping-mall) | Lahore | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Siddiq Trade Center | [siddiq-trade-center](https://meezanproperty.com/projects/siddiq-trade-center) | Lahore | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Vogue Towers | [vogue-towers](https://meezanproperty.com/projects/vogue-towers) | Lahore | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Abdali Colony | [abdali-colony](https://meezanproperty.com/projects/abdali-colony) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Chah Bohar Wala | [chah-bohar-wala](https://meezanproperty.com/projects/chah-bohar-wala) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Chah Daud Jahanian | [chah-daud-jahanian](https://meezanproperty.com/projects/chah-daud-jahanian) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Chah Shakir Khan Wala | [chah-shakir-khan-wala](https://meezanproperty.com/projects/chah-shakir-khan-wala) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Fatima Jinnah Town Phase-I | [fatima-jinnah-town-phase-i](https://meezanproperty.com/projects/fatima-jinnah-town-phase-i) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| Fatima Jinnah Town Phase-II + (Journalist Quota: 534 Plots) | [fatima-jinnah-town-phase-ii-journalist-quota-534-plots](https://meezanproperty.com/projects/fatima-jinnah-town-phase-ii-journalist-quota-534-plots) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| Gul Din Colony | [gul-din-colony](https://meezanproperty.com/projects/gul-din-colony) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Hassan Parwana | [hassan-parwana](https://meezanproperty.com/projects/hassan-parwana) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Hassan Parwana Extension | [hassan-parwana-extension](https://meezanproperty.com/projects/hassan-parwana-extension) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Housing Scheme near Grain Market | [housing-scheme-near-grain-market](https://meezanproperty.com/projects/housing-scheme-near-grain-market) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Housing Scheme near New Grain Market | [housing-scheme-near-new-grain-market](https://meezanproperty.com/projects/housing-scheme-near-new-grain-market) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Housing Scheme Willayatabad Extension 2 | [housing-scheme-willayatabad-extension-2](https://meezanproperty.com/projects/housing-scheme-willayatabad-extension-2) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Jamal Pura | [jamal-pura](https://meezanproperty.com/projects/jamal-pura) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Jamal Pura Extension | [jamal-pura-extension](https://meezanproperty.com/projects/jamal-pura-extension) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Lawyer's Colony | [lawyers-colony](https://meezanproperty.com/projects/lawyers-colony) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Lodhi Colony | [lodhi-colony](https://meezanproperty.com/projects/lodhi-colony) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| Model Town Phase-I and Phase-II | [model-town-phase-i-and-phase-ii](https://meezanproperty.com/projects/model-town-phase-i-and-phase-ii) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| Model Town Phase-III | [model-town-phase-iii](https://meezanproperty.com/projects/model-town-phase-iii) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| Muslim Colony | [muslim-colony](https://meezanproperty.com/projects/muslim-colony) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| New Qasimpur Colony | [new-qasimpur-colony](https://meezanproperty.com/projects/new-qasimpur-colony) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| New Shah Shams Housing Scheme | [new-shah-shams-housing-scheme](https://meezanproperty.com/projects/new-shah-shams-housing-scheme) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| Officers' Colony | [officers-colony](https://meezanproperty.com/projects/officers-colony) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Officers' Colony Extension | [officers-colony-extension](https://meezanproperty.com/projects/officers-colony-extension) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Old Exhibition Ground | [old-exhibition-ground](https://meezanproperty.com/projects/old-exhibition-ground) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Shah Rukne Alam Phase-II | [shah-rukne-alam-phase-ii](https://meezanproperty.com/projects/shah-rukne-alam-phase-ii) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| Shah Rukne Alam-II (A) (Shaheenabad) | [shah-rukne-alam-ii-a-shaheenabad](https://meezanproperty.com/projects/shah-rukne-alam-ii-a-shaheenabad) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| Shamsabad | [shamsabad](https://meezanproperty.com/projects/shamsabad) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Tipu Sultan Colony | [tipu-sultan-colony](https://meezanproperty.com/projects/tipu-sultan-colony) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Tughlaq Town | [tughlaq-town](https://meezanproperty.com/projects/tughlaq-town) | Multan | Housing society | MDA Multan — housing schemes developed by MDA | High |
| Willayatabad Extension | [willayatabad-extension](https://meezanproperty.com/projects/willayatabad-extension) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Willayatabad-I | [willayatabad-i](https://meezanproperty.com/projects/willayatabad-i) | Multan | Housing society | MDA Multan — MIT housing schemes | High |
| Centro Plaza Shopping Mall | [centro-plaza-shopping-mall](https://meezanproperty.com/projects/centro-plaza-shopping-mall) | Multan | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Citi Mall Multan | [citi-mall-multan](https://meezanproperty.com/projects/citi-mall-multan) | Multan | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Crystal Mall | [crystal-mall](https://meezanproperty.com/projects/crystal-mall) | Multan | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Mall of Multan | [mall-of-multan](https://meezanproperty.com/projects/mall-of-multan) | Multan | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Orient Mall | [orient-mall](https://meezanproperty.com/projects/orient-mall) | Multan | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Panorama Mall Multan | [panorama-mall-multan](https://meezanproperty.com/projects/panorama-mall-multan) | Multan | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| SS Mall Multan Cantt | [ss-mall-multan-cantt](https://meezanproperty.com/projects/ss-mall-multan-cantt) | Multan | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| United Mall Gulgasht | [united-mall-gulgasht](https://meezanproperty.com/projects/united-mall-gulgasht) | Multan | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Arbab Plaza | [arbab-plaza](https://meezanproperty.com/projects/arbab-plaza) | Peshawar | Commercial plaza | Wikipedia — List of shopping malls in Pakistan (entries named as plazas) | Low |
| Naiz Plaza | [naiz-plaza](https://meezanproperty.com/projects/naiz-plaza) | Peshawar | Commercial plaza | Wikipedia — List of shopping malls in Pakistan (entries named as plazas) | Low |
| 091 Mall | [091-mall](https://meezanproperty.com/projects/091-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| AFI Tower I | [afi-tower-i](https://meezanproperty.com/projects/afi-tower-i) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| AFI Tower II | [afi-tower-ii](https://meezanproperty.com/projects/afi-tower-ii) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| AH Tower | [ah-tower](https://meezanproperty.com/projects/ah-tower) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Al Haj Mall and Trade Center | [al-haj-mall-and-trade-center](https://meezanproperty.com/projects/al-haj-mall-and-trade-center) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Central Mall | [central-mall](https://meezanproperty.com/projects/central-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| City Towers | [city-towers](https://meezanproperty.com/projects/city-towers) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Dean's Shopping Mall | [deans-shopping-mall](https://meezanproperty.com/projects/deans-shopping-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Deans City Complex | [deans-city-complex](https://meezanproperty.com/projects/deans-city-complex) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Diamond Mall | [diamond-mall](https://meezanproperty.com/projects/diamond-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Florenza Mall | [florenza-mall](https://meezanproperty.com/projects/florenza-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Glorious Mall | [glorious-mall](https://meezanproperty.com/projects/glorious-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Great Wall Shopping Mall | [great-wall-shopping-mall](https://meezanproperty.com/projects/great-wall-shopping-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Himalaya Complex | [himalaya-complex](https://meezanproperty.com/projects/himalaya-complex) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Kaur Complex | [kaur-complex](https://meezanproperty.com/projects/kaur-complex) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Mall of KPK | [mall-of-kpk](https://meezanproperty.com/projects/mall-of-kpk) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Mall of Peshawar | [mall-of-peshawar](https://meezanproperty.com/projects/mall-of-peshawar) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Marina Mall | [marina-mall](https://meezanproperty.com/projects/marina-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Metropolis Mall | [metropolis-mall](https://meezanproperty.com/projects/metropolis-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Millennium Mall | [millennium-mall-3](https://meezanproperty.com/projects/millennium-mall-3) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Rabbani Mall | [rabbani-mall](https://meezanproperty.com/projects/rabbani-mall) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Rahman Twin Towers | [rahman-twin-towers](https://meezanproperty.com/projects/rahman-twin-towers) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Saif Mall and Residency | [saif-mall-and-residency](https://meezanproperty.com/projects/saif-mall-and-residency) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Sheikh Yaseen Mall and Trade Center | [sheikh-yaseen-mall-and-trade-center](https://meezanproperty.com/projects/sheikh-yaseen-mall-and-trade-center) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Syed's Towers | [syeds-towers](https://meezanproperty.com/projects/syeds-towers) | Peshawar | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Balochistan Askari Mall | [balochistan-askari-mall](https://meezanproperty.com/projects/balochistan-askari-mall) | Quetta | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Gold City Mall | [gold-city-mall](https://meezanproperty.com/projects/gold-city-mall) | Quetta | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
| Millennium Mall | [millennium-mall-2](https://meezanproperty.com/projects/millennium-mall-2) | Quetta | Shopping mall | Wikipedia — List of shopping malls in Pakistan | Low |
