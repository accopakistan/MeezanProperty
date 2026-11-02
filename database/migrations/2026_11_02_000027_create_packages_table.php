<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dealer subscription packages, managed by admins. Limits are nullable:
 * null means "no limit". Nothing in the code depends on a particular
 * package existing; subscriptions keep a snapshot of what was bought.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('description', 500)->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('PKR');
            $table->string('billing_interval', 10)->default('month'); // month, year
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedInteger('listing_limit')->nullable();
            $table->unsignedInteger('lead_limit')->nullable(); // per calendar month
            $table->unsignedInteger('featured_limit')->nullable();
            $table->unsignedInteger('team_member_limit')->nullable();
            $table->unsignedInteger('ai_request_limit')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false); // plan for dealers without a subscription
            $table->boolean('is_recommended')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
