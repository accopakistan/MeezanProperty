<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: facts a source states about a project (area, plots, units sold),
 * each with its source and date. Shown on the profile; never used in a score.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'research_facts')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->json('research_facts')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Additive: nothing is removed.
    }
};
