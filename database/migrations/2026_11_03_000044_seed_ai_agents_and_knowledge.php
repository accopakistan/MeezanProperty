<?php

use App\Services\Agents\AgentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registers the 16 agents (version 1) and the product help articles the
 * Customer Support agent answers from. The articles describe how this
 * application works (taken from its own screens and routes); they contain
 * no market figures or business claims. Adds rows only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (AgentCatalog::all() as $slug => $a) {
            if (DB::table('ai_agents')->where('slug', $slug)->exists()) {
                continue;
            }
            $row = [
                'slug' => $slug, 'name' => $a['name'], 'role' => $a['role'], 'description' => $a['description'],
                'instructions' => $a['instructions'], 'model_tier' => $a['tier'], 'model' => null, 'temperature' => 0.2,
                'tools' => json_encode($a['tools']), 'memory_policy' => json_encode($a['memory']), 'status' => 'active',
                'stage' => $a['stage'], 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
            ];
            $id = DB::table('ai_agents')->insertGetId($row);
            DB::table('ai_agent_versions')->insert(['agent_id' => $id, 'version' => 1, 'snapshot' => json_encode($row), 'change_note' => 'Initial registration (Phase 15)', 'created_at' => $now]);
        }

        $source = 'Product documentation written from the Meezan Property application (Phase 15)';
        $docs = [
            ['support_faq', 'How to register as a property dealer', "Open \"Dealer Registration\" (/dealer/register), enter your name, email and phone, choose individual or agency, give your business or agency name and city (office address and registration number are optional), set a password and accept the terms. After registering, upload verification documents from your dealer dashboard (CNIC front and back, agency registration or licence, office proof; private owners can upload an ownership document). Staff review the documents; you receive the verified badge and area-matched leads after approval. You can list properties while verification is in progress, and each listing is reviewed before it goes live.\nRoman Urdu: Dealer Registration (/dealer/register) par jaa kar naam, email, phone, individual ya agency, agency ka naam aur shehar dein, password banayein aur terms accept karein. Phir dashboard se verification documents upload karein (CNIC dono taraf, agency registration/licence, office proof). Staff documents check karta hai; approval ke baad verified badge aur leads milti hain."],
            ['support_faq', 'How to list a property for sale or rent', "Owners can start from \"Sell / List a Property\" (/sell); dealers use \"List Your Property\" in the dealer dashboard. Add the title, purpose (sale or rent), type, city and area, price, size, rooms, description, photos and the map pin. Every listing is reviewed by staff before it is published, and incomplete listings may be sent back with requested changes.\nRoman Urdu: Property list karne ke liye \"Sell / List a Property\" (/sell) ya dealer dashboard mein \"List Your Property\" kholein. Title, sale ya rent, type, shehar aur area, qeemat, size, kamre, tafseel, tasveerein aur map pin dein. Har listing publish hone se pehle staff review karta hai."],
            ['support_faq', 'Dealer subscriptions and packages', "Dealers choose a package under Dealer → Subscription (/dealer/subscription). Each package sets limits for active listings, leads per month and featured listings. Invoices and payments are under the dealer billing pages; bank transfers are confirmed by staff after reviewing the proof. Package prices are shown on the pricing page (/pricing).\nRoman Urdu: Dealer apna package Dealer → Subscription (/dealer/subscription) se chunte hain. Har package mein listings, mahana leads aur featured listings ki had hoti hai. Qeematein pricing page (/pricing) par hain."],
            ['support_faq', 'Account and password help', "If you cannot sign in, use \"Forgot your password?\" on the sign-in page to receive a reset link by email. For security, staff never ask for your password. If you still cannot access your account, ask to speak to our team and a support ticket will be opened.\nRoman Urdu: Sign in na ho to sign-in page par \"Forgot your password?\" dabayein, email par reset link aayega. Staff kabhi aapka password nahi poochta."],
            ['support_faq', 'How Meezan Property reviews listings and dealers', 'Listings are checked before publication (completeness, duplicates and location). Dealers are verified by staff from uploaded documents. Demo listings are labelled "Demo listing". Report a suspicious listing with the "Report" link on the listing page.'],
            ['investment', 'How investment rankings work', 'Rankings use only reviewed and verified market records with their sources. Scores (0-100) are shown with a separate data-confidence level and risk level; when data is insufficient, no ranking is shown. Growth scenarios are illustrations of past rates, not forecasts. Full methodology: /investment/methodology.'],
        ];
        foreach ($docs as [$category, $title, $body]) {
            if (! DB::table('ai_knowledge_documents')->where('title', $title)->exists()) {
                DB::table('ai_knowledge_documents')->insert(['category' => $category, 'title' => $title, 'body' => $body, 'source' => $source, 'agents' => null,
                    'status' => 'approved', 'version' => 1, 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        DB::table('ai_knowledge_documents')->where('source', 'like', 'Product documentation written from the Meezan Property application%')->delete();
        DB::table('ai_agents')->whereIn('slug', array_keys(AgentCatalog::all()))->delete();
    }
};
