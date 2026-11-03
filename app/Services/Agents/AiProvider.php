<?php

namespace App\Services\Agents;

use App\Models\AiAgent;
use App\Models\AiProviderSetting;
use App\Models\AiTask;
use App\Models\AiUsageRecord;
use App\Models\AppSetting;
use App\Services\Agents\Providers\AnthropicAdapter;
use App\Services\Agents\Providers\OpenAiAdapter;
use App\Services\Agents\Providers\ProviderAdapter;
use App\Services\Agents\Providers\ProviderConfig;
use Closure;
use Throwable;

/**
 * The central provider manager: every language-model call in the application
 * goes through here, whichever agent or feature makes it. It resolves the
 * provider and model, applies limits and budgets, calls the adapter, records
 * usage, and falls back to a secondary provider only when allowed.
 *
 * - available() is false without a usable key: agents then run in rules mode.
 * - Usage comes from the provider's own response; cost is calculated only from
 *   prices an admin has entered, otherwise it is null.
 * - Fallback happens only for temporary trouble or an unavailable provider, and
 *   only with the global switch on. It is recorded and shown, never silent.
 * - API keys never leave this layer (not in results, logs or views).
 */
class AiProvider
{
    public const ADAPTERS = ['openai' => OpenAiAdapter::class, 'anthropic' => AnthropicAdapter::class];

    /** Failures that may move to the secondary provider. Never: auth, quota, model, invalid, safety, budget, truncated. */
    public const FALLBACK_FAILURES = ['temporary', 'disabled', 'not_configured', 'no_model'];

    protected ?string $channel = null;

    public function __construct(protected BudgetGuard $budget) {}

    /* Configuration -------------------------------------------------------- */

    public function adapter(string $provider): ProviderAdapter
    {
        return app(self::ADAPTERS[$provider]);
    }

    /** @return array<string, string> provider key => label */
    public function labels(): array
    {
        return collect(self::ADAPTERS)->map(fn ($class) => app($class)->label())->all();
    }

    public function config(string $provider): ProviderConfig
    {
        $row = $this->row($provider);
        $saved = $row?->savedKey();
        $env = config("services.{$provider}.key");

        return new ProviderConfig(
            provider: $provider,
            label: $this->adapter($provider)->label(),
            apiKey: filled($saved) ? $saved : (filled($env) ? $env : null),
            keySource: filled($saved) ? 'saved' : (filled($env) ? 'env' : null),
            keyUnreadable: (bool) $row?->hasSavedKey() && ! filled($saved),
            baseUrl: $row?->base_url ?: (string) config("services.{$provider}.base_url"),
            defaultModel: $row?->default_model ?: (config("services.{$provider}.model") ?: null),
            enabled: $row?->enabled ?? true,
            timeout: (int) config("services.{$provider}.timeout", 30),
            retries: (int) config("services.{$provider}.max_retries", 2),
            options: ['version' => config("services.{$provider}.version"), 'effort' => config("services.{$provider}.effort")],
        );
    }

    public function row(string $provider): ?AiProviderSetting
    {
        try {
            return AiProviderSetting::where('provider', $provider)->first();
        } catch (Throwable) {
            return null; // table not migrated yet
        }
    }

    /** At least one provider can take requests, and language-model use is not switched off. */
    public function available(): bool
    {
        return array_key_exists((string) config('meezan.agents.provider'), self::ADAPTERS) && $this->anyReady();
    }

    public function anyReady(): bool
    {
        foreach (array_keys(self::ADAPTERS) as $provider) {
            if ($this->config($provider)->ready()) {
                return true;
            }
        }

        return false;
    }

    /** @return array{emergency_stop: bool, fallback_allowed: bool, default_provider: ?string, monthly_total_usd: ?float, daily_requests_total: ?int, max_tokens_per_request: ?int, channels: array} */
    public static function controls(): array
    {
        $saved = AppSetting::get('ai.controls');

        return (is_array($saved) ? $saved : []) + [
            'emergency_stop' => false, 'fallback_allowed' => (bool) config('meezan.agents.fallback_allowed'), 'default_provider' => null,
            'monthly_total_usd' => null, 'daily_requests_total' => null, 'max_tokens_per_request' => null, 'channels' => [],
        ];
    }

