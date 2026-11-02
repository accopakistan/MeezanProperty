<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dealer's contract for one package. Changing package starts a new row
 * (previous_subscription_id), so history is never overwritten; each paid
 * period is an invoice. package_snapshot keeps the limits and price that
 * applied, whatever happens to the package later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('status', 20); // pending_payment, trial, active, past_due, cancelled, expired, suspended
            $table->string('change_type', 20)->default('new'); // new, upgrade, downgrade, change
            $table->string('billing_interval', 10);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->json('package_snapshot');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable(); // renewal / expiry date; null = no end (free)
            $table->boolean('cancel_at_period_end')->default(false);
            $table->foreignId('scheduled_package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 255)->nullable();
            $table->timestamp('expiry_reminder_sent_at')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
