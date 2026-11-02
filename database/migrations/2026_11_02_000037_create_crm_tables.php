<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dealer CRM records, all hanging off one lead assignment (one dealer's
 * relationship with one lead), so they are private to that dealer:
 * notes, follow-ups, appointments, property recommendations and the
 * append-only activity timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['lead_assignment_id', 'created_at']);
        });

        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dealer_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // call, whatsapp, meeting, general
            $table->string('priority', 10)->default('normal');
            $table->timestamp('due_at');
            $table->string('notes', 1000)->nullable();
            $table->string('status', 20)->default('pending'); // pending, completed, cancelled (overdue = pending and past due)
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->index(['dealer_id', 'status', 'due_at']);
            $table->index(['lead_assignment_id', 'status']);
        });

        Schema::create('lead_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dealer_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // viewing, office, phone, other
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location', 255)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled, completed, cancelled, no_show
            $table->string('status_reason', 255)->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['dealer_id', 'status', 'starts_at']);
            $table->index('lead_assignment_id');
        });

        Schema::create('lead_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dealer_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('recommended'); // recommended, viewed, interested, not_interested
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['lead_assignment_id', 'property_id']);
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_assignment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lead_assignment_id', 'id']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('lead_recommendations');
        Schema::dropIfExists('lead_appointments');
        Schema::dropIfExists('lead_follow_ups');
        Schema::dropIfExists('lead_notes');
    }
};
