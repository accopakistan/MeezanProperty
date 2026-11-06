<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: how a listing reached Meezan Property, and so whether it may be
 * published. A dealer's or owner's own submission is "direct_submission"; an
 * imported listing needs a named basis (an authorised feed, a licence or a
 * partnership) and its source. A listing seen on another portal has no basis
 * and is never published here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'publication_basis')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->string('publication_basis', 30)->nullable()->index();
                $table->string('source_name', 120)->nullable();
                $table->string('source_url', 2048)->nullable();
                $table->string('source_reference', 120)->nullable();
                $table->dateTime('source_verified_at')->nullable();
                $table->index(['source_name', 'source_reference']);
            });
        }

        // Every existing real listing was submitted through the site by its dealer or owner.
        DB::table('properties')->whereNull('publication_basis')->where('is_demo', false)->update(['publication_basis' => 'direct_submission']);
    }

    public function down(): void
    {
        // Additive: nothing is removed.
    }
};
