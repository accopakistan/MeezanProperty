<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investment Index, Phase 2: authorized collection from official sources
 * (source authorization, collection runs, the official registry entries
 * they read), owner-submitted documents for staff verification, and the
 * exceptions queue. Additive: existing tables only gain nullable columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Databases that ran the first version of 000051 have collected_at as TIMESTAMP, which
        // MariaDB/MySQL may auto-update on every change; make it a plain DATETIME (no default).
        foreach (['investment_evidence', 'investment_legal_records'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dateTime('collected_at')->change());
        }

        Schema::table('market_sources', function (Blueprint $table) {
            $table->string('collector', 40)->nullable()->after('is_active');
            $table->json('collector_config')->nullable()->after('collector');
            $table->string('access_basis', 30)->nullable()->after('collector_config');
            $table->string('terms_url', 2048)->nullable()->after('access_basis');
            $table->text('access_note')->nullable()->after('terms_url');
            $table->timestamp('authorized_at')->nullable()->after('access_note');
            $table->foreignId('authorized_by')->nullable()->after('authorized_at')->constrained('users')->nullOnDelete();
            $table->timestamp('last_collected_at')->nullable()->after('authorized_by');
            $table->string('last_collection_status', 16)->nullable()->after('last_collected_at');
        });

        Schema::create('investment_collection_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('market_sources')->restrictOnDelete();
            $table->string('trigger', 20);
            $table->string('status', 16)->default('running'); // running | completed | partial | failed | blocked
            $table->unsignedInteger('pages_fetched')->default(0);
            $table->unsignedInteger('entries_found')->default(0);
            $table->unsignedInteger('entries_new')->default(0);
            $table->unsignedInteger('entries_changed')->default(0);
            $table->unsignedInteger('entries_removed')->default(0);
            $table->unsignedInteger('matched')->default(0);
            $table->unsignedInteger('legal_records_created')->default(0);
            $table->unsignedInteger('exceptions_opened')->default(0);
            $table->string('content_hash', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['source_id', 'started_at']);
        });

        Schema::create('investment_registry_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('market_sources')->restrictOnDelete();
            $table->string('external_key', 120);
            $table->string('authority', 80);
            $table->string('list_type', 30); // approved | illegal | authority_scheme
            $table->string('scheme_name');
            $table->string('normalized_name')->index();
            $table->string('city', 80)->nullable();
            $table->string('location', 500)->nullable();
            $table->string('raw_status')->nullable();
            $table->string('status', 30)->nullable(); // normalized legal status, null when not recognised
            $table->date('issued_on')->nullable();
            $table->string('reference', 160)->nullable();
            $table->string('source_url', 2048);
            $table->string('content_hash', 64);
            $table->json('raw');
            // DATETIME: never auto-filled or auto-updated by the database.
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('match_status', 16)->default('unmatched'); // matched | manual | ambiguous | unmatched | rejected
            $table->decimal('match_score', 4, 3)->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_id', 'external_key']);
            $table->index(['match_status', 'city']);
        });

        Schema::create('investment_evidence_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('organisation', 160);
            $table->string('submitter_role', 120);
            $table->string('kind', 12); // evidence | legal
            $table->string('factor', 40)->nullable();
            $table->string('subject', 60)->nullable();
            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->string('value_level', 40)->nullable();
            $table->json('figures')->nullable(); // e.g. annual rent and value basis for a yield
            $table->date('observed_on');
            $table->string('authority', 80)->nullable();
            $table->string('record_type', 30)->nullable();
            $table->string('legal_status', 30)->nullable();
            $table->string('reference', 160)->nullable();
            $table->date('issued_on')->nullable();
            $table->string('document_type', 20); // owner_statement | third_party
            $table->string('issuer', 160)->nullable();
            $table->json('files');
            $table->text('notes')->nullable();
            $table->string('status', 12)->default('pending'); // pending | verified | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('evidence_id')->nullable()->constrained('investment_evidence')->nullOnDelete();
            $table->foreignId('legal_record_id')->nullable()->constrained('investment_legal_records')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('investment_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // source_access | format_change | ambiguous_match | removed_from_list | legal_conflict | unrecognised_status | security
            $table->string('status', 12)->default('open'); // open | resolved | dismissed
            $table->string('title');
            $table->text('details')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('market_sources')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registry_entry_id')->nullable()->constrained('investment_registry_entries')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->string('fingerprint', 64);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
            $table->index(['fingerprint', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_exceptions');
        Schema::dropIfExists('investment_evidence_submissions');
        Schema::dropIfExists('investment_registry_entries');
        Schema::dropIfExists('investment_collection_runs');
        Schema::table('market_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('authorized_by');
            $table->dropColumn(['collector', 'collector_config', 'access_basis', 'terms_url', 'access_note', 'authorized_at', 'last_collected_at', 'last_collection_status']);
        });
    }
};
