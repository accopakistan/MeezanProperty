<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Market Intelligence & Data Centre (additive).
 *
 * - market_sources       where data comes from, its licence and reliability
 * - market_observations  sale-price and rent observations (asking vs transaction kept apart)
 * - market_indicators    demand, supply, liquidity, development, infrastructure, legal, developer status, growth drivers
 * - market_import_batches CSV imports and their row-level results
 * - investment_methodologies versioned scoring weights and normalisation ranges
 * - investment_calculations  every recalculation run (inputs, status, result)
 * - investment_rankings      ranking snapshots per calculation (never rewritten once published)
 *
 * Observations and indicators follow draft -> submitted -> reviewed -> verified
 * (or rejected); only verified rows are used in calculations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            // verified_transactions | public_asking_prices | authorized_report | official_record | verified_rental | admin_research
            $table->string('type', 30);
            $table->string('url', 500)->nullable();
            $table->string('license', 255)->nullable();
            // high | medium | low
            $table->string('reliability', 10)->default('medium');
            $table->string('coverage', 255)->nullable();
            // Sources from the same organisation share a group (for independence).
            $table->string('publisher', 160)->nullable();
            $table->date('last_checked_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('market_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // observations | indicators
            $table->string('file_name', 200);
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $workflow = function (Blueprint $table) {
            // draft | submitted | reviewed | verified | rejected
            $table->string('status', 12)->default('draft');
            $table->text('methodology');
            $table->text('notes')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('market_import_batches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        };

        Schema::create('market_observations', function (Blueprint $table) use ($workflow) {
            $table->id();
            $table->string('kind', 10);            // sale | rent
            $table->string('price_basis', 12);     // asking | transaction | valuation
            $table->string('country', 60)->default('Pakistan');
            $table->string('city', 80);
            $table->foreignId('area_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('asset_class', 12);     // residential | commercial
            $table->string('property_type', 30);
            $table->decimal('size_value', 12, 2)->nullable();
            $table->string('size_unit', 10)->nullable();
            $table->decimal('size_sqft', 12, 2)->nullable();
            $table->char('currency', 3)->default('PKR');
            $table->unsignedBigInteger('average_amount');   // sale: total price; rent: per month
            $table->unsignedBigInteger('min_amount')->nullable();
            $table->unsignedBigInteger('max_amount')->nullable();
            $table->date('observed_on');
            $table->unsignedInteger('sample_size');
            $table->foreignId('source_id')->constrained('market_sources')->restrictOnDelete();
            $table->string('fingerprint', 64);     // duplicate detection
            $workflow($table);

            $table->index(['status', 'city', 'kind', 'observed_on']);
            $table->index(['area_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index('fingerprint');
        });

        Schema::create('market_indicators', function (Blueprint $table) use ($workflow) {
            $table->id();
            $table->string('country', 60)->default('Pakistan');
            $table->string('city', 80);
            $table->foreignId('area_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('asset_class', 12)->nullable();
            // demand | supply | liquidity | development_progress | infrastructure | legal_verification | developer_status | growth_drivers
            $table->string('indicator', 30);
            $table->string('level', 30)->nullable();               // e.g. high, balanced, verified
            $table->decimal('numeric_value', 8, 2)->nullable();    // e.g. development progress %
            $table->date('observed_on');
            $table->foreignId('source_id')->constrained('market_sources')->restrictOnDelete();
            $table->string('fingerprint', 64);
            $workflow($table);

            $table->index(['status', 'city', 'indicator', 'observed_on']);
            $table->index(['area_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index('fingerprint');
        });

        Schema::create('investment_methodologies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('name', 120);
            $table->json('weights');        // [category => [factor => weight]]
            $table->json('parameters');     // normalisation ranges, penalties, thresholds
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('draft'); // draft | active | retired
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('investment_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('methodology_id')->constrained('investment_methodologies')->restrictOnDelete();
            $table->unsignedInteger('methodology_version');
            $table->string('scope', 80)->default('all'); // all | a city name
            $table->string('status', 12)->default('running'); // running | completed | failed | published | superseded
            $table->string('trigger', 12)->default('manual'); // manual | scheduled
            $table->json('inputs')->nullable();   // observation / indicator ids and a checksum
            $table->json('summary')->nullable();  // entities ranked, insufficient, per category
            $table->text('error')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scope']);
        });

        Schema::create('investment_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_id')->constrained('investment_calculations')->cascadeOnDelete();
            $table->unsignedInteger('methodology_version');
            $table->string('category', 20);       // overall | appreciation | rental | low_risk | emerging | commercial | residential
            $table->string('entity_type', 10);    // city | area | project
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_name', 160);
            $table->string('country', 60)->default('Pakistan');
            $table->string('city', 80);
            $table->string('asset_class', 12);    // residential | commercial
            $table->string('status', 20);         // ranked | insufficient_data
            $table->unsignedInteger('rank')->nullable();
            $table->decimal('score', 5, 1)->nullable();
            $table->string('confidence', 12);     // high | medium | limited | insufficient
            $table->decimal('confidence_score', 5, 1);
            $table->string('risk', 12);           // low | medium | high | insufficient
            $table->json('factors');              // per factor: score, weight, inputs
            $table->json('metrics');              // changes, yield, indicators, scenarios, sources
            $table->timestamps();

            $table->index(['calculation_id', 'category', 'city', 'asset_class', 'rank'], 'investment_rankings_listing_index');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_rankings');
        Schema::dropIfExists('investment_calculations');
        Schema::dropIfExists('investment_methodologies');
        Schema::dropIfExists('market_indicators');
        Schema::dropIfExists('market_observations');
        Schema::dropIfExists('market_import_batches');
        Schema::dropIfExists('market_sources');
    }
};
