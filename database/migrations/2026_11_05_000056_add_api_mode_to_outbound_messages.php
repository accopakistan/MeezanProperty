<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: records whether an outgoing WhatsApp message was a mock, a real
 * Meta test message or a production message. Existing rows are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('outbound_messages', 'api_mode')) {
            Schema::table('outbound_messages', function (Blueprint $table) {
                $table->string('api_mode', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Additive: nothing is removed, so the delivery record stays intact.
    }
};