    public function defaultProvider(): string
    {
        $choice = static::controls()['default_provider'] ?: config('meezan.agents.provider');

        return array_key_exists((string) $choice, self::ADAPTERS) ? $choice : 'openai';
    }

    /** The model chosen for a workload tier, or the provider's default. */
    public function modelFor(string $provider, string $tier): ?string
    {
        $tierModel = config($provider === 'openai' ? "meezan.agents.models.{$tier}" : "meezan.agents.{$provider}_models.{$tier}");

        return $tierModel ?: $this->config($provider)->defaultModel;
    }

    /**
     * Who should answer: the channel's setting, then the agent's, then the
     * default provider. The secondary is only ever a different provider.
     *
     * @return array{primary: array{provider: string, model: ?string}, secondary: ?array{provider: string, model: ?string}, fallback: bool}
     */
    public function route(?AiAgent $agent, string $tier = 'routine', ?string $channel = null): array
    {
        $controls = static::controls();
        $override = $controls['channels'][$channel ?? $this->channel] ?? [];
        $agentPrimary = $agent && array_key_exists((string) $agent->primary_provider, self::ADAPTERS) ? $agent->primary_provider : null;

        if (filled($override['provider'] ?? null) && array_key_exists($override['provider'], self::ADAPTERS)) {
            $provider = $override['provider'];
            $model = ($override['model'] ?? null) ?: $this->modelFor($provider, $tier);
        } else {
            $provider = $agentPrimary ?? $this->defaultProvider();
            $model = ($agentPrimary ? $agent->model : null) ?: $this->modelFor($provider, $tier);
        }

        $secondary = $agent ? $agent->secondary_provider : array_key_first(array_diff_key(self::ADAPTERS, [$provider => true]));
        $secondary = array_key_exists((string) $secondary, self::ADAPTERS) && $secondary !== $provider ? $secondary : null;

        return [
            'primary' => ['provider' => $provider, 'model' => $model],
            'secondary' => $secondary ? ['provider' => $secondary, 'model' => ($agent ? $agent->secondary_model : null) ?: $this->modelFor($secondary, $tier)] : null,
            'fallback' => $secondary !== null && $controls['fallback_allowed'] && ($agent?->fallback_enabled ?? true) && ($override['fallback'] ?? true),
        ];
    }

    /** Work done inside $callback belongs to this chat channel. */
    public function inChannel(?string $channel, Closure $callback): mixed
    {
        $previous = $this->channel;
        $this->channel = $channel === null ? null : ($channel === 'whatsapp' ? 'whatsapp' : 'website');
        try {
            return $callback();
        } finally {
            $this->channel = $previous;
        }
    }

    /* Requests ------------------------------------------------------------- */

    /**
     * A model call for an agent: its provider and model, its limits.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{ok: bool, text: ?string, model: string, provider: string, tokens_in: int, tokens_out: int, cost_usd: ?float, error: ?string, failure: ?string, fallback_from: ?string}
     */
    public function completeFor(AiAgent $agent, array $messages, string $purpose, ?AiTask $task = null, float $temperature = 0.2, int $maxTokens = 800, bool $json = false): array
    {
        $channel = in_array($task?->source, ['website', 'whatsapp'], true) ? $task->source : null;

        return $this->run($this->route($agent, (string) $agent->model_tier, $channel), $messages, compact('purpose', 'temperature', 'maxTokens', 'json', 'agent', 'task'));
    }

