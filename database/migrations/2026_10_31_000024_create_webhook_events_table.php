<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per webhook delivery: makes retried deliveries idempotent (same
 * payload hash) and keeps a record of failures for admins. Payloads are not
 * stored here; messages live in conversation_messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('payload_hash', 64);
            $table->string('status', 20)->index(); // processed, duplicate, rejected, failed
            $table->unsignedSmallInteger('messages')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'payload_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
