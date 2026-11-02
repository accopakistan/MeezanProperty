<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an inbound WhatsApp message is in the pipeline: queued for the
 * assistant, processed, held (auto-reply off or rate-limited), skipped
 * (a person is handling the chat) or failed. Older rows stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->string('processing_status', 20)->nullable()->after('delivery_status')->index();
            $table->timestamp('processed_at')->nullable()->after('processing_status');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropIndex(['processing_status']);
            $table->dropColumn(['processing_status', 'processed_at']);
        });
    }
};
