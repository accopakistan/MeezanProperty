<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp channel (additive): which agent is handling a chat, human handoff,
 * who wrote each reply, and safe webhook metadata. Finance and Technical
 * Operations gain the support ticket tool, each as a new version, so they can
 * pass customer problems to staff. Each step is safe to repeat.
 */
return new class extends Migration
{
    protected const SNAPSHOT_FIELDS = ['primary_provider', 'secondary_provider', 'secondary_model', 'fallback_enabled', 'max_tokens', 'daily_request_limit',
        'monthly_budget_usd', 'name', 'role', 'description', 'instructions', 'model_tier', 'model', 'temperature', 'tools', 'memory_policy', 'status', 'daily_token_budget'];

    public function up(): void
    {
        if (! Schema::hasColumn('conversations', 'handoff_status')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('assigned_agent', 40)->nullable()->index();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('handoff_status', 20)->nullable()->index();
                $table->string('handoff_reason', 40)->nullable();
                $table->dateTime('handoff_requested_at')->nullable();
                $table->dateTime('handoff_assigned_at')->nullable();
                $table->dateTime('handoff_resolved_at')->nullable();
                $table->text('internal_note')->nullable();
            });
        }
        if (! Schema::hasColumn('conversation_messages', 'agent_slug')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                $table->string('agent_slug', 40)->nullable();
                $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('webhook_events', 'event_type')) {
            Schema::table('webhook_events', function (Blueprint $table) {
                $table->string('event_type', 20)->nullable();
                $table->dateTime('processed_at')->nullable();
            });
        }

        $now = now();
        foreach (DB::table('ai_agents')->whereIn('slug', ['finance-manager', 'technical-operations'])->orderBy('id')->get() as $agent) {
            $tools = json_decode((string) $agent->tools, true) ?: [];
            if (in_array('support.create_ticket', $tools, true)) {
                continue;
            }
            $version = (int) $agent->version + 1;
            DB::table('ai_agents')->where('id', $agent->id)->update(['tools' => json_encode([...$tools, 'support.create_ticket']), 'version' => $version, 'updated_at' => $now]);
            $current = (array) DB::table('ai_agents')->where('id', $agent->id)->first();
            $snapshot = collect(self::SNAPSHOT_FIELDS)->mapWithKeys(fn ($f) => [$f => in_array($f, ['tools', 'memory_policy'], true) ? json_decode((string) $current[$f], true) : $current[$f]]);
            DB::table('ai_agent_versions')->insert(['agent_id' => $agent->id, 'version' => $version, 'snapshot' => json_encode($snapshot),
                'change_note' => 'Added tool: support.create_ticket (pass WhatsApp customer problems to staff).', 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        // Additive: nothing is removed, so history stays intact.
    }
};
