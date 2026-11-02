<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds, credits and debits recorded by staff. Append-only (the model
 * refuses updates and deletes), each with a reason and the staff member.
 * Recording a refund here does not move money: it records a refund made
 * through the bank or gateway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20); // refund, credit, debit
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('reason', 500);
            $table->string('external_reference', 120)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_adjustments');
    }
};
