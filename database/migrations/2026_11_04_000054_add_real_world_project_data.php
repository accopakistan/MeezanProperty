<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real-world project data (additive): where each project record came from,
 * alternate names for duplicate prevention, and the fuller source registry.
 * Five agents gain the read-only coverage tool, each as a new version.
 */
return new class extends Migration
{
    protected const SNAPSHOT_FIELDS = ['primary_provider', 'secondary_provider', 'secondary_model', 'fallback_enabled', 'max_tokens', 'daily_request_limit',
        'monthly_budget_usd', 'name', 'role', 'description', 'instructions', 'model_tier', 'model', 'temperature', 'tools', 'memory_policy', 'status', 'daily_token_budget'];

    protected const COVERAGE_AGENTS = ['ceo', 'property-manager', 'investment-analyst', 'global-data-manager', 'technical-operations'];

    public function up(): void
    {
        // Each step is safe to repeat, so an interrupted run can simply be run again.
        if (! Schema::hasColumn('projects', 'discovery_source_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->foreignId('discovery_source_id')->nullable()->after('approval_status')->constrained('market_sources')->nullOnDelete();
                $table->string('discovery_url', 2048)->nullable()->after('discovery_source_id');
                $table->dateTime('discovered_at')->nullable()->after('discovery_url');
                // official | source | inferred: how the category was decided.
                $table->string('category_basis', 12)->nullable()->after('discovered_at');
            });
        }

        Schema::hasTable('project_aliases') || Schema::create('project_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 250);
            $table->string('normalized', 250);
            $table->foreignId('source_id')->nullable()->constrained('market_sources')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'normalized']);
            $table->index('normalized');
        });

        Schema::hasColumn('market_sources', 'authority_level') || Schema::table('market_sources', function (Blueprint $table) {
            // 1 official record ... 7 unverified secondary information.
            $table->unsignedTinyInteger('authority_level')->nullable()->after('reliability');
            $table->string('access_method', 40)->nullable()->after('authority_level');
            $table->json('data_categories')->nullable()->after('access_method');
            $table->text('access_restrictions')->nullable()->after('access_note');
            $table->dateTime('last_success_at')->nullable()->after('last_collected_at');
        });

        $now = now();
        foreach (DB::table('ai_agents')->whereIn('slug', self::COVERAGE_AGENTS)->orderBy('id')->get() as $agent) {
            $tools = json_decode((string) $agent->tools, true) ?: [];
            $version = (int) $agent->version;
            if (! in_array('investment_index.coverage', $tools, true)) {
                $version++;
                DB::table('ai_agents')->where('id', $agent->id)->update(['tools' => json_encode([...$tools, 'investment_index.coverage']), 'version' => $version, 'updated_at' => $now]);
            } elseif (DB::table('ai_agent_versions')->where('agent_id', $agent->id)->where('version', $version)->exists()) {
                continue;
            }
            $current = (array) DB::table('ai_agents')->where('id', $agent->id)->first();
            $snapshot = collect(self::SNAPSHOT_FIELDS)->mapWithKeys(fn ($f) => [$f => in_array($f, ['tools', 'memory_policy'], true) ? json_decode((string) $current[$f], true) : $current[$f]]);
            DB::table('ai_agent_versions')->insert(['agent_id' => $agent->id, 'version' => $version, 'snapshot' => json_encode($snapshot),
                'change_note' => 'Added tool: investment_index.coverage (read-only real-world data coverage).', 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('market_sources', fn (Blueprint $table) => $table->dropColumn(['authority_level', 'access_method', 'data_categories', 'access_restrictions', 'last_success_at']));
        Schema::dropIfExists('project_aliases');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discovery_source_id');
            $table->dropColumn(['discovery_url', 'discovered_at', 'category_basis']);
        });
    }
};