    /** A model call that belongs to no agent (routing, requirement parsing, investment wording). */
    public function completeGeneral(array $messages, string $purpose, float $temperature = 0.2, int $maxTokens = 800, bool $json = false, string $tier = 'routine', ?int $timeout = null, ?int $retries = null): array
    {
        return $this->run($this->route(null, $tier), $messages, compact('purpose', 'temperature', 'maxTokens', 'json', 'timeout', 'retries'));
    }

    protected function run(array $route, array $messages, array $request): array
    {
        $first = $this->attempt($route['primary']['provider'], $route['primary']['model'], $messages, $request);
        if ($first['ok'] || ! $route['fallback'] || ! in_array($first['failure'], self::FALLBACK_FAILURES, true)) {
            return $first;
        }

        $second = $this->attempt($route['secondary']['provider'], $route['secondary']['model'], $messages, $request, fallbackFrom: $route['primary']['provider']);
        $second['tokens_in'] += $first['tokens_in'];
        $second['tokens_out'] += $first['tokens_out'];
        $second['cost_usd'] = $first['cost_usd'] === null && $second['cost_usd'] === null ? null : (float) $first['cost_usd'] + (float) $second['cost_usd'];

        return $second['ok']
            ? $second + ['fallback_reason' => $first['error']]
            : ['error' => $first['error'].' The fallback also failed: '.$second['error']] + $second;
    }

    protected function attempt(string $provider, ?string $model, array $messages, array $request, ?string $fallbackFrom = null): array
    {
        $config = $this->config($provider);
        $base = ['ok' => false, 'text' => null, 'model' => (string) $model, 'provider' => $provider, 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => null, 'fallback_from' => $fallbackFrom];
        $refuse = fn (string $failure, string $error) => $base + ['failure' => $failure, 'error' => $error];

        if (! $config->enabled) {
            return $refuse('disabled', "{$config->label} is disabled.");
        }
        if ($config->keyUnreadable) {
            return $refuse('not_configured', "The saved {$config->label} API key can no longer be read: enter it again.");
        }
        if (! $config->hasKey()) {
            return $refuse('not_configured', "Language model not configured: {$config->label} has no API key.");
        }
        if (blank($model)) {
            return $refuse('no_model', "No model is selected for {$config->label}: choose one in AI Providers.");
        }

        $agent = $request['agent'] ?? null;
        $task = $request['task'] ?? null;
        $adapter = $this->adapter($provider);
        // Thinking models need room to answer, but an administrator's limits always win.
        $maxTokens = max($request['maxTokens'], $adapter->minOutputTokens());
        foreach ([$agent?->max_tokens, static::controls()['max_tokens_per_request']] as $limit) {
            $maxTokens = $limit ? min($maxTokens, (int) $limit) : $maxTokens;
        }
        [$allowed, $why] = $this->budget->beforeRequest($provider, $model, $agent, $messages, $maxTokens);
        if (! $allowed) {
            return $refuse('budget', $why);
        }

        $res = $adapter->send($config, $model, $messages, $request['temperature'], $maxTokens, $request['json'], $request['timeout'] ?? null, $request['retries'] ?? null);
        $cost = $res['tokens_in'] || $res['tokens_out'] ? $this->cost($model, $res['tokens_in'], $res['tokens_out']) : null;

        AiUsageRecord::create(['task_id' => $task?->id, 'agent_id' => $agent?->id, 'provider' => $provider, 'fallback_from' => $fallbackFrom, 'model' => $res['model'], 'purpose' => mb_substr($request['purpose'], 0, 30),
            'tokens_in' => $res['tokens_in'], 'tokens_out' => $res['tokens_out'], 'cost_usd' => $cost, 'ok' => $res['ok'],
            'error' => $res['error'] ? mb_substr($res['error'], 0, 255) : null, 'created_at' => now()]);
        $this->noteResult($provider, $res);

        return ['provider' => $provider, 'fallback_from' => $fallbackFrom, 'cost_usd' => $cost] + $res;
    }

