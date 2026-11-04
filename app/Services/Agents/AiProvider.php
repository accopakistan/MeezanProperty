<?php

namespace App\Services\Agents;

use App\Models\AiAgent;
use App\Models\AiProviderSetting;
use App\Models\AiTask;
use App\Models\AiUsageRecord;
use App\Models\AppSetting;
use App\Services\Agents\Providers\ProviderAdapter;
use App\Services\Agents\Providers\ProviderConfig;
use Carbon\CarbonImmutable;
use Closure;
use Throwable;

/**
 * The central provider manager: every language-model call in the application
 * goes through here, whichever agent or feature makes it. The provider is
 * Google Gemini (config meezan.agents.adapters); another can be registered
 * there without touching the agents. It resolves the model, masks personal
 * data, applies limits, budgets and quota stops, calls the adapter, and
 * records usage.
 *
 * - available() is false without a usable key: agents then run in rules mode.
 * - Usage comes from the provider's own response; cost is calculated from
 *   admin-entered prices, else Google's published prices in config, else null.
 * - A daily or free-tier quota stops all requests until it resets; nothing
 *   is retried against it and no answer is invented in its place.
 * - Fallback needs a second registered provider and the global switch.
 * - API keys never leave this layer (not in results, logs or views).
 */
class AiProvider
{
    /** Failures that may move to a secondary provider. Never: auth, quota, model, invalid, safety, budget, truncated. */
    public const FALLBACK_FAILURES = ['temporary', 'disabled', 'not_configured', 'no_model'];

    protected ?string $channel = null;

    public function __construct(protected BudgetGuard $budget) {}

    /* Configuration -------------------------------------------------------- */

    /** @return array<string, class-string<ProviderAdapter>> provider key => adapter class */
    public static function adapters(): array
    {
        return (array) config('meezan.agents.adapters', []);
    }

    public function adapter(string $provider): ProviderAdapter
    {
        return app(static::adapters()[$provider]);
    }

    /** @return array<string, string> provider key => label */
    public function labels(): array
    {
        return collect(static::adapters())->map(fn ($class) => app($class)->label())->all();
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
            timeout: (int) config("services.{$provider}.timeout", 60),
            retries: (int) config("services.{$provider}.max_retries", 2),
            options: ['thinking_level' => config("services.{$provider}.thinking_level")],
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
        return array_key_exists((string) config('meezan.agents.provider'), static::adapters()) && $this->anyReady();
    }

