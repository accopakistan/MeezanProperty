<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meezan Investment Index: verified evidence per project and factor, legal
 * records from official authorities, versioned methodologies, automatic
 * calculation runs, their scores, and the ranking change history. Purely
 * additive; existing ranking tables are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('factor', 40);
            $table->string('subject', 60)->nullable(); // e.g. electricity, for per-utility records
            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->string('value_level', 40)->nullable();
            $table->string('unit', 20)->nullable();
            $table->date('observed_on');
            $this->provenance($table);
            $table->index(['project_id', 'factor', 'verification_status']);
        });

        Schema::create('investment_legal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('authority', 80);
            $table->string('record_type', 30);
            $table->string('status', 30);
            $table->string('severity', 12)->default('none');
            $table->string('reference', 160)->nullable();
            $table->string('title')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('valid_until')->nullable();
            $this->provenance($table);
            $table->index(['project_id', 'verification_status']);
        });

        Schema::create('investment_index_methodologies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('name');
            $table->json('weights');
            $table->json('parameters')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 12)->default('draft'); // draft | active | retired
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('investment_index_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('methodology_id')->constrained('investment_index_methodologies')->restrictOnDelete();
            $table->unsignedInteger('methodology_version');
            $table->string('trigger', 30);
            $table->string('status', 16)->default('running'); // running | published | superseded | failed
            $table->string('inputs_hash', 64)->nullable();
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('investment_index_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('investment_index_runs')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('category', 30);
            $table->string('status', 16); // ranked | not_eligible | insufficient | excluded
            $table->decimal('score', 5, 1)->nullable();
            $table->unsignedInteger('category_rank')->nullable();
            $table->decimal('normalized_score', 5, 1)->nullable();
            $table->decimal('overall_index', 5, 1)->nullable();
            $table->unsignedInteger('overall_rank')->nullable();
            $table->decimal('confidence_score', 5, 1)->default(0);
            $table->string('confidence_level', 14);
            $table->decimal('coverage', 4, 3)->default(0);
            $table->string('risk_class', 12);
            $table->unsignedSmallInteger('risk_points')->default(0);
            $table->string('legal_status', 30);
            $table->decimal('verified_yield', 6, 2)->nullable();
            $table->decimal('development_progress', 5, 1)->nullable();
            $table->string('operational_status', 30)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->json('factors');
            $table->json('reasons')->nullable();
            $table->json('risks')->nullable();
            $table->json('source_ids')->nullable();
            $table->timestamps();
            $table->index(['run_id', 'category', 'category_rank']);
            $table->index(['run_id', 'overall_rank']);
            $table->index(['project_id', 'run_id']);
        });

        Schema::create('investment_index_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('investment_index_runs')->cascadeOnDelete();
            $table->foreignId('previous_run_id')->nullable()->constrained('investment_index_runs')->nullOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('category', 30);
            $table->string('change', 20); // entered | left | moved_up | moved_down | rescored | status
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->unsignedInteger('from_rank')->nullable();
            $table->unsignedInteger('to_rank')->nullable();
            $table->decimal('from_score', 5, 1)->nullable();
            $table->decimal('to_score', 5, 1)->nullable();
            $table->unsignedInteger('from_overall_rank')->nullable();
            $table->unsignedInteger('to_overall_rank')->nullable();
            $table->json('reasons');
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
        });
    }

    /** Where a record came from, when it was collected, and how it was verified. */
    private function provenance(Blueprint $table): void
    {
        $table->foreignId('source_id')->nullable()->constrained('market_sources')->restrictOnDelete();
        $table->string('source_url', 2048);
        // DATETIME, not TIMESTAMP: MariaDB/MySQL without explicit_defaults_for_timestamp give
        // the first NOT NULL TIMESTAMP "ON UPDATE CURRENT_TIMESTAMP", which would overwrite
        // the collection time whenever the record is verified or rejected.
        $table->dateTime('collected_at');
        $table->string('content_hash', 64)->nullable();
        $table->text('excerpt')->nullable();
        $table->string('collector', 80)->nullable();
        $table->string('verification_status', 20)->default('unverified'); // unverified | verified | rejected | superseded
        $table->string('verification_method', 40)->nullable();
        $table->timestamp('verified_at')->nullable();
        $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
        $table->text('rejection_reason')->nullable();
        $table->string('fingerprint', 64)->unique();
        $table->json('meta')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_index_changes');
        Schema::dropIfExists('investment_index_scores');
        Schema::dropIfExists('investment_index_runs');
        Schema::dropIfExists('investment_index_methodologies');
        Schema::dropIfExists('investment_legal_records');
        Schema::dropIfExists('investment_evidence');
    }
};
