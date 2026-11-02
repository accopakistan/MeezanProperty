<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing records. Never deleted or rewritten: a cancelled or refunded
 * invoice keeps its number and amounts; refunds are billing_adjustments.
 * billing_snapshot holds the dealer and package details as issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20); // new, upgrade, downgrade, change, renewal
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('status', 20)->index(); // draft, pending, paid, failed, cancelled, refunded
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('gateway_reference', 120)->nullable();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->json('billing_snapshot');
            $table->boolean('is_test')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
