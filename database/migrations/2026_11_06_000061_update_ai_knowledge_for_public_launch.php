<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Knowledge the website assistant may quote: company and contact details
 * confirmed by the owner, the Investment Index as it now works, and corrected
 * text where the platform changed. Written from the application itself; it
 * names no property, ranking, price or approval, so the assistant has nothing
 * to repeat as data. Re-running updates the same titles; nothing is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $source = 'Product documentation written from the Meezan Property application, with contact details confirmed by the owner (6 Nov 2026)';
        $now = now();
        $documents = [
            'How Meezan Property reviews listings and dealers' => ['support_faq', 'Listings are checked before publication (completeness, duplicates, location and placeholder text). Dealers are verified by staff from uploaded documents; the "Verified" badge appears only after that check, and sample or test accounts never show it. Sample listings are not shown on the public site. Report a suspicious listing with the "Report" link on the listing page.'],
            'How investment rankings work' => ['investment', 'Meezan Property has two kinds of investment pages. The Meezan Investment Index (/investment-rankings) researches development projects in five categories and ranks only projects with enough verified evidence; see "How the Meezan Investment Index works". The Investment Insights pages (/investment) use the market insight score (MPIS), calculated only from reviewed price and rent records; when the records are not enough, no score is shown. Growth scenarios are illustrations of past rates, not forecasts. MPIS methodology: /investment/methodology.'],
            'Dealer subscriptions and packages' => ['support_faq', 'Dealer package prices and limits are being finalised and are not published yet, so no package can be bought at the moment. Dealers and agencies can register and list properties now without choosing a package. Once packages are final they will appear on the pricing page (/pricing) and under Dealer > Subscription (/dealer/subscription), each with its limits for active listings, monthly leads and featured listings. Never quote a package price until it is published there.
Roman Urdu: Dealer packages ki qeematein abhi final ho rahi hain aur shaya nahi hui. Dealer abhi register kar ke listings daal sakte hain.'],
            'About Meezan Property and how to contact us' => ['company', 'Meezan Property is a property marketplace and investment research platform for Pakistan, covering Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar. Visitors can search homes, plots, apartments and commercial property for sale or rent, use map search and comparison, describe a requirement to the AI Property Finder, and read research on development projects in the Meezan Investment Index. Dealers and agencies can register, list properties and manage leads. Contact: office phone +92 300 2358853; WhatsApp +92 311 1749849 (a chat with our team, not an automated assistant); email support@meezanproperty.com; office: Office 2, 3rd Floor, Bigcity Plaza, Gulberg-III, Lahore, Pakistan. The office phone and the WhatsApp number are different numbers. Contact page: /contact.'],
            'How the Meezan Investment Index works' => ['investment', 'The Meezan Investment Index (/investment-rankings) covers five categories: Housing Societies, Commercial Plazas, Apartment Buildings, Shopping Malls and Business Towers. Each category has weighted factors; legal approval is 20 to 25 percent of the weight, and the others include development progress, occupancy, rental income, structural safety, location and market data. A project is ranked only when verified evidence covers at least 60 percent of its category\'s weight, its legal status is verified with an official authority, and its data confidence is at least 35. Missing evidence is never treated as positive. Projects that do not meet the rules are shown as "under research", with their legal status, sources and what is missing, and no score. "On an authority\'s list, not yet checked by us" means a record exists but staff have not verified it. "Unknown / Unverified" means no official record has been found; it does not mean a project is illegal. Projects with a cancellation or adverse official record are excluded. Asking prices and tax valuations are not sales and are not used as verified prices. Methodology: /investment-rankings/methodology. Never state a project\'s rank, score, legal approval or yield unless the published Index shows it.'],
            'Finding property on Meezan Property' => ['product', 'Search from the homepage or /properties by purpose (buy or rent), city, area, property type, price, size and bedrooms. Buy (/buy) shows homes for sale; Plots (/plots) and Commercial (/commercial) have their own pages. Map search is at /map and comparison at /compare. The AI Property Finder (/ai-property-finder) turns a plain-language requirement into search filters and shows matching published listings. Buyers can save a requirement and choose whether to share it with verified dealers who serve those areas. If no listing matches, say so plainly; never describe a property that is not published.'],
        ];

        foreach ($documents as $title => [$category, $body]) {
            $existing = DB::table('ai_knowledge_documents')->where('title', $title)->first();
            if ($existing) {
                if ($existing->body !== $body) {
                    DB::table('ai_knowledge_documents')->where('id', $existing->id)->update(['category' => $category, 'body' => $body, 'source' => $source,
                        'version' => (int) $existing->version + 1, 'updated_at' => $now]);
                }

                continue;
            }
            DB::table('ai_knowledge_documents')->insert(['category' => $category, 'title' => $title, 'body' => $body, 'source' => $source, 'agents' => null,
                'status' => 'approved', 'version' => 1, 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Knowledge is edited in the admin AI Command Center; nothing is removed here.
    }
};
