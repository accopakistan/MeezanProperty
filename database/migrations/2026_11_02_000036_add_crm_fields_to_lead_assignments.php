<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dealer CRM state lives on the assignment (one row per lead and dealer),
 * so each of the up-to-three dealers on a lead has an independent pipeline.
 * The lead's own status stays the marketplace (routing) status.
 *
 * Existing assignments are given a starting stage from their lead's old
 * status; nothing else is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->string('pipeline_stage', 24)->default('new')->after('status');
            $table->string('priority', 10)->default('normal')->after('pipeline_stage');
            $table->timestamp('stage_changed_at')->nullable()->after('priority');
            $table->timestamp('last_activity_at')->nullable()->after('stage_changed_at');
            // Milestones (first time reached), for analytics that survive later stage changes.
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('outcome_reason', 40)->nullable();
            $table->string('outcome_note', 500)->nullable();
            // Won details as reported by the dealer (not verified, never company revenue).
            $table->string('won_transaction_type', 20)->nullable();
            $table->foreignId('won_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->decimal('won_value', 14, 2)->nullable();
            $table->timestamp('stale_flagged_at')->nullable();
            $table->string('end_reason', 255)->nullable();

            $table->index(['dealer_id', 'status', 'pipeline_stage']);
            $table->index(['dealer_id', 'last_activity_at']);
        });

        $map = ['contacted' => 'contacted', 'qualified' => 'qualified', 'closed' => 'won', 'lost' => 'lost'];
        foreach ($map as $leadStatus => $stage) {
            DB::table('lead_assignments')
                ->whereIn('lead_id', DB::table('leads')->where('status', $leadStatus)->select('id'))
                ->update(['pipeline_stage' => $stage, 'outcome_reason' => $stage === 'lost' ? 'other' : null]);
        }
        DB::table('lead_assignments')->whereNull('last_activity_at')->update(['last_activity_at' => DB::raw('assigned_at')]);
    }

    public function down(): void
    {
        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->dropIndex(['dealer_id', 'status', 'pipeline_stage']);
            $table->dropIndex(['dealer_id', 'last_activity_at']);
            $table->dropConstrainedForeignId('won_property_id');
            $table->dropColumn(['pipeline_stage', 'priority', 'stage_changed_at', 'last_activity_at', 'first_contact_at', 'qualified_at', 'closed_at',
                'outcome_reason', 'outcome_note', 'won_transaction_type', 'won_value', 'stale_flagged_at', 'end_reason']);
        });
    }
};
