<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('reference', 20)->nullable()->unique();
            $table->string('title', 160);
            $table->string('slug', 200)->unique();
            $table->string('purpose', 10)->index();
            $table->string('type', 30)->index();
            $table->string('city', 80)->index();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('price')->index();
            $table->decimal('area_size', 10, 2);
            $table->string('area_unit', 10);
            $table->unsignedInteger('area_sqft')->index();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->string('furnishing', 20)->nullable();
            $table->text('description');
            $table->json('amenities')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('rejection_reason')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'purpose', 'type']);
        });

        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('saved_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_properties');
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('properties');
    }
};
