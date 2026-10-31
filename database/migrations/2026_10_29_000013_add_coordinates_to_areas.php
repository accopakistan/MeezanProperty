<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approximate centre point per area, used to place listings that have no
 * exact pin on the map. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('slug');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('coordinates_source', 40)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'coordinates_source']);
        });
    }
};
