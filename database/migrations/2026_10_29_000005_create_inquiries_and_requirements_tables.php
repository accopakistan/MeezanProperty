<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 30);
            $table->text('message');
            $table->string('preferred_contact', 20)->default('call');
            $table->boolean('consent_to_share')->default(false);
            $table->string('status', 20)->default('new')->index();
            $table->string('source', 20)->default('website');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['email', 'property_id', 'created_at']);
        });

        Schema::create('buyer_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 160)->nullable();
            $table->string('purpose', 10);
            $table->string('type', 30)->nullable();
            $table->string('city', 80);
            $table->json('area_ids')->nullable();
            $table->unsignedBigInteger('min_price')->nullable();
            $table->unsignedBigInteger('max_price')->nullable();
            $table->unsignedInteger('min_area_sqft')->nullable();
            $table->unsignedInteger('max_area_sqft')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->string('source', 20)->default('account');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_requirements');
        Schema::dropIfExists('inquiries');
    }
};
