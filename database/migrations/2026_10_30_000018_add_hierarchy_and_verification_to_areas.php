<?php

use App\Support\LocationName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the flat Lahore area list into a national location hierarchy
 * (city → society/area → phase → sector/block) with approval and research
 * metadata. Additive only: new columns (nullable or defaulted); existing rows
 * keep their names, slugs and coordinates and are linked to their city.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('city')->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->after('city_id')->constrained('areas')->nullOnDelete();
            $table->unsignedTinyInteger('depth')->default(0)->after('parent_id');
            $table->string('official_name', 200)->nullable()->after('name');
            $table->string('normalized_name', 140)->nullable()->after('official_name');
            $table->string('type', 30)->default('neighbourhood')->after('slug');
            $table->string('authority', 120)->nullable()->after('type');
            $table->string('approval_status', 30)->default('unverified')->after('authority');
            $table->string('approval_reference', 255)->nullable()->after('approval_status');
            $table->string('verification_source_url', 255)->nullable()->after('approval_reference');
            $table->string('source_ids', 120)->nullable()->after('verification_source_url');
            $table->date('verified_on')->nullable()->after('source_ids');
            $table->string('confidence', 10)->nullable()->after('verified_on');
            $table->string('coordinate_precision', 20)->nullable()->after('coordinates_source');
            $table->boolean('needs_review')->default(false)->after('confidence');
            $table->boolean('is_active')->default(true)->after('needs_review');
            $table->foreignId('merged_into_id')->nullable()->after('is_active')->constrained('areas')->nullOnDelete();
            $table->string('research_key', 160)->nullable()->unique()->after('merged_into_id');
            $table->text('notes')->nullable()->after('research_key');

            $table->index(['city_id', 'is_active', 'name']);
            $table->index('normalized_name');
            $table->index('approval_status');
        });

        // Link existing rows to their city (Lahore today) and fill the search key.
        foreach (DB::table('cities')->get(['id', 'name']) as $city) {
            DB::table('areas')->where('city', $city->name)->whereNull('city_id')->update(['city_id' => $city->id]);
        }
        foreach (DB::table('areas')->whereNull('normalized_name')->get(['id', 'name']) as $area) {
            DB::table('areas')->where('id', $area->id)->update(['normalized_name' => LocationName::normalize($area->name)]);
        }
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropIndex(['city_id', 'is_active', 'name']);
            $table->dropIndex(['normalized_name']);
            $table->dropIndex(['approval_status']);
            $table->dropUnique(['research_key']);
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('city_id');
            $table->dropColumn(['depth', 'type', 'official_name', 'normalized_name', 'authority', 'approval_status', 'approval_reference',
                'verification_source_url', 'source_ids', 'verified_on', 'confidence', 'coordinate_precision', 'needs_review', 'is_active', 'research_key', 'notes']);
        });
    }
};
