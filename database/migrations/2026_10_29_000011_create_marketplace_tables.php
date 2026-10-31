<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('filters');
            $table->string('alert_frequency', 10)->default('weekly');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('property_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('old_price');
            $table->unsignedBigInteger('new_price');
            $table->timestamp('changed_at')->useCurrent();
        });

        Schema::create('listing_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 30)->index();
            $table->text('details')->nullable();
            $table->string('reporter_email', 190)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('developer', 160)->nullable();
            $table->string('city', 80)->index();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('under_construction')->index();
            $table->unsignedBigInteger('min_price')->nullable();
            $table->unsignedBigInteger('max_price')->nullable();
            $table->text('description');
            $table->string('website')->nullable();
            $table->string('approval_note')->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
        Schema::dropIfExists('listing_reports');
        Schema::dropIfExists('property_price_changes');
        Schema::dropIfExists('saved_searches');
    }
};