    public function anyReady(): bool
    {
        foreach (array_keys(static::adapters()) as $provider) {
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
        $adapters = static::adapters();
        $choice = static::controls()['default_provider'] ?: config('meezan.agents.provider');

        return array_key_exists((string) $choice, $adapters) ? $choice : (string) array_key_first($adapters);
    }

    /** The model chosen for a workload tier (default provider), or the provider's default. */
    public function modelFor(string $provider, string $tier): ?string
    {
        $tierModel = $provider === config('meezan.agents.provider') ? config("meezan.agents.models.{$tier}") : null;

        return $tierModel ?: $this->config($provider)->defaultModel;
    }

    /**
     * Who should answer: the channel's setting, then the agent's, then the
     * default provider. A secondary is only ever a different registered provider.
     *
     * @return array{primary: array{provider: string, model: ?string}, secondary: ?array{provider: string, model: ?string}, fallback: bool}
     */
    public function route(?AiAgent $agent, string $tier = 'routine', ?string $channel = null): array
    {
        $adapters = static::adapters();
        $controls = static::controls();
        $override = $controls['channels'][$channel ?? $this->channel] ?? [];
        $agentPrimary = $agent && array_key_exists((string) $agent->primary_provider, $adapters) ? $agent->primary_provider : null;

        if (filled($override['provider'] ?? null) && array_key_exists($override['provider'], $adapters)) {
            $provider = $override['provider'];
            $model = ($override['model'] ?? null) ?: $this->modelFor($provider, $tier);
        } else {
            $provider = $agentPrimary ?? $this->defaultProvider();
            $model = ($agentPrimary ? $agent->model : null) ?: $this->modelFor($provider, $tier);
        }

        $secondary = $agent ? $agent->secondary_provider : array_key_first(array_diff_key($adapters, [$provider => true]));
        $secondary = array_key_exists((string) $secondary, $adapters) && $secondary !== $provider ? $secondary : null;

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

    /**
     * Gemini function calling for an agent: the model may call the declared
     * functions; $execute runs each call (through ToolRunner, which checks
     * permission, validates input, enforces limits and logs) and its result
     * goes back in Google's functionResponse format. Ends with the model's
     * text, or when the round limit is reached. Every round is a recorded,
     * budget-checked request.
     *
     * @param  list<array>  $declarations  Gemini functionDeclarations
     * @param  Closure(string $name, array $args): array  $execute  returns {ok, data, error}
     * @return array{ok: bool, text: ?string, rounds: int, calls: list<array{name: string, ok: bool}>, tokens_in: int, tokens_out: int, cost_usd: ?float, error: ?string, failure: ?string, model: string, provider: string}
     */
    public function completeWithTools(AiAgent $agent, array $messages, array $declarations, Closure $execute, string $purpose, ?AiTask $task = null, ?int $maxRounds = null): array
    {
        $route = $this->route($agent, (string) $agent->model_tier, in_array($task?->source, ['website', 'whatsapp'], true) ? $task->source : null)['primary'];
        $provider = $route['provider'];
        $adapter = $this->adapter($provider);
        $totals = ['tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => null, 'calls' => [], 'rounds' => 0, 'provider' => $provider, 'model' => (string) $route['model']];
        if (! method_exists($adapter, 'generate')) {
            return $totals + ['ok' => false, 'text' => null, 'error' => "{$adapter->label()} does not support function calling here.", 'failure' => 'invalid'];
        }

        [$system, $contents] = $adapter::toContents($this->redact($messages));
        $maxRounds ??= (int) config('meezan.agents.max_model_tool_rounds', 4);
        for ($round = 1; $round <= $maxRounds; $round++) {
            $res = $this->attempt($provider, $route['model'], $messages, ['purpose' => $purpose, 'temperature' => (float) $agent->temperature, 'maxTokens' => (int) ($agent->max_tokens ?: 800),
                'json' => false, 'agent' => $agent, 'task' => $task, 'contents' => $contents, 'system' => $system, 'declarations' => $declarations]);
            $totals['rounds'] = $round;
            $totals['tokens_in'] += $res['tokens_in'];
            $totals['tokens_out'] += $res['tokens_out'];
            $totals['cost_usd'] = $res['cost_usd'] === null ? $totals['cost_usd'] : (float) $totals['cost_usd'] + $res['cost_usd'];
            if (! $res['ok']) {
                return $totals + ['ok' => false, 'text' => null, 'error' => $res['error'], 'failure' => $res['failure']];
            }
            if (($res['function_calls'] ?? []) === []) {
                return $totals + ['ok' => true, 'text' => $res['text'], 'error' => null, 'failure' => null];
            }

            // The model turn goes back exactly as received (thought signatures included), then one response per call.
            $contents[] = $res['content'];
            $responses = [];
            foreach ($res['function_calls'] as $call) {
                $out = $execute($call['name'], $call['args']);
                $totals['calls'][] = ['name' => $call['name'], 'ok' => (bool) $out['ok']];
                $responses[] = ['functionResponse' => array_filter(['name' => $call['name'], 'id' => $call['id'],
                    'response' => $out['ok'] ? ['output' => $out['data']] : ['error' => (string) $out['error']]], fn ($v) => $v !== null)];
            }
            $contents[] = ['role' => 'user', 'parts' => $responses];
        }

        return $totals + ['ok' => false, 'text' => null, 'error' => "Stopped after {$maxRounds} model turns without a final answer.", 'failure' => 'limit'];
    }

    /**
     * A streamed answer: text reaches $onText as it arrives. Same checks and
     * usage records as any request.
     *
     * @param  callable(string): void  $onText
     */
    public function streamGeneral(array $messages, string $purpose, callable $onText, int $maxTokens = 800): array
    {
        $route = $this->route(null)['primary'];

        return $this->attempt($route['provider'], $route['model'], $messages, ['purpose' => $purpose, 'temperature' => 0.2, 'maxTokens' => $maxTokens, 'json' => false, 'stream' => $onText]);
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
        $base = ['ok' => false, 'text' => null, 'model' => (string) $model, 'provider' => $provider, 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => null, 'fallback_from' => $fallbackFrom, 'function_calls' => [], 'content' => null];
        $refuse = fn (string $failure, string $error) => $base + ['failure' => $failure, 'error' => $error];

        if (! $config->enabled) {
            return $refuse('disabled', "{$config->label} is disabled.");
        }
        if ($config->keyUnreadable) {
            return $refuse('not_configured', "The saved {$config->label} API key can no longer be read: enter it again.");
        }
        if (! $config->hasKey()) {
            return $refuse('not_configured', "{$config->label} API key not configured. Add your API key to activate AI services.");
        }
        if (blank($model)) {
            return $refuse('no_model', "No model is selected for {$config->label}: choose one in Gemini Settings.");
        }
        if (($block = static::quotaBlock($provider)) && ($request['purpose'] ?? '') !== 'connection_test') {
            return $refuse('quota', $block['reason'].($block['until'] ? ' Paused until '.CarbonImmutable::parse($block['until'])->setTimezone(config('meezan.timezone'))->format('j M, H:i').'.' : ' Paused until an administrator tests the connection again.'));
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

        $messages = $this->redact($messages);
        $res = match (true) {
            isset($request['declarations']) => $adapter->generate($config, $model, $request['contents'], $request['system'],
                ['temperature' => $request['temperature'], 'maxOutputTokens' => $maxTokens], $request['declarations'], $request['timeout'] ?? null, $request['retries'] ?? null),
            isset($request['stream']) && method_exists($adapter, 'stream') => $adapter->stream($config, $model, $messages, $maxTokens, $request['stream'], $request['timeout'] ?? null),
            default => $adapter->send($config, $model, $messages, $request['temperature'], $maxTokens, $request['json'], $request['timeout'] ?? null, $request['retries'] ?? null),
        };
        $cost = $res['tokens_in'] || $res['tokens_out'] ? $this->cost($res['model'] ?: $model, $res['tokens_in'], $res['tokens_out']) : null;

        AiUsageRecord::create(['task_id' => $task?->id, 'agent_id' => $agent?->id, 'provider' => $provider, 'fallback_from' => $fallbackFrom, 'model' => mb_substr((string) $res['model'], 0, 80), 'purpose' => mb_substr($request['purpose'], 0, 30),
            'tokens_in' => $res['tokens_in'], 'tokens_out' => $res['tokens_out'], 'cost_usd' => $cost, 'ok' => $res['ok'], 'failure' => $res['failure'] ?? null,
            'error' => $res['error'] ? mb_substr($res['error'], 0, 255) : null, 'created_at' => now()]);
        $this->noteResult($provider, $res);

        return ['provider' => $provider, 'fallback_from' => $fallbackFrom, 'cost_usd' => $cost] + $res + ['function_calls' => [], 'content' => null];
    }

    /** Mask personal data before it leaves the server (config meezan.agents.redact_personal_data). */
    protected function redact(array $messages): array
    {
        if (! config('meezan.agents.redact_personal_data', true)) {
            return $messages;
        }

        return array_map(fn ($m) => ['content' => PersonalDataMask::text((string) $m['content'])] + $m, $messages);
    }

    protected function noteResult(string $provider, array $res): void
    {
        AiProviderSetting::where('provider', $provider)->update($res['ok']
            ? ['last_success_at' => now(), 'last_error' => null, 'last_error_at' => null]
            : ['last_error' => mb_substr((string) $res['error'], 0, 255), 'last_error_at' => now()]);
        if ($res['ok']) {
            static::clearQuotaBlock($provider);
        }
        // A daily quota, missing billing or a rejected key: stop sending, and tell the administrators once.
        if (in_array($res['failure'] ?? null, ['auth', 'quota'], true)) {
            static::setQuotaBlock($provider, (string) $res['error'], $res['quota_reset'] ?? null);
            AgentAlerts::providerProblem($this->adapter($provider)->label(), (string) $res['error']);
        }
    }

    /* Quota stop ----------------------------------------------------------- */

    /** @return ?array{reason: string, until: ?string, at: string} the current stop, if any (an expired one is cleared) */
    public static function quotaBlock(string $provider): ?array
    {
        $block = AppSetting::get("ai.quota_block.{$provider}");
        if (! is_array($block)) {
            return null;
        }
        if ($block['until'] && CarbonImmutable::parse($block['until'])->isPast()) {
            static::clearQuotaBlock($provider);

            return null;
        }

        return $block;
    }

    public static function setQuotaBlock(string $provider, string $reason, ?string $until): void
    {
        AppSetting::put("ai.quota_block.{$provider}", ['reason' => $reason, 'until' => $until, 'at' => now()->toIso8601String()]);
    }

    public static function clearQuotaBlock(string $provider): void
    {
        if (AppSetting::get("ai.quota_block.{$provider}") !== null) {
            AppSetting::put("ai.quota_block.{$provider}", null);
        }
    }

    /* Cost ----------------------------------------------------------------- */

    /** USD cost from prices per million tokens; null when the model has no known price. */
    public function cost(string $model, int $in, int $out): ?float
    {
        return static::costOf($model, $in, $out);
    }

    public static function costOf(string $model, int $in, int $out): ?float
    {
        $price = static::pricing()[$model] ?? static::pricing()[preg_replace('/-\d{3}$|-latest$/', '', $model)] ?? null;
        if (! $price || ! isset($price['input'], $price['output'])) {
            return null;
        }

        return round($in / 1_000_000 * (float) $price['input'] + $out / 1_000_000 * (float) $price['output'], 6);
    }

    /**
     * Prices in force today: Google's published prices from config (by date),
     * overridden by any price an administrator entered.
     *
     * @return array<string, array{input: float, output: float, source?: string}>
     */
    public static function pricing(?string $on = null): array
    {
        $on ??= now()->toDateString();
        $published = [];
        foreach (static::adapters() as $key => $class) {
            foreach ((array) config("services.{$key}.pricing", []) as $model => $periods) {
                $current = collect($periods)->filter(fn ($p) => $p['from'] === null || $p['from'] <= $on)->sortBy(fn ($p) => $p['from'] ?? '')->last();
                if ($current) {
                    $published[$model] = ['input' => (float) $current['input'], 'output' => (float) $current['output'], 'source' => 'published'];
                }
            }
        }
        $value = AppSetting::get('ai.pricing');
        $entered = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);

        return array_map(fn ($p) => $p + ['source' => 'entered'], $entered) + $published;
    }

    /** Prices an administrator entered (without the published defaults). */
    public static function enteredPricing(): array
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
    public function check(?string $provider = null): array
    {
        $provider ??= $this->defaultProvider();
        $config = $this->config($provider);
        if (! $config->enabled) {
            return ['status' => 'not_configured', 'detail' => "{$config->label} is disabled."];
        }
        if (! $config->hasKey()) {
            return ['status' => 'not_configured', 'detail' => "{$config->label} API key not configured. Add your API key to activate AI services."];
        }

        $res = $this->adapter($provider)->listModels($config);
        AiProviderSetting::where('provider', $provider)->update(['last_checked_at' => now(), 'connection_status' => $res['ok'] ? 'verified' : 'error']
            + ($res['ok'] ? ['available_models' => json_encode($res['models']), 'last_success_at' => now(), 'last_error' => null, 'last_error_at' => null] : ['last_error' => mb_substr((string) $res['error'], 0, 255), 'last_error_at' => now()]));

        return $res['ok']
            ? ['status' => 'verified', 'detail' => 'Credentials accepted by the provider.']
            : ['status' => 'error', 'detail' => (string) $res['error']];
    }

    /**
     * The full test behind the admin button: the model list, then one very
     * small real request to the active model (recorded in usage, within the
     * budgets), which also proves the model is usable and the quota is not used up.
     *
     * @return array{status: string, detail: string}
     */
    public function testConnection(?string $provider = null): array
    {
        $provider ??= $this->defaultProvider();
        $check = $this->check($provider);
        if ($check['status'] !== 'verified') {
            return $check;
        }
        $config = $this->config($provider);
        if (blank($config->defaultModel)) {
            return ['status' => 'configured', 'detail' => 'The key works. Choose a model, then test again to send a real request.'];
        }
        if ($warning = $this->modelWarning($provider, $config->defaultModel)) {
            AiProviderSetting::where('provider', $provider)->update(['connection_status' => 'error']);

            return ['status' => 'error', 'detail' => 'The key works, but '.$warning];
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
            ? "\"{$model}\" is not in the model list this key returned. Requests to it would fail: choose a listed model."
            : null;
    }
}
