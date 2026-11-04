<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demonstration data for Investment Rankings, kept apart from real data
 * (additive only):
 * - is_demo on projects, market sources, observations and indicators, so every
 *   demo record can be found and removed without touching real records;
 * - is_featured on projects (featured cards);
 * - includes_demo on calculations and is_demo on ranking rows: demo rankings
 *   are a separate published snapshot that never supersedes the real one.
 * Also grants the Investment Analyst agent the investment.advisor tool.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_published')->index();
            $table->boolean('is_featured')->default(false)->after('is_demo');
        });
        foreach (['market_sources', 'market_observations', 'market_indicators'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->boolean('is_demo')->default(false)->index());
        }
        Schema::table('investment_calculations', fn (Blueprint $table) => $table->boolean('includes_demo')->default(false)->after('status'));
        Schema::table('investment_rankings', fn (Blueprint $table) => $table->boolean('is_demo')->default(false)->after('status'));

        $agent = DB::table('ai_agents')->where('slug', 'investment-analyst')->first();
        $tools = $agent ? (json_decode($agent->tools, true) ?: []) : null;
        if ($agent && ! in_array('investment.advisor', $tools, true)) {
            $tools[] = 'investment.advisor';
            $now = now();
            DB::table('ai_agents')->where('id', $agent->id)->update(['tools' => json_encode($tools), 'version' => $agent->version + 1, 'updated_at' => $now]);
            $row = DB::table('ai_agents')->where('id', $agent->id)->first();
            DB::table('ai_agent_versions')->insert(['agent_id' => $agent->id, 'version' => $row->version, 'snapshot' => json_encode($row),
                'change_note' => 'Granted investment.advisor (Investment Rankings advisor through the Central AI Brain)', 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('investment_rankings', fn (Blueprint $table) => $table->dropColumn('is_demo'));
        Schema::table('investment_calculations', fn (Blueprint $table) => $table->dropColumn('includes_demo'));
        foreach (['market_indicators', 'market_observations', 'market_sources'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['is_demo']);
                $table->dropColumn('is_demo');
            });
        }
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn(['is_demo', 'is_featured']);
        });
    }
};