    protected function noteResult(string $provider, array $res): void
    {
        AiProviderSetting::where('provider', $provider)->update($res['ok']
            ? ['last_success_at' => now(), 'last_error' => null, 'last_error_at' => null]
            : ['last_error' => mb_substr((string) $res['error'], 0, 255), 'last_error_at' => now()]);
        if (in_array($res['failure'] ?? null, ['auth', 'quota'], true)) {
            AgentAlerts::providerProblem($this->adapter($provider)->label(), (string) $res['error']);
        }
    }

    /* Cost ----------------------------------------------------------------- */

    /** USD cost from configured per-million-token prices; null when the model has no configured price. */
    public function cost(string $model, int $in, int $out): ?float
    {
        return static::costOf($model, $in, $out);
    }

    public static function costOf(string $model, int $in, int $out): ?float
    {
        $price = static::pricing()[$model] ?? null;
        if (! $price || ! isset($price['input'], $price['output'])) {
            return null;
        }

        return round($in / 1_000_000 * (float) $price['input'] + $out / 1_000_000 * (float) $price['output'], 6);
    }

    /** @return array<string, array{input: float, output: float}> */
    public static function pricing(): array
    {
        $value = AppSetting::get('ai.pricing');

        return is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
    }

    /* Checks --------------------------------------------------------------- */

    /**
     * A free connection check: asks the provider for its model list. Never
     * reports "verified" without a successful response.
     *
     * @return array{status: string, detail: string}
     */
    public function check(string $provider = 'openai'): array
    {
        $config = $this->config($provider);
        if (! $config->enabled) {
            return ['status' => 'not_configured', 'detail' => "{$config->label} is disabled."];
        }
        if (! $config->hasKey()) {
            return ['status' => 'not_configured', 'detail' => 'API key not configured. Add your API key to activate this provider.'];
        }

        $res = $this->adapter($provider)->listModels($config);
        AiProviderSetting::where('provider', $provider)->update(['last_checked_at' => now(), 'connection_status' => $res['ok'] ? 'verified' : 'error']
            + ($res['ok'] ? ['available_models' => json_encode($res['models']), 'last_success_at' => now(), 'last_error' => null, 'last_error_at' => null] : ['last_error' => mb_substr((string) $res['error'], 0, 255), 'last_error_at' => now()]));

        return $res['ok']
            ? ['status' => 'verified', 'detail' => 'Credentials accepted by the provider.']
            : ['status' => 'error', 'detail' => (string) $res['error']];
    }

    /**
     * The full test behind the admin button: the free check, then one very
     * small real request to the default model (recorded in usage, within the
     * budgets), which also proves the model is usable and the account has credit.
     *
     * @return array{status: string, detail: string}
     */
    public function testConnection(string $provider): array
    {
        $check = $this->check($provider);
        if ($check['status'] !== 'verified') {
            return $check;
        }
        $config = $this->config($provider);
        if (blank($config->defaultModel)) {
            return ['status' => 'configured', 'detail' => 'The key works. Choose a default model, then test again to send a real request.'];
        }

        $res = $this->attempt($provider, $config->defaultModel, [['role' => 'user', 'content' => 'Reply with the single word OK.']], ['purpose' => 'connection_test', 'temperature' => 0.0, 'maxTokens' => 16, 'json' => false]);
        AiProviderSetting::where('provider', $provider)->update(['connection_status' => $res['ok'] ? 'verified' : 'error']);

        return $res['ok']
            ? ['status' => 'verified', 'detail' => "Connected: {$res['model']} answered a test request ({$res['tokens_in']} tokens in, {$res['tokens_out']} out)."]
            : ['status' => 'error', 'detail' => "The key works, but a test request to {$config->defaultModel} failed: {$res['error']}"];
    }

    /** A plain warning when a chosen model is not in the list this account returned. */
    public function modelWarning(string $provider, ?string $model): ?string
    {
        $listed = $this->row($provider)?->available_models;

        return $model && $listed && ! in_array($model, $listed, true)
            ? "\"{$model}\" is not in the model list this account returned. Requests to it will probably fail."
            : null;
    }
}
