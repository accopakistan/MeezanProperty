<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: the research state of each project in a run, kept apart from the
 * verified score and rank so a provisional figure can never be read as either.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('investment_index_scores', 'provisional_score')) {
            Schema::table('investment_index_scores', function (Blueprint $table) {
                $table->decimal('provisional_score', 5, 1)->nullable();
                $table->unsignedInteger('provisional_rank')->nullable();
                $table->decimal('provisional_coverage', 4, 3)->nullable();
                $table->decimal('provisional_confidence', 5, 1)->nullable();
                $table->unsignedSmallInteger('provisional_unchecked')->nullable();
                $table->unsignedInteger('research_depth')->default(0);
                $table->index(['run_id', 'category', 'provisional_rank']);
            });
        }
    }

    public function down(): void
    {
        // Additive: nothing is removed.
    }
};
