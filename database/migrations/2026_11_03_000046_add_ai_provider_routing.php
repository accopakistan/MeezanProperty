<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two language-model providers behind one manager. Adds provider settings
 * (a saved API key is encrypted; .env remains an option), per-agent provider
 * routing and limits, and marks usage that came from a fallback. Additive:
 * every agent starts on OpenAI with Anthropic as its secondary provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->unique();
            $table->boolean('enabled')->default(true);
            $table->text('api_key')->nullable();                 // encrypted; empty means use the environment
            $table->string('base_url', 255)->nullable();         // null means the configured default
            $table->string('default_model', 80)->nullable();     // null means the configured default
            $table->decimal('monthly_budget_usd', 10, 2)->nullable();
            $table->string('connection_status', 20)->default('untested'); // untested | verified | error
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->json('available_models')->nullable();        // from the provider's own model list
            $table->timestamps();
        });
        $now = now();
        DB::table('ai_provider_settings')->insert([
            ['provider' => 'openai', 'created_at' => $now, 'updated_at' => $now],
            ['provider' => 'anthropic', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('ai_agents', function (Blueprint $table) {
            $table->string('primary_provider', 20)->default('openai')->after('model_tier');
            $table->string('secondary_provider', 20)->nullable()->default('anthropic')->after('model');
            $table->string('secondary_model', 80)->nullable()->after('secondary_provider');
            $table->boolean('fallback_enabled')->default(true)->after('secondary_model');
            $table->unsignedInteger('max_tokens')->nullable()->after('fallback_enabled');
            $table->unsignedInteger('daily_request_limit')->nullable()->after('daily_token_budget');
            $table->decimal('monthly_budget_usd', 10, 2)->nullable()->after('daily_request_limit');
        });

        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->string('fallback_from', 20)->nullable()->after('provider');
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->dropIndex(['provider', 'created_at']);
            $table->dropColumn('fallback_from');
        });
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn(['primary_provider', 'secondary_provider', 'secondary_model', 'fallback_enabled', 'max_tokens', 'daily_request_limit', 'monthly_budget_usd']);
        });
        Schema::dropIfExists('ai_provider_settings');
    }
};
