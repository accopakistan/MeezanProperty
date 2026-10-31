<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only.
 * - users.notify_account_email: service emails about a user's own listings,
 *   inquiries and leads (on by default; buyer marketing-style alerts keep
 *   using the opt-in notify_email flag).
 * - properties.expiry_reminder_sent_at: so each listing gets one reminder
 *   per listing period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_account_email')->default(true)->after('notify_whatsapp');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->timestamp('expiry_reminder_sent_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notify_account_email'));
        Schema::table('properties', fn (Blueprint $table) => $table->dropColumn('expiry_reminder_sent_at'));
    }
};
