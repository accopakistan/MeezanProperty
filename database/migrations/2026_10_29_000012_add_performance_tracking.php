<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance tracking from this point on: daily listing views and the time
 * a dealer first responds to an inquiry or lead. Nothing is backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_view_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);

            $table->unique(['property_id', 'date']);
            $table->index('date');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->timestamp('responded_at')->nullable()->after('status');
        });

        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->timestamp('responded_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('lead_assignments', fn (Blueprint $table) => $table->dropColumn('responded_at'));
        Schema::table('inquiries', fn (Blueprint $table) => $table->dropColumn('responded_at'));
        Schema::dropIfExists('property_view_stats');
    }
};
