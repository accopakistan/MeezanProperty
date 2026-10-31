<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dealer verification workflow: when the dealer submitted, when review
 * started, when more documents were requested, an admin-only note and an
 * optional expiry. Per-document review on dealer_documents. Additive only:
 * existing statuses and rows are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealer_profiles', function (Blueprint $table) {
            $table->timestamp('verification_submitted_at')->nullable()->after('verified_at');
            $table->timestamp('review_started_at')->nullable()->after('verification_submitted_at');
            $table->timestamp('changes_requested_at')->nullable()->after('review_started_at');
            $table->timestamp('verification_expires_at')->nullable()->after('changes_requested_at');
            $table->text('internal_note')->nullable()->after('rejection_reason'); // admins only, never shown to the dealer
        });

        Schema::table('dealer_documents', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('type')->index(); // pending, approved, rejected, superseded
            $table->string('review_note', 255)->nullable()->after('status');           // shown to the dealer
            $table->foreignId('reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('sha256', 64)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('dealer_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'review_note', 'reviewed_at', 'sha256']);
        });
        Schema::table('dealer_profiles', function (Blueprint $table) {
            $table->dropColumn(['verification_submitted_at', 'review_started_at', 'changes_requested_at', 'verification_expires_at', 'internal_note']);
        });
    }
};
