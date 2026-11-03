<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grants the tools added after the agents were first registered. Adds to each
 * agent's current list (keeping any admin edits) and records a new version,
 * so the change shows in the agent's history. Agents that already have the
 * tool are left alone. No schema change.
 */
return new class extends Migration
{
    protected const GRANTS = [
        'dealer-manager' => 'dealers.listing_quality',
        'sales-manager' => 'crm.outcomes',
        'finance-manager' => 'payments.reconciliation',
        'global-data-manager' => 'locations.review_queue',
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $slug => $tool) {
            $agent = DB::table('ai_agents')->where('slug', $slug)->first();
            $tools = $agent ? (json_decode($agent->tools, true) ?: []) : null;
            if (! $agent || in_array($tool, $tools, true)) {
                continue;
            }
            $tools[] = $tool;
            $now = now();
            DB::table('ai_agents')->where('id', $agent->id)->update(['tools' => json_encode($tools), 'version' => $agent->version + 1, 'updated_at' => $now]);
            $row = DB::table('ai_agents')->where('id', $agent->id)->first();
            DB::table('ai_agent_versions')->insert(['agent_id' => $agent->id, 'version' => $row->version, 'snapshot' => json_encode($row), 'change_note' => "Granted new tool {$tool} (Phase 15 continuation)", 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (self::GRANTS as $slug => $tool) {
            $agent = DB::table('ai_agents')->where('slug', $slug)->first();
            if ($agent) {
                DB::table('ai_agents')->where('id', $agent->id)->update(['tools' => json_encode(array_values(array_diff(json_decode($agent->tools, true) ?: [], [$tool])))]);
            }
        }
    }
};
