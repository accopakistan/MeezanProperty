<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('agency_name', 150);
            $table->string('slug', 170)->unique();
            $table->string('city', 80)->index();
            $table->string('office_address')->nullable();
            $table->string('registration_number', 80)->nullable();
            $table->unsignedTinyInteger('years_in_business')->nullable();
            $table->text('bio')->nullable();
            $table->string('public_phone', 30)->nullable();
            $table->string('whatsapp_number', 30)->nullable();
            $table->string('verification_status', 20)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();
            $table->boolean('accepts_leads')->default(true);
            $table->timestamps();
        });

        Schema::create('area_dealer_profile', function (Blueprint $table) {
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dealer_profile_id')->constrained()->cascadeOnDelete();
            $table->primary(['area_id', 'dealer_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_dealer_profile');
        Schema::dropIfExists('dealer_profiles');
    }
};
