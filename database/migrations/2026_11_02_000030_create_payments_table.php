<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per payment attempt against an invoice. Card data, CVVs and
 * banking passwords are never stored: only the gateway's own reference,
 * the payer's transfer reference and (for bank transfers) a proof file
 * on the private disk. A payment only becomes "succeeded" through the
 * payment service, from a verified gateway event or an admin approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 30);
            $table->string('status', 20)->index(); // pending, awaiting_review, succeeded, failed, cancelled, refunded
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('gateway_reference', 120)->nullable();
            $table->string('payer_reference', 120)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_mime', 100)->nullable();
            $table->char('proof_sha256', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamps();

            $table->unique(['gateway', 'gateway_reference']);
            $table->index(['user_id', 'status']);
            $table->index('payer_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
