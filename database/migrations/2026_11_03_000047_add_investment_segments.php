<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investment Rankings by property type (additive only):
 * - projects: investment category (shopping mall, business tower …), map
 *   position, approval status, launch date, specifications and a gallery;
 * - market_observations: bedrooms, so apartments compare like with like;
 * - investment_rankings: the segment (property-type view) and size group a
 *   snapshot row belongs to. Existing rows keep segment = null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('developer')->index();
            $table->decimal('latitude', 10, 7)->nullable()->after('area_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('approval_status', 30)->nullable()->after('approval_note');
            $table->date('launched_on')->nullable()->after('status');
            $table->json('specifications')->nullable()->after('description');
            $table->json('gallery')->nullable()->after('cover_path');
        });

        Schema::table('market_observations', function (Blueprint $table) {
            $table->unsignedTinyInteger('bedrooms')->nullable()->after('size_sqft');
        });

        Schema::table('investment_rankings', function (Blueprint $table) {
            $table->string('segment', 30)->nullable()->after('category');
            $table->string('size_key', 20)->nullable()->after('segment');
            $table->index(['calculation_id', 'segment', 'city'], 'investment_rankings_segment_index');
        });
    }

    public function down(): void
    {
        Schema::table('investment_rankings', function (Blueprint $table) {
            $table->dropIndex('investment_rankings_segment_index');
            $table->dropColumn(['segment', 'size_key']);
        });
        Schema::table('market_observations', fn (Blueprint $table) => $table->dropColumn('bedrooms'));
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'latitude', 'longitude', 'approval_status', 'launched_on', 'specifications', 'gallery']);
        });
    }
};
