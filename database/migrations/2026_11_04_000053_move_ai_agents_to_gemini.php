<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Google Gemini becomes the only active language-model provider. Additive:
 * nothing is deleted. Every agent moves to Gemini as a new version (the
 * previous definition stays in its version history); a model override that
 * names another provider's model is cleared so the agent uses the Gemini
 * model for its tier. The OpenAI and Anthropic settings rows are kept
 * (with any saved key) but disabled; usage history keeps its provider.
 * Adds the per-request failure class to usage and the agent planning mode.
 */
return new class extends Migration
{
    protected const SNAPSHOT_FIELDS = ['primary_provider', 'secondary_provider', 'secondary_model', 'fallback_enabled', 'max_tokens', 'daily_request_limit',
        'monthly_budget_usd', 'name', 'role', 'description', 'instructions', 'model_tier', 'model', 'temperature', 'tools', 'memory_policy', 'status', 'daily_token_budget'];

    public function up(): void
    {
        Schema::table('ai_usage_records', fn (Blueprint $t) => $t->string('failure', 20)->nullable()->after('ok'));
        Schema::table('ai_agents', function (Blueprint $t) {
            // workflow: fixed workflows choose the tools (default). model_tools: Gemini may also call the agent's own tools.
            $t->string('planning', 20)->default('workflow')->after('memory_policy');
        });
        Schema::table('ai_agents', function (Blueprint $t) {
            $t->string('primary_provider', 20)->default('gemini')->change();
            $t->string('secondary_provider', 20)->nullable()->default(null)->change();
        });

        $now = now();
        if (! DB::table('ai_provider_settings')->where('provider', 'gemini')->exists()) {
            DB::table('ai_provider_settings')->insert(['provider' => 'gemini', 'enabled' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('ai_provider_settings')->whereIn('provider', ['openai', 'anthropic'])->update(['enabled' => false, 'updated_at' => $now]);

        foreach (DB::table('ai_agents')->orderBy('id')->get() as $agent) {
            $model = $agent->model && ! str_starts_with((string) $agent->model, 'gemini') ? null : $agent->model;
            $changes = ['primary_provider' => 'gemini', 'secondary_provider' => null, 'secondary_model' => null, 'model' => $model];
            $note = '';
            // The Investment Analyst can also read the Meezan Investment Index (read-only, published results only).
            if ($agent->slug === 'investment-analyst') {
                $tools = json_decode((string) $agent->tools, true) ?: [];
                $added = array_values(array_diff(['investment_index.leaderboard', 'investment_index.project'], $tools));
                if ($added) {
                    $changes['tools'] = json_encode(array_values(array_merge($tools, $added)));
                    $note = ' Added tools: '.implode(', ', $added).'.';
                }
            }
            if (collect($changes)->every(fn ($v, $k) => $agent->{$k} === $v)) {
                continue;
            }
            $version = (int) $agent->version + 1;
            DB::table('ai_agents')->where('id', $agent->id)->update($changes + ['version' => $version, 'updated_at' => $now]);
            $current = (array) DB::table('ai_agents')->where('id', $agent->id)->first();
            $snapshot = collect(self::SNAPSHOT_FIELDS)->mapWithKeys(fn ($f) => [$f => in_array($f, ['tools', 'memory_policy'], true) ? json_decode((string) $current[$f], true) : $current[$f]]);
            DB::table('ai_agent_versions')->insert(['agent_id' => $agent->id, 'version' => $version, 'snapshot' => json_encode($snapshot),
                'change_note' => mb_substr('Moved to Google Gemini (provider migration); previous provider '.$agent->primary_provider.($agent->model ? ', model '.$agent->model : '').'.'.$note, 0, 255), 'created_at' => $now]);
        }

        // Saved routing that names a removed provider falls back to Gemini.
        $row = DB::table('app_settings')->where('key', 'ai.controls')->first();
        if ($row) {
            $value = json_decode((string) $row->value, true) ?: [];
            $controls = $value['v'] ?? [];
            if (is_array($controls)) {
                if (in_array($controls['default_provider'] ?? null, ['openai', 'anthropic'], true)) {
                    $controls['default_provider'] = null;
                }
                foreach ($controls['channels'] ?? [] as $channel => $c) {
                    if (in_array($c['provider'] ?? null, ['openai', 'anthropic'], true)) {
                        $controls['channels'][$channel] = ['provider' => null, 'model' => null] + $c;
                    }
                }
                $controls['fallback_allowed'] = false;
                DB::table('app_settings')->where('key', 'ai.controls')->update(['value' => json_encode(['v' => $controls]), 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ai_usage_records', fn (Blueprint $t) => $t->dropColumn('failure'));
        Schema::table('ai_agents', fn (Blueprint $t) => $t->dropColumn('planning'));
        // Provider assignments are restored from each agent's version history if ever needed.
    }
};
