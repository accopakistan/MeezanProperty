<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consent records: who agreed to what, on which channel, when and where it
 * came from, and when it was withdrawn. Nothing promotional is sent without
 * a granted, unrevoked record for that purpose and channel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);          // website, whatsapp, email
            $table->string('contact_hash', 64)->nullable()->index(); // sha256 of the phone/email, never the raw value
            $table->string('purpose', 30);          // share_with_dealers, whatsapp_replies, listing_alerts
            $table->string('source', 40);           // ai_finder, whatsapp_keyword, requirement_form
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['channel', 'purpose', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_consents');
    }
};
