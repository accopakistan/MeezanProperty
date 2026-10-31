<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requirements saved from an AI conversation: the conversation they came
 * from, soft preferences, the last match outcome, and (WhatsApp buyers have
 * no account) the contact shared with explicit consent. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_requirements', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->json('preferences')->nullable()->after('notes');
            $table->string('match_status', 20)->nullable()->after('status');
            $table->unsignedSmallInteger('last_match_count')->nullable()->after('match_status');
            $table->timestamp('matched_at')->nullable()->after('last_match_count');
            $table->string('contact_name', 120)->nullable()->after('source');
            $table->string('contact_phone', 30)->nullable()->after('contact_name');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('buyer_requirement_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_requirement_id');
        });
        Schema::table('buyer_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropColumn(['preferences', 'match_status', 'last_match_count', 'matched_at', 'contact_name', 'contact_phone']);
        });
    }
};
