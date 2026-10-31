<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace trust fields on listings. Additive only.
 *
 * - location_privacy: what the public may see (exact, approximate, area).
 *   Existing listings keep "exact", which is how they were shown before.
 * - public_latitude/longitude: the only coordinates public pages and
 *   spatial search use; the dealer's pin stays in latitude/longitude.
 * - location_status: none, approximate, exact, verified or flagged.
 * - completeness_score, review_flags, flagged_at: internal review aids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('location_privacy', 12)->default('exact')->after('longitude');
            $table->decimal('public_latitude', 10, 7)->nullable()->after('location_privacy');
            $table->decimal('public_longitude', 10, 7)->nullable()->after('public_latitude');
            $table->string('location_status', 12)->default('none')->after('public_longitude');
            $table->string('location_flag_reason', 255)->nullable()->after('location_status');
            $table->timestamp('location_verified_at')->nullable()->after('location_flag_reason');
            $table->foreignId('location_verified_by')->nullable()->after('location_verified_at')->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('completeness_score')->nullable()->after('location_verified_by');
            $table->json('review_flags')->nullable()->after('completeness_score');
            $table->timestamp('flagged_at')->nullable()->after('review_flags');

            $table->index('location_status');
            $table->index('completeness_score');
            $table->index(['public_latitude', 'public_longitude']);
        });

        Schema::table('property_images', function (Blueprint $table) {
            $table->string('status', 12)->default('active')->after('sort_order'); // active, flagged, rejected, removed
            $table->string('moderation_note', 255)->nullable()->after('status');
            $table->foreignId('moderated_by')->nullable()->after('moderation_note')->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('property_images', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'status']);
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['status', 'moderation_note', 'moderated_at']);
        });
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['location_status']);
            $table->dropIndex(['completeness_score']);
            $table->dropIndex(['public_latitude', 'public_longitude']);
            $table->dropConstrainedForeignId('location_verified_by');
            $table->dropColumn(['location_privacy', 'public_latitude', 'public_longitude', 'location_status', 'location_flag_reason',
                'location_verified_at', 'completeness_score', 'review_flags', 'flagged_at']);
        });
    }
};
