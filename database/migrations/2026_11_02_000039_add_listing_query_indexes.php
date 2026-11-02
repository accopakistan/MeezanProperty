<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the commonest listing queries (additive):
 * - city pages, city category pages and search by city:
 *   status + city + purpose, then newest first;
 * - area guides and area category pages: status + area_id, newest first;
 * - dealer listing counts (package allowance, dashboards): user_id + status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->index(['status', 'city', 'purpose', 'published_at'], 'properties_status_city_purpose_published_index');
            $table->index(['status', 'area_id', 'published_at'], 'properties_status_area_published_index');
            $table->index(['user_id', 'status'], 'properties_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_status_city_purpose_published_index');
            $table->dropIndex('properties_status_area_published_index');
            $table->dropIndex('properties_user_status_index');
        });
    }
};
