<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every outgoing WhatsApp message: replies to buyers, staff replies and
 * dealer lead alerts. The phone number is stored encrypted (it is needed
 * to send and retry) with a hash for look-ups and the last four digits for
 * display. dedupe_key stops the same message being queued twice; status
 * moves only on real provider responses or webhook status updates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('provider', 20);
            $table->text('recipient'); // encrypted
            $table->char('recipient_hash', 64)->index();
            $table->string('recipient_last4', 4);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 20); // transactional, notification, promotional
            $table->string('purpose', 40); // reply, staff_reply, dealer_lead, opt_out_confirmation …
            $table->string('type', 20)->default('text'); // text, interactive, template
            $table->text('body');
            $table->json('payload')->nullable(); // buttons or template name/parameters (no credentials)
            $table->string('status', 20)->index(); // queued, sending, sent, delivered, read, failed, blocked
            $table->string('blocked_reason', 60)->nullable();
            $table->string('provider_message_id', 120)->nullable()->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->string('dedupe_key', 120)->nullable()->unique();
            $table->boolean('is_test')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['recipient_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
