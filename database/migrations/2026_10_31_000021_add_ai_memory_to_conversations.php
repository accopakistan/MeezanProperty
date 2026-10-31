<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI conversation memory on the existing conversation tables, shared by the
 * website assistant and WhatsApp. Additive only: existing WhatsApp rows keep
 * working ("direction" stays; "role" is added alongside).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Website conversations belong to a browser session and, when signed in, a user.
            $table->string('session_key', 64)->nullable()->after('external_contact')->index();
            $table->string('status', 20)->default('active')->after('mode')->index();
            $table->json('state')->nullable()->after('status');
            $table->string('locale', 5)->nullable()->after('state');
            $table->timestamp('escalated_at')->nullable()->after('last_message_at');
            $table->string('escalation_reason', 160)->nullable()->after('escalated_at');
            $table->index(['channel', 'last_message_at']);
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->string('role', 10)->nullable()->after('direction');
            $table->string('intent', 30)->nullable()->after('type');
            $table->json('extracted')->nullable()->after('payload');
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'id']);
            $table->dropColumn(['role', 'intent', 'extracted']);
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['channel', 'last_message_at']);
            $table->dropIndex(['session_key']);
            $table->dropIndex(['status']);
            $table->dropColumn(['session_key', 'status', 'state', 'locale', 'escalated_at', 'escalation_reason']);
        });
    }
};
