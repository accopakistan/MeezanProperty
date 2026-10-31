<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 10)->nullable();
            $table->string('type', 30)->nullable();
            $table->string('source', 20);
            $table->string('status', 20)->default('new')->index();
            $table->boolean('consent_to_share')->default(false);
            $table->string('dedupe_key', 120)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dealer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('score')->nullable();
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'dealer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assignments');
        Schema::dropIfExists('leads');
    }
};
