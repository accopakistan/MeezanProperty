<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('external_contact', 40);
            $table->string('contact_name', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 20)->default('bot')->index();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'external_contact']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            $table->string('external_id', 120)->nullable()->unique();
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('delivery_status', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
